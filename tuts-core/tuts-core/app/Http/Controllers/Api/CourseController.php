<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    private array $covers = [
        'linear-gradient(135deg, #009957 0%, #43e97b 100%)',
        'linear-gradient(135deg, #1E1E1E 0%, #656966 100%)',
        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    ];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['per_page'] ?? 50;

        $courses = Course::select('id', 'name')
            ->with(['subjects' => function ($query) {
                $query->select('subjects.id', 'subjects.name', 'subjects.url');
            }])
            ->orderBy('name', 'asc')
            ->paginate($perPage);

        return response()->json($courses);
    }

    public function mySubjects(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Utilizador não autenticado.');
        }

        if ($this->isTeacherRole((string) $user->role)) {
            $subjects = $user->teachingSubjects()
                ->with(['creator', 'teachers'])
                ->withCount(['students', 'sections', 'materials'])
                ->orderBy('subjects.name')
                ->get();

            Log::info('[TUTS][Subjects] using subject_user memberships for teacher', [
                'user_id' => $user->id,
                'count' => $subjects->count(),
            ]);
        } else {
            $subjects = $user->studentSubjects()
                ->with(['creator', 'teachers'])
                ->withCount(['students', 'sections', 'materials'])
                ->orderBy('subjects.name')
                ->get();

            Log::info('[TUTS][Subjects] using subject_user memberships for student', [
                'user_id' => $user->id,
                'count' => $subjects->count(),
            ]);
        }

        return response()->json([
            'status' => 'sucesso',
            'subjects' => $subjects->values()->map(function (Subject $subject, int $index) {
                return $this->formatSubject($subject, $index);
            }),
        ]);
    }

    private function isTeacherRole(string $role): bool
    {
        return in_array($role, ['professor', 'teacher'], true);
    }

    private function formatSubject(Subject $subject, int $index): array
    {
        $acronym = $subject->acronym ?: $this->shortCode($subject->name);
        $code = $subject->enrollment_code ?: $this->fallbackEnrollmentCode($subject);
        $membershipRole = $this->membershipRoleFor($subject);
        $enrolledStudentsCount = (int) ($subject->students_count ?? 0);

        return [
            'id' => 'uc-' . $subject->id,
            'subject_id' => $subject->id,
            'name' => $subject->name,
            'url' => $subject->url,
            'teacher' => $this->teacherLabelFor($subject),
            'teacherNote' => null,
            'year' => $subject->year ?? 'Ano não definido',
            'semester' => $subject->semester ?? 'Semestre não definido',
            'academicYear' => $subject->academic_year ?? '2025/2026',
            'type' => 'mandatory',
            'electiveGroup' => null,
            'cover' => $this->covers[$index % count($this->covers)],
            'personal_cover' => $this->personalCoverFor($subject),
            'can_manage_personal_cover' => $this->canManagePersonalCover($subject),
            'shortCode' => $acronym,
            'description' => 'Unidade curricular de ' . $subject->name . '.',
            'acronym' => $acronym,
            'code' => $code,
            'enrollment_code' => $code,
            'enrollmentCode' => $code,
            'created_by' => $subject->created_by,
            'membership_role' => $membershipRole,
            'is_teacher' => $membershipRole === 'teacher',
            'enrolled_students_count' => $enrolledStudentsCount,
            'students_count' => $enrolledStudentsCount,
            'sections_count' => $subject->sections_count ?? 0,
            'materials_count' => $subject->materials_count ?? 0,
            'color' => $subject->color,
            'status' => $subject->status ?? 'active',
        ];
    }

    private function membershipRoleFor(Subject $subject): ?string
    {
        $role = $subject->pivot?->role ?? null;

        return is_string($role) && $role !== '' ? $role : null;
    }

    private function fallbackEnrollmentCode(Subject $subject): string
    {
        return 'UC' . str_pad((string) $subject->id, 5, '0', STR_PAD_LEFT);
    }

    private function canManagePersonalCover(Subject $subject): bool
    {
        $userId = auth()->id();

        if (! $userId) {
            return false;
        }

        return \DB::table('subject_user')
            ->where('subject_id', $subject->id)
            ->where('user_id', $userId)
            ->where('role', 'student')
            ->where('status', 'active')
            ->exists();
    }

    private function personalCoverFor(Subject $subject): ?array
    {
        if (! $this->canManagePersonalCover($subject)) {
            return null;
        }

        $preference = \App\Models\UserSubjectPreference::query()
            ->where('user_id', auth()->id())
            ->where('subject_id', $subject->id)
            ->first();

        if (! $preference || ! $preference->cover_external_id) {
            return null;
        }

        return [
            'provider' => $preference->cover_provider,
            'external_id' => $preference->cover_external_id,
            'image_url' => $preference->cover_image_url,
            'thumbnail_url' => $preference->cover_thumbnail_url,
            'color' => $preference->cover_color,
            'blur_hash' => $preference->cover_blur_hash,
            'alt' => $preference->cover_alt,
            'photographer_name' => $preference->cover_photographer_name,
            'photographer_url' => $preference->cover_photographer_url,
            'source_url' => $preference->cover_source_url,
        ];
    }

    private function teacherLabelFor(Subject $subject): string
    {
        if ($subject->relationLoaded('teachers') && $subject->teachers->isNotEmpty()) {
            return $this->formatTeacher($subject->teachers->pluck('name')->implode(', '));
        }

        if ($subject->relationLoaded('creator') && $subject->creator) {
            return $this->formatTeacher($subject->creator->name);
        }

        return 'Docente a definir';
    }

    private function formatTeacher(?string $teacher): string
    {
        $teacher = trim((string) $teacher);

        if ($teacher === '') {
            return 'Docente a definir';
        }

        if (Str::startsWith($teacher, ['Prof.', 'Profa.', 'Professor', 'Professora'])) {
            return $teacher;
        }

        return 'Prof. ' . $teacher;
    }

    private function shortCode(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];

        $letters = collect($words)
            ->filter(fn($word) => mb_strlen($word) > 2)
            ->take(4)
            ->map(fn($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : mb_strtoupper(mb_substr($name, 0, 3));
    }
}
