<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectMaterial;
use App\Services\StudyPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StudyPlanController extends Controller
{
    protected StudyPlanService $studyPlanService;

    public function __construct(StudyPlanService $studyPlanService)
    {
        $this->studyPlanService = $studyPlanService;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'required|integer|exists:subjects,id',
            'subject_name' => 'nullable|string|max:255',
            'context' => 'required|string|max:3000',
            'material_ids' => 'nullable|array|max:20',
            'material_ids.*' => 'integer|distinct',
            'duration_weeks' => 'required|integer|min:1|max:16',
            'sessions_per_week' => 'required|integer|min:1|max:14',
            'adaptability_preferences' => 'nullable|array',
        ]);

        $user = $request->user();
        $subject = Subject::findOrFail((int) $validated['subject_id']);

        if (!$this->userCanAccessSubject($subject, $user)) {
            Log::warning('[TUTS][StudyPlan] subject access denied', [
                'user_id' => $user?->id,
                'subject_id' => $subject->id,
                'role' => $user?->role,
            ]);

            return response()->json([
                'status' => 'erro',
                'message' => 'Não tens acesso a esta UC.',
            ], Response::HTTP_FORBIDDEN);
        }

        Log::info('[TUTS][StudyPlan] subject access allowed', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'role' => $user->role,
        ]);

        $materials = $this->resolveSubjectMaterials($validated['material_ids'] ?? [], $subject);

        try {
            $plan = $this->studyPlanService->generate([
                'user_id' => $user->id,
                'subject' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                ],
                'context' => $validated['context'],
                'materials' => $materials,
                'duration_weeks' => (int) $validated['duration_weeks'],
                'sessions_per_week' => (int) $validated['sessions_per_week'],
                'adaptability_preferences' => $validated['adaptability_preferences'] ?? null,
            ]);

            return response()->json([
                'status' => 'sucesso',
                'plan' => $plan,
                'meta' => [
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                    'duration_weeks' => (int) $validated['duration_weeks'],
                    'sessions_per_week' => (int) $validated['sessions_per_week'],
                    'material_count' => count($materials),
                ],
            ], Response::HTTP_CREATED);
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
                'details' => $details,
            ], $statusCode);
        }
    }

    private function userCanAccessSubject(Subject $subject, $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'aluno') {
            return DB::table('subject_user')
                ->where('subject_id', $subject->id)
                ->where('user_id', $user->id)
                ->where('role', 'student')
                ->where('status', 'active')
                ->exists();
        }

        if ($user->role === 'professor') {
            $hasTeacherMembership = DB::table('subject_user')
                ->where('subject_id', $subject->id)
                ->where('user_id', $user->id)
                ->where('role', 'teacher')
                ->where('status', 'active')
                ->exists();

            return $hasTeacherMembership || (int) $subject->created_by === (int) $user->id;
        }

        return false;
    }

    private function resolveSubjectMaterials(array $materialIds, Subject $subject): array
    {
        if ($materialIds === []) {
            return [];
        }

        $materials = SubjectMaterial::query()
            ->whereIn('id', $materialIds)
            ->where('subject_id', $subject->id)
            ->get()
            ->keyBy('id');

        if ($materials->count() !== count(array_unique($materialIds))) {
            abort(response()->json([
                'status' => 'erro',
                'message' => 'Um ou mais materiais não pertencem a esta UC.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return collect($materialIds)
            ->map(function (int $materialId) use ($materials) {
                $material = $materials->get($materialId);

                return [
                    'id' => $material->id,
                    'name' => $material->name,
                    'title' => $material->name,
                    'type' => $material->type,
                    'mime_type' => $material->mime_type,
                    'size_bytes' => $material->size_bytes,
                    'subject_id' => $material->subject_id,
                    'section_id' => $material->section_id,
                ];
            })
            ->values()
            ->all();
    }
}
