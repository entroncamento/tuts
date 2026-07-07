<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalMaterial;
use App\Models\SpaceFolder;
use App\Models\SpaceMaterialLink;
use App\Models\SpaceMaterial;
use App\Models\StudySpace;
use App\Models\SubjectMaterial;
use App\Services\PersonalMaterialStorageService;
use App\Services\RagIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpaceMaterialController extends Controller
{
    private const QUOTA_BYTES = 20 * 1024 * 1024;

    private const MAX_SIZE_KB = 5120;

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

    private function userId(): int
    {
        $userId = Auth::id();

        if (!$userId) {
            abort(401, 'Utilizador não autenticado.');
        }

        return (int) $userId;
    }

    public function index(Request $request, StudySpace $space): JsonResponse
    {
        $this->authorizeSpace($space);

        $validated = $request->validate([
            'folder_id' => 'nullable|integer',
        ]);

        $legacyQuery = $space->materials()
            ->where('user_id', $this->userId())
            ->with('folder')
            ->latest();

        $linkQuery = SpaceMaterialLink::query()
            ->where('study_space_id', $space->id)
            ->where('added_by', $this->userId())
            ->with('folder')
            ->latest();

        if (array_key_exists('folder_id', $validated)) {
            $folderId = $validated['folder_id'];

            if ($folderId) {
                $this->resolveFolder($space, (int) $folderId);
                $legacyQuery->where('space_folder_id', $folderId);
                $linkQuery->where('space_folder_id', $folderId);
            } else {
                $legacyQuery->whereNull('space_folder_id');
                $linkQuery->whereNull('space_folder_id');
            }
        }

        $linkedMaterials = $linkQuery->get()
            ->map(fn (SpaceMaterialLink $link) => $this->formatLinkedMaterial($space, $link))
            ->filter()
            ->values();

        $legacyMaterials = $legacyQuery->get()
            ->map(fn (SpaceMaterial $material) => $this->formatLegacyMaterial($space, $material));

        return response()->json([
            'status' => 'sucesso',
            'materials' => $linkedMaterials
                ->concat($legacyMaterials)
                ->sortByDesc('created_at')
                ->values(),
        ]);
    }

    public function store(Request $request, StudySpace $space, PersonalMaterialStorageService $storage, RagIngestionService $ragIngestion): JsonResponse
    {
        $this->authorizeSpace($space);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . self::MAX_SIZE_KB,
                'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
            ],
            'notes' => 'nullable|string|max:1000',
            'folder_id' => 'nullable|integer|exists:space_folders,id',
        ]);

        $folderId = null;
        if (!empty($validated['folder_id'])) {
            $folderId = $this->resolveFolder($space, (int) $validated['folder_id'])->id;
        }

        $file = $validated['file'];
        $sizeBytes = (int) ($file->getSize() ?: 0);
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();

        abort_unless($sizeBytes > 0 && in_array($extension, self::ALLOWED_EXTENSIONS, true) && in_array($mimeType, self::ALLOWED_MIME_TYPES, true), 422, 'Tipo de ficheiro não permitido.');

        $userId = $this->userId();

        if ($this->usedPersonalMaterialBytes($userId) + $sizeBytes > self::QUOTA_BYTES) {
            return response()->json([
                'message' => 'Personal materials quota exceeded.',
            ], 422);
        }

        $material = null;
        $link = null;

        try {
            [$material, $link] = DB::transaction(function () use ($storage, $userId, $file, $space, $folderId, $validated) {
                $material = $storage->createFromUpload($userId, $file);

                $link = SpaceMaterialLink::create([
                    'study_space_id' => $space->id,
                    'space_folder_id' => $folderId,
                    'material_type' => SpaceMaterialLink::TYPE_PERSONAL,
                    'material_id' => $material->id,
                    'added_by' => $userId,
                    'notes' => $validated['notes'] ?? null,
                ]);

                return [$material, $link];
            });
        } catch (\Throwable $exception) {
            Log::error('[TUTS][SpaceMaterials][Storage] upload failed', [
                'user_id' => $userId,
                'space_id' => $space->id,
                'folder_id' => $folderId,
                'disk' => 'r2',
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to upload space material.',
            ], 500);
        }

        $space->touch();

        try {
            $ragResult = $ragIngestion->ingestSpaceMaterialLink($link->load('studySpace'));
        } catch (\Throwable $exception) {
            Log::error('[TUTS][SpaceMaterials][RAG] Ingestion crashed during canonical upload', [
                'link_id' => $link->id,
                'material_id' => $material->id,
                'space_id' => $space->id,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            $ragResult = [
                'status' => 'failed',
                'message' => 'Material guardado, mas ocorreu um erro ao comunicar com o RAG.',
                'reason' => 'ingestion_crash',
            ];
        }

        return response()->json([
            'status' => 'sucesso',
            'material' => $this->formatLinkedMaterial($space, $link->load('folder'), $material),
            'rag_ingestion' => [
                'status' => $ragResult['status'] ?? 'failed',
                'message' => $ragResult['message'] ?? 'Material guardado, mas ainda nao ficou pesquisavel pelo RAG.',
                'reason' => $ragResult['reason'] ?? null,
            ],
        ], 201);
    }

    public function linkPersonal(Request $request, StudySpace $space, RagIngestionService $ragIngestion): JsonResponse
    {
        $this->authorizeSpace($space);

        $validated = $request->validate([
            'personal_material_id' => 'required|integer|exists:personal_materials,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $userId = $this->userId();

        $material = PersonalMaterial::query()
            ->where('id', $validated['personal_material_id'])
            ->where('owner_id', $userId)
            ->first();

        abort_unless($material, 404);

        $notesProvided = array_key_exists('notes', $validated);

        [$link, $created] = DB::transaction(function () use ($space, $material, $userId, $validated, $notesProvided) {
            $link = SpaceMaterialLink::query()
                ->where('study_space_id', $space->id)
                ->whereNull('space_folder_id')
                ->where('material_type', SpaceMaterialLink::TYPE_PERSONAL)
                ->where('material_id', $material->id)
                ->where('added_by', $userId)
                ->lockForUpdate()
                ->first();

            if ($link) {
                if ($notesProvided) {
                    $link->update([
                        'notes' => $validated['notes'] ?? null,
                    ]);
                }

                return [$link->fresh(), false];
            }

            $link = SpaceMaterialLink::create([
                'study_space_id' => $space->id,
                'space_folder_id' => null,
                'material_type' => SpaceMaterialLink::TYPE_PERSONAL,
                'material_id' => $material->id,
                'added_by' => $userId,
                'notes' => $validated['notes'] ?? null,
            ]);

            return [$link, true];
        });

        $space->touch();

        try {
            $ragResult = $ragIngestion->ingestSpaceMaterialLink($link->load('studySpace'));
        } catch (\Throwable $exception) {
            Log::error('[TUTS][SpaceMaterials][RAG] Ingestion crashed during personal material link', [
                'link_id' => $link->id,
                'material_id' => $material->id,
                'space_id' => $space->id,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            $ragResult = [
                'status' => 'failed',
                'message' => 'Material guardado, mas ocorreu um erro ao comunicar com o RAG.',
                'reason' => 'ingestion_crash',
            ];
        }

        return response()->json([
            'status' => 'sucesso',
            'material' => $this->formatLinkedMaterial($space, $link->load('folder'), $material),
            'rag_ingestion' => [
                'status' => $ragResult['status'] ?? 'failed',
                'message' => $ragResult['message'] ?? 'Material guardado, mas ainda nao ficou pesquisavel pelo RAG.',
                'reason' => $ragResult['reason'] ?? null,
            ],
        ], $created ? 201 : 200);
    }

    public function moveToFolder(Request $request, StudySpace $space, string $material): JsonResponse
    {
        $validated = $request->validate([
            'folder_id' => 'nullable|integer|exists:space_folders,id',
        ]);

        $folderId = null;
        if (!empty($validated['folder_id'])) {
            $folderId = $this->resolveFolder($space, (int) $validated['folder_id'])->id;
        }

        if ($link = $this->resolveLinkReference($space, $material)) {
            $link->update(['space_folder_id' => $folderId]);
            $space->touch();

            return response()->json([
                'status' => 'sucesso',
                'material' => $this->formatLinkedMaterial($space, $link->fresh()->load('folder')),
            ]);
        }

        $legacyMaterial = $this->resolveLegacyMaterial($space, $material);
        $legacyMaterial->update(['space_folder_id' => $folderId]);
        $space->touch();

        return response()->json([
            'status' => 'sucesso',
            'material' => $this->formatLegacyMaterial($space, $legacyMaterial->fresh()->load('folder')),
        ]);
    }

    public function download(StudySpace $space, string $material): StreamedResponse
    {
        if ($link = $this->resolveLinkReference($space, $material)) {
            [$stream, $filename, $mimeType, $sizeBytes] = $this->openLinkedMaterialStream($link);

            return response()->streamDownload(function () use ($stream) {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, $filename, [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $sizeBytes,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $legacyMaterial = $this->resolveLegacyMaterial($space, $material);
        $stream = $this->openLegacyMaterialStream($legacyMaterial);

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $legacyMaterial->original_name, [
            'Content-Type' => $legacyMaterial->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) $legacyMaterial->size_bytes,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function view(StudySpace $space, string $material): StreamedResponse
    {
        if ($link = $this->resolveLinkReference($space, $material)) {
            [$stream, $filename, $mimeType, $sizeBytes, $extension] = $this->openLinkedMaterialStream($link);

            $inlineExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'md', 'csv'];
            abort_unless(in_array(strtolower((string) $extension), $inlineExtensions, true), 415);

            return response()->stream(function () use ($stream) {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
                'Content-Length' => (string) $sizeBytes,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $legacyMaterial = $this->resolveLegacyMaterial($space, $material);

        $inlineExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'md', 'csv'];
        abort_unless(in_array(strtolower((string) $legacyMaterial->extension), $inlineExtensions, true), 415);

        $stream = $this->openLegacyMaterialStream($legacyMaterial);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $legacyMaterial->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($legacyMaterial->original_name) . '"',
            'Content-Length' => (string) $legacyMaterial->size_bytes,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(StudySpace $space, string $material): JsonResponse
    {
        if ($link = $this->resolveLinkReference($space, $material)) {
            $link->delete();
            $space->touch();

            return response()->json([
                'status' => 'sucesso',
            ]);
        }

        $legacyMaterial = $this->resolveLegacyMaterial($space, $material);

        if ($legacyMaterial->path && Storage::disk($legacyMaterial->disk)->exists($legacyMaterial->path)) {
            Storage::disk($legacyMaterial->disk)->delete($legacyMaterial->path);
        }

        $legacyMaterial->delete();
        $space->touch();

        return response()->json([
            'status' => 'sucesso',
        ]);
    }

    private function authorizeSpace(StudySpace $space): void
    {
        abort_unless((int) $space->user_id === $this->userId(), 403);
    }

    private function resolveFolder(StudySpace $space, int $folderId): SpaceFolder
    {
        return SpaceFolder::query()
            ->where('id', $folderId)
            ->where('study_space_id', $space->id)
            ->where('user_id', $this->userId())
            ->firstOrFail();
    }

    private function usedPersonalMaterialBytes(int $userId): int
    {
        return (int) PersonalMaterial::query()
            ->where('owner_id', $userId)
            ->sum('size_bytes');
    }

    private function resolveLinkReference(StudySpace $space, string $materialReference): ?SpaceMaterialLink
    {
        $this->authorizeSpace($space);

        if (!str_starts_with($materialReference, 'link-')) {
            return null;
        }

        $linkId = (int) substr($materialReference, 5);
        abort_unless($linkId > 0, 404);

        return SpaceMaterialLink::query()
            ->where('id', $linkId)
            ->where('study_space_id', $space->id)
            ->where('added_by', $this->userId())
            ->firstOrFail();
    }

    private function resolveLegacyMaterial(StudySpace $space, string $materialReference): SpaceMaterial
    {
        $this->authorizeSpace($space);

        $materialId = (int) $materialReference;
        abort_unless($materialId > 0, 404);

        return SpaceMaterial::query()
            ->where('id', $materialId)
            ->where('study_space_id', $space->id)
            ->where('user_id', $this->userId())
            ->firstOrFail();
    }

    private function formatLinkedMaterial(StudySpace $space, SpaceMaterialLink $link, PersonalMaterial|SubjectMaterial|null $material = null): ?array
    {
        $material ??= $this->resolveCanonicalMaterial($link);

        if (!$material) {
            return null;
        }

        $name = $material instanceof PersonalMaterial
            ? $material->original_name
            : $material->name;
        $extension = $material instanceof PersonalMaterial
            ? $material->extension
            : ($material->type ?: strtolower((string) pathinfo((string) $material->path, PATHINFO_EXTENSION)));
        $sizeBytes = (int) ($material->size_bytes ?? 0);
        $routeId = 'link-' . $link->id;
        $ragMaterialId = $link->ragMaterialId();

        return [
            'id' => $routeId,
            'link_id' => $link->id,
            'material_id' => $link->id,
            'rag_material_id' => $ragMaterialId,
            'material_type' => 'space_link',
            'source' => 'space',
            'canonical_material_type' => $link->material_type,
            'canonical_material_id' => $material->id,
            'personal_material_id' => $material instanceof PersonalMaterial ? $material->id : null,
            'space_id' => $link->study_space_id,
            'folder_id' => $link->space_folder_id,
            'folder_name' => $link->folder?->name,
            'name' => $name,
            'title' => $name,
            'original_name' => $name,
            'mime_type' => $material->mime_type,
            'extension' => $extension,
            'size_bytes' => $sizeBytes,
            'human_size' => $this->humanSize($sizeBytes),
            'notes' => $link->notes,
            'download_url' => url('/api/spaces/' . $space->id . '/materials/' . $routeId . '/download'),
            'view_url' => url('/api/spaces/' . $space->id . '/materials/' . $routeId . '/view'),
            'created_at' => $link->created_at?->toISOString(),
            'updated_at' => $link->updated_at?->toISOString(),
        ];
    }

    private function formatLegacyMaterial(StudySpace $space, SpaceMaterial $material): array
    {
        return [
            'id' => $material->id,
            'link_id' => null,
            'material_id' => $material->id,
            'material_type' => 'legacy_space',
            'source' => 'space',
            'space_id' => $material->study_space_id,
            'folder_id' => $material->space_folder_id,
            'folder_name' => $material->folder?->name,
            'name' => $material->original_name,
            'title' => $material->original_name,
            'original_name' => $material->original_name,
            'mime_type' => $material->mime_type,
            'extension' => $material->extension,
            'size_bytes' => $material->size_bytes,
            'human_size' => $this->humanSize((int) $material->size_bytes),
            'notes' => $material->notes,
            'download_url' => url('/api/spaces/' . $space->id . '/materials/' . $material->id . '/download'),
            'view_url' => url('/api/spaces/' . $space->id . '/materials/' . $material->id . '/view'),
            'created_at' => $material->created_at?->toISOString(),
            'updated_at' => $material->updated_at?->toISOString(),
        ];
    }

    private function resolveCanonicalMaterial(SpaceMaterialLink $link): PersonalMaterial|SubjectMaterial|null
    {
        if ($link->material_type === SpaceMaterialLink::TYPE_PERSONAL) {
            return PersonalMaterial::query()
                ->where('id', $link->material_id)
                ->where('owner_id', $this->userId())
                ->first();
        }

        if ($link->material_type === SpaceMaterialLink::TYPE_SUBJECT) {
            return SubjectMaterial::query()
                ->where('id', $link->material_id)
                ->first();
        }

        return null;
    }

    private function openLinkedMaterialStream(SpaceMaterialLink $link): array
    {
        $material = $this->resolveCanonicalMaterial($link);
        abort_unless($material, 404, 'Ficheiro não encontrado.');

        if ($material instanceof PersonalMaterial) {
            $path = trim((string) $material->storage_key);
            $diskName = $material->storage_disk ?: 'r2';
            $filename = $material->original_name;
            $mimeType = $material->mime_type ?: 'application/octet-stream';
            $sizeBytes = (int) $material->size_bytes;
            $extension = $material->extension;
        } else {
            $path = trim((string) $material->path);
            $diskName = $material->disk ?: 'r2';
            $filename = $material->name;
            $mimeType = $material->mime_type ?: 'application/octet-stream';
            $sizeBytes = (int) $material->size_bytes;
            $extension = $material->type ?: strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        }

        $stream = $this->openStorageStream($diskName, $path, [
            'material_link_id' => $link->id,
            'material_type' => $link->material_type,
            'material_id' => $link->material_id,
            'space_id' => $link->study_space_id,
        ]);

        return [$stream, $filename, $mimeType, $sizeBytes, $extension];
    }

    private function openLegacyMaterialStream(SpaceMaterial $material)
    {
        $path = trim((string) $material->path);

        $diskName = $material->disk ?: 'local';

        return $this->openStorageStream($diskName, $path, [
            'material_id' => $material->id,
            'space_id' => $material->study_space_id,
        ]);
    }

    private function openStorageStream(string $diskName, string $path, array $logContext)
    {
        abort_unless($path !== '', 404, 'Ficheiro não encontrado.');

        $disk = Storage::disk($diskName);

        try {
            abort_unless($disk->exists($path), 404, 'Ficheiro não encontrado.');

            $stream = $disk->readStream($path);
        } catch (\Throwable $exception) {
            Log::warning('[TUTS][SpaceMaterials] failed to open material stream', [
                'disk' => $diskName,
                'exception_class' => $exception::class,
            ] + $logContext);

            abort(404, 'Ficheiro não encontrado.');
        }

        abort_unless(is_resource($stream), 404, 'Ficheiro não encontrado.');

        return $stream;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
