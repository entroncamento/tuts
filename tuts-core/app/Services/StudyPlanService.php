<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class StudyPlanService
{
    public function generate(array $data): array
    {
        $ragBaseUrl = config('services.rag.base_url', 'http://127.0.0.1:8001');
        $internalToken = config('services.rag.internal_token');

        if (empty($data['material_ids'])) {
            abort(422, 'Não foram fornecidos materiais suficientes para gerar um plano de estudo.');
        }

        // Mapeamento dos materiais para a estrutura esperada pela RAG: id, title, type
        $materials = [];
        foreach ($data['material_ids'] as $matId) {
            $materials[] = [
                'id' => $matId,
                'title' => 'Material ' . $matId,
                'type' => 'pdf'
            ];
        }

        $payload = [
            'subject_id' => $data['subject_id'],
            'subject_name' => $data['subject_name'],
            'context' => $data['context'],
            'materials' => $materials,
            'duration_weeks' => (int) $data['duration_weeks'],
            'sessions_per_week' => (int) $data['sessions_per_week']
        ];

        $response = Http::withHeaders([
            'X-Internal-Token' => $internalToken,
            'Content-Type' => 'application/json'
        ])->post("{$ragBaseUrl}/api/study-plans/generate", $payload);

        if ($response->failed()) {
            $msg = $response->body() ?: 'Resposta vazia do RAG';
            throw new \RuntimeException($msg, $response->status());
        }

        return $response->json();
    }
}
