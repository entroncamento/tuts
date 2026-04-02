<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    private function buildHistorico(int $chat_id): string
    {
        // 🔥 MAGIA DE LARAVEL: Uma única query que traz os últimos 6 e depois inverte a coleção 
        // para ficarem por ordem cronológica. (Performance +100%)
        return Message::where('chat_id', $chat_id)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => [
                'role'    => $m->role === 'ai' ? 'assistant' : 'user',
                'content' => $m->content,
            ])->toJson();
    }

    public function criarChat(Request $request)
    {
        $request->validate([
            'subject_id' => 'nullable|exists:subjects,id',
            'title'      => 'nullable|string|max:255',
        ]);

        $chat = Chat::create([
            'user_id'    => $request->user()->id,
            'subject_id' => $request->input('subject_id'),
            'title'      => $request->input('title', 'Nova Conversa com o STU'),
        ]);

        return response()->json(['status' => 'sucesso', 'chat_id' => $chat->id]);
    }

    public function enviarPerguntaStream(Request $request)
    {
        $request->validate([
            'texto'       => 'required|string|max:4000',
            'uc'          => 'required|string',
            'preferencia' => 'nullable|string|in:default,visual,plano,quiz,feynman',
            'imagem'      => 'nullable|image|max:4096',
        ]);

        $user    = $request->user();
        $subject = Subject::where('name', $request->uc)->first();

        $chat = Chat::firstOrCreate(
            ['user_id' => $user->id, 'subject_id' => $subject?->id],
            ['title'   => 'Chat de ' . $request->uc]
        );

        $historico = $this->buildHistorico($chat->id);

        // Captura dados do ficheiro
        $imagemPath  = null;
        $imagemNome  = null;
        $imagemMime  = null;
        if ($request->hasFile('imagem')) {
            $file       = $request->file('imagem');
            $imagemPath = $file->getPathname();
            $imagemNome = $file->getClientOriginalName();
            $imagemMime = $file->getMimeType() ?? 'image/jpeg';
        }

        $urlPython     = config('services.python.url', 'http://host.docker.internal:8001/perguntar');
        $internalToken = trim(config('services.python.internal_token'));
        $texto         = $request->texto;
        $uc            = $request->uc;
        $preferencia   = $request->input('preferencia', 'default');
        $threadId      = (string) $chat->id;
        $chatId        = $chat->id;

        return response()->stream(function () use (
            $urlPython,
            $internalToken,
            $texto,
            $uc,
            $preferencia,
            $threadId,
            $historico,
            $chatId,
            $imagemPath,
            $imagemNome,
            $imagemMime
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Constrói multipart/form-data manualmente
            $boundary = '----TutsBoundary' . uniqid();
            $body     = '';

            foreach (['texto' => $texto, 'uc' => $uc, 'preferencia' => $preferencia, 'thread_id' => $threadId, 'historico' => $historico] as $field => $value) {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"{$field}\"\r\n\r\n";
                $body .= $value . "\r\n";
            }

            if ($imagemPath && file_exists($imagemPath)) {
                $imgData = file_get_contents($imagemPath);
                $body   .= "--{$boundary}\r\n";
                $body   .= "Content-Disposition: form-data; name=\"imagem\"; filename=\"{$imagemNome}\"\r\n";
                $body   .= "Content-Type: {$imagemMime}\r\n\r\n";
                $body   .= $imgData . "\r\n";
            }

            $body .= "--{$boundary}--\r\n";

            // Preparar a Socket
            $parsedUrl = parse_url($urlPython);
            $host      = $parsedUrl['host'];
            $port      = $parsedUrl['port'] ?? 80;
            $path      = $parsedUrl['path'] ?? '/';

            $httpRequest  = "POST {$path} HTTP/1.1\r\n";
            $httpRequest .= "Host: {$host}:{$port}\r\n";
            $httpRequest .= "X-Internal-Token: {$internalToken}\r\n";
            $httpRequest .= "Content-Type: multipart/form-data; boundary={$boundary}\r\n";
            $httpRequest .= "Content-Length: " . strlen($body) . "\r\n";
            $httpRequest .= "Connection: close\r\n\r\n";
            $httpRequest .= $body;

            $errno  = 0;
            $errstr = '';
            $socket = @fsockopen($host, $port, $errno, $errstr, 30);

            if (!$socket) {
                echo "data: " . json_encode(['chunk' => "\n\n❌ Não foi possível ligar ao servidor de Inteligência Artificial: {$errstr}"]) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();
                return; // 🛑 SAÍDA IMEDIATA: A mensagem do user não é guardada na BD!
            }

            // 🔥 SE A SOCKET ABRIU, AGORA SIM GUARDAMOS A PERGUNTA DO ALUNO NA BD!
            Message::create([
                'chat_id' => $chatId,
                'role'    => 'user',
                'content' => $texto,
            ]);

            fwrite($socket, $httpRequest);
            stream_set_blocking($socket, false);
            stream_set_timeout($socket, 120);

            $fullAiText  = '';
            $headersDone = false;
            $buffer      = '';

            while (!feof($socket)) {
                $chunk = fread($socket, 512);
                if ($chunk === false || $chunk === '') {
                    usleep(5000);
                    continue;
                }

                $buffer .= $chunk;

                if (!$headersDone) {
                    $headerEnd = strpos($buffer, "\r\n\r\n");
                    if ($headerEnd === false) continue;
                    $buffer      = substr($buffer, $headerEnd + 4);
                    $headersDone = true;
                }

                // 🔥 PARSER SEGURO: Apenas processa "data: " quando encontrado numa linha SSE válida
                while (($newline = strpos($buffer, "\n")) !== false) {
                    $line   = substr($buffer, 0, $newline + 1);
                    $buffer = substr($buffer, $newline + 1);

                    // Verifica se a linha começa por "data: " ignorando eventuais prefixos de Chunked HTTP
                    if (($dataPos = strpos($line, 'data: ')) !== false) {
                        $jsonStr = substr($line, $dataPos + 6);

                        if (trim($jsonStr) !== '[DONE]') {
                            $data = json_decode($jsonStr, true);
                            if (isset($data['chunk'])) {
                                $fullAiText .= $data['chunk'];
                            }
                        }

                        // Enviamos a linha SSE limpa para o Vue.js
                        $linhaLimpa = substr($line, $dataPos);
                        echo $linhaLimpa;
                        if (!str_ends_with($linhaLimpa, "\n")) echo "\n";
                        flush();
                    }
                }
            }

            fclose($socket);

            echo "data: [DONE]\n\n";
            flush();

            if (!empty(trim($fullAiText))) {
                Message::create([
                    'chat_id' => $chatId,
                    'role'    => 'ai',
                    'content' => $fullAiText,
                ]);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function obterHistorico(Request $request, $chat_id)
    {
        $user = $request->user();

        $chat = Chat::where('id', $chat_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $mensagens = Message::where('chat_id', $chat->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status'    => 'sucesso',
            'chat_id'   => $chat->id,
            'titulo'    => $chat->title,
            'mensagens' => $mensagens,
        ]);
    }

    public function listarChatsPorUC(Request $request)
    {
        $user = $request->user();

        // Se a lógica do sistema exigir a criação de um chat base por UC, descomentamos o firstOrCreate.
        // Como best practice de API REST (read-only em GET), retornamos apenas os chats que o aluno já criou.
        $chats = Chat::where('user_id', $user->id)
            ->join('subjects', 'chats.subject_id', '=', 'subjects.id')
            ->select('chats.id as chat_id', 'subjects.name as nome_uc', 'chats.title')
            ->orderBy('chats.updated_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'aluno'  => $user->name,
            'chats'  => $chats,
        ]);
    }
}
