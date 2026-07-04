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

Route::post('/api/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');

Route::post('/api/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth')->group(function () {
    Route::post('/api/logout', [AuthController::class, 'logout']);
    Route::get('/api/me', [AuthController::class, 'me']);
    Route::post('/api/me/onboarding/complete', [AuthController::class, 'completeOnboarding']);

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
    Route::post('/api/subjects/{subject}/sections', [SubjectOfficialContentController::class, 'storeSection'])
        ->middleware('throttle:30,1');
    Route::patch('/api/subjects/{subject}/sections/{section}', [SubjectOfficialContentController::class, 'updateSection'])
        ->middleware('throttle:30,1');
    Route::delete('/api/subjects/{subject}/sections/{section}', [SubjectOfficialContentController::class, 'destroySection'])
        ->middleware('throttle:30,1');
    Route::get('/api/subjects/{subject}/materials', [SubjectOfficialContentController::class, 'materials']);
    Route::post('/api/subjects/{subject}/materials', [SubjectOfficialContentController::class, 'storeMaterial'])
        ->middleware('throttle:20,1');
    Route::delete('/api/subjects/{subject}/materials/{material}', [SubjectOfficialContentController::class, 'destroyMaterial'])
        ->middleware('throttle:30,1');
    Route::post('/api/subjects/{subject}/materials/{material}/ingest', [SubjectOfficialContentController::class, 'ingestMaterial'])
        ->middleware('throttle:10,1');
    Route::get('/api/subjects/{subject}/materials/{material}/view', [SubjectOfficialContentController::class, 'view']);
    Route::get('/api/subjects/{subject}/materials/{material}/download', [SubjectOfficialContentController::class, 'download']);
    Route::get('/api/subjects/{subject}/students', [SubjectController::class, 'students']);
    Route::get('/api/subjects/{subject}', [SubjectController::class, 'show']);
    Route::patch('/api/subjects/{subject}', [SubjectController::class, 'update']);
    Route::delete('/api/subjects/{subject}', [SubjectController::class, 'destroy']);

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
        Route::patch('/{chat}/retention', [ChatController::class, 'atualizarRetencao']);
        Route::delete('/{chat}', [ChatController::class, 'destroy']);
        Route::post('/', [ChatController::class, 'criarChat'])->middleware('throttle:chat.create');
        Route::post('/stream', [ChatController::class, 'enviarPerguntaStream'])->middleware('throttle:chat.stream');
    });

    Route::get('/dashboard-professor', function () {
        return Inertia::render('DashboardProfessor');
    })->middleware('can:view-dashboard')->name('dashboard.professor');

    Route::middleware('admin')->prefix('api/admin')->group(function () {
        Route::get('/health', function () {
            return response()->json([
                'status' => 'ok',
                'role' => auth()->user()->role,
                'message' => 'Admin area is healthy and operational.'
            ]);
        });
        Route::get('/me', function () {
            return response()->json([
                'user' => [
                    'id' => auth()->user()->id,
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                    'role' => auth()->user()->role,
                ]
            ]);
        });

        // Visão Geral / Dashboard stats
        Route::get('/overview', [\App\Http\Controllers\Api\Admin\AdminOverviewController::class, 'index']);

        // Gestão de Utilizadores
        Route::get('/users', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'index']);
        Route::get('/users/{id}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'show']);
        Route::post('/users', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'store']);
        Route::patch('/users/{id}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'update']);
        Route::patch('/users/{id}/role', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'updateRole']);
        Route::patch('/users/{id}/block', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'toggleBlock']);
        Route::delete('/users/{id}', [\App\Http\Controllers\Api\Admin\AdminUserController::class, 'destroy']);

        // Gestão de Cursos e UCs (Subjects)
        Route::get('/courses', [\App\Http\Controllers\Api\Admin\AdminCourseController::class, 'index']);
        Route::post('/courses', [\App\Http\Controllers\Api\Admin\AdminCourseController::class, 'store']);
        Route::get('/courses/{id}', [\App\Http\Controllers\Api\Admin\AdminCourseController::class, 'show']);
        Route::patch('/courses/{id}', [\App\Http\Controllers\Api\Admin\AdminCourseController::class, 'update']);

        Route::get('/subjects', [\App\Http\Controllers\Api\Admin\AdminSubjectController::class, 'index']);
        Route::post('/subjects', [\App\Http\Controllers\Api\Admin\AdminSubjectController::class, 'store']);
        Route::get('/subjects/{id}', [\App\Http\Controllers\Api\Admin\AdminSubjectController::class, 'show']);
        Route::patch('/subjects/{id}', [\App\Http\Controllers\Api\Admin\AdminSubjectController::class, 'update']);
        Route::delete('/subjects/{id}', [\App\Http\Controllers\Api\Admin\AdminSubjectController::class, 'destroy']);

        // Gestão de Materiais
        Route::get('/materials', [\App\Http\Controllers\Api\Admin\AdminMaterialController::class, 'index']);
        Route::get('/materials/{id}', [\App\Http\Controllers\Api\Admin\AdminMaterialController::class, 'show']);
        Route::patch('/materials/{id}', [\App\Http\Controllers\Api\Admin\AdminMaterialController::class, 'update']);
        Route::delete('/materials/{id}', [\App\Http\Controllers\Api\Admin\AdminMaterialController::class, 'destroy']);
        Route::post('/materials/{id}/reingest', [\App\Http\Controllers\Api\Admin\AdminMaterialController::class, 'reingest']);

        // Operações RAG / IA
        Route::get('/rag/health', [\App\Http\Controllers\Api\Admin\AdminRagController::class, 'health']);
        Route::get('/rag/materials', [\App\Http\Controllers\Api\Admin\AdminRagController::class, 'materials']);
        Route::post('/rag/materials/{id}/reingest', [\App\Http\Controllers\Api\Admin\AdminRagController::class, 'reingest']);
        Route::post('/rag/test-query', [\App\Http\Controllers\Api\Admin\AdminRagController::class, 'testQuery']);

        // Logs de Auditoria
        Route::get('/audit-logs', [\App\Http\Controllers\Api\Admin\AdminAuditLogController::class, 'index']);

        // Estado do Sistema
        Route::get('/system/health', [\App\Http\Controllers\Api\Admin\AdminSystemController::class, 'health']);
    });

    Route::get('/novo', function () {
        return Inertia::render('TutsNew');
    });

    Route::get('/pdfs/{filename}', function (string $filename) {
        // Bloqueio preventivo de Path Traversal
        if (
            str_contains($filename, '..') ||
            str_contains($filename, '/') ||
            str_contains($filename, '\\') ||
            str_contains(urldecode($filename), '..') ||
            str_contains(urldecode($filename), '/') ||
            str_contains(urldecode($filename), '\\')
        ) {
            abort(400, 'Nome de ficheiro inválido.');
        }

        $safeFilename = basename($filename);

        // 1. Resolve o material a partir da base de dados (ID, UUID ou sufixo)
        $materialId = null;
        if (preg_match('/^(?:.*_)?(\d+)(?:-|\.pdf)/i', $safeFilename, $matches)) {
            $materialId = (int) $matches[1];
        }

        $material = null;
        if ($materialId) {
            $material = \App\Models\SubjectMaterial::with('subject')->find($materialId);
        }

        if (!$material) {
            if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $safeFilename, $matches)) {
                $uuid = $matches[1];
                $material = \App\Models\SubjectMaterial::with('subject')->where('path', 'like', '%' . $uuid . '%')->first();
            }
        }

        if (!$material) {
            $material = \App\Models\SubjectMaterial::with('subject')->where('path', 'like', '%' . $safeFilename)->first();
        }

        // 2. Se o material existe na base de dados, valida OBRIGATORIAMENTE as permissões antes de servir
        if ($material) {
            $user = auth()->user();
            if (!$user) {
                abort(401);
            }

            $subject = $material->subject;
            if (!$subject) {
                abort(404, 'UC associada não encontrada.');
            }

            // Permissão de Professor ou Admin
            $canTeach = $user->role === 'admin' || $subject->teachers()->where('users.id', $user->id)->exists();

            // Permissão de Aluno (inscrito na UC via curso)
            $canView = $canTeach || $user->courses()
                ->whereHas('subjects', function ($query) use ($subject) {
                    $query->where('subjects.id', $subject->id);
                })
                ->exists();

            abort_unless($canView, 403, 'Acesso negado a este material.');

            // 3. Tenta servir do local em cache se existir
            $possiblePaths = [
                storage_path('app/public/pdfs/' . $safeFilename),
                storage_path('app/pdfs/' . $safeFilename),
                public_path('storage/pdfs/' . $safeFilename),
            ];

            $localPath = collect($possiblePaths)->first(function ($possiblePath) {
                return file_exists($possiblePath) && is_file($possiblePath);
            });

            if ($localPath) {
                return response()->file($localPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . addslashes($material->name) . '"',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            // 4. Se não existe localmente, serve via Cloudflare R2
            $diskName = $material->disk ?: 'r2';
            $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
            if ($disk->exists($material->path)) {
                $contents = $disk->get($material->path);
                return response($contents, 200, [
                    'Content-Type' => $material->mime_type ?: 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . addslashes($material->name) . '"',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            abort(404, 'Ficheiro não encontrado no storage.');
        }

        // 5. Fallback restrito apenas para ficheiros de sistema explicitamente whitelisted
        $systemWhitelist = ['termos.pdf', 'ajuda.pdf', 'regulamento.pdf', 'faq.pdf'];
        $isSystemFile = in_array(strtolower($safeFilename), $systemWhitelist, true);

        if ($isSystemFile) {
            $systemPath = storage_path('app/system-pdfs/' . $safeFilename);
            if (file_exists($systemPath) && is_file($systemPath)) {
                return response()->file($systemPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        // Se parece material, mas não resolveu na base de dados, bloqueia qualquer local cache fallback
        abort(404, 'Ficheiro não encontrado ou acesso não autorizado.');
    })->where('filename', '.*')->name('pdfs.show');

    Route::get('/{any}', function () {
        return Inertia::render('TutsNew');
    })->where('any', 'home|chat|ucs|uc/.*|spaces|space/.*|calendar|planificacao.*|meus-planos|profile|dashboard|notificacoes|notifications|admin|admin/.*');
});

require __DIR__ . '/auth.php';
