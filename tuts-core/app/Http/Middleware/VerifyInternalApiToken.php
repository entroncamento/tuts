<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. IP Allowlist (Defesa em Profundidade na Camada de Rede)
        // Em produção, garantimos que apenas pedidos locais ou da rede interna do Docker entram.
        if (app()->environment('production')) {
            $allowedIps = ['127.0.0.1', '::1'];

            // Dica: Se o Python RAG estiver num container Docker separado (ex: 172.18.0.x),
            // deves adicionar o IP ou subnet do Docker à whitelist acima.

            if (!in_array($request->ip(), $allowedIps, true)) {
                abort(403, 'Acesso bloqueado por restrição de IP.');
            }
        }

        // 2. Pré-validação Estrutural
        // Como implementámos a assinatura HMAC avançada no InternalMessageController,
        // o Middleware garante que o pedido não prossegue se faltarem as credenciais base.
        if (!$request->hasHeader('X-Timestamp') || !$request->hasHeader('X-Signature')) {
            // Em caso de falta de headers, o abort(403) pára o fluxo imediatamente
            abort(403, 'Acesso não autorizado. Cabeçalhos de segurança ausentes.');
        }

        // Tudo certo! Passa o pedido para o InternalMessageController processar o HMAC.
        return $next($request);
    }
}
