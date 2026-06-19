<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectMaterial;
use App\Models\SubjectSection;
use App\Models\User;
use App\Services\RagIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubjectOfficialContentController extends Controller
{
    private const MAX_UPLOAD_KB = 20480;

    private const ALLOWED_EXTENSIONS = [
        'pdf',
        'docx',
        'pptx',
        'png',
        'jpg',
        'jpeg',
        'txt',
    ];

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/png',
        'image/jpeg',
        'text/plain',
    ];

    public function sections(Request $request, string $subject): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);
        $user = $this->user($request);
        $canTeach = $this->canTeachSubject($user, $resolvedSubject);

        abort_unless($canTeach || $this->canViewSubject($user, $resolvedSubject), 403, 'Sem acesso a esta UC.');

        Log::info('official_subject_sections.enter', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
        ]);

        $sections = $resolvedSubject->sections()
            ->withCount('materials')
            ->when(!$canTeach, function ($query) {
                $query
                    ->where('visible_to_students', true)
                    ->where(function ($visibilityQuery) {
                        $visibilityQuery
                            ->whereNull('visible_from')
                            ->orWhere('visible_from', '<=', now());
                    });
            })
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

    public function storeSection(Request $request, string $subject): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);
        $user = $this->user($request);

        Log::info('[TUTS][SubjectSections] create requested', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
        ]);

        $this->authorizeSectionWrite($user, $resolvedSubject);

        $validated = $request->validate([
            'title' => 'required_without:name|nullable|string|max:255',
            'name' => 'required_without:title|nullable|string|max:255',
            'description' => 'nullable|string',
            'visible_to_students' => 'nullable|boolean',
            'visible_from' => 'nullable|date',
            'order' => 'nullable|integer|min:0',
            'index' => 'nullable|integer|min:0',
        ]);

        $name = $this->sectionNameFromPayload($validated, true);
        $order = $this->sectionOrderFromPayload($validated)
            ?? ((int) $resolvedSubject->sections()->max('order') + 1);

        $section = $resolvedSubject->sections()->create([
            'name' => $name,
            'description' => $validated['description'] ?? null,
            'visible_to_students' => $validated['visible_to_students'] ?? true,
            'visible_from' => $validated['visible_from'] ?? null,
            'order' => $order,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'section' => $this->formatSection($section->fresh()),
        ], 201);
    }

    public function updateSection(Request $request, string $subject, SubjectSection $section): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);
        $user = $this->user($request);
        $this->ensureSectionBelongsToSubject($section, $resolvedSubject);

        Log::info('[TUTS][SubjectSections] update requested', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'section_id' => $section->id,
        ]);

        $this->authorizeSectionWrite($user, $resolvedSubject);

        $validated = $request->validate([
            'title' => 'sometimes|nullable|string|max:255',
            'name' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'visible_to_students' => 'sometimes|boolean',
            'visible_from' => 'sometimes|nullable|date',
            'order' => 'sometimes|nullable|integer|min:0',
            'index' => 'sometimes|nullable|integer|min:0',
        ]);

        $updates = [];
        $name = $this->sectionNameFromPayload($validated, false);

        if ($name !== null) {
            $updates['name'] = $name;
        }

        foreach (['description', 'visible_to_students', 'visible_from'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        $order = $this->sectionOrderFromPayload($validated);
        if ($order !== null) {
            $updates['order'] = $order;
        }

        if ($updates) {
            $section->update($updates);
        }

        return response()->json([
            'status' => 'sucesso',
            'section' => $this->formatSection($section->fresh()),
        ]);
    }

    public function destroySection(Request $request, string $subject, SubjectSection $section): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);
        $user = $this->user($request);
        $this->ensureSectionBelongsToSubject($section, $resolvedSubject);

        Log::info('[TUTS][SubjectSections] delete requested', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'section_id' => $section->id,
        ]);

        $this->authorizeSectionWrite($user, $resolvedSubject);

        $section->delete();

        return response()->json([
            'status' => 'sucesso',
            'message' => 'Secção apagada com sucesso.',
        ]);
    }

    public function materials(Request $request, string $subject): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);
        $user = $this->user($request);
        $canTeach = $this->canTeachSubject($user, $resolvedSubject);

        abort_unless($canTeach || $this->canViewSubject($user, $resolvedSubject), 403, 'Sem acesso a esta UC.');

        Log::info('official_subject_materials.enter', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
        ]);

        $materials = $resolvedSubject->materials()
            ->when(!$canTeach, function ($query) {
                $query->where(function ($materialQuery) {
                    $materialQuery
                        ->whereNull('section_id')
                        ->orWhereHas('section', function ($sectionQuery) {
                            $sectionQuery
                                ->where('visible_to_students', true)
                                ->where(function ($visibilityQuery) {
                                    $visibilityQuery
                                        ->whereNull('visible_from')
                                        ->orWhere('visible_from', '<=', now());
                                });
                        });
                });
            })
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

    public function storeMaterial(Request $request, string $subject, RagIngestionService $ragIngestion): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);
        $user = $this->user($request);

        Log::info('[TUTS][Subject Materials] upload request received', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'section_id' => $request->input('section_id'),
        ]);

        abort_unless($this->canTeachSubject($user, $resolvedSubject), 403, 'Sem permissao para adicionar materiais a esta UC.');

        Log::info('[TUTS][Subject Materials] authorization passed', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
        ]);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . self::MAX_UPLOAD_KB,
                'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
            ],
            'name' => 'nullable|string|max:255',
            'section_id' => 'nullable|integer|exists:subject_sections,id',
            'type' => 'nullable|string|max:40',
        ]);

        $section = null;
        if (!empty($validated['section_id'])) {
            $section = SubjectSection::query()
                ->where('id', (int) $validated['section_id'])
                ->where('subject_id', $resolvedSubject->id)
                ->firstOrFail();
        }

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $sizeBytes = (int) ($file->getSize() ?: 0);
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $originalName = basename((string) $file->getClientOriginalName());
        $displayName = trim((string) ($validated['name'] ?? '')) ?: $originalName;
        $safeFilename = $this->safeFilename($originalName, $extension);
        $storedName = (string) Str::uuid() . '-' . $safeFilename;
        $storagePath = 'subject-materials/subjects/' . $resolvedSubject->id . '/' . $storedName;

        Log::info('[TUTS][Subject Materials] file validated', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'section_id' => $section?->id,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

        $stored = false;
        $material = null;

        try {
            $stored = Storage::disk('local')->putFileAs(
                'subject-materials/subjects/' . $resolvedSubject->id,
                $file,
                $storedName
            );

            if (!$stored) {
                throw new \RuntimeException('storage_write_failed');
            }

            Log::info('[TUTS][Subject Materials] file stored', [
                'user_id' => $user->id,
                'subject_id' => $resolvedSubject->id,
                'section_id' => $section?->id,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
            ]);

            $material = SubjectMaterial::create([
                'subject_id' => $resolvedSubject->id,
                'section_id' => $section?->id,
                'name' => $displayName,
                'type' => $validated['type'] ?? $this->inferTypeFromUpload($mimeType, $extension),
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'path' => $storagePath,
                'url' => null,
                'source' => 'official',
                'verified_by_teacher' => true,
            ]);

            Log::info('[TUTS][Subject Materials] material row created', [
                'user_id' => $user->id,
                'subject_id' => $resolvedSubject->id,
                'section_id' => $material->section_id,
                'material_id' => $material->id,
                'mime_type' => $material->mime_type,
                'size_bytes' => $material->size_bytes,
            ]);
        } catch (\Throwable $exception) {
            if ($stored && Storage::disk('local')->exists($storagePath)) {
                try {
                    Storage::disk('local')->delete($storagePath);
                } catch (\Throwable) {
                    // Cleanup is best-effort; the original failure remains the response driver.
                }
            }

            Log::warning('[TUTS][Subject Materials] upload failed', [
                'user_id' => $user->id,
                'subject_id' => $resolvedSubject->id,
                'section_id' => $section?->id,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'error_category' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Failed to upload subject material.',
            ], 500);
        }

        $ragResult = $this->ragSkipped();

        if ($this->isPdfMaterial($material)) {
            Log::info('[TUTS][Subject Materials] RAG ingestion attempted', [
                'user_id' => $user->id,
                'subject_id' => $resolvedSubject->id,
                'section_id' => $material->section_id,
                'material_id' => $material->id,
                'mime_type' => $material->mime_type,
                'size_bytes' => $material->size_bytes,
            ]);

            $ragResult = $ragIngestion->ingestSubjectMaterial($material);
        }

        Log::info('[TUTS][Subject Materials] upload completed', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'section_id' => $material->section_id,
            'material_id' => $material->id,
            'mime_type' => $material->mime_type,
            'size_bytes' => $material->size_bytes,
            'rag_ingestion_status' => $ragResult['status'] ?? 'unknown',
        ]);

        return response()->json([
            'status' => 'sucesso',
            'material' => $this->formatMaterial($material->fresh()),
            'rag_ingestion' => [
                'status' => $ragResult['status'] ?? 'failed',
                'message' => $ragResult['message'] ?? 'Material guardado, mas ainda nao ficou pesquisavel pelo RAG.',
                'reason' => $ragResult['reason'] ?? null,
            ],
        ], 201);
    }

    public function ingestMaterial(Request $request, string $subject, SubjectMaterial $material, RagIngestionService $ragIngestion): JsonResponse
    {
        $resolvedSubject = $this->resolveSubject($subject);
        $user = $this->user($request);

        abort_unless((int) $material->subject_id === (int) $resolvedSubject->id, 404);
        abort_unless($this->canTeachSubject($user, $resolvedSubject), 403, 'Sem permissao para indexar materiais desta UC.');

        Log::info('official_subject_materials.ingest.enter', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'material_id' => $material->id,
            'section_id' => $material->section_id,
            'mime_type' => $material->mime_type,
            'size_bytes' => $material->size_bytes,
        ]);

        $result = $ragIngestion->ingestSubjectMaterial($material);

        return response()->json([
            'status' => 'sucesso',
            'material' => $this->formatMaterial($material->fresh()),
            'rag_ingestion' => [
                'status' => $result['status'] ?? 'failed',
                'message' => $result['message'] ?? 'Material guardado, mas ainda nao ficou pesquisavel pelo RAG.',
                'reason' => $result['reason'] ?? null,
            ],
        ]);
    }

    private function resolveSubject(string $subject): Subject
    {
        $subjectId = Str::startsWith($subject, 'uc-') ? Str::after($subject, 'uc-') : $subject;

        abort_unless(ctype_digit((string) $subjectId), 404);

        return Subject::query()->findOrFail($subjectId);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Utilizador nao autenticado.');
        }

        return $user;
    }

    private function canTeachSubject(User $user, Subject $subject): bool
    {
        if ((int) $subject->created_by === (int) $user->id) {
            return true;
        }

        return DB::table('subject_user')
            ->where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('role', 'teacher')
            ->where('status', 'active')
            ->exists();
    }

    private function canViewSubject(User $user, Subject $subject): bool
    {
        if ($this->canTeachSubject($user, $subject)) {
            return true;
        }

        return DB::table('subject_user')
            ->where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('role', 'student')
            ->where('status', 'active')
            ->exists();
    }

    private function authorizeSectionWrite(User $user, Subject $subject): void
    {
        if ($this->canTeachSubject($user, $subject)) {
            return;
        }

        Log::warning('[TUTS][SubjectSections] authorization failed', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
        ]);

        abort(403, 'Sem permissao para gerir secções desta UC.');
    }

    private function ensureSectionBelongsToSubject(SubjectSection $section, Subject $subject): void
    {
        abort_unless((int) $section->subject_id === (int) $subject->id, 404);
    }

    private function sectionNameFromPayload(array $payload, bool $required): ?string
    {
        foreach (['title', 'name'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null && trim((string) $payload[$field]) === '') {
                throw ValidationException::withMessages([
                    $field => 'O nome da secção não pode estar vazio.',
                ]);
            }
        }

        if (array_key_exists('title', $payload) && $payload['title'] !== null) {
            return trim((string) $payload['title']);
        }

        if (array_key_exists('name', $payload) && $payload['name'] !== null) {
            return trim((string) $payload['name']);
        }

        if ($required) {
            throw ValidationException::withMessages([
                'title' => 'Indica o título da secção.',
            ]);
        }

        return null;
    }

    private function sectionOrderFromPayload(array $payload): ?int
    {
        if (array_key_exists('order', $payload) && $payload['order'] !== null) {
            return (int) $payload['order'];
        }

        if (array_key_exists('index', $payload) && $payload['index'] !== null) {
            return (int) $payload['index'];
        }

        return null;
    }

    private function formatSection(SubjectSection $section): array
    {
        return [
            'id' => (int) $section->id,
            'subject_id' => (int) $section->subject_id,
            'name' => $section->name,
            'title' => $section->name,
            'description' => $section->description,
            'visible_to_students' => (bool) $section->visible_to_students,
            'visible_from' => $section->visible_from?->toISOString(),
            'order' => (int) $section->order,
            'index' => (int) $section->order,
            'material_count' => $section->materials_count ?? 0,
            'created_at' => $section->created_at?->toISOString(),
            'updated_at' => $section->updated_at?->toISOString(),
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

    private function inferTypeFromUpload(?string $mimeType, string $extension): ?string
    {
        if ($extension !== '') {
            return $extension;
        }

        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'text/plain' => 'txt',
            default => null,
        };
    }

    private function isPdfMaterial(SubjectMaterial $material): bool
    {
        return strtolower((string) $material->mime_type) === 'application/pdf'
            || strtolower((string) $material->type) === 'pdf'
            || strtolower((string) pathinfo((string) $material->path, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function ragSkipped(): array
    {
        return [
            'status' => 'skipped',
            'message' => 'Apenas PDFs sao indexados pelo RAG nesta fase.',
            'reason' => 'unsupported_type',
        ];
    }

    private function safeFilename(string $filename, string $extension): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $safeName = Str::slug(Str::ascii($name), '-');

        if ($safeName === '') {
            $safeName = 'material';
        }

        return $extension !== '' ? $safeName . '.' . $extension : $safeName;
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
