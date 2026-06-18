<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InternalMessageController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StudyPlanController;

/*
|--------------------------------------------------------------------------
| API: Rotas de Saúde (Healthchecks)
|--------------------------------------------------------------------------
*/
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/health', [HealthController::class, 'health']);

Route::middleware('metrics')->get('/metrics', function () {
    return response('laravel_app_up 1', 200)->header('Content-Type', 'text/plain');
});

/*
|--------------------------------------------------------------------------
| API: Rotas de Professores / Administração
|--------------------------------------------------------------------------
*/
// Usamos auth:sanctum (padrão de APIs no Laravel) e um limite de taxa global
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Proteção de Autorização (Gate/Policy): Apenas quem tem a permissão 'view-dashboard'
    Route::middleware('can:view-dashboard')->group(function () {
        Route::get('/dashboard/metrics', [DashboardController::class, 'getMetrics']);
    });
});

/*
|--------------------------------------------------------------------------
| API: Rotas Internas (Comunicação RAG Python -> Laravel)
|--------------------------------------------------------------------------
*/
// Grupo isolado. Protegido por limite de taxa dedicado e middleware de segurança.
Route::middleware(['throttle:internal', 'internal.api'])->group(function () {

    // Mantivemos a mesma rota que está configurada no services/analise.py do Python
    Route::post('/messages/{id}/metadata', [InternalMessageController::class, 'guardarMetadata']);
});

Route::post('/study-plans', [StudyPlanController::class, 'store'])->middleware('auth:sanctum');
