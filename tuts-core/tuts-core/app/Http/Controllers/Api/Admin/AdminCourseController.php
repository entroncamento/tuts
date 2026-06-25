<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::withCount('subjects')
            ->orderBy('name', 'asc')
            ->paginate($request->input('per_page', 15));

        return response()->json($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:courses',
            'url' => 'nullable|url|max:255',
        ]);

        $course = Course::create($validated);

        AuditLogService::log(
            'course_created',
            Course::class,
            $course->id,
            ['name' => $course->name]
        );

        return response()->json([
            'message' => 'Curso criado com sucesso.',
            'course' => $course
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $course = Course::with('subjects')->findOrFail($id);
        return response()->json($course);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:courses,name,' . $course->id,
            'url' => 'nullable|url|max:255',
        ]);

        $oldName = $course->name;
        $course->update($validated);

        AuditLogService::log(
            'course_updated',
            Course::class,
            $course->id,
            ['old_name' => $oldName, 'new_name' => $course->name]
        );

        return response()->json([
            'message' => 'Curso atualizado com sucesso.',
            'course' => $course
        ]);
    }
}
