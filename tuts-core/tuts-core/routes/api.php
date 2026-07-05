<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InternalMessageController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StudyPlanController;
use App\Http\Controllers\Api\MobileAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API: Rotas de Autenticação Móvel (Fase 2A)
|--------------------------------------------------------------------------
*/
Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/register', [MobileAuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
    });
});

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
    Route::post('/study-plans', [StudyPlanController::class, 'store']);

    // Personal Cover Routes
    Route::get('/me/subjects/{subject}/cover/photos', [\App\Http\Controllers\Api\PersonalCoverController::class, 'searchPhotos']);
    Route::put('/me/subjects/{subject}/cover', [\App\Http\Controllers\Api\PersonalCoverController::class, 'updateCover']);
    Route::delete('/me/subjects/{subject}/cover', [\App\Http\Controllers\Api\PersonalCoverController::class, 'deleteCover']);

    // Proteção de Autorização (Gate/Policy): Apenas quem tem a permissão 'view-dashboard'
    Route::middleware('can:view-dashboard')->group(function () {
        Route::get('/dashboard/metrics', [DashboardController::class, 'getMetrics']);
        Route::get('/teacher/dashboard/insights', [\App\Http\Controllers\Api\TeacherDashboardController::class, 'insights']);
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

// TODO: REMOVE BEFORE DELIVERY - Temporary route to debug R2 storage issues on Render
Route::get('/_debug/r2', function (\Illuminate\Http\Request $request) {
    $debugToken = env('R2_DEBUG_TOKEN');

    if (empty($debugToken) || $request->query('token') !== $debugToken) {
        abort(403, 'Unauthorized debug access.');
    }

    $r2Config = config('filesystems.disks.r2', []);
    $defaultDisk = config('filesystems.default');

    $key = $r2Config['key'] ?? null;
    $secret = $r2Config['secret'] ?? null;

    $responseData = [
        'filesystems' => [
            'default' => $defaultDisk,
        ],
        'r2' => [
            'bucket' => $r2Config['bucket'] ?? null,
            'endpoint' => $r2Config['endpoint'] ?? null,
            'region' => $r2Config['region'] ?? null,
            'has_key' => !empty($key),
            'has_secret' => !empty($secret),
            'key_length' => !empty($key) ? strlen((string) $key) : 0,
            'secret_length' => !empty($secret) ? strlen((string) $secret) : 0,
        ],
    ];

    $writeTestPath = 'debug/r2-test-' . \Illuminate\Support\Str::uuid() . '.txt';
    $responseData['write_test_path'] = $writeTestPath;

    try {
        // Write test
        $putResult = \Illuminate\Support\Facades\Storage::disk('r2')->put($writeTestPath, 'ok');
        $responseData['operations']['put'] = $putResult ? 'ok' : 'failed';

        // Exist check test
        $existsResult = \Illuminate\Support\Facades\Storage::disk('r2')->exists($writeTestPath);
        $responseData['operations']['exists'] = $existsResult;

        // Delete test
        $deleteResult = \Illuminate\Support\Facades\Storage::disk('r2')->delete($writeTestPath);
        $responseData['operations']['delete'] = $deleteResult;
    } catch (\Throwable $e) {
        $responseData['operations']['status'] = 'exception';
        $responseData['error'] = [
            'exception_class' => get_class($e),
            'exception_message' => $e->getMessage(),
            'previous_exception_class' => $e->getPrevious() ? get_class($e->getPrevious()) : null,
            'previous_exception_message' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
        ];
    }

    return response()->json($responseData);
});

