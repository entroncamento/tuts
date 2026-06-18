<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalendarConflictService
{
    public function attach(Collection $items): Collection
    {
        $blocked = $items->filter(fn(array $item) => $item['kind'] === 'blocked')->values();

        return $items->map(function (array $item) use ($blocked) {
            if ($item['kind'] === 'blocked' || $item['all_day']) {
                return $item;
            }

            $conflicts = $blocked
                ->filter(fn(array $blockedItem) => $this->overlaps($item, $blockedItem))
                ->pluck('id')
                ->values()
                ->all();

            $item['is_conflicting'] = count($conflicts) > 0;
            $item['conflicts_with'] = $conflicts;

            return $item;
        });
    }

    private function overlaps(array $item, array $blocked): bool
    {
        $itemStart = CarbonImmutable::parse($item['start_at']);
        $itemEnd = CarbonImmutable::parse($item['end_at']);
        $blockedStart = CarbonImmutable::parse($blocked['start_at']);
        $blockedEnd = CarbonImmutable::parse($blocked['end_at']);

        return $itemStart->lessThan($blockedEnd) && $itemEnd->greaterThan($blockedStart);
    }
}
