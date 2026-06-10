<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\SpaceFolder;
use App\Models\StudySpace;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
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

        if ($user->role === 'professor' || $user->role === 'teacher') {
            return true;
        }

        return $subject
            ->courses()
            ->where('courses.id', $user->course_id)
            ->exists();
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
            'space_id' => 'nullable|exists:study_spaces,id',
            'folder_id' => 'nullable|exists:space_folders,id',
            'context_type' => 'nullable|string|in:uc,space,temporary',
            'title' => 'nullable|string|max:255',
        ]);

        $userId = $this->requireAuthenticatedUserId();
        $contextType = $validated['context_type'] ?? 'uc';
        $subjectId = $validated['subject_id'] ?? null;
        $spaceId = $validated['space_id'] ?? null;
        $folderId = $validated['folder_id'] ?? null;

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
            'uc' => 'nullable|string|max:255',
            'context_type' => 'nullable|string|in:uc,space,temporary',
            'space_id' => 'nullable|integer|exists:study_spaces,id',
            'folder_id' => 'nullable|integer|exists:space_folders,id',
            'preferencia' => 'nullable|string|in:default,visual,plano,quiz,feynman',
            'imagem' => 'nullable|image|max:4096',
        ]);

        $userId = $this->requireAuthenticatedUserId();
        $contextType = $request->input('context_type', 'uc');
        $subject = null;
        $space = null;
        $folder = null;

        if ($contextType === 'uc') {
            if (!$request->filled('uc')) {
                abort(422, 'Tens de escolher uma UC antes de perguntar ao TUT\'S.');
            }

            $subject = Subject::where('name', $request->uc)->firstOrFail();
            abort_unless($this->userCanAccessSubject($subject), 403, 'Acesso negado. Não está inscrito no curso desta Unidade Curricular.');
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

        if ($request->filled('chat_id')) {
            $chat = Chat::where('id', (int) $request->chat_id)
                ->where('user_id', $userId)
                ->firstOrFail();

            if ($contextType === 'uc' && $subject && (int) $chat->subject_id !== (int) $subject->id) {
                abort(422, 'O chat indicado não pertence à Unidade Curricular atual.');
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
                'study_space_id' => $space?->id,
                'space_folder_id' => $folder?->id,
                'context_type' => $contextType,
                'is_temporary' => $contextType === 'temporary',
                'title' => $titulo,
            ]);
        }

        $chatId = (int) $chat->id;
        $threadId = (string) $chatId;
        $historico = $this->buildHistorico($chatId);

        $imagemPath = null;
        $imagemNome = null;
        $imagemMime = null;

        if ($request->hasFile('imagem')) {
            $file = $request->file('imagem');
            $imagemPath = $file->getPathname();
            $imagemNome = $file->getClientOriginalName();
            $imagemMime = $file->getMimeType() ?? 'image/jpeg';
        }

        $urlPython = config('services.python.url', 'http://127.0.0.1:8001/perguntar');
        $internalToken = trim((string) config('services.python.internal_token', ''));

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
        $uc = match ($contextType) {
            'uc' => $subject?->name ?? (string) $request->uc,
            'space' => 'Espaço: ' . ($space?->name ?? 'Sem nome'),
            default => 'Conversa temporária',
        };

        $userMessage = DB::transaction(function () use ($chat, $chatId, $texto) {
            $chat->touch();

            return Message::create([
                'chat_id' => $chatId,
                'role' => 'user',
                'content' => $texto,
            ]);
        });

        $userMessageId = (int) $userMessage->id;

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
            $userMessageId
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            echo 'data: ' . json_encode([
                'chat_id' => $chatId,
            ], JSON_UNESCAPED_UNICODE) . "\n\n";

            @ob_flush();
            flush();

            $postFields = [
                'texto' => $texto,
                'uc' => $uc,
                'preferencia' => $preferencia,
                'thread_id' => $threadId,
                'historico' => $historico,
                'message_id' => $userMessageId,
            ];

            if ($imagemPath && file_exists($imagemPath)) {
                $postFields['imagem'] = new \CURLFile($imagemPath, $imagemMime, $imagemNome);
            }

            $buffer = '';
            $fullAiText = '';
            $responseCode = 0;

            $ch = curl_init($urlPython);

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/event-stream',
                    'X-Internal-Token: ' . $internalToken,
                ],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,

                CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$buffer, &$fullAiText) {
                    if (connection_aborted()) {
                        return 0;
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
                    }

                    return strlen($chunk);
                },
            ]);

            $ok = curl_exec($ch);
            $responseCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            $falhouServicoIa = $ok === false || $responseCode >= 400;

            if (
                !$falhouServicoIa &&
                !empty(trim($fullAiText)) &&
                !$this->looksLikeTechnicalError($fullAiText)
            ) {
                DB::transaction(function () use ($chat, $chatId, $fullAiText) {
                    Message::create([
                        'chat_id' => $chatId,
                        'role' => 'ai',
                        'content' => $fullAiText,
                    ]);

                    $chat->touch();
                });
            }

            if (!connection_aborted()) {
                if ($falhouServicoIa) {
                    $msg = $responseCode === 401 || $responseCode === 403
                        ? "\n\n❌ Falha de autenticação interna com o serviço de IA."
                        : "\n\n❌ Falha ao comunicar com o serviço de IA.";

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

        $mensagens = Message::where('chat_id', $chat->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'chat_id' => $chat->id,
            'titulo' => $chat->title,
            'context_type' => $chat->context_type ?? 'uc',
            'subject_id' => $chat->subject_id,
            'subject_name' => $chat->subject?->name,
            'space_id' => $chat->study_space_id,
            'space_name' => $chat->studySpace?->name,
            'folder_id' => $chat->space_folder_id,
            'folder_name' => $chat->spaceFolder?->name,
            'mensagens' => $mensagens,
        ]);
    }

    public function listarChatsPorUC(Request $request)
    {
        $userId = $this->requireAuthenticatedUserId();
        $userName = Auth::user()?->name ?? 'Utilizador';

        $chats = Chat::query()
            ->where('chats.user_id', $userId)
            ->leftJoin('subjects', 'chats.subject_id', '=', 'subjects.id')
            ->leftJoin('study_spaces', 'chats.study_space_id', '=', 'study_spaces.id')
            ->leftJoin('space_folders', 'chats.space_folder_id', '=', 'space_folders.id')
            ->select(
                'chats.id as chat_id',
                'chats.context_type',
                'chats.study_space_id as space_id',
                'chats.space_folder_id as folder_id',
                'space_folders.name as nome_pasta',
                'study_spaces.name as nome_espaco',
                'subjects.id as subject_id',
                'subjects.name as nome_uc',
                'chats.title',
                'chats.updated_at'
            )
            ->orderBy('chats.updated_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'aluno' => $userName,
            'chats' => $chats,
        ]);
    }

    private function formatChat(Chat $chat): array
    {
        return [
            'chat_id' => $chat->id,
            'title' => $chat->title,
            'context_type' => $chat->context_type ?? 'uc',
            'subject_id' => $chat->subject_id,
            'space_id' => $chat->study_space_id,
            'folder_id' => $chat->space_folder_id,
            'is_temporary' => (bool) $chat->is_temporary,
            'created_at' => $chat->created_at?->toISOString(),
            'updated_at' => $chat->updated_at?->toISOString(),
        ];
    }
}
