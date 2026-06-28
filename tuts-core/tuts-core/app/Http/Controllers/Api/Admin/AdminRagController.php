<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubjectMaterial;
use App\Services\AuditLogService;
use App\Services\RagIngestionService;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminRagController extends Controller
{
    public function health(RagService $ragService): JsonResponse
    {
        $internalUrl = config('services.python.url_health', 'http://rag:8001/health');
        $externalUrl = rtrim((string) config('services.rag.base_url', 'https://tutsai-tuts-rag-service.hf.space'), '/') . '/health';
        $internalToken = config('services.python.internal_token', '');

        $results = [
            'internal' => ['status' => 'unknown', 'url' => $internalUrl],
            'external' => ['status' => 'unknown', 'url' => $externalUrl],
            'circuit_breaker' => $ragService->getCircuitState(),
            'overall_status' => 'offline',
        ];

        // 1. Check Internal
        try {
            $start = microtime(true);
            $response = Http::timeout(3)
                ->withHeaders(['X-Internal-Token' => $internalToken])
                ->get($internalUrl);
            
            $results['internal'] = [
                'status' => $response->ok() ? 'online' : 'degraded',
                'code' => $response->status(),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'response' => $response->json(),
            ];
            if ($response->ok()) {
                $results['overall_status'] = 'online';
            }
        } catch (\Exception $e) {
            $results['internal'] = [
                'status' => 'offline',
                'error' => $e->getMessage()
            ];
        }

        // 2. Check External
        try {
            $start = microtime(true);
            $response = Http::timeout(3)
                ->withHeaders(['X-Internal-Token' => $internalToken])
                ->get($externalUrl);
            
            $results['external'] = [
                'status' => $response->ok() ? 'online' : 'degraded',
                'code' => $response->status(),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'response' => $response->json(),
            ];
            if ($response->ok() && $results['overall_status'] !== 'online') {
                $results['overall_status'] = 'online';
            }
        } catch (\Exception $e) {
            $results['external'] = [
                'status' => 'offline',
                'error' => $e->getMessage()
            ];
        }

        return response()->json($results);
    }

    public function materials(Request $request): JsonResponse
    {
        $query = SubjectMaterial::withTrashed()->with('subject:id,name,acronym');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $materials = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($materials);
    }

    public function reingest(string $id, RagIngestionService $ragIngestion): JsonResponse
    {
        $material = SubjectMaterial::findOrFail($id);

        try {
            $result = $ragIngestion->ingestSubjectMaterial($material);

            AuditLogService::log(
                'rag_reingestion_triggered',
                SubjectMaterial::class,
                $material->id,
                ['status' => $result['status'] ?? 'unknown']
            );

            return response()->json([
                'message' => 'Reprocessamento RAG executado.',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao reprocessar material no RAG.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testQuery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
            'subject_id' => 'required|integer|exists:subjects,id',
        ]);

        $subject = \App\Models\Subject::findOrFail($validated['subject_id']);
        $url = config('services.python.url', 'http://rag:8001/perguntar');
        $internalToken = config('services.python.internal_token', '');

        try {
            $start = microtime(true);
            $response = Http::timeout(10)
                ->withHeaders(['X-Internal-Token' => $internalToken])
                ->asForm()
                ->post($url, [
                    'texto' => $validated['query'],
                    'uc' => $subject->name,
                    'subject_id' => (string) $subject->id,
                    'context_type' => 'uc',
                ]);

            $latency = round((microtime(true) - $start) * 1000, 2);

            AuditLogService::log('rag_test_query_executed', null, null, [
                'query' => $validated['query'],
                'subject_id' => $subject->id,
                'latency_ms' => $latency,
                'status_code' => $response->status()
            ]);

            return response()->json([
                'status_code' => $response->status(),
                'latency_ms' => $latency,
                'response' => str_contains((string) $response->header('Content-Type'), 'application/json')
                    ? $response->json()
                    : $response->body(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro na resposta do serviço RAG.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
