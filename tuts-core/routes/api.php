<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    // Aqui ficarão as rotas que OBRIGAM a login no futuro
});

// 🚀 ROTAS PÚBLICAS (Para testares à vontade sem Login!)
Route::post('/chat/stream', [ChatController::class, 'enviarPerguntaStream']);
Route::post('/chat', [ChatController::class, 'criarChat']);
Route::get('/chat/ucs', [ChatController::class, 'listarChatsPorUC']);
Route::get('/chat/{id}', [ChatController::class, 'obterHistorico']);
