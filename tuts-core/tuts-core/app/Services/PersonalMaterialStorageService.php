<?php

namespace App\Services;

use App\Models\PersonalMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersonalMaterialStorageService
{
    public function createFromUpload(int $userId, UploadedFile $file): PersonalMaterial
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
        $sizeBytes = (int) ($file->getSize() ?: 0);
        $originalName = basename((string) $file->getClientOriginalName());
        $safeFilename = $this->safeFilename($originalName, $extension);
        $storageKey = 'personal/users/' . $userId . '/' . Str::uuid() . '-' . $safeFilename;
        $disk = Storage::disk('r2');
        $fileStream = null;

        try {
            $fileStream = fopen($file->getRealPath(), 'rb');

            if ($fileStream === false) {
                throw new \RuntimeException('Unable to open uploaded file stream.');
            }

            $stored = $disk->put($storageKey, $fileStream);

            if (!$stored) {
                throw new \RuntimeException('R2 write returned false.');
            }

            return DB::transaction(function () use ($userId, $originalName, $mimeType, $extension, $sizeBytes, $storageKey) {
                return PersonalMaterial::create([
                    'owner_id' => $userId,
                    'uploaded_by' => $userId,
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'size_bytes' => $sizeBytes,
                    'storage_disk' => 'r2',
                    'storage_key' => $storageKey,
                ]);
            });
        } catch (\Throwable $exception) {
            try {
                if ($storageKey !== '' && $disk->exists($storageKey)) {
                    $disk->delete($storageKey);
                }
            } catch (\Throwable) {
                // Upload failure remains the response driver.
            }

            throw $exception;
        } finally {
            if (is_resource($fileStream)) {
                fclose($fileStream);
            }
        }
    }

    private function safeFilename(string $filename, string $extension): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $slug = Str::slug($name);

        if ($slug === '') {
            $slug = 'material';
        }

        return $extension !== '' ? $slug . '.' . $extension : $slug;
    }
}
