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

class AdminOverviewController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $totalUsers = User::count();
            $totalStudents = User::whereIn('role', ['student', 'aluno'])->count();
            $totalTeachers = User::whereIn('role', ['teacher', 'professor'])->count();
            $totalAdmins = User::whereIn('role', ['admin', 'super_admin'])->count();

            $totalCourses = Course::count();
            $totalSubjects = Subject::count();

            // Safe fallback count queries
            $totalSpaces = class_exists(StudySpace::class) ? StudySpace::count() : 0;
            $totalSubjectMaterials = class_exists(SubjectMaterial::class) ? SubjectMaterial::count() : 0;
            $totalPersonalMaterials = class_exists(PersonalMaterial::class) ? PersonalMaterial::count() : 0;
            $totalMaterials = $totalSubjectMaterials + $totalPersonalMaterials;

            $totalChats = class_exists(Chat::class) ? Chat::count() : 0;
            $totalMessages = class_exists(Message::class) ? Message::count() : 0;

            // Fetch recent entries
            $latestUsers = User::latest()->limit(5)->get(['id', 'name', 'email', 'role', 'created_at', 'blocked_at']);
            
            $latestMaterials = [];
            if (class_exists(SubjectMaterial::class)) {
                $latestMaterials = SubjectMaterial::latest()
                    ->limit(5)
                    ->get(['id', 'name', 'created_at', 'subject_id']);
            }

            $latestAuditLogs = [];
            if (class_exists(AuditLog::class)) {
                $latestAuditLogs = AuditLog::with('actor:id,name,email')
                    ->latest()
                    ->limit(5)
                    ->get();
            }

            return response()->json([
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
                'latest_users' => $latestUsers,
                'latest_materials' => $latestMaterials,
                'latest_audit_logs' => $latestAuditLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao obter dados de visão geral.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
