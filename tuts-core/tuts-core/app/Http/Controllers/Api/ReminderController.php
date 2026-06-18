<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'context_type' => ['required', Rule::in(['calendar', 'uc'])],
            'subject_id' => 'nullable|integer|exists:subjects,id',
        ]);

        $query = Reminder::query()
            ->where('user_id', $request->user()->id)
            ->where('context_type', $validated['context_type'])
            ->whereNull('completed_at')
            ->latest();

        if ($validated['context_type'] === 'uc') {
            $query->where('subject_id', $validated['subject_id']);
        } else {
            $query->whereNull('subject_id');
        }

        return response()->json([
            'status' => 'sucesso',
            'reminders' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'context_type' => ['required', Rule::in(['calendar', 'uc'])],
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'title' => 'required|string|max:255',
        ]);

        $this->validateContext($request->user()->id, $validated);
        $this->enforceLimit($request->user()->id, $validated);

        Log::info('[TUTS][Reminder] creating reminder', [
            'user_id' => $request->user()->id,
        ]);

        $reminder = Reminder::create([
            'user_id' => $request->user()->id,
            'context_type' => $validated['context_type'],
            'subject_id' => $validated['context_type'] === 'uc' ? $validated['subject_id'] : null,
            'title' => $validated['title'],
        ]);

        return response()->json([
            'status' => 'sucesso',
            'reminder' => $reminder,
        ], 201);
    }

    public function complete(Request $request, Reminder $reminder): JsonResponse
    {
        abort_unless((int) $reminder->user_id === (int) $request->user()->id, 403);

        Log::info('[TUTS][Reminder] completing reminder', [
            'user_id' => $request->user()->id,
            'reminder_id' => $reminder->id,
        ]);

        $reminder->update(['completed_at' => now()]);

        return response()->json([
            'status' => 'sucesso',
            'reminder' => $reminder->fresh(),
        ]);
    }

    public function destroy(Request $request, Reminder $reminder): JsonResponse
    {
        abort_unless((int) $reminder->user_id === (int) $request->user()->id, 403);

        $reminder->delete();

        return response()->json(['status' => 'sucesso']);
    }

    private function validateContext(int $userId, array $validated): void
    {
        if ($validated['context_type'] === 'calendar') {
            abort_unless(empty($validated['subject_id']), 422, 'Lembretes de calendario nao usam UC.');
            return;
        }

        abort_unless(!empty($validated['subject_id']), 422, 'A UC e obrigatoria.');

        $isMember = DB::table('subject_user')
            ->where('user_id', $userId)
            ->where('subject_id', $validated['subject_id'])
            ->where('status', 'active')
            ->exists();

        abort_unless($isMember, 403, 'Sem acesso a esta UC.');
    }

    private function enforceLimit(int $userId, array $validated): void
    {
        $query = Reminder::query()
            ->where('user_id', $userId)
            ->where('context_type', $validated['context_type'])
            ->whereNull('completed_at');

        if ($validated['context_type'] === 'uc') {
            $query->where('subject_id', $validated['subject_id']);
        } else {
            $query->whereNull('subject_id');
        }

        abort_unless($query->count() < 10, 422, 'Limite de 10 lembretes ativos atingido.');
    }
}
