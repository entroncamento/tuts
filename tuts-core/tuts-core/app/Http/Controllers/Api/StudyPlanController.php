<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudyPlanService;
use Illuminate\Http\Request;
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
            'material_ids.*' => 'string',
            'duration_weeks' => 'required|integer',
            'sessions_per_week' => 'required|integer',
        ]);

        try {
            $plan = $this->studyPlanService->generate($validated);
            return response()->json($plan, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao comunicar com o serviço RAG.',
                'details' => $e->getMessage()
            ], Response::HTTP_BAD_GATEWAY); // 502 Bad Gateway
        }
    }
}
