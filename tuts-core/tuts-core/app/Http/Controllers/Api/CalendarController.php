<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CalendarAggregatorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    public function __construct(private CalendarAggregatorService $aggregator)
    {
    }

    public function items(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
        ]);

        Log::info('[TUTS][Calendar] listing calendar items', [
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'items' => $this->aggregator->items(
                $request->user(),
                CarbonImmutable::parse($validated['start_at'])->utc(),
                CarbonImmutable::parse($validated['end_at'])->utc(),
            ),
        ]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $validated['limit'] ?? 5;
        $now = CarbonImmutable::now('UTC');

        Log::info('[TUTS][Calendar] listing upcoming items', [
            'user_id' => $request->user()->id,
            'limit' => $limit,
        ]);

        $items = $this->aggregator
            ->items($request->user(), $now, $now->addMonths(6), false)
            ->filter(fn(array $item) => in_array($item['kind'], ['event', 'task', 'teacher_event'], true))
            ->take($limit)
            ->values();

        return response()->json([
            'status' => 'sucesso',
            'items' => $items,
        ]);
    }
}
