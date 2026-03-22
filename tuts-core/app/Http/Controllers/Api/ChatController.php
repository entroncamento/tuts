<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;

class ChatController extends Controller
{
    private function buildHistorico(int $chat_id): string
    {
        $ids = Message::where('chat_id', $chat_id)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->pluck('id');

        $msgs = Message::whereIn('id', $ids)
            ->orderBy('created_at', 'asc')
            ->get();

        return $msgs->map(fn($m) => [
            'role'    => $m->role === 'ai' ? 'assistant' : 'user',
            'content' => $m->content,
        ])->toJson();
    }

    private function obterUtilizadorDeTeste(Request $request)
    {
        return $request->user() ?? User::firstOrCreate(
            ['email' => 'teste@tuts.pt'],
            ['name' => 'Aluno Teste', 'password' => bcrypt('123456')]
        );
    }

    public function criarChat(Request $request)
    {
        $request->validate([
            'subject_id' => 'nullable|exists:subjects,id',
            'title'      => 'nullable|string|max:255',
        ]);

        $user = $this->obterUtilizadorDeTeste($request);

        $chat = Chat::create([
            'user_id'    => $user->id,
            'subject_id' => $request->input('subject_id'),
            'title'      => $request->input('title', 'Nova Conversa com o STU'),
        ]);

        return response()->json(['status' => 'sucesso', 'chat_id' => $chat->id]);
    }

    public function enviarPerguntaStream(Request $request)
    {
        $request->validate([
            'texto'       => 'required|string',
            'uc'          => 'required|string',
            'preferencia' => 'nullable|string|in:textual,visual,plano,quiz,feynman',
            'imagem'      => 'nullable|image|max:4096',
        ]);

        $user    = $this->obterUtilizadorDeTeste($request);
        $subject = \App\Models\Subject::where('name', $request->uc)->first();

        $chat = Chat::firstOrCreate([
            'user_id'    => $user->id,
            'subject_id' => $subject?->id,
        ], [
            'title' => 'Chat de ' . $request->uc,
        ]);

        $historico = $this->buildHistorico($chat->id);

        Message::create([
            'chat_id' => $chat->id,
            'role'    => 'user',
            'content' => $request->texto,
        ]);

        return response()->stream(function () use ($request, $chat, $historico) {

            // ✅ FIX: Elimina TODOS os buffers PHP existentes para que cada
            // echo chegue ao browser imediatamente, sem ficar preso em buffer.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $urlPython = 'http://26.188.11.167:8001/perguntar';

            try {
                $http = Http::withHeaders(['X-Internal-Token' => 'TUTS_SUPER_SECRET_123'])
                    ->withOptions(['stream' => true])
                    ->asMultipart();

                if ($request->hasFile('imagem')) {
                    $file = $request->file('imagem');
                    $http->attach(
                        'imagem',
                        file_get_contents($file->getPathname()),
                        $file->getClientOriginalName()
                    );
                }

                $response = $http->post($urlPython, [
                    'texto'       => $request->texto,
                    'thread_id'   => (string) $chat->id,
                    'uc'          => $request->uc,
                    'preferencia' => $request->input('preferencia', 'textual'),
                    'historico'   => $historico,
                ]);

                $fullAiText = '';
                $stream = $response->toPsrResponse()->getBody()->detach();

                if (is_resource($stream)) {
                    while (!feof($stream)) {
                        $line = fgets($stream);

                        if ($line === false) {
                            continue;
                        }

                        // ✅ Envia TODAS as linhas SSE (status_msg, chunk, sem_contexto, etc.)
                        if (str_starts_with($line, 'data: ')) {
                            $jsonStr = substr($line, 6);

                            if (trim($jsonStr) !== '[DONE]') {
                                $data = json_decode($jsonStr, true);
                                if (isset($data['chunk'])) {
                                    $fullAiText .= $data['chunk'];
                                }
                            }

                            echo $line;
                            flush(); // ob_flush() removido — já não há buffer para fazer flush
                        }
                    }
                    fclose($stream);
                }

                if (!empty(trim($fullAiText))) {
                    Message::create([
                        'chat_id' => $chat->id,
                        'role'    => 'ai',
                        'content' => $fullAiText,
                    ]);
                }
            } catch (\Exception $e) {
                $errorMsg = json_encode(['chunk' => "\n\n❌ Erro na IA: " . $e->getMessage()]);
                echo "data: $errorMsg\n\n";
                echo "data: [DONE]\n\n";
                flush();
            }
        }, 200, [
            'Cache-Control'     => 'no-cache',
            'Content-Type'      => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function obterHistorico(Request $request, $chat_id)
    {
        $user = $this->obterUtilizadorDeTeste($request);

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
        $user = $this->obterUtilizadorDeTeste($request);
        $ucs  = $user->course?->subjects ?? collect();

        $chatsDoAluno = $ucs->map(function ($uc) use ($user) {
            $chat = Chat::firstOrCreate(
                ['user_id' => $user->id, 'subject_id' => $uc->id],
                ['title'   => 'Chat de ' . $uc->name]
            );

            return ['chat_id' => $chat->id, 'nome_uc' => $uc->name];
        });

        return response()->json([
            'status' => 'sucesso',
            'aluno'  => $user->name,
            'chats'  => $chatsDoAluno,
        ]);
    }
}
