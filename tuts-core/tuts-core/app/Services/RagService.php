<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RagService
{
    private const CIRCUIT_STATE_CLOSED = 'closed';
    private const CIRCUIT_STATE_OPEN = 'open';
    private const CIRCUIT_STATE_HALF_OPEN = 'half_open';

    private string $name = 'rag_service';
    private int $threshold = 5;
    private int $recoveryTimeout = 45;
    private int $window = 60;

    private function key(string $suffix): string
    {
        return "circuit:{$this->name}:{$suffix}";
    }

    public function getUrl(): string
    {
        return config('services.python.url', 'http://rag:8001/perguntar');
    }

    public function getToken(): string
    {
        return trim((string) config('services.python.internal_token', ''));
    }

    public function getCircuitState(): string
    {
        $state = Cache::get($this->key('state'), self::CIRCUIT_STATE_CLOSED);

        Log::debug("[CIRCUIT][{$this->name}] Reading circuit state", [
            'state' => $state,
            'cache_store' => config('cache.default'),
        ]);

        if ($state === self::CIRCUIT_STATE_OPEN) {
            $lastFailure = Cache::get($this->key('last_failure'));
            if ($lastFailure && (microtime(true) - (float)$lastFailure > $this->recoveryTimeout)) {
                Log::info("[CIRCUIT][{$this->name}] Cooldown elapsed; moving to half-open", [
                    'recovery_timeout_seconds' => $this->recoveryTimeout,
                ]);
                $this->setCircuitState(self::CIRCUIT_STATE_HALF_OPEN);
                return self::CIRCUIT_STATE_HALF_OPEN;
            }
        }

        return $state;
    }

    private function setCircuitState(string $state): void
    {
        Cache::put($this->key('state'), $state);

        $level = $state === self::CIRCUIT_STATE_OPEN ? 'warning' : 'info';
        Log::{$level}("[CIRCUIT][{$this->name}] Circuit state changed", [
            'state' => $state,
            'cache_store' => config('cache.default'),
        ]);
    }

    public function reportSuccess(): void
    {
        $state = $this->getCircuitState();
        if ($state === self::CIRCUIT_STATE_HALF_OPEN) {
            $this->setCircuitState(self::CIRCUIT_STATE_CLOSED);
            Cache::forget($this->key('failures'));
            Cache::forget($this->key('last_failure'));
            Log::info("[CIRCUIT][{$this->name}] Circuit closed after successful half-open request");
        } elseif ($state === self::CIRCUIT_STATE_CLOSED) {
            Cache::forget($this->key('failures'));
            Cache::forget($this->key('last_failure'));
            Log::debug("[CIRCUIT][{$this->name}] Failure counter reset after successful request");
        }
    }

    public function reportFailure(): void
    {
        Cache::put($this->key('last_failure'), microtime(true));
        
        $state = $this->getCircuitState();
        if ($state === self::CIRCUIT_STATE_HALF_OPEN || $state === self::CIRCUIT_STATE_OPEN) {
            Log::warning("[CIRCUIT][{$this->name}] Failure recorded while circuit was {$state}; opening circuit");
            $this->setCircuitState(self::CIRCUIT_STATE_OPEN);
            return;
        }

        Cache::add($this->key('failures'), 0, now()->addSeconds($this->window));
        $failures = (int) Cache::increment($this->key('failures'));

        Log::warning("[CIRCUIT][{$this->name}] Failure recorded", [
            'failures' => $failures,
            'threshold' => $this->threshold,
            'window_seconds' => $this->window,
        ]);

        if ($failures >= $this->threshold) {
            Log::warning("[CIRCUIT][{$this->name}] Failure threshold reached; opening circuit", [
                'failures' => $failures,
                'threshold' => $this->threshold,
                'recovery_timeout_seconds' => $this->recoveryTimeout,
            ]);
            $this->setCircuitState(self::CIRCUIT_STATE_OPEN);
        }
    }

    public function isAvailable(): bool
    {
        return $this->getCircuitState() !== self::CIRCUIT_STATE_OPEN;
    }
}
