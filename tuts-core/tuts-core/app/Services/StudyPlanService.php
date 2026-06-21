<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

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

            throw new \RuntimeException('Serviço de planos de estudo indisponível.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $subject = $data['subject'];

        $payload = [
            'subject_id' => $subject['id'],
            'subject_name' => $subject['name'],
            'context' => $data['context'],
            'materials' => $data['materials'],
            'duration_weeks' => (int) $data['duration_weeks'],
            'sessions_per_week' => (int) $data['sessions_per_week'],
        ];

        if (isset($data['adaptability_preferences']) && is_array($data['adaptability_preferences'])) {
            $payload['adaptability_preferences'] = $data['adaptability_preferences'];
        }

        $ragUrl = "{$ragBaseUrl}/api/study-plans/generate";

        Log::info('[TUTS][StudyPlan] RAG request', [
            'rag_url' => $ragUrl,
            'subject_id' => $payload['subject_id'],
            'subject_name' => $payload['subject_name'],
            'material_ids' => collect($payload['materials'])->pluck('id')->values()->all(),
            'duration_weeks' => $payload['duration_weeks'],
            'sessions_per_week' => $payload['sessions_per_week'],
            'user_id' => $data['user_id'] ?? null,
        ]);

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-Internal-Token' => $internalToken,
                ])
                ->post($ragUrl, $payload);
        } catch (ConnectionException $e) {
            Log::warning('[TUTS][StudyPlan] failed to communicate with RAG', [
                'subject_id' => $subject['id'],
                'user_id' => $data['user_id'] ?? null,
                'error' => mb_substr($e->getMessage(), 0, 250),
            ]);

            throw new \RuntimeException('Serviço de planos de estudo indisponível.', Response::HTTP_BAD_GATEWAY);
        }

        if ($response->failed()) {
            $responseJson = $response->json();
            $responseBody = $response->body();
            $safeMessage = $this->extractErrorMessage($responseJson) ?: $this->extractErrorMessageFromRawBody($responseBody);
            $statusCode = $this->frontendStatusForRagStatus($response->status());

            Log::warning('[TUTS][StudyPlan] RAG response failed', [
                'subject_id' => $subject['id'],
                'subject_name' => $subject['name'],
                'material_ids' => collect($payload['materials'])->pluck('id')->values()->all(),
                'user_id' => $data['user_id'] ?? null,
                'status' => $response->status(),
                'body' => mb_substr($responseBody, 0, 2000),
                'error_detail' => $safeMessage,
            ]);

            throw new \RuntimeException(json_encode([
                'message' => $safeMessage ?: 'Erro ao gerar plano de estudo.',
                'details' => $responseJson ?? $responseBody,
                'rag_status' => $response->status(),
            ], JSON_UNESCAPED_UNICODE), $statusCode);
        }

        $plan = $response->json();

        return is_array($plan) ? $plan : [];
    }

    private function frontendStatusForRagStatus(int $ragStatus): int
    {
        if ($ragStatus === Response::HTTP_UNPROCESSABLE_ENTITY) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        if (in_array($ragStatus, [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNAUTHORIZED,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_NOT_FOUND,
            Response::HTTP_CONFLICT,
        ], true)) {
            return $ragStatus;
        }

        return Response::HTTP_BAD_GATEWAY;
    }

    private function extractErrorMessageFromRawBody(string $body): ?string
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            return $this->extractErrorMessage($decoded);
        }

        $body = preg_replace('/\s+/u', ' ', trim($body));

        return $body !== '' ? mb_substr($body, 0, 500) : null;
    }

    private function extractErrorMessage(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        foreach (['details', 'detail', 'message', 'error', 'errors'] as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $message = $this->stringifyErrorValue($payload[$key]);
            if ($message !== null) {
                return $message;
            }
        }

        return $this->stringifyErrorValue($payload);
    }

    private function stringifyErrorValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = preg_replace('/\s+/u', ' ', trim($value));

            return $value !== '' ? mb_substr($value, 0, 500) : null;
        }

        if (!is_array($value)) {
            return null;
        }

        if (isset($value['warnings']) && is_array($value['warnings'])) {
            return $this->joinMessages($value['warnings']);
        }

        foreach (['message', 'msg', 'detail', 'error'] as $key) {
            if (array_key_exists($key, $value)) {
                $message = $this->stringifyErrorValue($value[$key]);
                if ($message !== null) {
                    return $message;
                }
            }
        }

        if (array_is_list($value)) {
            return $this->joinMessages(array_map(
                fn (mixed $item) => $this->stringifyErrorValue($item),
                $value,
            ));
        }

        return null;
    }

    private function joinMessages(array $messages): ?string
    {
        $text = collect($messages)
            ->filter(fn (mixed $message) => is_string($message) && trim($message) !== '')
            ->map(fn (string $message) => preg_replace('/\s+/u', ' ', trim($message)))
            ->implode(' ');

        return $text !== '' ? mb_substr($text, 0, 500) : null;
    }
}