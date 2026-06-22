<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\RequestTracing::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\RequestTracing::class,
        ]);

        // ── REGISTO DE ALIASES (SEGURANÇA) ───────────────────────────────────
        // Aqui ligamos a string 'internal.api' que usámos nas routes/api.php 
        // à classe física do Middleware que fará a verificação do Token/IP.
        $middleware->alias([
            'internal.api' => \App\Http\Middleware\VerifyInternalApiToken::class,
            'metrics' => \App\Http\Middleware\MetricsMiddleware::class,
        ]);

        // ── SECURITY HEADERS GLOBAIS ─────────────────────────────────────────
        // Em produção, deves criar e adicionar um middleware global para 
        // injetar headers como X-Frame-Options, Strict-Transport-Security, etc.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->validateCsrfTokens(except: [
            'api/register',
            'api/login',
            'api/logout',
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ── PROTEÇÃO CONTRA LEAK DE STACK TRACES NA API ──────────────────────

        // 1. Forçar resposta em JSON se for uma rota da API (Evita que o Laravel
        // devolva uma página HTML de erro quando o cliente espera JSON).
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });

        // 2. Sanitização do Erro 500
        // Se por infelicidade o APP_DEBUG ficar "true" em produção, 
        // isto esconde as credenciais e devolve uma mensagem limpa.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->is('api/*') && $response->getStatusCode() === 500) {
                if (!config('app.debug')) {
                    return response()->json([
                        'message' => 'Erro interno do servidor. A equipa técnica foi notificada.',
                        'error' => 'internal_server_error'
                    ], 500);
                }
            }
            return $response;
        });
    })->create();
