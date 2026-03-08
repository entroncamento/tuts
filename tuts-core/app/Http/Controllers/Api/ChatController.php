<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Chat;
use App\Models\Message;

class ChatController extends Controller
{
    public function sendMessage(Request $request)
    {
        // 1. Validar o que o utilizador enviou
        $request->validate([
            'chat_id' => 'required|exists:chats,id',
            'texto'   => 'required|string'
        ]);

        $chatId = $request->chat_id;
        $pergunta = $request->texto;

        // 2. Guardar a pergunta do Aluno na Base de Dados
        Message::create([
            'chat_id' => $chatId,
            'role'    => 'user',
            'content' => $pergunta
        ]);

        // 3. Fazer o pedido HTTP ao Microserviço Python (A PONTE!)
        // Como o Laravel está no Docker e o Python no teu PC, usamos host.docker.internal
        try {
            $response = Http::post('172.29.208.1:8001/perguntar', [
                'texto' => $pergunta
            ]);

            // Se o Python for abaixo, lançamos um erro
            if ($response->failed()) {
                return response()->json(['error' => 'Falha na comunicação com o motor de IA.'], 500);
            }

            // Apanhamos o que o Python nos devolveu (neste momento, é o conhecimento_recuperado cru)
            $dadosPython = $response->json();
            $respostaIA = $dadosPython['conhecimento_recuperado'] ?? 'Desculpa, não encontrei informação sobre isso.';
        } catch (\Exception $e) {
            return response()->json(['error' => 'O microserviço Python está desligado! Liga o FastAPI.'], 500);
        }

        // 4. Guardar a resposta da IA na Base de Dados
        $mensagemIA = Message::create([
            'chat_id' => $chatId,
            'role'    => 'ai',
            'content' => $respostaIA
        ]);

        // 5. Devolver tudo ao Frontend
        return response()->json([
            'sucesso' => true,
            'pergunta' => $pergunta,
            'resposta' => $mensagemIA->content
        ]);
    }
}
