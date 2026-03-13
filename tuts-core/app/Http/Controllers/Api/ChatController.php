<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User; // Assumindo que o aluno está logado
use App\Models\Subject;


class ChatController extends Controller
{
    // 1. Rota para CRIAR um novo chat (quando o aluno entra na página)
    public function criarChat(Request $request)
    {
        // Para testes, vamos assumir o utilizador com ID 1 (ajusta se usares Auth real)
        $chat = Chat::create([
            'user_id' => 1,
            'title' => 'Nova Conversa com o STU'
        ]);

        return response()->json([
            'status' => 'sucesso',
            'chat_id' => $chat->id
        ]);
    }

    // 2. Rota para ENVIAR a pergunta e GUARDAR no histórico
    public function enviarPergunta(Request $request)
    {
        // Agora sim, exigimos que o chat exista na base de dados!
        $request->validate([
            'chat_id' => 'required|exists:chats,id',
            'texto' => 'required|string'
        ]);

        $chat = Chat::findOrFail($request->chat_id);

        // A. Guardar a pergunta do Aluno na Base de Dados
        $mensagemAluno = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $request->texto
        ]);

        // B. Falar com o Python (A ponte mágica do Docker)
        $urlPython = 'http://host.docker.internal:8001/perguntar';

        try {
            $respostaPython = Http::timeout(30)->post($urlPython, [
                'texto' => $request->texto
            ]);

            if ($respostaPython->successful()) {
                $dadosIA = $respostaPython->json();

                // C. Guardar a resposta da IA na Base de Dados
                $mensagemIA = Message::create([
                    'chat_id' => $chat->id,
                    'role' => 'ai', // <--- A MUDANÇA É AQUI! O STU chama-se tuts!
                    'content' => $dadosIA['resposta_stu']
                ]);

                // D. Devolver o pacote completo ao Frontend
                return response()->json([
                    'status' => 'sucesso',
                    'mensagem_aluno' => $mensagemAluno,
                    'mensagem_ia' => $mensagemIA,
                    'fontes' => $dadosIA['fontes_consultadas'] ?? []
                ]);
            }

            return response()->json(['erro' => 'O Python devolveu erro: ' . $respostaPython->status()], 500);
        } catch (\Exception $e) {
            return response()->json([
                'erro' => 'Falha de comunicação com o Python.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    // 3. Rota para OBTER O HISTÓRICO de um chat
    public function obterHistorico($chat_id)
    {
        // 1. Verificar se o chat existe (se não existir, o Laravel devolve logo erro 404)
        $chat = Chat::findOrFail($chat_id);

        // 2. Ir à tabela 'messages' buscar todas as mensagens deste chat, ordenadas da mais antiga para a mais recente
        $mensagens = Message::where('chat_id', $chat->id)
            ->orderBy('created_at', 'asc')
            ->get();

        // 3. Devolver o histórico ao frontend
        return response()->json([
            'status' => 'sucesso',
            'chat_id' => $chat->id,
            'titulo' => $chat->title,
            'mensagens' => $mensagens
        ]);
    }
    public function listarChatsPorUC()
    {
        // 1. Aluno de teste (ID 1)
        $user = \App\Models\User::find(1);

        // 2. Vai buscar todas as UCs à tabela 'subjects'
        $ucs = \App\Models\Subject::all();

        $chatsDoAluno = [];

        // 3. Cria ou vai buscar o chat para cada UC
        foreach ($ucs as $uc) {
            $chat = \App\Models\Chat::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'subject_id' => $uc->id // <-- Agora usamos subject_id!
                ],
                [
                    'title' => 'Chat de ' . $uc->name // Assumindo que a coluna se chama 'name' na tabela subjects
                ]
            );

            $chatsDoAluno[] = [
                'chat_id' => $chat->id,
                'nome_uc' => $uc->name,
            ];
        }

        return response()->json([
            'status' => 'sucesso',
            'aluno' => $user->name,
            'chats' => $chatsDoAluno
        ]);
    }
}
