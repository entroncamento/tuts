<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
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
            ->map(fn($m) => [
                'role' => $m->role === 'ai' ? 'assistant' : 'user',
                'content' => $m->content,
            ])->toJson();
    }

    private function requireAuthenticatedUserId(): int
    {
        $userId = Auth::id();

        if (!$userId) {
            abort(401, 'Utilizador não autenticado.');
        }

        return (int) $userId;
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
        $request->validate([
            'subject_id' => 'nullable|exists:subjects,id',
            'title' => 'nullable|string|max:255',
        ]);

        $userId = $this->requireAuthenticatedUserId();

        $chat = Chat::create([
            'user_id' => $userId,
            'subject_id' => $request->input('subject_id'),
            'title' => $request->input('title', 'Nova Conversa com o TUT\'S'),
        ]);

        return response()->json(['status' => 'sucesso', 'chat_id' => $chat->id]);
    }

    public function enviarPerguntaStream(Request $request)
    {
        // 1. Validação de Limites de Input (Prevenção DoS)
        $request->validate([
            'chat_id' => 'nullable|integer|exists:chats,id',
            'texto' => 'required|string|max:4000',
            'uc' => 'required|string|exists:subjects,name',
            'preferencia' => 'nullable|string|in:default,visual,plano,quiz,feynman',
            'imagem' => 'nullable|image|max:4096',
        ]);

        $user = Auth::user();
        $userId = $this->requireAuthenticatedUserId();

        // 2. Validação Académica (Autorização por UC/Curso)
        $subject = Subject::where('name', $request->uc)->firstOrFail();

        // Verifica se o aluno pertence a um dos cursos associados à UC.
        // A relação UC ↔ curso é feita pela tabela pivot course_subject.
        if ($user->role !== 'professor') {
            $temAcessoAUc = $subject
                ->courses()
                ->where('courses.id', $user->course_id)
                ->exists();

            if (!$temAcessoAUc) {
                abort(403, 'Acesso negado. Não está inscrito no curso desta Unidade Curricular.');
            }
        }

        if ($request->filled('chat_id')) {
            $chat = Chat::where('id', (int) $request->chat_id)
                ->where('user_id', $userId)
                ->firstOrFail();
        } else {
            $chat = Chat::create([
                'user_id' => $userId,
                'subject_id' => $subject->id,
                'title' => 'Chat de ' . $request->uc,
            ]);
        }

        $historico = $this->buildHistorico($chat->id);

        $imagemPath = null;
        $imagemNome = null;
        $imagemMime = null;

        if ($request->hasFile('imagem')) {
            $file = $request->file('imagem');
            $imagemPath = $file->getPathname();
            $imagemNome = $file->getClientOriginalName();
            $imagemMime = $file->getMimeType() ?? 'image/jpeg';
        }

        // 3. Validação do Serviço Interno e Defesa de Redes
        $urlPython = config('services.python.url', 'http://127.0.0.1:8001/perguntar');
        $internalToken = trim((string) config('services.python.internal_token', ''));

        // Fail Early: Se o token estiver vazio, aborta imediatamente em vez de enviar um request inútil
        if ($internalToken === '') {
            abort(500, 'Configuração crítica: Token interno do serviço IA não configurado.');
        }

        // Validação da Whitelist do Host (Evita que variáveis de ambiente maliciosas desviem o tráfego)
        $parsedUrl = parse_url($urlPython);
        $allowedHosts = ['127.0.0.1', 'localhost', 'tuts-rag-service', env('PYTHON_HOST')];
        if (!in_array($parsedUrl['host'] ?? '', $allowedHosts, true)) {
            abort(500, 'Configuração insegura: O Host do serviço de IA não é de confiança.');
        }

        $texto = $request->texto;
        $uc = $request->uc;
        $preferencia = $request->input('preferencia', 'default');
        $threadId = (string) $chat->id;
        $chatId = $chat->id;

        // Guarda a pergunta do user e atualiza o timestamp do Chat usando uma transação de DB
        DB::transaction(function () use ($chat, $chatId, $texto) {
            $chat->touch(); // Move o chat para o topo da lista
            return Message::create([
                'chat_id' => $chatId,
                'role' => 'user',
                // O frontend Vue DEVE usar bibliotecas como DOMPurify ou v-text/v-html com MarkDown seguro
                // para renderizar isto e evitar Stored XSS. O backend guarda raw por design no RAG.
                'content' => $texto,
            ]);
        });

        // Vamos buscar o id da última mensagem acabada de criar para passar ao RAG
        $userMessageId = Message::where('chat_id', $chatId)->orderByDesc('id')->first()->id;

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
            // Limpeza de buffer mais segura
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

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
            $doneSent = false;
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

                // PROTEÇÃO CRÍTICA: Não seguir redirects. Previne Token Leak!
                CURLOPT_FOLLOWLOCATION => false,

                CURLOPT_CONNECTTIMEOUT => 10,

                // Redução de timeout para não saturar os workers do PHP FPM
                CURLOPT_TIMEOUT => 60,

                CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$buffer, &$fullAiText, &$doneSent) {
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
                            echo "data: [DONE]\n\n";
                            @ob_flush();
                            flush();
                            $doneSent = true;
                            continue;
                        }

                        $decoded = json_decode($payload, true);
                        if (is_array($decoded) && isset($decoded['chunk'])) {
                            // Cuidado com crescimento infinito de string. Max de 20k caracteres por segurança.
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

            if (!$doneSent && !connection_aborted()) {
                if ($ok === false || $responseCode >= 400) {
                    $msg = $responseCode === 401 || $responseCode === 403
                        ? "\n\n❌ Falha de autenticação interna com o serviço de IA."
                        : "\n\n❌ Falha ao comunicar com o serviço de IA.";

                    if ($curlError) {
                        $msg = "\n\n❌ Falha ao comunicar com o serviço de IA.";
                    }

                    echo 'data: ' . json_encode(['chunk' => $msg], JSON_UNESCAPED_UNICODE) . "\n\n";
                }

                echo "data: [DONE]\n\n";
                @ob_flush();
                flush();
            }

            // Apenas guarda a resposta na DB se não for um erro técnico
            if (!empty(trim($fullAiText)) && !$this->looksLikeTechnicalError($fullAiText)) {
                DB::transaction(function () use ($chat, $chatId, $fullAiText) {
                    Message::create([
                        'chat_id' => $chatId,
                        'role' => 'ai',
                        'content' => $fullAiText,
                    ]);
                    $chat->touch(); // Move para o topo da lista novamente (Última atividade)
                });
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // Essencial para NGINX não bloquear o stream
        ]);
    }

    public function obterHistorico(Request $request, $chat_id)
    {
        $userId = $this->requireAuthenticatedUserId();

        $chat = Chat::where('id', $chat_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $mensagens = Message::where('chat_id', $chat->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'chat_id' => $chat->id,
            'titulo' => $chat->title,
            'mensagens' => $mensagens,
        ]);
    }

    public function listarChatsPorUC(Request $request)
    {
        $userId = $this->requireAuthenticatedUserId();
        $userName = Auth::user()?->name ?? 'Utilizador';

        $chats = Chat::where('user_id', $userId)
            ->leftJoin('subjects', 'chats.subject_id', '=', 'subjects.id')
            ->select('chats.id as chat_id', 'subjects.name as nome_uc', 'chats.title')
            // Agora a ordenação por updated_at vai funcionar perfeitamente graças ao $chat->touch()
            ->orderBy('chats.updated_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'aluno' => $userName,
            'chats' => $chats,
        ]);
    }
}
