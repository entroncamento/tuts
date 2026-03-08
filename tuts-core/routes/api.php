<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ChatController;

Route::get('/courses', [CourseController::class, 'index']);
// Rota para enviar mensagens para a IA
Route::post('/chat/mensagem', [ChatController::class, 'sendMessage']);
