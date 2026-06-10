<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SpaceFolderController;
use App\Http\Controllers\Api\SpaceMaterialController;
use App\Http\Controllers\Api\StudySpaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/novo')->name('home');

Route::post('/api/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/api/logout', [AuthController::class, 'logout']);
    Route::get('/api/me', [AuthController::class, 'me']);

    Route::get('/api/subjects', [CourseController::class, 'mySubjects']);

    Route::prefix('api/notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/', [NotificationController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
    });

    Route::prefix('api/spaces')->group(function () {
        Route::get('/', [StudySpaceController::class, 'index']);
        Route::post('/', [StudySpaceController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/{space}', [StudySpaceController::class, 'show']);
        Route::patch('/{space}', [StudySpaceController::class, 'update']);
        Route::delete('/{space}', [StudySpaceController::class, 'destroy']);

        Route::get('/{space}/folders', [SpaceFolderController::class, 'index']);
        Route::post('/{space}/folders', [SpaceFolderController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/{space}/folders/{folder}', [SpaceFolderController::class, 'update']);
        Route::delete('/{space}/folders/{folder}', [SpaceFolderController::class, 'destroy']);

        Route::get('/{space}/conversations', [StudySpaceController::class, 'conversations']);
        Route::post('/{space}/conversations', [StudySpaceController::class, 'createConversation'])->middleware('throttle:30,1');
        Route::patch('/{space}/conversations/{chat}/folder', [StudySpaceController::class, 'moveConversation']);

        Route::get('/{space}/materials', [SpaceMaterialController::class, 'index']);
        Route::post('/{space}/materials', [SpaceMaterialController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('/{space}/materials/{material}/folder', [SpaceMaterialController::class, 'moveToFolder']);
        Route::get('/{space}/materials/{material}/download', [SpaceMaterialController::class, 'download']);
        Route::get('/{space}/materials/{material}/view', [SpaceMaterialController::class, 'view']);
        Route::delete('/{space}/materials/{material}', [SpaceMaterialController::class, 'destroy']);
    });

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
