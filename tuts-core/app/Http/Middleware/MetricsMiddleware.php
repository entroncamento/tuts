<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MetricsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.metrics.token');

        if (!$token || $request->header('X-Metrics-Token') !== $token) {
            // Allow if request comes from local network (optional, safer to use token)
            if ($this->isInternalNetwork($request)) {
                return $next($request);
            }

            abort(403, 'Unauthorized metrics access.');
        }

        return $next($request);
    }

    private function isInternalNetwork(Request $request): bool
    {
        $ip = $request->ip();
        // Simple check for private IP ranges (RFC 1918)
        return preg_match('/^(127\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $ip);
    }
}
