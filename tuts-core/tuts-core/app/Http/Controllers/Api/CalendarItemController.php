<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CalendarItemController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);
        $user = $request->user();
        $this->validateSubjectScope($user->id, $validated);

        Log::info('[TUTS][Calendar] creating calendar item', [
            'user_id' => $user->id,
        ]);

        $item = CalendarItem::create($this->payloadForSave($validated) + [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'item' => $item->fresh(),
        ], 201);
    }

    public function update(Request $request, CalendarItem $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);

        $validated = $this->validatedPayload($request, true);
        $this->validateSubjectScope($request->user()->id, [
            'scope' => $validated['scope'] ?? $item->scope,
            'subject_id' => array_key_exists('subject_id', $validated) ? $validated['subject_id'] : $item->subject_id,
        ]);

        Log::info('[TUTS][Calendar] updating calendar item', [
            'user_id' => $request->user()->id,
            'item_id' => $item->id,
        ]);

        $item->update($this->payloadForSave($validated));

        return response()->json([
            'status' => 'sucesso',
            'item' => $item->fresh(),
        ]);
    }

    public function destroy(Request $request, CalendarItem $item): JsonResponse
    {
        $this->authorizeOwner($request, $item);

        Log::info('[TUTS][Calendar] deleting calendar item', [
            'user_id' => $request->user()->id,
            'item_id' => $item->id,
        ]);

        $item->delete();

        return response()->json(['status' => 'sucesso']);
    }

    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return $request->validate([
            'kind' => [$required, Rule::in(['event', 'task'])],
            'title' => $required . '|string|max:255',
            'description' => 'nullable|string|max:4000',
            'start_at' => $required . '|date',
            'end_at' => $required . '|date|after:start_at',
            'all_day' => 'nullable|boolean',
            'scope' => [$required, Rule::in(['personal', 'uc'])],
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:40',
        ]);
    }

    private function payloadForSave(array $validated): array
    {
        if (($validated['scope'] ?? null) === 'personal') {
            $validated['subject_id'] = null;
        }

        foreach (['start_at', 'end_at'] as $key) {
            if (isset($validated[$key])) {
                $validated[$key] = CarbonImmutable::parse($validated[$key])->utc();
            }
        }

        return $validated;
    }

    private function validateSubjectScope(int $userId, array $validated): void
    {
        if (($validated['scope'] ?? null) !== 'uc') {
            return;
        }

        abort_unless(!empty($validated['subject_id']), 422, 'A UC e obrigatoria para itens associados a UC.');

        $isMember = DB::table('subject_user')
            ->where('user_id', $userId)
            ->where('subject_id', $validated['subject_id'])
            ->where('status', 'active')
            ->exists();

        abort_unless($isMember, 403, 'Sem acesso a esta UC.');
    }

    private function authorizeOwner(Request $request, CalendarItem $item): void
    {
        if ((int) $item->user_id !== (int) $request->user()->id) {
            Log::warning('[TUTS][Calendar] forbidden calendar action', [
                'user_id' => $request->user()->id,
                'item_id' => $item->id,
            ]);
            abort(403);
        }
    }
}
