<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityBlockController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CalendarItemController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PersonalMaterialController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SpaceFolderController;
use App\Http\Controllers\Api\SpaceMaterialController;
use App\Http\Controllers\Api\StudySpaceController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\SubjectOfficialContentController;
use App\Http\Controllers\Api\TeacherEventController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/novo')->name('home');

Route::post('/api/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/api/logout', [AuthController::class, 'logout']);
    Route::get('/api/me', [AuthController::class, 'me']);

    Route::prefix('api/me/materials')->group(function () {
        Route::get('/', [PersonalMaterialController::class, 'index']);
        Route::post('/', [PersonalMaterialController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/{material}/view', [PersonalMaterialController::class, 'view']);
        Route::delete('/{material}', [PersonalMaterialController::class, 'destroy']);
    });

    Route::get('/api/me/subjects', [SubjectController::class, 'studentSubjects']);
    Route::get('/api/me/teaching-subjects', [SubjectController::class, 'teachingSubjects']);
    Route::get('/api/subjects', [CourseController::class, 'mySubjects']);
    Route::post('/api/subjects', [SubjectController::class, 'store'])->middleware('throttle:30,1');
    Route::post('/api/subjects/join', [SubjectController::class, 'join'])->middleware('throttle:30,1');
    Route::get('/api/subjects/{subject}/sections', [SubjectOfficialContentController::class, 'sections']);
    Route::get('/api/subjects/{subject}/materials', [SubjectOfficialContentController::class, 'materials']);
    Route::post('/api/subjects/{subject}/materials', [SubjectOfficialContentController::class, 'storeMaterial'])
        ->middleware('throttle:20,1');
    Route::post('/api/subjects/{subject}/materials/{material}/ingest', [SubjectOfficialContentController::class, 'ingestMaterial'])
        ->middleware('throttle:10,1');
    Route::get('/api/subjects/{subject}/students', [SubjectController::class, 'students']);
    Route::get('/api/subjects/{subject}', [SubjectController::class, 'show']);
    Route::patch('/api/subjects/{subject}', [SubjectController::class, 'update']);

    Route::prefix('api/notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/', [NotificationController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
    });

    Route::prefix('api/calendar')->group(function () {
        Route::get('/items', [CalendarController::class, 'items']);
        Route::get('/upcoming', [CalendarController::class, 'upcoming']);
        Route::post('/items', [CalendarItemController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/items/{item}', [CalendarItemController::class, 'update']);
        Route::delete('/items/{item}', [CalendarItemController::class, 'destroy']);
    });

    Route::prefix('api/reminders')->group(function () {
        Route::get('/', [ReminderController::class, 'index']);
        Route::post('/', [ReminderController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/{reminder}/complete', [ReminderController::class, 'complete']);
        Route::delete('/{reminder}', [ReminderController::class, 'destroy']);
    });

    Route::prefix('api/availability-blocks')->group(function () {
        Route::get('/', [AvailabilityBlockController::class, 'index']);
        Route::post('/', [AvailabilityBlockController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/{block}', [AvailabilityBlockController::class, 'update']);
        Route::delete('/{block}', [AvailabilityBlockController::class, 'destroy']);
    });

    Route::prefix('api/teacher-events')->group(function () {
        Route::get('/', [TeacherEventController::class, 'index']);
        Route::post('/', [TeacherEventController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/{event}', [TeacherEventController::class, 'update']);
        Route::delete('/{event}', [TeacherEventController::class, 'destroy']);
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
        Route::get('/', [ChatController::class, 'listarChats']);
        Route::get('/ucs', [ChatController::class, 'listarChatsPorUC']);
        Route::get('/{id}', [ChatController::class, 'obterHistorico']);
        Route::post('/', [ChatController::class, 'criarChat'])->middleware('throttle:chat.create');
        Route::post('/stream', [ChatController::class, 'enviarPerguntaStream'])->middleware('throttle:chat.stream');
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
    })->where('any', 'home|chat|ucs|uc/.*|spaces|space/.*|calendar|planificacao.*|meus-planos|profile|dashboard|notificacoes|notifications');
});

require __DIR__ . '/auth.php';
