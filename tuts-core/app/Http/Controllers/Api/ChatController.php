<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\Subject;

class ChatController extends Controller
{
    // ==========================================
    // FUNÇÃO AUXILIAR: Construir Histórico para o Python
    // ==========================================
    private function buildHistorico(int $chat_id): string
    {
        // Vai buscar as últimas 6 mensagens (3 perguntas + 3 respostas)
        $msgs = Message::where('chat_id', $chat_id)
            ->orderBy('created_at', 'desc') // Ordena da mais recente para a mais antiga para o limit
            ->take(6)
            ->get()
            ->reverse() // Volta a colocar na ordem cronológica correta
            ->values(); // Reseta os índices da coleção

        // Mapeia para o formato que a IAedu/Python espera: [{"role": "user", "content": "..."}, ...]
        $historicoFormatado = $msgs->map(function ($m) {
            return [
                // Garantir que a IAedu percebe os papéis (mapear 'ai' do Laravel para 'assistant' se necessário, ou manter 'ai')
                'role'    => $m->role === 'ai' ? 'assistant' : 'user',
                'content' => $m->content,
            ];
        });

        return $historicoFormatado->toJson();
    }

    // ==========================================
    // 1. ROTA: Criar um novo chat
    // ==========================================
    public function criarChat(Request $request)
    {
        // 🌟 OTIMIZAÇÃO: Usar o utilizador autenticado via Sanctum/Passport
        $user = $request->user() ?? User::find(1); // Fallback para ID 1 apenas para testes locais se não houver auth

        $chat = Chat::create([
            'user_id' => $user->id,
            'title' => 'Nova Conversa com o STU'
        ]);

        return response()->json([
            'status' => 'sucesso',
            'chat_id' => $chat->id
        ]);
    }

    // ==========================================
    // 2. ROTA: Enviar Pergunta (A Ponte Mágica)
    // ==========================================
    public function enviarPergunta(Request $request)
    {
        // Validação rigorosa dos dados que vêm do Frontend (Vue.js)
        $request->validate([
            'chat_id' => 'required|exists:chats,id',
            'texto' => 'required|string',
            'preferencia' => 'nullable|string|in:textual,visual' // 🌟 OTIMIZAÇÃO: Receber preferência do aluno
        ]);

        // Carregar o chat e a UC (Subject) associada para dar contexto ao Python
        $chat = Chat::with('subject')->findOrFail($request->chat_id);

        // A. Guardar a pergunta do Aluno na Base de Dados
        $mensagemAluno = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $request->texto
        ]);

        // B. Falar com o Python (O Motor de IA)
        $urlPython = 'http://host.docker.internal:8001/perguntar';

        try {
            // 🌟 OTIMIZAÇÃO: Timeout aumentado para 90s para dar margem de manobra ao Python e à IAedu
            $respostaPython = Http::timeout(90)->post($urlPython, [
                'texto'       => $request->texto,
                'thread_id'   => (string) $chat->id, // 🌟 OTIMIZAÇÃO: Isolamento Multi-Tenant
                'uc'          => $chat->subject->name ?? 'Geral', // 🌟 OTIMIZAÇÃO: Contexto rigoroso anti-alucinação
                'preferencia' => $request->preferencia ?? 'textual', // 🌟 OTIMIZAÇÃO: Suporte a Mermaid.js
                'historico'   => $this->buildHistorico($chat->id) // 🌟 OTIMIZAÇÃO: Expansão de Query
            ]);

            if ($respostaPython->successful()) {
                $dadosIA = $respostaPython->json();

                // C. Guardar a resposta da IA na Base de Dados
                $mensagemIA = Message::create([
                    'chat_id' => $chat->id,
                    'role' => 'ai',
                    'content' => $dadosIA['resposta_stu']
                ]);

                // D. Devolver o pacote completo ao Frontend
                return response()->json([
                    'status' => 'sucesso',
                    'mensagem_aluno' => $mensagemAluno,
                    'mensagem_ia' => $mensagemIA,
                    'query_expandida' => $dadosIA['query_expandida'] ?? null, // Útil para debug no frontend
                    'fontes' => $dadosIA['fontes_consultadas'] ?? []
                ]);
            }

            // 🌟 OTIMIZAÇÃO: Devolver o corpo do erro do Python (facilita muito o debug)
            return response()->json([
                'erro' => 'O serviço de IA devolveu um erro HTTP ' . $respostaPython->status(),
                'detalhe' => $respostaPython->json() ?? $respostaPython->body()
            ], $respostaPython->status());
        } catch (\Exception $e) {
            return response()->json([
                'erro' => 'Falha de comunicação interna com o motor de Inteligência Artificial.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 3. ROTA: Obter o Histórico de um Chat
    // ==========================================
    public function obterHistorico($chat_id)
    {
        $chat = Chat::findOrFail($chat_id);

        $mensagens = Message::where('chat_id', $chat->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'chat_id' => $chat->id,
            'titulo' => $chat->title,
            'mensagens' => $mensagens
        ]);
    }

    // ==========================================
    // 4. ROTA: Listar as "Salas de Aula" (Chats por UC)
    // ==========================================
    public function listarChatsPorUC(Request $request)
    {
        // 🌟 OTIMIZAÇÃO: Usar o utilizador autenticado
        $user = $request->user() ?? User::find(1);

        $ucs = Subject::all();
        $chatsDoAluno = [];

        foreach ($ucs as $uc) {
            $chat = Chat::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'subject_id' => $uc->id
                ],
                [
                    'title' => 'Chat de ' . $uc->name
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
