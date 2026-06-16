<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpaceFolder;
use App\Models\SpaceMaterial;
use App\Models\StudySpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function store(Request $request, StudySpace $space): JsonResponse
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
        $storedName = (string) Str::uuid() . ($extension ? '.' . $extension : '');
        $directory = 'space-materials/' . $userId . '/' . $space->id;
        $path = $file->storeAs($directory, $storedName, 'local');

        $material = SpaceMaterial::create([
            'user_id' => $userId,
            'study_space_id' => $space->id,
            'space_folder_id' => $folderId,
            'original_name' => basename((string) $file->getClientOriginalName()),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize() ?: 0,
            'disk' => 'local',
            'path' => $path,
            'notes' => $validated['notes'] ?? null,
        ]);

        $space->touch();

        return response()->json([
            'status' => 'sucesso',
            'material' => $this->formatMaterial($space, $material->load('folder')),
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

    public function download(StudySpace $space, SpaceMaterial $material): BinaryFileResponse
    {
        $this->authorizeMaterial($space, $material);

        $absolutePath = Storage::disk($material->disk)->path($material->path);
        abort_unless(file_exists($absolutePath), 404, 'Ficheiro não encontrado.');

        return response()->download($absolutePath, $material->original_name, [
            'Content-Type' => $material->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function view(StudySpace $space, SpaceMaterial $material): BinaryFileResponse
    {
        $this->authorizeMaterial($space, $material);

        $inlineExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'md', 'csv'];
        abort_unless(in_array(strtolower((string) $material->extension), $inlineExtensions, true), 415);

        $absolutePath = Storage::disk($material->disk)->path($material->path);
        abort_unless(file_exists($absolutePath), 404, 'Ficheiro não encontrado.');

        return response()->file($absolutePath, [
            'Content-Type' => $material->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($material->original_name) . '"',
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
