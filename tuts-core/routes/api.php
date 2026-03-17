<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ChatController;

// Pública: usada no ecrã de registo para listar os cursos disponíveis
Route::get('/courses', [CourseController::class, 'index']);

// Protegidas: requerem token Sanctum válido
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/chat/criar',                  [ChatController::class, 'criarChat']);
    Route::post('/chat/perguntar',              [ChatController::class, 'enviarPergunta']);
    Route::get('/chat/{chat_id}/historico',     [ChatController::class, 'obterHistorico']);
    Route::get('/meus-chats',                   [ChatController::class, 'listarChatsPorUC']);
});
