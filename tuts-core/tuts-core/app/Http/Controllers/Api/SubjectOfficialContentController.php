<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectMaterial;
use App\Models\SubjectSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubjectOfficialContentController extends Controller
{
    public function sections(string $subject): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);

        Log::info('official_subject_sections.enter', [
            'subject_id' => $resolvedSubject->id,
        ]);

        $sections = $resolvedSubject->sections()
            ->withCount('materials')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        Log::info('official_subject_sections.index', [
            'subject_id' => $resolvedSubject->id,
            'records_returned' => $sections->count(),
        ]);

        return response()->json([
            'data' => $sections->map(fn (SubjectSection $section) => $this->formatSection($section))->values(),
        ]);
    }

    public function materials(string $subject): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);

        Log::info('official_subject_materials.enter', [
            'subject_id' => $resolvedSubject->id,
        ]);

        $materials = $resolvedSubject->materials()
            ->orderBy('section_id')
            ->orderBy('created_at')
            ->orderBy('name')
            ->get();

        Log::info('official_subject_materials.index', [
            'subject_id' => $resolvedSubject->id,
            'records_returned' => $materials->count(),
        ]);

        return response()->json([
            'data' => $materials->map(fn (SubjectMaterial $material) => $this->formatMaterial($material))->values(),
        ]);
    }

    private function resolveSubject(string $subject): Subject
    {
        $subjectId = Str::startsWith($subject, 'uc-') ? Str::after($subject, 'uc-') : $subject;

        abort_unless(ctype_digit((string) $subjectId), 404);

        return Subject::query()->findOrFail($subjectId);
    }

    private function formatSection(SubjectSection $section): array
    {
        return [
            'id' => (string) $section->id,
            'subject_id' => (string) $section->subject_id,
            'name' => $section->name,
            'description' => $section->description,
            'order' => $section->order,
            'material_count' => $section->materials_count ?? 0,
        ];
    }

    private function formatMaterial(SubjectMaterial $material): array
    {
        return [
            'id' => (string) $material->id,
            'subject_id' => (string) $material->subject_id,
            'section_id' => $material->section_id ? (string) $material->section_id : null,
            'name' => $material->name,
            'type' => $material->type ?: $this->inferType($material),
            'mime_type' => $material->mime_type,
            'size' => $this->humanSize($material->size_bytes),
            'size_bytes' => $material->size_bytes,
            'source' => $material->source,
            'verified_by_teacher' => $material->verified_by_teacher,
            'url' => $material->url,
            'created_at' => $material->created_at?->toISOString(),
        ];
    }

    private function inferType(SubjectMaterial $material): ?string
    {
        $source = $material->url ?: $material->path ?: $material->name;
        $extension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : null;
    }

    private function humanSize(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
