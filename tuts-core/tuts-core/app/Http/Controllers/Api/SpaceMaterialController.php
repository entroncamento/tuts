<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpaceFolder;
use App\Models\SpaceMaterial;
use App\Models\StudySpace;
use App\Services\RagIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpaceMaterialController extends Controller
{
    private const MAX_SIZE_KB = 20480; // 20MB

    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'txt', 'md', 'png', 'jpg', 'jpeg', 'webp', 'zip',
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

        $query = $space->materials()
            ->where('user_id', $this->userId())
            ->with('folder')
            ->latest();

        if (array_key_exists('folder_id', $validated)) {
            $folderId = $validated['folder_id'];

            if ($folderId) {
                $this->resolveFolder($space, (int) $folderId);
                $query->where('space_folder_id', $folderId);
            } else {
                $query->whereNull('space_folder_id');
            }
        }

        return response()->json([
            'status' => 'sucesso',
            'materials' => $query->get()->map(fn (SpaceMaterial $material) => $this->formatMaterial($space, $material)),
        ]);
    }

    public function store(Request $request, StudySpace $space, RagIngestionService $ragIngestion): JsonResponse
    {
        $this->authorizeSpace($space);

        $validated = $request->validate([
            'file' => 'required|file|max:' . self::MAX_SIZE_KB,
            'notes' => 'nullable|string|max:1000',
            'folder_id' => 'nullable|integer|exists:space_folders,id',
        ]);

        $folderId = null;
        if (!empty($validated['folder_id'])) {
            $folderId = $this->resolveFolder($space, (int) $validated['folder_id'])->id;
        }

        $file = $validated['file'];
        $extension = strtolower((string) $file->getClientOriginalExtension());

        abort_unless(in_array($extension, self::ALLOWED_EXTENSIONS, true), 422, 'Tipo de ficheiro não permitido.');

        $userId = $this->userId();
        $originalName = basename((string) $file->getClientOriginalName());
        $safeFilename = $this->safeFilename($originalName, $extension);
        $materialUuid = (string) Str::uuid();
        $directory = 'space-materials/spaces/' . $space->id . '/materials/' . $materialUuid;
        $path = $directory . '/' . $safeFilename;
        $stored = false;
        $material = null;

        try {
            $storedPath = Storage::disk('r2')->putFileAs($directory, $file, $safeFilename);

            if (!$storedPath) {
                throw new \RuntimeException('storage_write_failed');
            }

            $stored = true;
            $path = $storedPath;

            $material = SpaceMaterial::create([
                'user_id' => $userId,
                'study_space_id' => $space->id,
                'space_folder_id' => $folderId,
                'original_name' => $originalName,
                'stored_name' => $safeFilename,
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
                'size_bytes' => $file->getSize() ?: 0,
                'disk' => 'r2',
                'path' => $path,
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            try {
                if ($stored && Storage::disk('r2')->exists($path)) {
                    Storage::disk('r2')->delete($path);
                }
            } catch (\Throwable) {
                // Best-effort cleanup; the upload failure remains the response driver.
            }

            Log::error('[TUTS][SpaceMaterials][Storage] upload failed', [
                'user_id' => $userId,
                'space_id' => $space->id,
                'folder_id' => $folderId,
                'disk' => 'r2',
                'target_path' => $path,
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

        $ragResult = $this->ragSkipped();

        if ($this->isPdfMaterial($material)) {
            Log::info('[TUTS][Space Materials] RAG ingestion attempted', [
                'user_id' => $userId,
                'space_id' => $space->id,
                'folder_id' => $material->space_folder_id,
                'material_id' => $material->id,
                'mime_type' => $material->mime_type,
                'size_bytes' => $material->size_bytes,
            ]);

            try {
                $ragResult = $ragIngestion->ingestSpaceMaterial($material->load('studySpace'));
            } catch (\Throwable $exception) {
                Log::error('[TUTS][SpaceMaterials][RAG] Ingestion crashed during upload', [
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
        }

        return response()->json([
            'status' => 'sucesso',
            'material' => $this->formatMaterial($space, $material->load('folder')),
            'rag_ingestion' => [
                'status' => $ragResult['status'] ?? 'failed',
                'message' => $ragResult['message'] ?? 'Material guardado, mas ainda nao ficou pesquisavel pelo RAG.',
                'reason' => $ragResult['reason'] ?? null,
            ],
        ], 201);
    }

    public function moveToFolder(Request $request, StudySpace $space, SpaceMaterial $material): JsonResponse
    {
        $this->authorizeMaterial($space, $material);

        $validated = $request->validate([
            'folder_id' => 'nullable|integer|exists:space_folders,id',
        ]);

        $folderId = null;
        if (!empty($validated['folder_id'])) {
            $folderId = $this->resolveFolder($space, (int) $validated['folder_id'])->id;
        }

        $material->update(['space_folder_id' => $folderId]);
        $space->touch();

        return response()->json([
            'status' => 'sucesso',
            'material' => $this->formatMaterial($space, $material->fresh()->load('folder')),
        ]);
    }

    public function download(StudySpace $space, SpaceMaterial $material): StreamedResponse
    {
        $this->authorizeMaterial($space, $material);

        $stream = $this->openMaterialStream($material);

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $material->original_name, [
            'Content-Type' => $material->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) $material->size_bytes,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function view(StudySpace $space, SpaceMaterial $material): StreamedResponse
    {
        $this->authorizeMaterial($space, $material);

        $inlineExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'md', 'csv'];
        abort_unless(in_array(strtolower((string) $material->extension), $inlineExtensions, true), 415);

        $stream = $this->openMaterialStream($material);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $material->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($material->original_name) . '"',
            'Content-Length' => (string) $material->size_bytes,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(StudySpace $space, SpaceMaterial $material): JsonResponse
    {
        $this->authorizeMaterial($space, $material);

        if ($material->path && Storage::disk($material->disk)->exists($material->path)) {
            Storage::disk($material->disk)->delete($material->path);
        }

        $material->delete();
        $space->touch();

        return response()->json([
            'status' => 'sucesso',
        ]);
    }

    private function authorizeSpace(StudySpace $space): void
    {
        abort_unless((int) $space->user_id === $this->userId(), 403);
    }

    private function authorizeMaterial(StudySpace $space, SpaceMaterial $material): void
    {
        $this->authorizeSpace($space);

        abort_unless(
            (int) $material->study_space_id === (int) $space->id && (int) $material->user_id === $this->userId(),
            403
        );
    }

    private function resolveFolder(StudySpace $space, int $folderId): SpaceFolder
    {
        return SpaceFolder::query()
            ->where('id', $folderId)
            ->where('study_space_id', $space->id)
            ->where('user_id', $this->userId())
            ->firstOrFail();
    }

    private function formatMaterial(StudySpace $space, SpaceMaterial $material): array
    {
        return [
            'id' => $material->id,
            'space_id' => $material->study_space_id,
            'folder_id' => $material->space_folder_id,
            'folder_name' => $material->folder?->name,
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

    private function openMaterialStream(SpaceMaterial $material)
    {
        $path = trim((string) $material->path);
        abort_unless($path !== '', 404, 'Ficheiro não encontrado.');

        $diskName = $material->disk ?: 'local';
        $disk = Storage::disk($diskName);

        try {
            abort_unless($disk->exists($path), 404, 'Ficheiro não encontrado.');

            $stream = $disk->readStream($path);
        } catch (\Throwable $exception) {
            Log::warning('[TUTS][SpaceMaterials] failed to open material stream', [
                'material_id' => $material->id,
                'space_id' => $material->study_space_id,
                'disk' => $diskName,
                'exception_class' => $exception::class,
            ]);

            abort(404, 'Ficheiro não encontrado.');
        }

        abort_unless(is_resource($stream), 404, 'Ficheiro não encontrado.');

        return $stream;
    }

    private function isPdfMaterial(SpaceMaterial $material): bool
    {
        return strtolower((string) $material->mime_type) === 'application/pdf'
            || strtolower((string) $material->extension) === 'pdf'
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
