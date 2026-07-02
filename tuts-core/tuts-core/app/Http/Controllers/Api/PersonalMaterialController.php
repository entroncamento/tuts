<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalMaterial;
use App\Services\PersonalMaterialStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersonalMaterialController extends Controller
{
    private const QUOTA_BYTES = 20 * 1024 * 1024;

    private const MAX_UPLOAD_KB = 5120;

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

    private const INLINE_EXTENSIONS = [
        'pdf',
        'png',
        'jpg',
        'jpeg',
        'txt',
    ];

    public function index(): JsonResponse
    {
        $userId = $this->userId();

        Log::info('[TUTS][PersonalMaterials] listing materials', [
            'user_id' => $userId,
        ]);

        $materials = PersonalMaterial::query()
            ->where('owner_id', $userId)
            ->latest()
            ->get();

        return response()->json([
            'data' => $materials->map(fn (PersonalMaterial $material) => $this->formatMaterial($material))->values(),
            'quota' => $this->quotaPayload($userId),
        ]);
    }

    public function store(Request $request, PersonalMaterialStorageService $storage): JsonResponse
    {
        $userId = $this->userId();

        Log::info('[TUTS][PersonalMaterials] upload requested', [
            'user_id' => $userId,
        ]);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . self::MAX_UPLOAD_KB,
                'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
            ],
        ]);

        $file = $validated['file'];
        $sizeBytes = (int) ($file->getSize() ?: 0);
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();

        if ($sizeBytes <= 0 || !in_array($extension, self::ALLOWED_EXTENSIONS, true) || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return response()->json([
                'message' => 'Invalid file. Allowed types: PDF, DOCX, PPTX, PNG, JPEG and TXT.',
            ], 422);
        }

        $usedBytes = $this->usedBytes($userId);

        if ($usedBytes + $sizeBytes > self::QUOTA_BYTES) {
            return response()->json([
                'message' => 'Personal materials quota exceeded.',
                'quota' => $this->quotaPayload($userId),
            ], 422);
        }

        try {
            $material = $storage->createFromUpload($userId, $file);
        } catch (\Throwable $exception) {
            Log::warning('[TUTS][PersonalMaterials] upload failed', [
                'user_id' => $userId,
                'size_bytes' => $sizeBytes,
                'mime_type' => $mimeType,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Failed to upload material.',
            ], 500);
        }

        Log::info('[TUTS][PersonalMaterials] upload stored', [
            'user_id' => $userId,
            'material_id' => $material->id,
            'size_bytes' => $material->size_bytes,
            'mime_type' => $material->mime_type,
        ]);

        return response()->json([
            'data' => $this->formatMaterial($material),
            'quota' => $this->quotaPayload($userId),
        ], 201);
    }

    public function view(PersonalMaterial $material): Response|JsonResponse
    {
        $userId = $this->userId();

        if ((int) $material->owner_id !== $userId) {
            return response()->json([
                'message' => 'You do not have permission to view this material.',
            ], 403);
        }

        $rawPath = trim((string) $material->storage_key);

        if ($rawPath === '') {
            return response()->json([
                'message' => 'Material has no PDF path.',
                'material_id' => $material->id,
            ], 404);
        }

        $path = Str::contains($rawPath, '/storage/')
            ? Str::after($rawPath, '/storage/')
            : ltrim($rawPath, '/');
        $diskName = $material->storage_disk ?: 'public';

        try {
            $disk = Storage::disk($diskName);
            $exists = $disk->exists($path);

            Log::info('[TUTS][PersonalMaterials] preparing file response', [
                'material_id' => $material->id,
                'user_id' => $userId,
                'disk' => $diskName,
                'raw_path' => $rawPath,
                'resolved_path' => $path,
                'exists' => $exists,
                'file_size' => $material->size_bytes ?: null,
                'content_type' => $material->mime_type,
            ]);

            if (!$exists) {
                return response()->json([
                    'message' => 'PDF file not found.',
                    'material_id' => $material->id,
                    'path' => $path,
                    'disk' => $diskName,
                ], 404);
            }

            $contents = $disk->get($path);

            if ($contents === '') {
                return response()->json([
                    'message' => 'PDF file is empty or unreadable.',
                    'material_id' => $material->id,
                    'path' => $path,
                    'disk' => $diskName,
                ], 500);
            }

            $contentType = strtolower((string) $material->extension) === 'pdf'
                ? 'application/pdf'
                : ($material->mime_type ?: 'application/octet-stream');
            $disposition = in_array(strtolower((string) $material->extension), self::INLINE_EXTENSIONS, true)
                ? 'inline'
                : 'attachment';
            $filename = str_replace(['"', "\r", "\n"], '', basename($material->original_name ?: $path));

            Log::info('[TUTS][PersonalMaterials] file loaded', [
                'material_id' => $material->id,
                'user_id' => $userId,
                'disk' => $diskName,
                'resolved_path' => $path,
                'bytes_read' => strlen($contents),
                'content_type' => $contentType,
            ]);

            return response($contents, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
                'Content-Length' => (string) strlen($contents),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            Log::error('[TUTS][PersonalMaterials] failed to load file', [
                'material_id' => $material->id,
                'user_id' => $userId,
                'disk' => $diskName,
                'raw_path' => $rawPath,
                'resolved_path' => $path,
                'content_type' => $material->mime_type,
                'exception' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to load PDF from storage.',
            ], 500);
        }
    }

    public function destroy(PersonalMaterial $material): JsonResponse
    {
        $this->authorizeMaterial($material);

        Log::info('[TUTS][PersonalMaterials] delete requested', [
            'user_id' => $this->userId(),
            'material_id' => $material->id,
            'size_bytes' => $material->size_bytes,
            'mime_type' => $material->mime_type,
        ]);

        $disk = Storage::disk($material->storage_disk);

        try {
            if ($disk->exists($material->storage_key)) {
                $disk->delete($material->storage_key);
            }
        } catch (\Throwable $exception) {
            Log::warning('[TUTS][PersonalMaterials] delete failed', [
                'user_id' => $this->userId(),
                'material_id' => $material->id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Failed to delete material.',
            ], 500);
        }

        $material->delete();

        return response()->json([
            'message' => 'Material deleted successfully.',
        ]);
    }

    private function authorizeMaterial(PersonalMaterial $material): void
    {
        abort_unless((int) $material->owner_id === $this->userId(), 404);
    }

    private function userId(): int
    {
        $userId = Auth::id();

        if (!$userId) {
            abort(401, 'Utilizador não autenticado.');
        }

        return (int) $userId;
    }

    private function formatMaterial(PersonalMaterial $material): array
    {
        return [
            'id' => (string) $material->id,
            'name' => $material->original_name,
            'original_name' => $material->original_name,
            'mime_type' => $material->mime_type,
            'extension' => $material->extension,
            'size' => $this->humanSize((int) $material->size_bytes),
            'size_bytes' => $material->size_bytes,
            'source' => 'personal',
            'visibility' => 'private',
            'created_at' => $material->created_at?->toISOString(),
            'view_url' => '/api/me/materials/' . $material->id . '/view',
        ];
    }

    private function quotaPayload(int $userId): array
    {
        $usedBytes = $this->usedBytes($userId);

        return [
            'used_bytes' => $usedBytes,
            'limit_bytes' => self::QUOTA_BYTES,
            'remaining_bytes' => max(0, self::QUOTA_BYTES - $usedBytes),
        ];
    }

    private function usedBytes(int $userId): int
    {
        return (int) PersonalMaterial::query()
            ->where('owner_id', $userId)
            ->sum('size_bytes');
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
