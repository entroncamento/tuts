<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestTracing
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?: (string) Str::uuid();
        
        // Ensure the header is available in the request
        $request->headers->set('X-Request-ID', $requestId);

        // Add to Laravel 11 Context so all subsequent logs have it automatically
        Context::add('request_id', $requestId);
        
        if ($user = $request->user()) {
            Context::add('user_id', $user->id);
            Context::add('user_role', $user->role ?? 'aluno');
        } else {
            Context::add('user_id', 'guest');
        }

        Context::add('request_path', $request->path());
        Context::add('request_method', $request->method());
        Context::add('request_ip', $request->ip());

        $response = $next($request);

        // Inject correlation ID back to the client
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
