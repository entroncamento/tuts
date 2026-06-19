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
