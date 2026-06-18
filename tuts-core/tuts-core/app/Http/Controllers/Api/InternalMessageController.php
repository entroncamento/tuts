<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;

class InternalMessageController extends Controller
{
    public function guardarMetadata(Request $request, int $id)
    {
        // 1. Defesa Avançada: Assinatura HMAC e Anti-Replay Attack
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        $body      = $request->getContent();
        $secret    = trim((string) config('services.python.internal_token', ''));

        // Se faltar algum dado, aborta com mensagem genérica (não revela que é um endpoint interno)
        if ($secret === '' || !$timestamp || !$signature) {
            abort(403, 'Acesso não autorizado.');
        }

        // Janela de validade: Previne que um pedido intercetado seja reutilizado (ex: max 5 minutos)
        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            abort(403, 'Acesso não autorizado (Expirado).');
        }

        // Validação criptográfica do corpo do pedido
        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            abort(403, 'Acesso não autorizado (Assinatura Inválida).');
        }

        // 2. Validação Rigorosa de Dados (Prevenção de Carga e XSS)
        $data = $request->validate([
            'frustracao' => 'required|integer|min:0|max:10',
            'topicos'    => 'required|array|max:10', // Bloqueia arrays infinitos (Memory Exhaustion)
            // Usa a mesma Regex rigorosa que criámos no Dashboard para prevenir injeções
            'topicos.*'  => 'string|max:80|regex:/^[\pL\pN\s\-_]+$/u',
        ]);

        // 3. Verificação Lógica de Negócio
        // Garante que só as perguntas dos alunos ('user') podem receber metadados de análise.
        $message = Message::where('id', $id)
            ->where('role', 'user')
            ->firstOrFail();

        $existing = is_array($message->meta_data) ? $message->meta_data : [];

        $message->meta_data = array_replace_recursive($existing, [
            'analysis' => $data,
        ]);
        $message->save();

        return response()->json([
            'status'     => 'sucesso',
            'message_id' => $message->id,
        ]);
    }
}
