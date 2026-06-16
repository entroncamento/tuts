<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Liveness probe: Is the app process alive?
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'service' => 'laravel']);
    }

    /**
     * Readiness probe: Is the app ready to serve traffic?
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];

        $allOk = !collect($checks)->contains(fn($c) => $c['status'] !== 'ok');

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'service' => 'laravel',
            'checks' => $checks
        ], $allOk ? 200 : 503);
    }

    /**
     * Full health check: Deep dive into all dependencies
     */
    public function health(): JsonResponse
    {
        $startTime = microtime(true);
        
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'rag_service' => $this->checkRagService(),
        ];

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $allOk = !collect($checks)->contains(fn($c) => $c['status'] !== 'ok');

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'service' => 'laravel',
            'duration_ms' => $duration,
            'checks' => $checks
        ], $allOk ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            return [
                'status' => 'ok',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            return [
                'status' => 'ok',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $writable = is_writable(storage_path('app/public'));
            return [
                'status' => $writable ? 'ok' : 'error',
                'writable' => $writable
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkRagService(): array
    {
        try {
            $url = config('services.python.url_health', 'http://rag:8001/health');
            $start = microtime(true);
            $response = Http::timeout(2)->get($url);
            
            return [
                'status' => $response->ok() ? 'ok' : 'degraded',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'code' => $response->status()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
