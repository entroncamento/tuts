<?php

namespace App\Services;

use App\Models\PersonalMaterial;
use App\Models\SpaceMaterial;
use App\Models\SpaceMaterialLink;
use App\Models\SubjectMaterial;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RagIngestionService
{
    public function ingestSubjectMaterial(SubjectMaterial $material): array
    {
        $material->loadMissing('subject');

        Log::info('[TUTS][RAG Ingestion] preparing subject material ingestion', [
            'subject_id' => $material->subject_id,
            'material_id' => $material->id,
            'section_id' => $material->section_id,
            'mime_type' => $material->mime_type,
            'size_bytes' => $material->size_bytes,
        ]);

        if (!$material->subject) {
            return $this->failed($material, 'missing_subject');
        }

        if (!$this->isPdf($material)) {
            return $this->failed($material, 'unsupported_type');
        }

        $baseUrl = rtrim((string) config('services.rag.base_url', ''), '/');
        $internalToken = trim((string) config('services.rag.internal_token', ''));

        if ($baseUrl === '' || $internalToken === '') {
            return $this->failed($material, 'rag_not_configured');
        }

        $file = $this->openReadableMaterialFile($material);

        if (!$file) {
            return $this->failed($material, 'file_not_readable');
        }

        [$stream, $filename, $mimeType] = $file;
        $filename = $material->id . '-' . $filename;

        Log::info('[TUTS][RAG Ingestion] sending subject material to RAG', [
            'subject_id' => $material->subject_id,
            'material_id' => $material->id,
            'section_id' => $material->section_id,
            'mime_type' => $mimeType,
            'size_bytes' => $material->size_bytes,
        ]);

        try {
            $response = Http::timeout(120)
                ->connectTimeout(15)
                ->withHeaders([
                    'X-Internal-Token' => $internalToken,
                ])
                ->attach('files', $stream, $filename, [
                    'Content-Type' => $mimeType,
                ])
                ->post($baseUrl . '/ingestao', [
                    'uc' => $material->subject->name,
                    'context_id' => (string) $material->subject_id,
                    'context_type' => 'uc',
                    'material_id' => (string) $material->id,
                    'materialId' => (string) $material->id,
                    'section_id' => $material->section_id ? (string) $material->section_id : '',
                    'source' => $material->source ?: 'official',
                    'verified' => $material->verified_by_teacher ? 'true' : 'false',
                    'storage_key' => (string) $material->path,
                    'file_path' => (string) $material->path,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[TUTS][RAG Ingestion] HTTP request failed', [
                'material_id' => $material->id,
                'subject_id' => $material->subject_id,
                'error_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->failed($material, 'rag_request_failed');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($response->successful()) {
            Log::info('[TUTS][RAG Ingestion] subject material ingestion succeeded', [
                'subject_id' => $material->subject_id,
                'material_id' => $material->id,
                'section_id' => $material->section_id,
                'mime_type' => $mimeType,
                'size_bytes' => $material->size_bytes,
                'status_code' => $response->status(),
            ]);

            return [
                'status' => 'success',
                'message' => 'Material enviado para indexacao RAG.',
                'http_status' => $response->status(),
                'response' => is_array($response->json()) ? $response->json() : null,
            ];
        }

        return $this->failed($material, 'rag_http_error', $response->status());
    }

    public function ingestSpaceMaterial(SpaceMaterial $material): array
    {
        $material->loadMissing('studySpace');

        Log::info('[TUTS][RAG Ingestion] preparing space material ingestion', [
            'space_id' => $material->study_space_id,
            'folder_id' => $material->space_folder_id,
            'material_id' => $material->id,
            'mime_type' => $material->mime_type,
            'size_bytes' => $material->size_bytes,
        ]);

        if (!$material->studySpace) {
            return $this->failedSpace($material, 'missing_space');
        }

        if (!$this->isSpacePdf($material)) {
            return $this->failedSpace($material, 'unsupported_type');
        }

        $baseUrl = rtrim((string) config('services.rag.base_url', ''), '/');
        $internalToken = trim((string) config('services.rag.internal_token', ''));

        if ($baseUrl === '' || $internalToken === '') {
            return $this->failedSpace($material, 'rag_not_configured');
        }

        $file = $this->openReadableMaterialFile($material);

        if (!$file) {
            return $this->failedSpace($material, 'file_not_readable');
        }

        [$stream, $filename, $mimeType] = $file;
        $filename = $material->id . '-' . $filename;
        $spaceLabel = trim((string) $material->studySpace->name);
        $spaceContextName = 'Espaço: ' . ($spaceLabel !== '' ? $spaceLabel : $material->study_space_id);
        $folderId = $material->space_folder_id ? (string) $material->space_folder_id : '';

        Log::info('[TUTS][RAG Ingestion] sending space material to RAG', [
            'space_id' => $material->study_space_id,
            'folder_id' => $material->space_folder_id,
            'material_id' => $material->id,
            'mime_type' => $mimeType,
            'size_bytes' => $material->size_bytes,
        ]);

        try {
            $response = Http::timeout(120)
                ->connectTimeout(15)
                ->withHeaders([
                    'X-Internal-Token' => $internalToken,
                ])
                ->attach('files', $stream, $filename, [
                    'Content-Type' => $mimeType,
                ])
                ->post($baseUrl . '/ingestao', [
                    'uc' => $spaceContextName,
                    'context_id' => (string) $material->study_space_id,
                    'context_type' => 'space',
                    'space_id' => (string) $material->study_space_id,
                    'folder_id' => $folderId,
                    'space_folder_id' => $folderId,
                    'material_id' => (string) $material->id,
                    'materialId' => (string) $material->id,
                    'source' => 'space',
                    'verified' => 'true',
                    'storage_key' => (string) $material->path,
                    'file_path' => (string) $material->path,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[TUTS][RAG Ingestion] HTTP request failed for space material', [
                'material_id' => $material->id,
                'space_id' => $material->study_space_id,
                'folder_id' => $material->space_folder_id,
                'error_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->failedSpace($material, 'rag_request_failed');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($response->successful()) {
            Log::info('[TUTS][RAG Ingestion] space material ingestion succeeded', [
                'space_id' => $material->study_space_id,
                'folder_id' => $material->space_folder_id,
                'material_id' => $material->id,
                'mime_type' => $mimeType,
                'size_bytes' => $material->size_bytes,
                'status_code' => $response->status(),
            ]);

            return [
                'status' => 'success',
                'message' => 'Material enviado para indexacao RAG.',
                'http_status' => $response->status(),
                'response' => is_array($response->json()) ? $response->json() : null,
            ];
        }

        return $this->failedSpace($material, 'rag_http_error', $response->status());
    }

    public function ingestSpaceMaterialLink(SpaceMaterialLink $link): array
    {
        $link->loadMissing('studySpace');

        Log::info('[TUTS][RAG Ingestion] preparing canonical space material link ingestion', [
            'space_id' => $link->study_space_id,
            'folder_id' => $link->space_folder_id,
            'link_id' => $link->id,
            'material_type' => $link->material_type,
            'material_id' => $link->ragMaterialId(),
            'rag_material_id' => $link->ragMaterialId(),
            'canonical_material_id' => $link->material_id,
        ]);

        if (!$link->studySpace) {
            return $this->failedSpaceLink($link, 'missing_space');
        }

        if ($link->material_type !== SpaceMaterialLink::TYPE_PERSONAL) {
            return $this->failedSpaceLink($link, 'unsupported_material_type');
        }

        $material = PersonalMaterial::query()
            ->where('id', $link->material_id)
            ->where('owner_id', $link->added_by)
            ->first();

        if (!$material) {
            return $this->failedSpaceLink($link, 'missing_personal_material');
        }

        if (!$this->isPersonalPdf($material)) {
            return $this->failedSpaceLink($link, 'unsupported_type', null, $material);
        }

        $baseUrl = rtrim((string) config('services.rag.base_url', ''), '/');
        $internalToken = trim((string) config('services.rag.internal_token', ''));

        if ($baseUrl === '' || $internalToken === '') {
            return $this->failedSpaceLink($link, 'rag_not_configured', null, $material);
        }

        $file = $this->openReadablePersonalMaterialFile($material);

        if (!$file) {
            return $this->failedSpaceLink($link, 'file_not_readable', null, $material);
        }

        [$stream, $filename, $mimeType] = $file;
        $filename = $material->id . '-' . $filename;
        $spaceLabel = trim((string) $link->studySpace->name);
        $spaceContextName = 'Espaço: ' . ($spaceLabel !== '' ? $spaceLabel : $link->study_space_id);
        $folderId = $link->space_folder_id ? (string) $link->space_folder_id : '';
        $ragMaterialId = $link->ragMaterialId();

        Log::info('[TUTS][RAG Ingestion] sending canonical space material link to RAG', [
            'space_id' => $link->study_space_id,
            'folder_id' => $link->space_folder_id,
            'link_id' => $link->id,
            'material_type' => $link->material_type,
            'material_id' => $ragMaterialId,
            'rag_material_id' => $ragMaterialId,
            'canonical_material_id' => $material->id,
            'mime_type' => $mimeType,
            'size_bytes' => $material->size_bytes,
        ]);

        try {
            $response = Http::timeout(120)
                ->connectTimeout(15)
                ->withHeaders([
                    'X-Internal-Token' => $internalToken,
                ])
                ->attach('files', $stream, $filename, [
                    'Content-Type' => $mimeType,
                ])
                ->post($baseUrl . '/ingestao', [
                    'uc' => $spaceContextName,
                    'context_id' => (string) $link->study_space_id,
                    'context_type' => 'space',
                    'space_id' => (string) $link->study_space_id,
                    'folder_id' => $folderId,
                    'space_folder_id' => $folderId,
                    'source' => 'space',
                    'material_id' => (string) $ragMaterialId,
                    'materialId' => (string) $ragMaterialId,
                    'link_id' => (string) $link->id,
                    'canonical_material_type' => SpaceMaterialLink::TYPE_PERSONAL,
                    'canonical_material_id' => (string) $material->id,
                    'verified' => 'true',
                    'storage_key' => (string) $material->storage_key,
                    'file_path' => (string) $material->storage_key,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[TUTS][RAG Ingestion] HTTP request failed for canonical space material link', [
                'link_id' => $link->id,
                'material_id' => $ragMaterialId,
                'rag_material_id' => $ragMaterialId,
                'canonical_material_id' => $material->id,
                'space_id' => $link->study_space_id,
                'folder_id' => $link->space_folder_id,
                'error_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->failedSpaceLink($link, 'rag_request_failed', null, $material);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($response->successful()) {
            Log::info('[TUTS][RAG Ingestion] canonical space material link ingestion succeeded', [
                'space_id' => $link->study_space_id,
                'folder_id' => $link->space_folder_id,
                'link_id' => $link->id,
                'material_id' => $ragMaterialId,
                'rag_material_id' => $ragMaterialId,
                'canonical_material_id' => $material->id,
                'mime_type' => $mimeType,
                'size_bytes' => $material->size_bytes,
                'status_code' => $response->status(),
            ]);

            return [
                'status' => 'success',
                'message' => 'Material enviado para indexacao RAG.',
                'http_status' => $response->status(),
                'response' => is_array($response->json()) ? $response->json() : null,
            ];
        }

        return $this->failedSpaceLink($link, 'rag_http_error', $response->status(), $material);
    }

    private function isPdf(SubjectMaterial $material): bool
    {
        $mimeType = strtolower((string) $material->mime_type);
        $extension = strtolower((string) pathinfo($material->path ?: $material->url ?: $material->name, PATHINFO_EXTENSION));
        $type = strtolower((string) $material->type);

        return $mimeType === 'application/pdf' || $extension === 'pdf' || $type === 'pdf';
    }

    private function isSpacePdf(SpaceMaterial $material): bool
    {
        $mimeType = strtolower((string) $material->mime_type);
        $extension = strtolower((string) ($material->extension ?: pathinfo((string) $material->path, PATHINFO_EXTENSION)));

        return $mimeType === 'application/pdf' || $extension === 'pdf';
    }

    private function isPersonalPdf(PersonalMaterial $material): bool
    {
        $mimeType = strtolower((string) $material->mime_type);
        $extension = strtolower((string) ($material->extension ?: pathinfo((string) $material->storage_key, PATHINFO_EXTENSION)));

        return $mimeType === 'application/pdf' || $extension === 'pdf';
    }

    private function openReadablePersonalMaterialFile(PersonalMaterial $material): ?array
    {
        $path = trim((string) $material->storage_key);

        if ($path === '') {
            return null;
        }

        $diskName = $material->storage_disk ?: 'r2';

        try {
            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                $stream = $disk->readStream($path);
                if (is_resource($stream)) {
                    return [$stream, basename($path), $material->mime_type ?: 'application/pdf'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[TUTS][RAG Ingestion] failed to read personal material from configured disk', [
                'disk' => $diskName,
                'path' => $path,
                'personal_material_id' => $material->id,
                'exception' => $e->getMessage(),
            ]);
        }

        foreach (['r2', 'public', 'local'] as $fallbackDisk) {
            if ($fallbackDisk === $diskName) {
                continue;
            }

            try {
                $disk = Storage::disk($fallbackDisk);
                if ($disk->exists($path)) {
                    $stream = $disk->readStream($path);
                    if (is_resource($stream)) {
                        return [$stream, basename($path), $material->mime_type ?: 'application/pdf'];
                    }
                }
            } catch (\Throwable) {
                // Ignore and try next disk.
            }
        }

        return null;
    }

    private function openReadableMaterialFile(SubjectMaterial|SpaceMaterial $material): ?array
    {
        $path = trim((string) $material->path);

        if ($path === '') {
            return null;
        }

        // Try explicit disk if set (e.g. 'r2')
        if (!empty($material->disk)) {
            try {
                $disk = Storage::disk($material->disk);
                if ($disk->exists($path)) {
                    $stream = $disk->readStream($path);
                    if (is_resource($stream)) {
                        return [$stream, basename($path), $material->mime_type ?: 'application/pdf'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[TUTS][RAG Ingestion] failed to read from configured disk', [
                    'disk' => $material->disk,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Fallback disk lookup
        foreach (['r2', 'public', 'local'] as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($path)) {
                    $stream = $disk->readStream($path);

                    if (is_resource($stream)) {
                        return [$stream, basename($path), $material->mime_type ?: 'application/pdf'];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and try next disk
            }
        }

        return $this->openSafeLocalPath($path, $material)
            ?? $this->openSafeLocalPath(storage_path('app/private/' . ltrim($path, '/')), $material)
            ?? $this->openSafeLocalPath(storage_path('app/public/' . ltrim($path, '/')), $material)
            ?? $this->openSafeLocalPath(storage_path('app/' . ltrim($path, '/')), $material)
            ?? $this->openSafeLocalPath(storage_path('app/public/pdfs/' . basename($path)), $material)
            ?? $this->openSafeLocalPath(storage_path('app/pdfs/' . basename($path)), $material);
    }

    private function openSafeLocalPath(string $path, SubjectMaterial|SpaceMaterial $material): ?array
    {
        $realPath = realpath($path);

        if (!$realPath || !is_file($realPath)) {
            return null;
        }

        $allowedRoots = array_filter([
            realpath(storage_path('app')),
            realpath(storage_path('app/private')),
            realpath(storage_path('app/public')),
            realpath(public_path('storage')),
        ]);

        foreach ($allowedRoots as $root) {
            if ($realPath === $root || str_starts_with($realPath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                $stream = fopen($realPath, 'rb');

                if (is_resource($stream)) {
                    return [$stream, basename($realPath), $material->mime_type ?: 'application/pdf'];
                }
            }
        }

        return null;
    }

    private function failed(SubjectMaterial $material, string $reason, ?int $statusCode = null): array
    {
        Log::warning('[TUTS][RAG Ingestion] subject material ingestion failed', [
            'subject_id' => $material->subject_id,
            'material_id' => $material->id,
            'section_id' => $material->section_id,
            'mime_type' => $material->mime_type,
            'size_bytes' => $material->size_bytes,
            'status_code' => $statusCode,
            'error_category' => $reason,
        ]);

        return [
            'status' => 'failed',
            'message' => 'Material guardado, mas ainda nao ficou pesquisavel pelo RAG.',
            'reason' => $reason,
            'http_status' => $statusCode,
        ];
    }

    private function failedSpace(SpaceMaterial $material, string $reason, ?int $statusCode = null): array
    {
        Log::warning('[TUTS][RAG Ingestion] space material ingestion failed', [
            'space_id' => $material->study_space_id,
            'folder_id' => $material->space_folder_id,
            'material_id' => $material->id,
            'mime_type' => $material->mime_type,
            'size_bytes' => $material->size_bytes,
            'status_code' => $statusCode,
            'error_category' => $reason,
        ]);

        return [
            'status' => 'failed',
            'message' => 'Material guardado, mas ainda nao ficou pesquisavel pelo RAG.',
            'reason' => $reason,
            'http_status' => $statusCode,
        ];
    }

    private function failedSpaceLink(SpaceMaterialLink $link, string $reason, ?int $statusCode = null, ?PersonalMaterial $material = null): array
    {
        Log::warning('[TUTS][RAG Ingestion] canonical space material link ingestion failed', [
            'space_id' => $link->study_space_id,
            'folder_id' => $link->space_folder_id,
            'link_id' => $link->id,
            'material_type' => $link->material_type,
            'material_id' => $link->ragMaterialId(),
            'rag_material_id' => $link->ragMaterialId(),
            'canonical_material_id' => $link->material_id,
            'personal_material_id' => $material?->id,
            'mime_type' => $material?->mime_type,
            'size_bytes' => $material?->size_bytes,
            'status_code' => $statusCode,
            'error_category' => $reason,
        ]);

        return [
            'status' => 'failed',
            'message' => 'Material guardado, mas ainda nao ficou pesquisavel pelo RAG.',
            'reason' => $reason,
            'http_status' => $statusCode,
        ];
    }
}
