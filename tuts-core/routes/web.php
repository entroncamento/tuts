<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth
Route::post('/api/login',  [AuthController::class, 'login']);
Route::post('/api/logout', [AuthController::class, 'logout']);

// Chat + me — protegidas pela sessão web
Route::middleware('auth')->group(function () {
    Route::get('/api/me',           [AuthController::class, 'me']);

    // Rotas estáticas PRIMEIRO!
    Route::get('/api/chat/ucs',     [ChatController::class, 'listarChatsPorUC']);
    Route::post('/api/chat/stream', [ChatController::class, 'enviarPerguntaStream']);
    Route::post('/api/chat',        [ChatController::class, 'criarChat']);

    // Rotas dinâmicas DEPOIS! (O {id} não vai engolir as ucs)
    Route::get('/api/chat/{id}',    [ChatController::class, 'obterHistorico']);
});

Route::get('/', function () {
    return Inertia::render('Welcome');
});

require __DIR__ . '/auth.php';
