<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subject::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('acronym', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $subjects = $query->withCount(['students', 'teachers', 'materials'])
            ->orderBy('name', 'asc')
            ->paginate($request->input('per_page', 15));

        return response()->json($subjects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'acronym' => 'required|string|max:20|unique:subjects',
            'degree' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1|max:6',
            'semester' => 'nullable|integer|min:1|max:2',
            'academic_year' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:50',
            'enrollment_code' => 'nullable|string|max:50',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'integer|exists:courses,id',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['source'] = 'manual';

        $subject = Subject::create($validated);

        if (!empty($validated['course_ids'])) {
            $subject->courses()->sync($validated['course_ids']);
        }

        AuditLogService::log(
            'subject_created',
            Subject::class,
            $subject->id,
            ['name' => $subject->name, 'acronym' => $subject->acronym]
        );

        return response()->json([
            'message' => 'Unidade Curricular criada com sucesso.',
            'subject' => $subject->load('courses')
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $subject = Subject::with(['courses', 'teachers:id,name,email', 'students:id,name,email', 'sections', 'materials'])
            ->findOrFail($id);

        return response()->json($subject);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'acronym' => 'required|string|max:20|unique:subjects,acronym,' . $subject->id,
            'degree' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1|max:6',
            'semester' => 'nullable|integer|min:1|max:2',
            'academic_year' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:50',
            'enrollment_code' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:30',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'integer|exists:courses,id',
        ]);

        $oldName = $subject->name;
        $subject->update($validated);

        if (isset($validated['course_ids'])) {
            $subject->courses()->sync($validated['course_ids']);
        }

        AuditLogService::log(
            'subject_updated',
            Subject::class,
            $subject->id,
            ['old_name' => $oldName, 'new_name' => $subject->name]
        );

        return response()->json([
            'message' => 'Unidade Curricular atualizada com sucesso.',
            'subject' => $subject->load('courses')
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);

        // Standard soft-delete since the Subject model implements SoftDeletes
        $subject->delete();

        AuditLogService::log('subject_deleted', Subject::class, $subject->id);

        return response()->json([
            'message' => 'Unidade Curricular arquivada/removida com sucesso (soft-delete).'
        ]);
    }
}
