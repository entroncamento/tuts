<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\SpaceFolder;
use App\Models\StudySpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudySpaceController extends Controller
{
    private const MAX_SPACES_PER_USER = 5;

    private function userId(): int
    {
        $userId = Auth::id();

        if (!$userId) {
            abort(401, 'Utilizador não autenticado.');
        }

        return (int) $userId;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:120',
        ]);

        $query = StudySpace::query()
            ->where('user_id', $this->userId())
            ->withCount(['chats', 'materials', 'folders'])
            ->latest('updated_at');

        if (!empty($validated['q'])) {
            $q = trim($validated['q']);

            $query->where(function ($builder) use ($q) {
                $builder
                    ->where('name', 'ilike', '%' . $q . '%')
                    ->orWhere('description', 'ilike', '%' . $q . '%');
            });
        }

        $spaces = $query->get();

        return response()->json([
            'status' => 'sucesso',
            'limit' => self::MAX_SPACES_PER_USER,
            'current_count' => $spaces->count(),
            'can_create' => $spaces->count() < self::MAX_SPACES_PER_USER,
            'spaces' => $spaces->map(fn(StudySpace $space) => $this->formatSpace($space)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'cover' => 'nullable|string|max:1000',
            'color' => ['nullable', 'string', 'max:30', 'regex:/^#?[A-Za-z0-9(),.%\s-]+$/'],
        ]);

        $spacesCount = StudySpace::query()
            ->where('user_id', $this->userId())
            ->count();

        if ($spacesCount >= self::MAX_SPACES_PER_USER) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Atingiste o limite de 5 Espaços. Elimina um Espaço existente para criar outro.',
                'limit' => self::MAX_SPACES_PER_USER,
                'current_count' => $spacesCount,
            ], 422);
        }

        $space = StudySpace::create([
            'user_id' => $this->userId(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'cover' => $validated['cover'] ?? null,
            'color' => $validated['color'] ?? '#009957',
        ]);

        return response()->json([
            'status' => 'sucesso',
            'limit' => self::MAX_SPACES_PER_USER,
            'current_count' => $spacesCount + 1,
            'can_create' => ($spacesCount + 1) < self::MAX_SPACES_PER_USER,
            'space' => $this->formatSpace($space->loadCount(['chats', 'materials', 'folders'])),
        ], 201);
    }

    public function show(StudySpace $space): JsonResponse
    {
        $this->authorizeSpace($space);

        $space->loadCount(['chats', 'materials', 'folders']);

        $latestChats = $space->chats()
            ->where('user_id', $this->userId())
            ->with('spaceFolder')
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn(Chat $chat) => $this->formatChat($chat));

        return response()->json([
            'status' => 'sucesso',
            'space' => $this->formatSpace($space),
            'latest_chats' => $latestChats,
        ]);
    }

    public function update(Request $request, StudySpace $space): JsonResponse
    {
        $this->authorizeSpace($space);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'cover' => 'nullable|string|max:1000',
            'color' => ['nullable', 'string', 'max:30', 'regex:/^#?[A-Za-z0-9(),.%\s-]+$/'],
        ]);

        $space->update($validated);

        return response()->json([
            'status' => 'sucesso',
            'space' => $this->formatSpace($space->fresh()->loadCount(['chats', 'materials', 'folders'])),
        ]);
    }

    public function destroy(StudySpace $space): JsonResponse
    {
        $this->authorizeSpace($space);

        $space->delete();

        return response()->json([
            'status' => 'sucesso',
        ]);
    }

    public function conversations(Request $request, StudySpace $space): JsonResponse
    {
        $this->authorizeSpace($space);

        $validated = $request->validate([
            'folder_id' => 'nullable|integer',
        ]);

        $query = $space->chats()
            ->where('user_id', $this->userId())
            ->with('spaceFolder')
            ->withCount('messages')
            ->latest('updated_at');

        if (array_key_exists('folder_id', $validated)) {
            $folderId = $validated['folder_id'];

            if ($folderId) {
                $this->resolveFolder($space, (int) $folderId);
                $query->where('space_folder_id', $folderId);
            } else {
                $query->whereNull('space_folder_id');
            }
        }

        $chats = $query->get()->map(fn(Chat $chat) => $this->formatChat($chat));

        return response()->json([
            'status' => 'sucesso',
            'space' => $this->formatSpace($space->loadCount(['chats', 'materials', 'folders'])),
            'chats' => $chats,
        ]);
    }

    public function createConversation(Request $request, StudySpace $space): JsonResponse
    {
        $this->authorizeSpace($space);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'folder_id' => 'nullable|integer|exists:space_folders,id',
        ]);

        $folderId = null;

        if (!empty($validated['folder_id'])) {
            $folderId = $this->resolveFolder($space, (int) $validated['folder_id'])->id;
        }

        $chat = Chat::create([
            'user_id' => $this->userId(),
            'study_space_id' => $space->id,
            'space_folder_id' => $folderId,
            'subject_id' => null,
            'context_type' => 'space',
            'is_temporary' => false,
            'title' => $validated['title'] ?? 'Nova conversa em ' . $space->name,
        ]);

        $space->touch();

        return response()->json([
            'status' => 'sucesso',
            'chat' => $this->formatChat($chat->load('spaceFolder')),
        ], 201);
    }

    public function moveConversation(Request $request, StudySpace $space, Chat $chat): JsonResponse
    {
        $this->authorizeSpace($space);

        abort_unless(
            (int) $chat->user_id === $this->userId() && (int) $chat->study_space_id === (int) $space->id,
            403
        );

        $validated = $request->validate([
            'folder_id' => 'nullable|integer|exists:space_folders,id',
        ]);

        $folderId = null;

        if (!empty($validated['folder_id'])) {
            $folderId = $this->resolveFolder($space, (int) $validated['folder_id'])->id;
        }

        $chat->update([
            'space_folder_id' => $folderId,
        ]);

        $space->touch();

        return response()->json([
            'status' => 'sucesso',
            'chat' => $this->formatChat($chat->fresh()->load('spaceFolder')->loadCount('messages')),
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

    private function formatSpace(StudySpace $space): array
    {
        return [
            'id' => $space->id,
            'name' => $space->name,
            'description' => $space->description,
            'cover' => $space->cover,
            'color' => $space->color,
            'chats_count' => $space->chats_count ?? null,
            'materials_count' => $space->materials_count ?? null,
            'folders_count' => $space->folders_count ?? null,
            'created_at' => $space->created_at?->toISOString(),
            'updated_at' => $space->updated_at?->toISOString(),
        ];
    }

    private function formatChat(Chat $chat): array
    {
        return [
            'chat_id' => $chat->id,
            'id' => $chat->id,
            'title' => $chat->title,
            'context_type' => $chat->context_type ?? 'space',
            'space_id' => $chat->study_space_id,
            'folder_id' => $chat->space_folder_id,
            'folder_name' => $chat->spaceFolder?->name,
            'messages_count' => $chat->messages_count ?? null,
            'created_at' => $chat->created_at?->toISOString(),
            'updated_at' => $chat->updated_at?->toISOString(),
        ];
    }
}
