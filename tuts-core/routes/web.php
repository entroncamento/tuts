<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Rotas Públicas
|--------------------------------------------------------------------------
*/
// Página principal do chat
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Auth Custom API (Protegido contra Brute-Force e Credential Stuffing)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/api/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Rotas Protegidas (Requer Login e Email Confirmado)
|--------------------------------------------------------------------------
*/
// Usamos o 'verified' para garantir que ninguém usa o RAG sem ter confirmado o email @ua.pt
Route::middleware(['auth', 'verified'])->group(function () {

    // O Logout passa para dentro do grupo (apenas faz sentido fazer logout se estiveres logged in)
    Route::post('/api/logout', [AuthController::class, 'logout']);
    Route::get('/api/me', [AuthController::class, 'me']);

    /*
    |----------------------------------------------------------------------
    | Área do Aluno (Interação com IA)
    |----------------------------------------------------------------------
    */
    Route::prefix('api/chat')->group(function () {
        Route::get('/ucs', [ChatController::class, 'listarChatsPorUC']);
        Route::get('/{id}', [ChatController::class, 'obterHistorico']);

        // Limite moderado para criação de sessões de chat
        Route::post('/', [ChatController::class, 'criarChat'])
            ->middleware('throttle:30,1');

        // PROTEÇÃO CRÍTICA: Limite rigoroso na rota mais cara da plataforma!
        // Impede que ataques de scripts consumam os tokens da Groq e o CPU do FastAPI
        Route::post('/stream', [ChatController::class, 'enviarPerguntaStream'])
            ->middleware('throttle:15,1'); // Máx: 15 perguntas por minuto por IP/User
    });

    /*
    |----------------------------------------------------------------------
    | Área do Professor / Admin
    |----------------------------------------------------------------------
    */
    // PROTEÇÃO DE ACESSO: Usa o Gate que definimos previamente para bloquear alunos curiosos
    Route::middleware('can:view-dashboard')->group(function () {

        Route::get('/dashboard-professor', function () {
            return Inertia::render('DashboardProfessor');
        })->name('dashboard.professor');
    });
});

require __DIR__ . '/auth.php';
