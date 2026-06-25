<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Course;
use App\Models\Subject;
use App\Models\StudySpace;
use App\Models\SubjectMaterial;
use App\Models\PersonalMaterial;
use App\Models\Chat;
use App\Models\Message;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AdminOverviewController extends Controller
{
    public function index(): JsonResponse
    {
        $warnings = [];

        // 1. KPIs
        $totalUsers = 0;
        $totalStudents = 0;
        $totalTeachers = 0;
        $totalAdmins = 0;
        try {
            if (Schema::hasTable('users')) {
                $totalUsers = User::count();
                if (Schema::hasColumn('users', 'role')) {
                    $totalStudents = User::whereIn('role', ['student', 'aluno'])->count();
                    $totalTeachers = User::whereIn('role', ['teacher', 'professor'])->count();
                    $totalAdmins = User::whereIn('role', ['admin', 'super_admin'])->count();
                }
            } else {
                $warnings[] = 'Tabela de utilizadores indisponível.';
            }
        } catch (\Throwable $e) {
            Log::error('Overview KPI users failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao consultar utilizadores.';
        }

        $totalCourses = 0;
        try {
            if (Schema::hasTable('courses')) {
                $totalCourses = Course::count();
            } else {
                $warnings[] = 'Tabela de cursos indisponível.';
            }
        } catch (\Throwable $e) {
            Log::error('Overview KPI courses failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao consultar cursos.';
        }

        $totalSubjects = 0;
        try {
            if (Schema::hasTable('subjects')) {
                $totalSubjects = Subject::count();
            } else {
                $warnings[] = 'Tabela de cadeiras indisponível.';
            }
        } catch (\Throwable $e) {
            Log::error('Overview KPI subjects failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao consultar cadeiras.';
        }

        $totalSpaces = 0;
        try {
            if (class_exists(StudySpace::class) && Schema::hasTable('study_spaces')) {
                $totalSpaces = StudySpace::count();
            }
        } catch (\Throwable $e) {
            Log::error('Overview KPI study_spaces failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao consultar espaços de estudo.';
        }

        $totalMaterials = 0;
        try {
            $totalSubjectMaterials = 0;
            $totalPersonalMaterials = 0;
            if (class_exists(SubjectMaterial::class) && Schema::hasTable('subject_materials')) {
                $totalSubjectMaterials = SubjectMaterial::count();
            }
            if (class_exists(PersonalMaterial::class) && Schema::hasTable('personal_materials')) {
                $totalPersonalMaterials = PersonalMaterial::count();
            }
            $totalMaterials = $totalSubjectMaterials + $totalPersonalMaterials;
        } catch (\Throwable $e) {
            Log::error('Overview KPI materials failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao consultar materiais.';
        }

        $totalChats = 0;
        $totalMessages = 0;
        try {
            if (class_exists(Chat::class) && Schema::hasTable('chats')) {
                $totalChats = Chat::count();
            }
            if (class_exists(Message::class) && Schema::hasTable('messages')) {
                $totalMessages = Message::count();
            }
        } catch (\Throwable $e) {
            Log::error('Overview KPI chats/messages failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao consultar conversas ou mensagens.';
        }

        $totalAuditLogs = 0;
        try {
            if (class_exists(AuditLog::class) && Schema::hasTable('audit_logs')) {
                $totalAuditLogs = AuditLog::count();
            }
        } catch (\Throwable $e) {
            Log::error('Overview KPI audit_logs failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao consultar logs de auditoria.';
        }

        // 2. Recent Data
        $recentUsers = [];
        try {
            if (Schema::hasTable('users')) {
                $userColumns = ['id', 'name', 'email'];
                if (Schema::hasColumn('users', 'role')) {
                    $userColumns[] = 'role';
                }
                if (Schema::hasColumn('users', 'created_at')) {
                    $userColumns[] = 'created_at';
                }
                if (Schema::hasColumn('users', 'blocked_at')) {
                    $userColumns[] = 'blocked_at';
                }
                $query = User::query();
                if (Schema::hasColumn('users', 'created_at')) {
                    $query->latest();
                }
                $recentUsers = $query->limit(5)->get($userColumns);
            }
        } catch (\Throwable $e) {
            Log::error('Overview recent users query failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao obter últimos utilizadores.';
        }

        $recentMaterials = [];
        try {
            if (class_exists(SubjectMaterial::class) && Schema::hasTable('subject_materials')) {
                $materialColumns = ['id', 'name'];
                if (Schema::hasColumn('subject_materials', 'created_at')) {
                    $materialColumns[] = 'created_at';
                }
                if (Schema::hasColumn('subject_materials', 'subject_id')) {
                    $materialColumns[] = 'subject_id';
                }
                $query = SubjectMaterial::query();
                if (Schema::hasColumn('subject_materials', 'created_at')) {
                    $query->latest();
                }
                $recentMaterials = $query->limit(5)->get($materialColumns);
            }
        } catch (\Throwable $e) {
            Log::error('Overview recent materials query failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao obter materiais recentes.';
        }

        $recentAuditLogs = [];
        try {
            if (class_exists(AuditLog::class) && Schema::hasTable('audit_logs')) {
                $query = AuditLog::query();
                if (Schema::hasColumn('audit_logs', 'created_at')) {
                    $query->latest();
                }
                if (Schema::hasColumn('audit_logs', 'actor_id') && Schema::hasTable('users')) {
                    $query->with('actor:id,name,email');
                }
                $recentAuditLogs = $query->limit(5)->get();
            }
        } catch (\Throwable $e) {
            Log::error('Overview recent audit logs query failed: ' . $e->getMessage());
            $warnings[] = 'Erro ao obter logs de auditoria recentes.';
        }

        // 3. RAG Status
        $ragStatus = "unknown";
        try {
            $url = config('services.python.url_health', 'http://rag:8001/health');
            $response = Http::timeout(1)->get($url);
            if ($response->ok()) {
                $ragStatus = "ok";
            } else {
                $ragStatus = "degraded";
            }
        } catch (\Throwable $e) {
            try {
                $extUrl = rtrim((string) config('services.rag.base_url', 'https://tutsai-tuts-rag-service.hf.space'), '/') . '/health';
                $response = Http::timeout(1)->get($extUrl);
                if ($response->ok()) {
                    $ragStatus = "ok";
                } else {
                    $ragStatus = "degraded";
                }
            } catch (\Throwable $e2) {
                $ragStatus = "degraded";
                Log::warning('Overview RAG health check failed: ' . $e2->getMessage());
            }
        }

        // 4. System Status
        $systemStatus = "ok";
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $systemStatus = "degraded";
            $warnings[] = "A base de dados principal está inacessível ou com erros.";
            Log::error('Overview DB connection error: ' . $e->getMessage());
        }

        return response()->json([
            'kpis' => [
                'total_users' => $totalUsers,
                'students' => $totalStudents,
                'teachers' => $totalTeachers,
                'admins' => $totalAdmins,
                'courses' => $totalCourses,
                'subjects' => $totalSubjects,
                'materials' => $totalMaterials,
                'spaces' => $totalSpaces,
                'chats' => $totalChats,
                'messages' => $totalMessages,
                'audit_logs' => $totalAuditLogs,
            ],
            // Keep stats for backwards compatibility
            'stats' => [
                'total_users' => $totalUsers,
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'total_admins' => $totalAdmins,
                'total_courses' => $totalCourses,
                'total_subjects' => $totalSubjects,
                'total_spaces' => $totalSpaces,
                'total_materials' => $totalMaterials,
                'total_chats' => $totalChats,
                'total_messages' => $totalMessages,
            ],
            'recent_users' => $recentUsers,
            'latest_users' => $recentUsers,
            'recent_materials' => $recentMaterials,
            'latest_materials' => $recentMaterials,
            'recent_audit_logs' => $recentAuditLogs,
            'latest_audit_logs' => $recentAuditLogs,
            'rag' => [
                'status' => $ragStatus
            ],
            'system' => [
                'status' => $systemStatus
            ],
            'warnings' => $warnings
        ]);
    }
}

