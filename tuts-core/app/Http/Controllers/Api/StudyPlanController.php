<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudyPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StudyPlanController extends Controller
{
    protected StudyPlanService $studyPlanService;

    public function __construct(StudyPlanService $studyPlanService)
    {
        $this->studyPlanService = $studyPlanService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'subject_name' => 'required|string',
            'context' => 'required|string',
            'material_ids' => 'required|array',
            'material_ids.*' => 'required',
            'duration_weeks' => 'required|integer',
            'sessions_per_week' => 'required|integer',
        ]);

        try {
            $plan = $this->studyPlanService->generate($validated);
            return response()->json($plan, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar plano de estudo', [
                'user_id' => optional($request->user())->id,
                'subject_id' => $request->input('subject_id'),
                'material_ids' => $request->input('material_ids'),
                'context' => $request->input('context'),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $details = 'Ocorreu um erro no processamento do plano. A equipa técnica foi notificada.';

            if ($e instanceof \RuntimeException && $e->getCode() >= 400 && $e->getCode() < 600) {
                $statusCode = $e->getCode();
                $body = json_decode($e->getMessage(), true);
                if (is_array($body) && isset($body['detail'])) {
                    if (is_array($body['detail']) && isset($body['detail']['warnings'])) {
                        $details = implode(' ', $body['detail']['warnings']);
                    } elseif (is_string($body['detail'])) {
                        $details = $body['detail'];
                    } else {
                        $details = json_encode($body['detail'], JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $details = $e->getMessage();
                }
            } elseif (str_contains($e->getMessage(), 'Connection timed out') || str_contains($e->getMessage(), 'cURL error')) {
                $statusCode = Response::HTTP_BAD_GATEWAY;
                $details = 'O serviço RAG está temporariamente indisponível. Por favor, tente novamente mais tarde.';
            }

            return response()->json([
                'status' => 'erro',
                'message' => 'Erro ao gerar plano de estudo.',
                'details' => $details
            ], $statusCode);
        }
    }
}
