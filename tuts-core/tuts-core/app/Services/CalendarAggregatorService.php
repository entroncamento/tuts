<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use App\Models\CalendarItem;
use App\Models\TeacherEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalendarAggregatorService
{
    public function __construct(
        private AvailabilityOccurrenceService $availabilityOccurrences,
        private CalendarConflictService $conflicts,
    ) {
    }

    public function items(User $user, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd, bool $includeBlocked = true): Collection
    {
        $items = collect()
            ->merge($this->calendarItems($user, $rangeStart, $rangeEnd))
            ->merge($this->teacherEvents($user, $rangeStart, $rangeEnd));

        if ($includeBlocked) {
            $items = $items->merge($this->blockedItems($user, $rangeStart, $rangeEnd));
        }

        return $this->conflicts
            ->attach($items)
            ->sortBy('start_at')
            ->values();
    }

    private function calendarItems(User $user, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): Collection
    {
        return CalendarItem::query()
            ->with('subject')
            ->where('user_id', $user->id)
            ->where('start_at', '<', $rangeEnd->utc())
            ->where('end_at', '>', $rangeStart->utc())
            ->get()
            ->map(fn(CalendarItem $item) => [
                'id' => 'calendar_item:' . $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'kind' => $item->kind,
                'source' => 'student',
                'scope' => $item->scope,
                'start_at' => $this->iso($item->start_at),
                'end_at' => $this->iso($item->end_at),
                'all_day' => (bool) $item->all_day,
                'subject_id' => $item->subject_id,
                'uc_id' => $item->subject_id,
                'uc_name' => $item->subject?->name,
                'location' => $item->location,
                'color' => $item->color,
                'can_edit' => true,
                'can_delete' => true,
                'can_hide' => false,
                'managed_in' => 'calendar',
                'hidden' => false,
                'is_conflicting' => false,
                'conflicts_with' => [],
            ]);
    }

    private function teacherEvents(User $user, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): Collection
    {
        $subjectIds = $this->visibleTeacherEventSubjectIds($user);

        if ($subjectIds->isEmpty()) {
            return collect();
        }

        return TeacherEvent::query()
            ->with('subject')
            ->whereIn('subject_id', $subjectIds)
            ->where('start_at', '<', $rangeEnd->utc())
            ->where('end_at', '>', $rangeStart->utc())
            ->get()
            ->map(function (TeacherEvent $event) use ($user) {
                $canManage = (int) $event->created_by === (int) $user->id;

                return [
                    'id' => 'teacher_event:' . $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'kind' => 'teacher_event',
                    'source' => 'teacher',
                    'scope' => 'uc',
                    'start_at' => $this->iso($event->start_at),
                    'end_at' => $this->iso($event->end_at),
                    'all_day' => (bool) $event->all_day,
                    'subject_id' => $event->subject_id,
                    'uc_id' => $event->subject_id,
                    'uc_name' => $event->subject?->name,
                    'location' => $event->location,
                    'color' => $event->color,
                    'can_edit' => $canManage,
                    'can_delete' => $canManage,
                    'can_hide' => !$canManage,
                    'managed_in' => 'teacher',
                    'hidden' => false,
                    'is_conflicting' => false,
                    'conflicts_with' => [],
                ];
            });
    }

    private function blockedItems(User $user, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): Collection
    {
        $blocks = AvailabilityBlock::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('starts_on', '<=', $rangeEnd->setTimezone('Europe/Lisbon')->toDateString())
            ->where(function ($query) use ($rangeStart) {
                $query
                    ->whereNull('ends_on')
                    ->orWhere('ends_on', '>=', $rangeStart->setTimezone('Europe/Lisbon')->toDateString());
            })
            ->get();

        return $this->availabilityOccurrences
            ->expand($blocks, $rangeStart, $rangeEnd)
            ->map(fn(array $occurrence) => [
                'id' => 'blocked:' . $occurrence['block']->id . ':' . $occurrence['date'],
                'title' => $occurrence['block']->title,
                'description' => null,
                'kind' => 'blocked',
                'source' => 'profile',
                'scope' => 'personal',
                'start_at' => $this->iso($occurrence['start_at']),
                'end_at' => $this->iso($occurrence['end_at']),
                'all_day' => false,
                'subject_id' => null,
                'uc_id' => null,
                'uc_name' => null,
                'location' => null,
                'color' => $occurrence['block']->color,
                'can_edit' => false,
                'can_delete' => false,
                'can_hide' => true,
                'managed_in' => 'profile',
                'hidden' => false,
                'is_conflicting' => false,
                'conflicts_with' => [],
            ]);
    }

    private function visibleTeacherEventSubjectIds(User $user): Collection
    {
        $studentIds = DB::table('subject_user')
            ->where('user_id', $user->id)
            ->where('role', 'student')
            ->where('status', 'active')
            ->pluck('subject_id');

        $teacherIds = DB::table('subject_user')
            ->where('user_id', $user->id)
            ->where('role', 'teacher')
            ->where('status', 'active')
            ->pluck('subject_id');

        $createdIds = DB::table('subjects')
            ->where('created_by', $user->id)
            ->pluck('id');

        return $studentIds->merge($teacherIds)->merge($createdIds)->unique()->values();
    }

    private function iso($value): string
    {
        return CarbonImmutable::parse($value)->utc()->toISOString();
    }
}
