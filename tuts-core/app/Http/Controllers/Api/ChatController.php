<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Chat;
use App\Models\Message;

class ChatController extends Controller
{
    /**
     * Constrói o histórico de conversa para enviar ao Python.
     * Chamado ANTES de guardar a nova mensagem para evitar duplicação.
     */
    private function buildHistorico(int $chat_id): string
    {
        $msgs = Message::where('chat_id', $chat_id)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->reverse()
            ->values();

        return $msgs->map(fn($m) => [
            'role'    => $m->role === 'ai' ? 'assistant' : 'user',
            'content' => $m->content,
        ])->toJson();
    }

    /**
     * Cria um novo chat para o aluno autenticado.
     */
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

        return response()->json([
            'status'  => 'sucesso',
            'chat_id' => $chat->id,
        ]);
    }

    /**
     * Envia pergunta ao Python e guarda a conversa.
     */
    public function enviarPergunta(Request $request)
    {
        $request->validate([
            'chat_id'     => 'required|exists:chats,id',
            'texto'       => 'required|string',
            'preferencia' => 'nullable|string|in:textual,visual',
        ]);

        // IDOR protection: só encontra o chat se pertencer ao utilizador autenticado
        $chat = Chat::with('subject')
            ->where('id', $request->chat_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Construir o histórico ANTES de guardar a nova mensagem
        // para evitar que a pergunta actual apareça duplicada no contexto enviado ao Python
        $historico = $this->buildHistorico($chat->id);

        // Guardar a pergunta do aluno
        $mensagemAluno = Message::create([
            'chat_id' => $chat->id,
            'role'    => 'user',
            'content' => $request->texto,
        ]);

        $urlPython = 'http://host.docker.internal:8001/perguntar';

        try {
            $respostaPython = Http::timeout(90)->post($urlPython, [
                'texto'       => $request->texto,
                'thread_id'   => (string) $chat->id,
                'uc'          => $chat->subject->name ?? 'Geral',
                'preferencia' => $request->input('preferencia', 'textual'),
                'historico'   => $historico,
            ]);

            if ($respostaPython->successful()) {
                $dadosIA = $respostaPython->json();

                // Guardar a resposta da IA
                $mensagemIA = Message::create([
                    'chat_id' => $chat->id,
                    'role'    => 'ai',
                    'content' => $dadosIA['resposta_stu'],
                ]);

                return response()->json([
                    'status'          => 'sucesso',
                    'mensagem_aluno'  => $mensagemAluno,
                    'mensagem_ia'     => $mensagemIA,
                    'query_expandida' => $dadosIA['query_expandida'] ?? null,
                    'fontes'          => $dadosIA['fontes_consultadas'] ?? [],
                ]);
            }

            // Devolver o corpo do erro do Python para facilitar o debug
            return response()->json([
                'erro'    => 'O serviço de IA devolveu um erro HTTP ' . $respostaPython->status(),
                'detalhe' => $respostaPython->json() ?? $respostaPython->body(),
            ], $respostaPython->status());
        } catch (\Exception $e) {
            return response()->json([
                'erro'    => 'Falha de comunicação interna com o motor de Inteligência Artificial.',
                'detalhe' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Devolve o histórico de mensagens de um chat.
     */
    public function obterHistorico(Request $request, $chat_id)
    {
        // IDOR protection: não deixa ver histórico de outros alunos
        $chat = Chat::where('id', $chat_id)
            ->where('user_id', $request->user()->id)
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

    /**
     * Lista ou cria automaticamente um chat por cada UC do curso do aluno.
     */
    public function listarChatsPorUC(Request $request)
    {
        $user = $request->user();

        // Null-safe: se o aluno ainda não tiver curso atribuído, devolve lista vazia
        $ucs = $user->course?->subjects ?? collect();

        $chatsDoAluno = $ucs->map(function ($uc) use ($user) {
            $chat = Chat::firstOrCreate(
                [
                    'user_id'    => $user->id,
                    'subject_id' => $uc->id,
                ],
                [
                    'title' => 'Chat de ' . $uc->name,
                ]
            );

            return [
                'chat_id' => $chat->id,
                'nome_uc' => $uc->name,
            ];
        });

        return response()->json([
            'status' => 'sucesso',
            'aluno'  => $user->name,
            'chats'  => $chatsDoAluno,
        ]);
    }
}
