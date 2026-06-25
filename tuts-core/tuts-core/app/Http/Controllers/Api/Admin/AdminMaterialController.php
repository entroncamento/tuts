<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubjectMaterial;
use App\Services\AuditLogService;
use App\Services\RagIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SubjectMaterial::query()->with('subject:id,name,acronym');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        $materials = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($materials);
    }

    public function show(string $id): JsonResponse
    {
        $material = SubjectMaterial::with('subject:id,name,acronym')->findOrFail($id);
        return response()->json($material);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $material = SubjectMaterial::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'verified_by_teacher' => 'required|boolean',
            'section_id' => 'nullable|integer|exists:subject_sections,id',
        ]);

        $oldVerified = $material->verified_by_teacher;
        $material->update($validated);

        AuditLogService::log(
            'material_updated',
            SubjectMaterial::class,
            $material->id,
            [
                'name' => $material->name,
                'old_verified' => $oldVerified,
                'new_verified' => $material->verified_by_teacher
            ]
        );

        return response()->json([
            'message' => 'Material atualizado com sucesso.',
            'material' => $material
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $material = SubjectMaterial::findOrFail($id);

        // Soft deletes the material
        $material->delete();

        AuditLogService::log('material_deleted', SubjectMaterial::class, $material->id);

        return response()->json([
            'message' => 'Material arquivado/removido logicamente (soft-delete).'
        ]);
    }

    public function reingest(string $id, RagIngestionService $ragIngestion): JsonResponse
    {
        $material = SubjectMaterial::findOrFail($id);

        try {
            $result = $ragIngestion->ingestSubjectMaterial($material);

            AuditLogService::log(
                'material_reingestion_attempted',
                SubjectMaterial::class,
                $material->id,
                ['status' => $result['status'] ?? 'unknown']
            );

            return response()->json([
                'message' => 'Processamento de ingestão RAG concluído/iniciado.',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao chamar o serviço de ingestão RAG.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
