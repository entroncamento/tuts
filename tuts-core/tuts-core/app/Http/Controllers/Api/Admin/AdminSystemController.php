<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class AdminSystemController extends Controller
{
    public function health(): JsonResponse
    {
        // 1. Check Database
        $dbStatus = false;
        $dbError = null;
        try {
            DB::connection()->getPdo();
            $dbStatus = true;
        } catch (\Exception $e) {
            $dbError = $e->getMessage();
        }

        // 2. Check Redis
        $redisStatus = false;
        $redisError = null;
        try {
            // Safe ping checking
            $ping = Redis::ping();
            $redisStatus = $ping === 'PONG' || $ping === true || !empty($ping);
        } catch (\Exception $e) {
            $redisError = $e->getMessage();
        }

        // 3. Check Storage (R2/Local Disk)
        $storageStatus = false;
        $storageError = null;
        try {
            $testFilename = 'healthcheck_' . time() . '.txt';
            Storage::put($testFilename, 'OK');
            if (Storage::exists($testFilename)) {
                Storage::delete($testFilename);
                $storageStatus = true;
            }
        } catch (\Exception $e) {
            $storageError = $e->getMessage();
        }

        // 4. Check RAG Health
        $ragStatus = false;
        $ragLatency = null;
        try {
            $url = config('services.python.url_health', 'http://rag:8001/health');
            $start = microtime(true);
            $response = Http::timeout(2)->get($url);
            if ($response->ok()) {
                $ragStatus = true;
                $ragLatency = round((microtime(true) - $start) * 1000, 2);
            }
        } catch (\Exception $e) {
            // Try fallback
            try {
                $extUrl = rtrim((string) config('services.rag.base_url', 'https://tutsai-tuts-rag-service.hf.space'), '/') . '/health';
                $start = microtime(true);
                $response = Http::timeout(2)->get($extUrl);
                if ($response->ok()) {
                    $ragStatus = true;
                    $ragLatency = round((microtime(true) - $start) * 1000, 2);
                }
            } catch (\Exception $e2) {
                // both failed
            }
        }

        // 5. Gather App Info
        $appInfo = [
            'env' => config('app.env'),
            'debug' => config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        return response()->json([
            'status' => ($dbStatus && $storageStatus && $ragStatus) ? 'healthy' : 'degraded',
            'services' => [
                'database' => [
                    'ok' => $dbStatus,
                    'error' => $dbStatus ? null : 'Falha na conexão de BD.',
                ],
                'redis' => [
                    'ok' => $redisStatus,
                    'error' => $redisStatus ? null : ($redisError ?: 'Redis indisponível/não configurado.'),
                ],
                'storage' => [
                    'ok' => $storageStatus,
                    'error' => $storageStatus ? null : ($storageError ?: 'Erro ao escrever no disco local/R2.'),
                ],
                'rag' => [
                    'ok' => $ragStatus,
                    'latency_ms' => $ragLatency,
                    'error' => $ragStatus ? null : 'FASTApi RAG indisponível.',
                ]
            ],
            'app' => $appInfo
        ]);
    }
}
