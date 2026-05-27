<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CourseController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::post('/api/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/api/logout', [AuthController::class, 'logout']);
    Route::get('/api/me', [AuthController::class, 'me']);

    Route::get('/api/subjects', [CourseController::class, 'mySubjects']);

    Route::prefix('api/chat')->group(function () {
        Route::get('/ucs', [ChatController::class, 'listarChatsPorUC']);
        Route::get('/{id}', [ChatController::class, 'obterHistorico']);
        Route::post('/', [ChatController::class, 'criarChat'])->middleware('throttle:30,1');
        Route::post('/stream', [ChatController::class, 'enviarPerguntaStream'])->middleware('throttle:15,1');
    });

    Route::get('/dashboard-professor', function () {
        return Inertia::render('DashboardProfessor');
    })->middleware('can:view-dashboard')->name('dashboard.professor');

    Route::get('/novo', function () {
        return Inertia::render('TutsNew');
    });

    Route::get('/pdfs/{filename}', function (string $filename) {
        $safeFilename = basename($filename);

        $possiblePaths = [
            storage_path('app/public/pdfs/' . $safeFilename),
            storage_path('app/pdfs/' . $safeFilename),
            public_path('storage/pdfs/' . $safeFilename),
        ];

        $path = collect($possiblePaths)->first(function ($possiblePath) {
            return file_exists($possiblePath) && is_file($possiblePath);
        });

        abort_unless($path, 404);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->where('filename', '.*')->name('pdfs.show');

    Route::get('/{any}', function () {
        return Inertia::render('TutsNew');
    })->where('any', 'home|chat|ucs|uc/.*|spaces|space/.*|calendar|planificacao.*|meus-planos|profile|dashboard');
});

require __DIR__ . '/auth.php';
