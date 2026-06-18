<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\TeacherEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeacherEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'nullable|integer|exists:subjects,id',
        ]);

        $subjectIds = $this->visibleSubjectIds($request->user());

        if (!empty($validated['subject_id'])) {
            abort_unless($subjectIds->contains((int) $validated['subject_id']), 403);
            $subjectIds = collect([(int) $validated['subject_id']]);
        }

        $events = TeacherEvent::query()
            ->with('subject')
            ->whereIn('subject_id', $subjectIds)
            ->orderBy('start_at')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'events' => $events,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);
        $subject = Subject::query()->findOrFail($validated['subject_id']);

        abort_unless($this->isProfessor($request->user()), 403, 'Apenas professores podem criar eventos de UC.');
        abort_unless($this->canTeachSubject($request->user(), $subject), 403, 'Sem permissao para criar eventos nesta UC.');

        Log::info('[TUTS][TeacherEvent] creating teacher event', [
            'user_id' => $request->user()->id,
            'subject_id' => $subject->id,
        ]);

        $event = TeacherEvent::create($this->payloadForSave($validated) + [
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'event' => $event->fresh('subject'),
        ], 201);
    }

    public function update(Request $request, TeacherEvent $event): JsonResponse
    {
        $this->authorizeCreator($request, $event);

        $validated = $this->validatedPayload($request, true);

        if (isset($validated['subject_id'])) {
            $subject = Subject::query()->findOrFail($validated['subject_id']);
            abort_unless($this->canTeachSubject($request->user(), $subject), 403);
        }

        $event->update($this->payloadForSave($validated));

        return response()->json([
            'status' => 'sucesso',
            'event' => $event->fresh('subject'),
        ]);
    }

    public function destroy(Request $request, TeacherEvent $event): JsonResponse
    {
        $this->authorizeCreator($request, $event);

        $event->delete();

        return response()->json(['status' => 'sucesso']);
    }

    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return $request->validate([
            'subject_id' => $required . '|integer|exists:subjects,id',
            'title' => $required . '|string|max:255',
            'description' => 'nullable|string|max:4000',
            'start_at' => $required . '|date',
            'end_at' => $required . '|date|after:start_at',
            'all_day' => 'nullable|boolean',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:40',
        ]);
    }

    private function payloadForSave(array $validated): array
    {
        foreach (['start_at', 'end_at'] as $key) {
            if (isset($validated[$key])) {
                $validated[$key] = CarbonImmutable::parse($validated[$key])->utc();
            }
        }

        return $validated;
    }

    private function authorizeCreator(Request $request, TeacherEvent $event): void
    {
        if ((int) $event->created_by !== (int) $request->user()->id) {
            Log::warning('[TUTS][Calendar] forbidden calendar action', [
                'user_id' => $request->user()->id,
                'teacher_event_id' => $event->id,
            ]);
            abort(403);
        }
    }

    private function isProfessor(User $user): bool
    {
        return in_array($user->role, ['professor', 'teacher'], true);
    }

    private function canTeachSubject(User $user, Subject $subject): bool
    {
        if ((int) $subject->created_by === (int) $user->id) {
            return true;
        }

        return DB::table('subject_user')
            ->where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('role', 'teacher')
            ->where('status', 'active')
            ->exists();
    }

    private function visibleSubjectIds(User $user)
    {
        $membershipIds = DB::table('subject_user')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('subject_id');

        $createdIds = DB::table('subjects')
            ->where('created_by', $user->id)
            ->pluck('id');

        return $membershipIds->merge($createdIds)->unique()->values();
    }
}
