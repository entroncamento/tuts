<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ChatController;

Route::get('/courses', [CourseController::class, 'index']);
// Rota para enviar mensagens para a IA
Route::post('/chat/criar', [ChatController::class, 'criarChat']);
Route::post('/chat/perguntar', [ChatController::class, 'enviarPergunta']);
Route::get('/chat/{chat_id}/historico', [ChatController::class, 'obterHistorico']);
Route::get('/meus-chats', [ChatController::class, 'listarChatsPorUC']);
