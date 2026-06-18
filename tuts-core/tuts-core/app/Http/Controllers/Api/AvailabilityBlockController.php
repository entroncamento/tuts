<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AvailabilityBlockController extends Controller
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

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'sucesso',
            'blocks' => AvailabilityBlock::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('is_active')
                ->orderBy('starts_on')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        Log::info('[TUTS][Availability] creating block', [
            'user_id' => $request->user()->id,
        ]);

        $block = AvailabilityBlock::create($validated + [
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'block' => $block,
        ], 201);
    }

    public function update(Request $request, AvailabilityBlock $block): JsonResponse
    {
        $this->authorizeOwner($request, $block);

        $block->update($this->validatedPayload($request, true));

        return response()->json([
            'status' => 'sucesso',
            'block' => $block->fresh(),
        ]);
    }

    public function destroy(Request $request, AvailabilityBlock $block): JsonResponse
    {
        $this->authorizeOwner($request, $block);

        $block->delete();

        return response()->json(['status' => 'sucesso']);
    }

    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        $validated = $request->validate([
            'title' => $required . '|string|max:255',
            'start_time' => $required . '|date_format:H:i',
            'end_time' => $required . '|date_format:H:i',
            'repeat_type' => [$required, Rule::in(['none', 'daily', 'weekly'])],
            'repeat_days' => 'nullable|array',
            'repeat_days.*' => [Rule::in(self::WEEKDAYS)],
            'starts_on' => $required . '|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'is_active' => 'nullable|boolean',
            'color' => 'nullable|string|max:40',
        ]);

        if (($validated['repeat_type'] ?? null) === 'weekly') {
            abort_unless(!empty($validated['repeat_days']), 422, 'Escolhe pelo menos um dia da semana.');
        } elseif (array_key_exists('repeat_type', $validated)) {
            $validated['repeat_days'] = null;
        }

        return $validated;
    }

    private function authorizeOwner(Request $request, AvailabilityBlock $block): void
    {
        abort_unless((int) $block->user_id === (int) $request->user()->id, 403);
    }
}
