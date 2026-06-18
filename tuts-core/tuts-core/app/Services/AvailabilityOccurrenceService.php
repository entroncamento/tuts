<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityOccurrenceService
{
    private const WEEKDAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function expand(Collection $blocks, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): Collection
    {
        return $blocks
            ->flatMap(fn(AvailabilityBlock $block) => $this->expandBlock($block, $rangeStart, $rangeEnd))
            ->values();
    }

    private function expandBlock(AvailabilityBlock $block, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
        $timezone = 'Europe/Lisbon';
        $localStart = $rangeStart->setTimezone($timezone)->startOfDay();
        $localEnd = $rangeEnd->setTimezone($timezone)->endOfDay();
        $startsOn = CarbonImmutable::parse($block->starts_on, $timezone)->startOfDay();
        $endsOn = $block->ends_on
            ? CarbonImmutable::parse($block->ends_on, $timezone)->endOfDay()
            : null;

        $cursor = $localStart->max($startsOn);
        $last = $endsOn ? $localEnd->min($endsOn) : $localEnd;
        $items = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            if ($this->occursOn($block, $cursor)) {
                [$start, $end] = $this->timesForDay($block, $cursor, $timezone);

                if ($start->lessThan($rangeEnd) && $end->greaterThan($rangeStart)) {
                    $items[] = [
                        'block' => $block,
                        'date' => $cursor->toDateString(),
                        'start_at' => $start->utc(),
                        'end_at' => $end->utc(),
                    ];
                }
            }

            $cursor = $cursor->addDay();
        }

        return $items;
    }

    private function occursOn(AvailabilityBlock $block, CarbonImmutable $date): bool
    {
        if ($block->repeat_type === 'daily') {
            return true;
        }

        if ($block->repeat_type === 'weekly') {
            $days = collect($block->repeat_days ?? [])
                ->map(fn($day) => strtolower((string) $day))
                ->intersect(self::WEEKDAYS);

            return $days->contains(strtolower($date->englishDayOfWeek));
        }

        return $date->isSameDay($block->starts_on);
    }

    private function timesForDay(AvailabilityBlock $block, CarbonImmutable $day, string $timezone): array
    {
        $start = CarbonImmutable::parse($day->toDateString() . ' ' . $block->start_time, $timezone);
        $end = CarbonImmutable::parse($day->toDateString() . ' ' . $block->end_time, $timezone);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }
}
