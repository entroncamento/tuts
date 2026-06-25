<?php

namespace App\Services;

use App\Models\SubjectMaterial;
use Illuminate\Http\Client\ConnectionException;
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
                    'section_id' => $material->section_id ? (string) $material->section_id : '',
                    'source' => $material->source ?: 'official',
                    'verified' => $material->verified_by_teacher ? 'true' : 'false',
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

    private function isPdf(SubjectMaterial $material): bool
    {
        $mimeType = strtolower((string) $material->mime_type);
        $extension = strtolower((string) pathinfo($material->path ?: $material->url ?: $material->name, PATHINFO_EXTENSION));
        $type = strtolower((string) $material->type);

        return $mimeType === 'application/pdf' || $extension === 'pdf' || $type === 'pdf';
    }

    private function openReadableMaterialFile(SubjectMaterial $material): ?array
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
            ?? $this->openSafeLocalPath(storage_path('app/public/' . ltrim($path, '/')), $material)
            ?? $this->openSafeLocalPath(storage_path('app/' . ltrim($path, '/')), $material)
            ?? $this->openSafeLocalPath(storage_path('app/public/pdfs/' . basename($path)), $material)
            ?? $this->openSafeLocalPath(storage_path('app/pdfs/' . basename($path)), $material);
    }

    private function openSafeLocalPath(string $path, SubjectMaterial $material): ?array
    {
        $realPath = realpath($path);

        if (!$realPath || !is_file($realPath)) {
            return null;
        }

        $allowedRoots = array_filter([
            realpath(storage_path('app')),
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
}
