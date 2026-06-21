<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));

        // Limite para comunicação interna (RAG <-> Laravel)
        RateLimiter::for('internal', function (Request $request) {
            return Limit::perMinute(200)->by($request->header('X-Internal-Token') ?: $request->ip());
        });

        // Limite para criação de chats (Proteção contra Spam)
        RateLimiter::for('chat.create', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Limite para perguntas via Stream (IA custa dinheiro/recursos)
        RateLimiter::for('chat.stream', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        // Limite geral da API
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Gate::define('view-dashboard', function ($user) {
            return $user->role === 'professor';
        });

        Gate::define('manage-personal-cover', function ($user, $subject) {
            $subjectId = $subject instanceof \App\Models\Subject ? $subject->id : $subject;
            return \DB::table('subject_user')
                ->where('subject_id', $subjectId)
                ->where('user_id', $user->id)
                ->where('role', 'student')
                ->where('status', 'active')
                ->exists();
        });
    }
}
