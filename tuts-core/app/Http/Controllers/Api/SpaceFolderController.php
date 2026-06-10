<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpaceFolder;
use App\Models\StudySpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpaceFolderController extends Controller
{
    private function userId(): int
    {
        $userId = Auth::id();

        if (!$userId) {
            abort(401, 'Utilizador não autenticado.');
        }

        return (int) $userId;
    }

    public function index(StudySpace $space): JsonResponse
    {
        $this->authorizeSpace($space);

        $folders = $space->folders()
            ->where('user_id', $this->userId())
            ->withCount(['chats', 'materials'])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (SpaceFolder $folder) => $this->formatFolder($folder));

        return response()->json([
            'status' => 'sucesso',
            'folders' => $folders,
        ]);
    }

    public function store(Request $request, StudySpace $space): JsonResponse
    {
        $this->authorizeSpace($space);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'type' => 'nullable|string|in:folder,module,topic,category',
            'color' => ['nullable', 'string', 'max:30', 'regex:/^#?[A-Za-z0-9(),.%\s-]+$/'],
            'position' => 'nullable|integer|min:0|max:9999',
            'parent_id' => 'nullable|integer|exists:space_folders,id',
        ]);

        if (!empty($validated['parent_id'])) {
            SpaceFolder::query()
                ->where('id', $validated['parent_id'])
                ->where('study_space_id', $space->id)
                ->where('user_id', $this->userId())
                ->firstOrFail();
        }

        $position = $validated['position'] ?? ((int) $space->folders()->max('position') + 1);

        $folder = SpaceFolder::create([
            'user_id' => $this->userId(),
            'study_space_id' => $space->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'folder',
            'color' => $validated['color'] ?? '#009957',
            'position' => $position,
        ]);

        $space->touch();

        return response()->json([
            'status' => 'sucesso',
            'folder' => $this->formatFolder($folder->loadCount(['chats', 'materials'])),
        ], 201);
    }

    public function update(Request $request, StudySpace $space, SpaceFolder $folder): JsonResponse
    {
        $this->authorizeFolder($space, $folder);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'type' => 'nullable|string|in:folder,module,topic,category',
            'color' => ['nullable', 'string', 'max:30', 'regex:/^#?[A-Za-z0-9(),.%\s-]+$/'],
            'position' => 'nullable|integer|min:0|max:9999',
            'parent_id' => 'nullable|integer|exists:space_folders,id',
        ]);

        if (!empty($validated['parent_id'])) {
            abort_if((int) $validated['parent_id'] === (int) $folder->id, 422, 'Uma pasta não pode ser filha dela própria.');

            SpaceFolder::query()
                ->where('id', $validated['parent_id'])
                ->where('study_space_id', $space->id)
                ->where('user_id', $this->userId())
                ->firstOrFail();
        }

        $folder->update($validated);
        $space->touch();

        return response()->json([
            'status' => 'sucesso',
            'folder' => $this->formatFolder($folder->fresh()->loadCount(['chats', 'materials'])),
        ]);
    }

    public function destroy(StudySpace $space, SpaceFolder $folder): JsonResponse
    {
        $this->authorizeFolder($space, $folder);

        $folder->chats()->update(['space_folder_id' => null]);
        $folder->materials()->update(['space_folder_id' => null]);
        $folder->children()->update(['parent_id' => null]);
        $folder->delete();
        $space->touch();

        return response()->json([
            'status' => 'sucesso',
        ]);
    }

    private function authorizeSpace(StudySpace $space): void
    {
        abort_unless((int) $space->user_id === $this->userId(), 403);
    }

    private function authorizeFolder(StudySpace $space, SpaceFolder $folder): void
    {
        $this->authorizeSpace($space);

        abort_unless(
            (int) $folder->study_space_id === (int) $space->id && (int) $folder->user_id === $this->userId(),
            403
        );
    }

    private function formatFolder(SpaceFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'space_id' => $folder->study_space_id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'type' => $folder->type,
            'color' => $folder->color,
            'position' => $folder->position,
            'chats_count' => $folder->chats_count ?? null,
            'materials_count' => $folder->materials_count ?? null,
            'created_at' => $folder->created_at?->toISOString(),
            'updated_at' => $folder->updated_at?->toISOString(),
        ];
    }
}
