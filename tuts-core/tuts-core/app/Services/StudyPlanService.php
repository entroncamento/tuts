<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StudyPlanService
{
    public function generate(array $data): array
    {
        $ragBaseUrl = rtrim((string) config('services.rag.base_url', ''), '/');
        $internalToken = trim((string) config('services.rag.internal_token', ''));

        if ($ragBaseUrl === '' || $internalToken === '') {
            Log::error('[TUTS][StudyPlan] RAG study-plan service is not configured', [
                'has_base_url' => $ragBaseUrl !== '',
                'has_internal_token' => $internalToken !== '',
            ]);

            throw new \RuntimeException('Serviço de planos de estudo indisponível.', 503);
        }

        $subject = $data['subject'];

        $payload = [
            'subject_id' => $subject['id'],
            'subject_name' => $subject['name'],
            'context' => $data['context'],
            'materials' => $data['materials'],
            'duration_weeks' => (int) $data['duration_weeks'],
            'sessions_per_week' => (int) $data['sessions_per_week']
        ];

        if (isset($data['adaptability_preferences']) && is_array($data['adaptability_preferences'])) {
            $payload['adaptability_preferences'] = $data['adaptability_preferences'];
        }

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-Internal-Token' => $internalToken,
                ])
                ->post("{$ragBaseUrl}/api/study-plans/generate", $payload);
        } catch (ConnectionException $e) {
            Log::warning('[TUTS][StudyPlan] failed to communicate with RAG', [
                'subject_id' => $subject['id'],
                'user_id' => $data['user_id'] ?? null,
                'error' => mb_substr($e->getMessage(), 0, 250),
            ]);

            throw new \RuntimeException('Serviço de planos de estudo indisponível.', 503);
        }

        if ($response->failed()) {
            $safeMessage = $this->extractErrorMessage($response->json());

            Log::warning('[TUTS][StudyPlan] non-success response from RAG', [
                'subject_id' => $subject['id'],
                'user_id' => $data['user_id'] ?? null,
                'http_status' => $response->status(),
                'error_detail' => $safeMessage,
            ]);

            throw new \RuntimeException($safeMessage ?: 'Erro ao gerar plano de estudo.', 502);
        }

        $plan = $response->json();

        return is_array($plan) ? $plan : [];
    }

    private function extractErrorMessage(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $message = $payload['detail'] ?? $payload['message'] ?? null;

        if (is_array($message)) {
            $message = $message['message'] ?? $message['msg'] ?? $message['detail'] ?? null;
        }

        if (!is_string($message)) {
            return null;
        }

        $message = preg_replace('/\s+/u', ' ', trim($message));

        return $message !== '' ? mb_substr($message, 0, 500) : null;
    }
}
