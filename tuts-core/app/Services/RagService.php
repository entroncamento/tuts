<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;

class RagService
{
    private const CIRCUIT_STATE_CLOSED = 'closed';
    private const CIRCUIT_STATE_OPEN = 'open';
    private const CIRCUIT_STATE_HALF_OPEN = 'half_open';

    private string $name = 'rag_service';
    private int $threshold = 5;
    private int $recoveryTimeout = 45;
    private int $window = 60;

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
        $state = Redis::get("circuit:{$this->name}:state") ?: self::CIRCUIT_STATE_CLOSED;

        if ($state === self::CIRCUIT_STATE_OPEN) {
            $lastFailure = Redis::get("circuit:{$this->name}:last_failure");
            if ($lastFailure && (microtime(true) - (float)$lastFailure > $this->recoveryTimeout)) {
                $this->setCircuitState(self::CIRCUIT_STATE_HALF_OPEN);
                return self::CIRCUIT_STATE_HALF_OPEN;
            }
        }

        return $state;
    }

    private function setCircuitState(string $state): void
    {
        Redis::set("circuit:{$this->name}:state", $state);
        Log::info("[CIRCUIT][{$this->name}] Transição para estado: {$state}");
    }

    public function reportSuccess(): void
    {
        $state = $this->getCircuitState();
        if ($state === self::CIRCUIT_STATE_HALF_OPEN) {
            $this->setCircuitState(self::CIRCUIT_STATE_CLOSED);
            Redis::del("circuit:{$this->name}:failures");
        } elseif ($state === self::CIRCUIT_STATE_CLOSED) {
            Redis::del("circuit:{$this->name}:failures");
        }
    }

    public function reportFailure(): void
    {
        Redis::set("circuit:{$this->name}:last_failure", microtime(true));
        
        $state = $this->getCircuitState();
        if ($state === self::CIRCUIT_STATE_HALF_OPEN || $state === self::CIRCUIT_STATE_OPEN) {
            $this->setCircuitState(self::CIRCUIT_STATE_OPEN);
            return;
        }

        $failures = Redis::incr("circuit:{$this->name}:failures");
        if ($failures === 1) {
            Redis::expire("circuit:{$this->name}:failures", $this->window);
        }

        if ($failures >= $this->threshold) {
            $this->setCircuitState(self::CIRCUIT_STATE_OPEN);
        }
    }

    public function isAvailable(): bool
    {
        return $this->getCircuitState() !== self::CIRCUIT_STATE_OPEN;
    }
}
