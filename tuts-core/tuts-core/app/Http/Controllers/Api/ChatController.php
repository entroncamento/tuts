<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\PersonalMaterial;
use App\Models\SpaceFolder;
use App\Models\SpaceMaterial;
use App\Models\StudySpace;
use App\Models\Subject;
use App\Models\SubjectMaterial;
use App\Models\SubjectSection;
use App\Services\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    private const MAX_PERSONAL_FILES_PER_MESSAGE = 3;
    private const MAX_PERSONAL_ATTACHMENT_BYTES = 5 * 1024 * 1024;

    protected $ragService;

    public function __construct(RagService $ragService)
    {
        $this->ragService = $ragService;
    }

    private function buildHistorico(int $chat_id): string
    {
        return Message::where('chat_id', $chat_id)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => [
                'role' => $m->role === 'ai' ? 'assistant' : 'user',
                'content' => $m->content,
            ])
            ->toJson();
    }

    private function requireAuthenticatedUserId(): int
    {
        $userId = Auth::id();

        if (!$userId) {
            abort(401, 'Utilizador não autenticado.');
        }

        return (int) $userId;
    }

    private function userCanAccessSubject(Subject $subject): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        Log::info('[TUTS][Chat] using subject_user UC authorization', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
        ]);

        $role = (string) $user->role;
        $isTeacher = in_array($role, ['professor', 'teacher'], true);
        $membershipRole = $isTeacher ? 'teacher' : 'student';

        $hasMembership = DB::table('subject_user')
            ->where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('role', $membershipRole)
            ->where('status', 'active')
            ->exists();

        if ($hasMembership) {
            return true;
        }

        if ($isTeacher && (int) $subject->created_by === (int) $user->id) {
            return true;
        }

        $legacyAllowed = !$isTeacher && $user->course_id && $subject
            ->courses()
            ->where('courses.id', $user->course_id)
            ->exists();

        if ($legacyAllowed) {
            Log::warning('[TUTS][Chat] using legacy UC fallback', [
                'user_id' => $user->id,
                'subject_id' => $subject->id,
            ]);

            return true;
        }

        Log::warning('[TUTS][Chat] forbidden UC chat access', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'role' => $role,
        ]);

        return false;
    }

    private function resolveSubjectForStream(Request $request): ?Subject
    {
        if ($request->filled('subject_id')) {
            return Subject::findOrFail((int) $request->input('subject_id'));
        }

        if ($request->filled('uc')) {
            Log::warning('[TUTS][Chat] using legacy UC fallback', [
                'user_id' => Auth::id(),
                'uc' => $request->input('uc'),
            ]);

            return Subject::where('name', $request->input('uc'))->firstOrFail();
        }

        return null;
    }

    private function resolveSection(?Subject $subject, ?int $sectionId): ?SubjectSection
    {
        if (!$sectionId) {
            return null;
        }

        if (!$subject) {
            throw ValidationException::withMessages([
                'section_id' => 'A secção só pode ser usada com uma UC válida.',
            ]);
        }

        return SubjectSection::query()
            ->where('id', $sectionId)
            ->where('subject_id', $subject->id)
            ->firstOrFail();
    }

    private function parseAttachedMaterialRefs(Request $request): array
    {
        if (!$request->filled('attachedMaterialRefs')) {
            return [];
        }

        $decoded = json_decode((string) $request->input('attachedMaterialRefs'), true);

        if (!is_array($decoded) || array_is_list($decoded) === false) {
            throw ValidationException::withMessages([
                'attachedMaterialRefs' => 'As referências de materiais têm de ser um array JSON.',
            ]);
        }

        if (count($decoded) > 20) {
            throw ValidationException::withMessages([
                'attachedMaterialRefs' => 'Não podes anexar mais de 20 materiais por mensagem.',
            ]);
        }

        return $decoded;
    }

    private function resolveAttachedMaterialRefs(array $refs, ?Subject $subject, ?SubjectSection $section, ?StudySpace $space, int $userId): array
    {
        $resolved = [];

        foreach ($refs as $index => $ref) {
            if (!is_array($ref)) {
                throw ValidationException::withMessages([
                    "attachedMaterialRefs.{$index}" => 'Cada referência de material tem de ser um objeto.',
                ]);
            }

            $source = (string) ($ref['source'] ?? '');
            $materialId = (int) ($ref['material_id'] ?? $ref['id'] ?? 0);

            if (!in_array($source, ['personal', 'subject', 'space'], true) || $materialId < 1) {
                throw ValidationException::withMessages([
                    "attachedMaterialRefs.{$index}" => 'Referência de material inválida.',
                ]);
            }

            $refSubjectId = isset($ref['subject_id']) ? (int) $ref['subject_id'] : null;
            $refSectionId = isset($ref['section_id']) ? (int) $ref['section_id'] : null;

            if ($refSectionId) {
                $refSection = SubjectSection::findOrFail($refSectionId);
                if ($refSubjectId && (int) $refSection->subject_id !== $refSubjectId) {
                    throw ValidationException::withMessages([
                        "attachedMaterialRefs.{$index}.section_id" => 'A secção indicada não pertence à UC do material.',
                    ]);
                }
            }

            if ($source === 'personal') {
                $material = PersonalMaterial::query()
                    ->where('id', $materialId)
                    ->where('owner_id', $userId)
                    ->firstOrFail();

                $resolved[] = [
                    'source' => 'personal',
                    'material_id' => $material->id,
                    'subject_id' => null,
                    'section_id' => null,
                    'display_name' => $material->original_name,
                    'mime_type' => $material->mime_type,
                    'size_bytes' => $material->size_bytes,
                    'meta_data' => [
                        'extension' => $material->extension,
                    ],
                ];

                continue;
            }

            if ($source === 'subject') {
                $material = SubjectMaterial::findOrFail($materialId);
                $materialSubject = Subject::findOrFail((int) $material->subject_id);

                abort_unless($this->userCanAccessSubject($materialSubject), 403, 'Acesso negado ao material da UC.');

                if ($subject && (int) $material->subject_id !== (int) $subject->id) {
                    throw ValidationException::withMessages([
                        "attachedMaterialRefs.{$index}.subject_id" => 'O material não pertence à UC do chat.',
                    ]);
                }

                if ($refSubjectId && (int) $material->subject_id !== $refSubjectId) {
                    throw ValidationException::withMessages([
                        "attachedMaterialRefs.{$index}.subject_id" => 'O subject_id indicado não coincide com o material.',
                    ]);
                }

                if ($refSectionId && (int) $material->section_id !== $refSectionId) {
                    throw ValidationException::withMessages([
                        "attachedMaterialRefs.{$index}.section_id" => 'O section_id indicado não coincide com o material.',
                    ]);
                }

                if ($section && $material->section_id && (int) $material->section_id !== (int) $section->id) {
                    throw ValidationException::withMessages([
                        "attachedMaterialRefs.{$index}.section_id" => 'O material pertence a outra secção.',
                    ]);
                }

                $resolved[] = [
                    'source' => 'subject',
                    'material_id' => $material->id,
                    'subject_id' => $material->subject_id,
                    'section_id' => $material->section_id,
                    'display_name' => $material->name,
                    'mime_type' => $material->mime_type,
                    'size_bytes' => $material->size_bytes,
                    'meta_data' => [
                        'type' => $material->type,
                        'source' => $material->source,
                    ],
                ];

                continue;
            }

            $material = SpaceMaterial::query()
                ->where('id', $materialId)
                ->where('user_id', $userId)
                ->firstOrFail();

            if (!$space || (int) $material->study_space_id !== (int) $space->id) {
                throw ValidationException::withMessages([
                    "attachedMaterialRefs.{$index}.id" => 'O material de Espaço não pertence ao Espaço do chat.',
                ]);
            }

            $resolved[] = [
                'source' => 'space',
                'material_id' => $material->id,
                'subject_id' => null,
                'section_id' => null,
                'display_name' => $material->original_name,
                'mime_type' => $material->mime_type,
                'size_bytes' => $material->size_bytes,
                'meta_data' => [
                    'study_space_id' => $material->study_space_id,
                    'folder_id' => $material->space_folder_id,
                    'extension' => $material->extension,
                ],
            ];
        }

        if (collect($resolved)->where('source', 'personal')->count() > self::MAX_PERSONAL_FILES_PER_MESSAGE) {
            throw ValidationException::withMessages([
                'attachedMaterialRefs' => 'Não podes anexar mais de 3 materiais pessoais por mensagem.',
            ]);
        }

        return $resolved;
    }

    private function preparePersonalFilesForRag(array $attachedMaterialRefs, int $userId, int $chatId, int $messageId): array
    {
        $personalRefs = collect($attachedMaterialRefs)
            ->where('source', 'personal')
            ->values();

        if ($personalRefs->isEmpty()) {
            return [[], [], []];
        }

        Log::info('[TUTS][Chat][Personal Attachments] resolving personal refs', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'count' => $personalRefs->count(),
        ]);

        $files = [];
        $metadata = [];
        $tempPaths = [];
        $totalBytes = 0;

        foreach ($personalRefs as $ref) {
            $materialId = (int) ($ref['material_id'] ?? 0);
            $baseMeta = [
                'source' => 'personal',
                'material_id' => $materialId,
                'owner_user_id' => $userId,
                'filename' => (string) ($ref['display_name'] ?? 'material'),
                'title' => (string) ($ref['display_name'] ?? 'material'),
                'mime_type' => (string) ($ref['mime_type'] ?? 'application/octet-stream'),
                'size_bytes' => (int) ($ref['size_bytes'] ?? 0),
                'temporary' => true,
            ];

            $material = PersonalMaterial::query()
                ->where('id', $materialId)
                ->where('owner_id', $userId)
                ->first();

            if (!$material) {
                $metadata[] = $baseMeta + ['status' => 'skipped', 'skip_reason' => 'not_authorized'];
                Log::warning('[TUTS][Chat][Personal Attachments] personal file skipped', [
                    'user_id' => $userId,
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'material_id' => $materialId,
                    'skip_reason' => 'not_authorized',
                ]);
                continue;
            }

            $sizeBytes = (int) $material->size_bytes;
            $safeMeta = [
                'source' => 'personal',
                'material_id' => (int) $material->id,
                'owner_user_id' => $userId,
                'filename' => (string) $material->original_name,
                'title' => (string) $material->original_name,
                'mime_type' => (string) ($material->mime_type ?: 'application/octet-stream'),
                'size_bytes' => $sizeBytes,
                'temporary' => true,
            ];

            Log::info('[TUTS][Chat][Personal Attachments] personal file authorized', [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'material_id' => $material->id,
                'mime_type' => $material->mime_type,
                'size_bytes' => $sizeBytes,
            ]);

            if ($sizeBytes <= 0 || $totalBytes + $sizeBytes > self::MAX_PERSONAL_ATTACHMENT_BYTES) {
                $metadata[] = $safeMeta + ['status' => 'skipped', 'skip_reason' => 'size_limit_exceeded'];
                Log::warning('[TUTS][Chat][Personal Attachments] personal file skipped', [
                    'user_id' => $userId,
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'material_id' => $material->id,
                    'mime_type' => $material->mime_type,
                    'size_bytes' => $sizeBytes,
                    'skip_reason' => 'size_limit_exceeded',
                ]);
                continue;
            }

            $stream = false;

            try {
                $stream = Storage::disk($material->storage_disk)->readStream($material->storage_key);
            } catch (\Throwable) {
                $stream = false;
            }

            if (!is_resource($stream)) {
                $metadata[] = $safeMeta + ['status' => 'skipped', 'skip_reason' => 'file_not_readable'];
                Log::warning('[TUTS][Chat][Personal Attachments] personal file skipped', [
                    'user_id' => $userId,
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'material_id' => $material->id,
                    'mime_type' => $material->mime_type,
                    'size_bytes' => $sizeBytes,
                    'skip_reason' => 'file_not_readable',
                ]);
                continue;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'tuts-personal-');
            $out = $tempPath ? fopen($tempPath, 'wb') : false;

            if (!is_resource($out)) {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $metadata[] = $safeMeta + ['status' => 'skipped', 'skip_reason' => 'temp_file_failed'];
                Log::warning('[TUTS][Chat][Personal Attachments] personal file skipped', [
                    'user_id' => $userId,
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'material_id' => $material->id,
                    'mime_type' => $material->mime_type,
                    'size_bytes' => $sizeBytes,
                    'skip_reason' => 'temp_file_failed',
                ]);
                continue;
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            $uploadIndex = count($files);
            $safeMeta['status'] = 'attached';
            $safeMeta['upload_index'] = $uploadIndex;
            $metadata[] = $safeMeta;
            $files[] = [
                'field' => "personal_files[{$uploadIndex}]",
                'path' => $tempPath,
                'name' => basename((string) $material->original_name),
                'mime_type' => $safeMeta['mime_type'],
            ];
            $tempPaths[] = $tempPath;
            $totalBytes += $sizeBytes;

            Log::info('[TUTS][Chat][Personal Attachments] personal file attached to RAG request', [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'material_id' => $material->id,
                'mime_type' => $material->mime_type,
                'size_bytes' => $sizeBytes,
            ]);
        }

        Log::info('[TUTS][Chat][Personal Attachments] personal temporary retrieval requested', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'requested_count' => $personalRefs->count(),
            'attached_count' => count($files),
            'total_bytes' => $totalBytes,
        ]);

        return [$files, $metadata, $tempPaths];
    }

    private function formatMaterialRef($ref): array
    {
        return [
            'source' => $ref->source,
            'id' => $ref->material_id,
            'material_id' => $ref->material_id,
            'name' => $ref->display_name,
            'display_name' => $ref->display_name,
            'mime_type' => $ref->mime_type,
            'size_bytes' => $ref->size_bytes,
            'subject_id' => $ref->subject_id,
            'section_id' => $ref->section_id,
            'meta_data' => $ref->meta_data,
        ];
    }

    private function formatMessage(Message $message): array
    {
        $payload = $message->toArray();
        $payload['materials'] = $message->relationLoaded('materialRefs')
            ? $message->materialRefs->map(fn ($ref) => $this->formatMaterialRef($ref))->values()
            : [];

        return $payload;
    }

    private function extractRagErrorMessage(string $body): ?string
    {
        $decoded = json_decode(trim($body), true);

        if (!is_array($decoded)) {
            return null;
        }

        $message = $this->stringFromRagErrorValue($decoded['detail'] ?? null)
            ?? $this->stringFromRagErrorValue($decoded['message'] ?? null);

        if (!$message) {
            return null;
        }

        $message = preg_replace('/\s+/u', ' ', trim($message));

        if ($message === '') {
            return null;
        }

        return mb_substr($message, 0, 500);
    }

    private function stringFromRagErrorValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                return $item;
            }

            if (is_array($item)) {
                $nested = $this->stringFromRagErrorValue($item['msg'] ?? $item['message'] ?? $item['detail'] ?? null);

                if ($nested) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function looksLikeTechnicalError(string $text): bool
    {
        $t = mb_strtolower(trim($text));

        return str_contains($t, '❌ o serviço de ia está temporariamente com demasiados pedidos')
            || str_contains($t, '❌ o serviço de ia falhou ao processar o pedido')
            || str_contains($t, '❌ falha na comunicação com o serviço de ia')
            || str_contains($t, '❌ erro: a ia não enviou resposta')
            || str_contains($t, '❌ falha de autenticação interna com o serviço de ia')
            || str_contains($t, '❌ falha ao comunicar com o serviço de ia')
            || str_contains($t, 'too many requests')
            || str_contains($t, 'rate limit')
            || str_contains($t, 'rate_limit_exceeded')
            || str_contains($t, 'temporariamente com demasiados pedidos');
    }

    public function criarChat(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => 'nullable|exists:subjects,id',
            'section_id' => 'nullable|integer|exists:subject_sections,id',
            'space_id' => 'nullable|exists:study_spaces,id',
            'folder_id' => 'nullable|exists:space_folders,id',
            'context_type' => 'nullable|string|in:uc,space,temporary',
            'title' => 'nullable|string|max:255',
        ]);

        $userId = $this->requireAuthenticatedUserId();
        $contextType = $validated['context_type'] ?? 'uc';
        $subjectId = $validated['subject_id'] ?? null;
        $sectionId = $validated['section_id'] ?? null;
        $spaceId = $validated['space_id'] ?? null;
        $folderId = $validated['folder_id'] ?? null;
        $subject = null;
        $section = null;

        if ($contextType === 'uc' && !$subjectId) {
            abort(422, 'Tens de escolher uma UC para criar uma conversa de UC.');
        }

        if ($contextType === 'space' && !$spaceId) {
            abort(422, 'Tens de escolher um Espaço para criar uma conversa de Espaço.');
        }

        if ($subjectId) {
            $subject = Subject::findOrFail($subjectId);
            abort_unless($this->userCanAccessSubject($subject), 403, 'Acesso negado à UC escolhida.');
        }

        if ($contextType === 'uc') {
            $section = $this->resolveSection($subject, $sectionId ? (int) $sectionId : null);
        } elseif ($sectionId) {
            throw ValidationException::withMessages([
                'section_id' => 'A secção só pode ser usada em conversas de UC.',
            ]);
        }

        if ($spaceId) {
            StudySpace::query()
                ->where('id', $spaceId)
                ->where('user_id', $userId)
                ->firstOrFail();
        }

        if ($folderId) {
            SpaceFolder::query()
                ->where('id', $folderId)
                ->where('study_space_id', $spaceId)
                ->where('user_id', $userId)
                ->firstOrFail();
        }

        $chat = Chat::create([
            'user_id' => $userId,
            'subject_id' => $contextType === 'uc' ? $subjectId : null,
            'section_id' => $contextType === 'uc' ? $section?->id : null,
            'study_space_id' => $contextType === 'space' ? $spaceId : null,
            'space_folder_id' => $contextType === 'space' ? $folderId : null,
            'context_type' => $contextType,
            'is_temporary' => $contextType === 'temporary',
            'title' => $validated['title'] ?? 'Nova Conversa com o TUT\'S',
        ]);

        return response()->json([
            'status' => 'sucesso',
            'chat_id' => $chat->id,
            'chat' => $this->formatChat($chat),
        ]);
    }

    public function enviarPerguntaStream(Request $request)
    {
        $request->validate([
            'chat_id' => 'nullable|integer|exists:chats,id',
            'texto' => 'required|string|max:4000',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'uc' => 'nullable|string|max:255',
            'context_type' => 'nullable|string|in:uc,space,temporary',
            'section_id' => 'nullable|integer|exists:subject_sections,id',
            'space_id' => 'nullable|integer|exists:study_spaces,id',
            'folder_id' => 'nullable|integer|exists:space_folders,id',
            'preferencia' => 'nullable|string|in:default,visual,plano,quiz,feynman',
            'imagem' => 'nullable|image|max:4096',
            'attachedMaterialRefs' => 'nullable|string',
            'adaptability_preferences' => 'nullable',
        ]);

        $userId = $this->requireAuthenticatedUserId();
        $contextType = $request->input('context_type', 'uc');
        $subject = null;
        $section = null;
        $space = null;
        $folder = null;

        if ($contextType === 'uc') {
            $subject = $this->resolveSubjectForStream($request);

            if (!$subject) {
                abort(422, 'Tens de escolher uma UC antes de perguntar ao TUT\'S.');
            }

            abort_unless($this->userCanAccessSubject($subject), 403, 'Acesso negado. Não está inscrito no curso desta Unidade Curricular.');
            $section = $this->resolveSection($subject, $request->filled('section_id') ? (int) $request->input('section_id') : null);
        }

        if ($contextType === 'space') {
            if (!$request->filled('space_id')) {
                abort(422, 'Tens de escolher um Espaço antes de perguntar ao TUT\'S.');
            }

            $space = StudySpace::query()
                ->where('id', (int) $request->space_id)
                ->where('user_id', $userId)
                ->firstOrFail();

            if ($request->filled('folder_id')) {
                $folder = SpaceFolder::query()
                    ->where('id', (int) $request->folder_id)
                    ->where('study_space_id', $space->id)
                    ->where('user_id', $userId)
                    ->firstOrFail();
            }
        }

        if ($contextType !== 'uc' && $request->filled('section_id')) {
            throw ValidationException::withMessages([
                'section_id' => 'A secção só pode ser usada em conversas de UC.',
            ]);
        }

        if ($request->filled('chat_id')) {
            $chat = Chat::where('id', (int) $request->chat_id)
                ->where('user_id', $userId)
                ->firstOrFail();

            if ($contextType === 'uc' && $subject && (int) $chat->subject_id !== (int) $subject->id) {
                abort(422, 'O chat indicado não pertence à Unidade Curricular atual.');
            }

            if ($contextType === 'uc') {
                if ($request->filled('section_id') && (int) $chat->section_id !== (int) $request->input('section_id')) {
                    abort(422, 'O chat indicado não pertence à secção indicada.');
                }

                $section = $chat->section_id
                    ? $this->resolveSection($subject, (int) $chat->section_id)
                    : null;
            }

            if ($contextType === 'space' && $space && (int) $chat->study_space_id !== (int) $space->id) {
                abort(422, 'O chat indicado não pertence ao Espaço atual.');
            }

            if ($contextType === 'space' && $folder && !$chat->space_folder_id) {
                $chat->update(['space_folder_id' => $folder->id]);
            }
        } else {
            $tituloBase = trim((string) $request->texto);
            $titulo = $tituloBase !== '' ? mb_substr($tituloBase, 0, 80) : 'Chat TUT\'S';

            $chat = Chat::create([
                'user_id' => $userId,
                'subject_id' => $subject?->id,
                'section_id' => $section?->id,
                'study_space_id' => $space?->id,
                'space_folder_id' => $folder?->id,
                'context_type' => $contextType,
                'is_temporary' => $contextType === 'temporary',
                'title' => $titulo,
            ]);
        }

        $attachedMaterialRefs = $this->resolveAttachedMaterialRefs(
            $this->parseAttachedMaterialRefs($request),
            $subject,
            $section,
            $space,
            $userId
        );

        $chatId = (int) $chat->id;
        $threadId = (string) $chatId;
        $historico = $this->buildHistorico($chatId);

        if (!$this->ragService->isAvailable()) {
            abort(503, 'O serviço de IA está temporariamente indisponível (Circuit Breaker).');
        }

        $imagemPath = null;
        $imagemNome = null;
        $imagemMime = null;

        if ($request->hasFile('imagem')) {
            $file = $request->file('imagem');
            $imagemPath = $file->getPathname();
            $imagemNome = $file->getClientOriginalName();
            $imagemMime = $file->getMimeType() ?? 'image/jpeg';
        }

        $urlPython = $this->ragService->getUrl();
        $internalToken = $this->ragService->getToken();

        if ($internalToken === '') {
            abort(500, 'Configuração crítica: Token interno do serviço IA não configurado.');
        }

        $parsedUrl = parse_url($urlPython);

        $allowedHosts = array_filter([
            '127.0.0.1',
            'localhost',
            'tuts-rag-service',
            'host.docker.internal',
            env('PYTHON_HOST'),
        ]);

        if (!in_array($parsedUrl['host'] ?? '', $allowedHosts, true)) {
            abort(500, 'Configuração insegura: O Host do serviço de IA não é de confiança.');
        }

        $texto = $request->texto;
        $preferencia = $request->input('preferencia', 'default');
        $adaptabilityPreferences = $request->input('adaptability_preferences');

        if (is_array($adaptabilityPreferences)) {
            $adaptabilityPreferences = json_encode($adaptabilityPreferences, JSON_UNESCAPED_UNICODE);
        }

        if (!is_string($adaptabilityPreferences) || trim($adaptabilityPreferences) === '') {
            $adaptabilityPreferences = null;
        }
        $requestId = Context::get('request_id');
        $uc = match ($contextType) {
            'uc' => $subject?->name ?? (string) $request->uc,
            'space' => 'Espaço: ' . ($space?->name ?? 'Sem nome'),
            default => 'Conversa temporária',
        };

        $messageMetadata = [
            'context' => [
                'context_type' => $contextType,
                'subject_id' => $subject?->id,
                'subject_name' => $subject?->name,
                'section_id' => $section?->id,
                'section_name' => $section?->name,
                'space_id' => $space?->id,
                'space_name' => $space?->name,
                'folder_id' => $folder?->id,
                'folder_name' => $folder?->name,
            ],
            'attached_material_refs' => $attachedMaterialRefs,
            'rag' => [
                'request_id' => $requestId,
                'sources' => [],
                'citations' => [],
            ],
        ];

        $userMessage = DB::transaction(function () use ($chat, $chatId, $texto, $messageMetadata, $attachedMaterialRefs) {
            $chat->touch();

            $message = Message::create([
                'chat_id' => $chatId,
                'role' => 'user',
                'content' => $texto,
                'meta_data' => $messageMetadata,
            ]);

            foreach ($attachedMaterialRefs as $ref) {
                $message->materialRefs()->create($ref);
            }

            return $message;
        });

        $userMessageId = (int) $userMessage->id;
        $personalMaterialIds = collect($attachedMaterialRefs)->where('source', 'personal')->pluck('material_id')->values()->all();
        $subjectMaterialIds = collect($attachedMaterialRefs)->where('source', 'subject')->pluck('material_id')->values()->all();
        $spaceMaterialIds = collect($attachedMaterialRefs)->where('source', 'space')->pluck('material_id')->values()->all();

        return response()->stream(function () use (
            $urlPython,
            $internalToken,
            $texto,
            $uc,
            $preferencia,
            $threadId,
            $historico,
            $chat,
            $chatId,
            $imagemPath,
            $imagemNome,
            $imagemMime,
            $userMessageId,
            $contextType,
            $subject,
            $section,
            $attachedMaterialRefs,
            $personalMaterialIds,
            $subjectMaterialIds,
            $spaceMaterialIds,
            $requestId,
            $userId,
            $adaptabilityPreferences
        ) {
            // Prevenir interrupção prematura do script pelo PHP
            ignore_user_abort(true);

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Enviar ID do chat imediatamente
            echo 'data: ' . json_encode([
                'chat_id' => $chatId,
                'message_id' => $userMessageId,
            ], JSON_UNESCAPED_UNICODE) . "\n\n";

            // Heartbeat inicial
            echo ": heartbeat\n\n";

            @ob_flush();
            flush();

            $postFields = [
                'texto' => $texto,
                'uc' => $uc,
                'preferencia' => $preferencia,
                'thread_id' => $threadId,
                'historico' => $historico,
                'message_id' => $userMessageId,
                'subject_id' => $subject?->id,
                'section_id' => $section?->id,
                'attached_material_refs' => json_encode($attachedMaterialRefs, JSON_UNESCAPED_UNICODE),
                'personal_material_ids' => json_encode($personalMaterialIds, JSON_UNESCAPED_UNICODE),
                'subject_material_ids' => json_encode($subjectMaterialIds, JSON_UNESCAPED_UNICODE),
                'space_material_ids' => json_encode($spaceMaterialIds, JSON_UNESCAPED_UNICODE),
                'context_type' => $contextType,
                'chat_id' => $chatId,
            ];

            if ($adaptabilityPreferences !== null) {
                $postFields['adaptability_preferences'] = $adaptabilityPreferences;
            }

            [$personalFiles, $personalFileRefs, $personalTempPaths] = $this->preparePersonalFilesForRag(
                $attachedMaterialRefs,
                $userId,
                $chatId,
                $userMessageId
            );

            if ($personalFileRefs) {
                $postFields['personal_file_refs'] = json_encode($personalFileRefs, JSON_UNESCAPED_UNICODE);
            }

            foreach ($personalFiles as $file) {
                $postFields[$file['field']] = new \CURLFile($file['path'], $file['mime_type'], $file['name']);
            }

            Log::info('[TUTS][Chat][RAG] sending structured context', [
                'chat_id' => $chatId,
                'message_id' => $userMessageId,
                'request_id' => $requestId,
                'context_type' => $contextType,
                'subject_id' => $subject?->id,
                'section_id' => $section?->id,
                'attached_refs_count' => count($attachedMaterialRefs),
                'personal_ids_count' => count($personalMaterialIds),
                'subject_ids_count' => count($subjectMaterialIds),
                'space_ids_count' => count($spaceMaterialIds),
                'personal_files_count' => count($personalFiles),
            ]);

            if ($imagemPath && file_exists($imagemPath)) {
                $postFields['imagem'] = new \CURLFile($imagemPath, $imagemMime, $imagemNome);
            }

            $buffer = '';
            $fullAiText = '';
            $rawRagBody = '';
            $lastHeartbeat = microtime(true);

            $ch = curl_init($urlPython);

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/event-stream',
                    'X-Internal-Token: ' . $internalToken,
                    'X-Request-ID: ' . $requestId,
                ],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120, // Aumentado para streams longos

                CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$buffer, &$fullAiText, &$rawRagBody, &$lastHeartbeat) {
                    if (connection_aborted()) {
                        return 0; // Para o cURL se o cliente desconectar
                    }

                    if (strlen($rawRagBody) < 10000) {
                        $rawRagBody .= substr($chunk, 0, 10000 - strlen($rawRagBody));
                    }

                    // Enviar heartbeat a cada 15 segundos se não houver chunks
                    if (microtime(true) - $lastHeartbeat > 15) {
                        echo ": heartbeat\n\n";
                        @ob_flush();
                        flush();
                        $lastHeartbeat = microtime(true);
                    }

                    $buffer .= $chunk;

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos + 1);
                        $buffer = substr($buffer, $pos + 1);

                        $trimmed = rtrim($line, "\r\n");

                        if ($trimmed === '' || !str_starts_with($trimmed, 'data: ')) {
                            continue;
                        }

                        $payload = substr($trimmed, 6);

                        if ($payload === '[DONE]') {
                            continue;
                        }

                        $decoded = json_decode($payload, true);

                        if (is_array($decoded) && isset($decoded['chunk'])) {
                            if (strlen($fullAiText) < 20000) {
                                $fullAiText .= $decoded['chunk'];
                            }
                        }

                        echo "data: {$payload}\n\n";
                        @ob_flush();
                        flush();
                        $lastHeartbeat = microtime(true);
                    }

                    return strlen($chunk);
                },
            ]);

            $ok = curl_exec($ch);
            $responseCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);
            $ragErrorMessage = $responseCode >= 400 ? $this->extractRagErrorMessage($rawRagBody) : null;

            if ($ok === false || ($responseCode >= 400 && !$ragErrorMessage)) {
                $this->ragService->reportFailure();
            } else {
                $this->ragService->reportSuccess();
            }

            if ($curlError) {
                Log::error('[TUTS][Chat][RAG] failed to communicate with RAG', [
                    'chat_id' => $chatId,
                    'message_id' => $userMessageId,
                    'request_id' => $requestId,
                    'context_type' => $contextType,
                    'subject_id' => $subject?->id,
                    'section_id' => $section?->id,
                    'curl_error' => mb_substr($curlError, 0, 250),
                    'http_status' => $responseCode ?: null,
                ]);
            } elseif ($responseCode >= 400) {
                Log::warning('[TUTS][Chat][RAG] non-success response from RAG', [
                    'chat_id' => $chatId,
                    'message_id' => $userMessageId,
                    'request_id' => $requestId,
                    'context_type' => $contextType,
                    'subject_id' => $subject?->id,
                    'section_id' => $section?->id,
                    'http_status' => $responseCode,
                    'error_detail' => $ragErrorMessage,
                ]);
            }

            curl_close($ch);

            foreach ($personalTempPaths ?? [] as $tempPath) {
                if (is_string($tempPath) && $tempPath !== '' && file_exists($tempPath)) {
                    @unlink($tempPath);
                }
            }

            $falhouServicoIa = $ok === false || $responseCode >= 400;

            if (
                !$falhouServicoIa &&
                !empty(trim($fullAiText)) &&
                !$this->looksLikeTechnicalError($fullAiText)
            ) {
                DB::transaction(function () use ($chat, $chatId, $fullAiText, $contextType, $subject, $section, $requestId) {
                    Message::create([
                        'chat_id' => $chatId,
                        'role' => 'ai',
                        'content' => $fullAiText,
                        'meta_data' => [
                            'context' => [
                                'context_type' => $contextType,
                                'subject_id' => $subject?->id,
                                'subject_name' => $subject?->name,
                                'section_id' => $section?->id,
                                'section_name' => $section?->name,
                            ],
                            'rag' => [
                                'request_id' => $requestId,
                                'sources' => [],
                                'citations' => [],
                            ],
                        ],
                    ]);

                    $chat->touch();
                });
            }

            if (!connection_aborted()) {
                if ($falhouServicoIa) {
                    $msg = $ragErrorMessage
                        ? "\n\n⚠️ " . $ragErrorMessage
                        : ($responseCode === 401 || $responseCode === 403
                        ? "\n\n❌ Falha de autenticação interna com o serviço de IA."
                        : "\n\n❌ Falha ao comunicar com o serviço de IA.");

                    if ($curlError) {
                        $msg = "\n\n❌ Falha ao comunicar com o serviço de IA.";
                    }

                    echo 'data: ' . json_encode([
                        'chunk' => $msg,
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                }

                echo "data: [DONE]\n\n";
                @ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function obterHistorico(Request $request, $chat_id)
    {
        $userId = $this->requireAuthenticatedUserId();

        $chat = Chat::with(['subject', 'studySpace', 'spaceFolder'])
            ->where('id', $chat_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $mensagens = Message::with('materialRefs')
            ->where('chat_id', $chat->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Message $message) => $this->formatMessage($message))
            ->values();

        return response()->json([
            'status' => 'sucesso',
            'chat_id' => $chat->id,
            'titulo' => $chat->title,
            'context_type' => $chat->context_type ?? 'uc',
            'subject_id' => $chat->subject_id,
            'section_id' => $chat->section_id,
            'subject_name' => $chat->subject?->name,
            'space_id' => $chat->study_space_id,
            'study_space_id' => $chat->study_space_id,
            'space_name' => $chat->studySpace?->name,
            'folder_id' => $chat->space_folder_id,
            'space_folder_id' => $chat->space_folder_id,
            'folder_name' => $chat->spaceFolder?->name,
            'mensagens' => $mensagens,
        ]);
    }

    public function listarChats(Request $request)
    {
        $userId = $this->requireAuthenticatedUserId();
        $userName = Auth::user()?->name ?? 'Utilizador';

        $chats = Chat::with(['subject', 'studySpace', 'spaceFolder'])
            ->with(['messages' => fn ($query) => $query->latest()->limit(1)])
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Chat $chat) => $this->formatChatListItem($chat))
            ->values();

        return response()->json([
            'status' => 'sucesso',
            'aluno' => $userName,
            'chats' => $chats,
        ]);
    }

    public function listarChatsPorUC(Request $request)
    {
        return $this->listarChats($request);
    }

    private function formatChatListItem(Chat $chat): array
    {
        $lastMessage = $chat->messages->first();
        $preview = $lastMessage ? mb_substr(trim((string) $lastMessage->content), 0, 160) : null;

        return [
            'chat_id' => $chat->id,
            'title' => $chat->title,
            'context_type' => $chat->context_type ?? 'uc',
            'subject_id' => $chat->subject_id,
            'section_id' => $chat->section_id,
            'subject_name' => $chat->subject?->name,
            'study_space_id' => $chat->study_space_id,
            'space_id' => $chat->study_space_id,
            'space_name' => $chat->studySpace?->name,
            'space_folder_id' => $chat->space_folder_id,
            'folder_id' => $chat->space_folder_id,
            'folder_name' => $chat->spaceFolder?->name,
            'is_temporary' => (bool) $chat->is_temporary,
            'last_message' => $preview,
            'last_message_role' => $lastMessage?->role,
            'created_at' => $chat->created_at?->toISOString(),
            'updated_at' => $chat->updated_at?->toISOString(),
        ];
    }

    private function formatChat(Chat $chat): array
    {
        $chat->loadMissing(['subject', 'studySpace', 'spaceFolder']);

        return [
            'chat_id' => $chat->id,
            'title' => $chat->title,
            'context_type' => $chat->context_type ?? 'uc',
            'subject_id' => $chat->subject_id,
            'section_id' => $chat->section_id,
            'subject_name' => $chat->subject?->name,
            'study_space_id' => $chat->study_space_id,
            'space_id' => $chat->study_space_id,
            'space_name' => $chat->studySpace?->name,
            'space_folder_id' => $chat->space_folder_id,
            'folder_id' => $chat->space_folder_id,
            'folder_name' => $chat->spaceFolder?->name,
            'is_temporary' => (bool) $chat->is_temporary,
            'created_at' => $chat->created_at?->toISOString(),
            'updated_at' => $chat->updated_at?->toISOString(),
        ];
    }
}
