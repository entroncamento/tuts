<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubjectController extends Controller
{
    private array $covers = [
        'linear-gradient(135deg, #009957 0%, #43e97b 100%)',
        'linear-gradient(135deg, #1E1E1E 0%, #656966 100%)',
        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    ];

    private array $stopwords = [
        'de',
        'da',
        'do',
        'das',
        'dos',
        'e',
        'a',
        'o',
        'as',
        'os',
        'ao',
    ];

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);

        if (!$this->isProfessor($user)) {
            $this->logForbidden('create', $user);
            abort(403, 'Apenas professores podem criar UCs.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'degree' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:80',
            'year' => 'nullable|string|max:40',
            'semester' => 'nullable|string|max:40',
            'academic_year' => 'nullable|string|max:20',
            'color' => ['nullable', 'string', 'max:40', 'regex:/^#?[A-Za-z0-9(),.%\s-]+$/'],
        ]);

        Log::info('[TUTS][Subjects] creating professor UC', [
            'user_id' => $user->id,
        ]);

        $subject = DB::transaction(function () use ($validated, $user) {
            $subject = Subject::create([
                'name' => trim($validated['name']),
                'acronym' => $this->generateAcronym($validated['name']),
                'enrollment_code' => $this->generateEnrollmentCode(),
                'created_by' => $user->id,
                'degree' => $validated['degree'] ?? null,
                'level' => $validated['level'] ?? null,
                'year' => $validated['year'] ?? null,
                'semester' => $validated['semester'] ?? null,
                'academic_year' => $validated['academic_year'] ?? null,
                'color' => $validated['color'] ?? null,
                'status' => 'active',
                'source' => 'app',
            ]);

            $this->upsertMembership($subject, $user, 'teacher', 'creator');

            return $subject;
        });

        Log::info('[TUTS][Subjects] created professor UC', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'subject' => $this->formatSubject($this->loadSubjectForResponse($subject), 0, 'teacher'),
        ], 201);
    }

    public function show(Request $request, string $subject): JsonResponse
    {
        $user = $this->user($request);
        $resolvedSubject = $this->resolveSubject($subject);

        if (!$this->canViewSubject($user, $resolvedSubject)) {
            $this->logForbidden('view', $user, $resolvedSubject);
            abort(403, 'Sem acesso a esta UC.');
        }

        return response()->json([
            'status' => 'sucesso',
            'subject' => $this->formatSubject(
                $this->loadSubjectForResponse($resolvedSubject),
                0,
                $this->membershipRole($user, $resolvedSubject)
            ),
        ]);
    }

    public function update(Request $request, string $subject): JsonResponse
    {
        $user = $this->user($request);
        $resolvedSubject = $this->resolveSubject($subject);

        if (!$this->canTeachSubject($user, $resolvedSubject)) {
            $this->logForbidden('update', $user, $resolvedSubject);
            abort(403, 'Sem permissao para editar esta UC.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'degree' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:80',
            'year' => 'nullable|string|max:40',
            'semester' => 'nullable|string|max:40',
            'academic_year' => 'nullable|string|max:20',
            'color' => ['nullable', 'string', 'max:40', 'regex:/^#?[A-Za-z0-9(),.%\s-]+$/'],
            'status' => 'sometimes|required|string|in:active,inactive,archived',
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
            $validated['acronym'] = $this->generateAcronym($validated['name']);
        }

        $resolvedSubject->update($validated);

        return response()->json([
            'status' => 'sucesso',
            'subject' => $this->formatSubject(
                $this->loadSubjectForResponse($resolvedSubject->fresh()),
                0,
                'teacher'
            ),
        ]);
    }

    public function destroy(Request $request, string $subject): JsonResponse
    {
        $user = $this->user($request);
        $resolvedSubject = $this->resolveSubject($subject);
        $membershipCategory = $this->membershipCategory($user, $resolvedSubject);

        Log::info('[TUTS][Subjects] delete request received', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'membership_category' => $membershipCategory,
        ]);

        if (!$this->canTeachSubject($user, $resolvedSubject)) {
            $this->logForbidden('delete', $user, $resolvedSubject);
            abort(403, 'Sem permissao para apagar esta UC.');
        }

        Log::info('[TUTS][Subjects] delete authorization passed', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'membership_category' => $membershipCategory,
        ]);

        try {
            DB::transaction(function () use ($resolvedSubject) {
                $resolvedSubject->forceFill([
                    'status' => 'archived',
                ])->save();

                $resolvedSubject->delete();
            });
        } catch (\Throwable $exception) {
            Log::warning('[TUTS][Subjects] delete failed', [
                'user_id' => $user->id,
                'subject_id' => $resolvedSubject->id,
                'membership_category' => $membershipCategory,
                'error_category' => $exception::class,
            ]);

            return response()->json([
                'status' => 'erro',
                'message' => 'Nao foi possivel apagar a UC.',
            ], 500);
        }

        Log::info('[TUTS][Subjects] subject soft deleted', [
            'user_id' => $user->id,
            'subject_id' => $resolvedSubject->id,
            'membership_category' => $membershipCategory,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'message' => 'UC apagada com sucesso.',
        ]);
    }

    public function teachingSubjects(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $subjects = $user->teachingSubjects()
            ->with(['creator', 'teachers'])
            ->withCount(['students', 'sections', 'materials'])
            ->orderBy('subjects.name')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'subjects' => $subjects
                ->values()
                ->map(fn(Subject $subject, int $index) => $this->formatSubject($subject, $index, 'teacher')),
        ]);
    }

    public function studentSubjects(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $subjects = $user->studentSubjects()
            ->with(['creator', 'teachers'])
            ->withCount(['students', 'sections', 'materials'])
            ->orderBy('subjects.name')
            ->get();

        return response()->json([
            'status' => 'sucesso',
            'subjects' => $subjects
                ->values()
                ->map(fn(Subject $subject, int $index) => $this->formatSubject($subject, $index, 'student')),
        ]);
    }

    public function students(Request $request, string $subject): JsonResponse
    {
        $user = $this->user($request);
        $resolvedSubject = $this->resolveSubject($subject);

        if (!$this->canTeachSubject($user, $resolvedSubject)) {
            $this->logForbidden('students', $user, $resolvedSubject);
            abort(403, 'Sem permissao para listar alunos desta UC.');
        }

        $students = $resolvedSubject->students()
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(fn(User $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'joined_at' => $student->pivot?->created_at?->toISOString(),
                'status' => $student->pivot?->status,
            ]);

        return response()->json([
            'status' => 'sucesso',
            'students' => $students,
        ]);
    }

    public function join(Request $request): JsonResponse
    {
        $user = $this->user($request);

        if ($this->isProfessor($user)) {
            $this->logForbidden('join', $user);
            abort(403, 'Professores nao podem aderir a UCs como alunos por codigo.');
        }

        $validated = $request->validate([
            'code' => 'nullable|string|max:32',
            'enrollment_code' => 'nullable|string|max:32',
        ]);

        $code = strtoupper(trim((string) ($validated['code'] ?? $validated['enrollment_code'] ?? '')));

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => ['Indica o codigo da UC.'],
            ]);
        }

        Log::info('[TUTS][Subjects] joining UC by code', [
            'user_id' => $user->id,
            'code_prefix' => substr($code, 0, 2),
        ]);

        $subject = Subject::query()
            ->where('status', 'active')
            ->where('enrollment_code', $code)
            ->first();

        if (!$subject) {
            throw ValidationException::withMessages([
                'code' => ['Codigo de UC invalido ou expirado.'],
            ]);
        }

        if ($this->hasActiveMembership($user, $subject, 'student')) {
            Log::info('[TUTS][Subjects] user already joined UC', [
                'user_id' => $user->id,
                'subject_id' => $subject->id,
            ]);

            return response()->json([
                'status' => 'sucesso',
                'already_joined' => true,
                'message' => 'Ja estas inscrito nesta UC.',
                'subject' => $this->formatSubject($this->loadSubjectForResponse($subject), 0, 'student'),
            ]);
        }

        DB::transaction(function () use ($subject, $user) {
            $this->upsertMembership($subject, $user, 'student', 'join_code');
        });

        Log::info('[TUTS][Subjects] joined UC by code', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'already_joined' => false,
            'message' => 'Inscricao na UC concluida.',
            'subject' => $this->formatSubject($this->loadSubjectForResponse($subject), 0, 'student'),
        ], 201);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Utilizador nao autenticado.');
        }

        return $user;
    }

    private function isProfessor(User $user): bool
    {
        return in_array($user->role, ['professor', 'teacher'], true);
    }

    private function isStudent(User $user): bool
    {
        return in_array($user->role, ['aluno', 'student'], true);
    }

    private function canTeachSubject(User $user, Subject $subject): bool
    {
        if ((int) $subject->created_by === (int) $user->id) {
            return true;
        }

        return $this->hasActiveMembership($user, $subject, 'teacher');
    }

    private function canViewSubject(User $user, Subject $subject): bool
    {
        if ($this->canTeachSubject($user, $subject)) {
            return true;
        }

        if ($this->hasActiveMembership($user, $subject, 'student')) {
            return true;
        }

        if ($this->isStudent($user) && $user->course_id) {
            return $subject->courses()
                ->where('courses.id', $user->course_id)
                ->exists();
        }

        if ($this->isProfessor($user) && $user->teachingSubjects()->count() === 0) {
            return true;
        }

        return false;
    }

    private function hasActiveMembership(User $user, Subject $subject, string $role): bool
    {
        return DB::table('subject_user')
            ->where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->where('status', 'active')
            ->exists();
    }

    private function membershipRole(User $user, Subject $subject): ?string
    {
        $membership = DB::table('subject_user')
            ->where('subject_id', $subject->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByRaw("case when role = 'teacher' then 0 else 1 end")
            ->first();

        return is_string($membership?->role ?? null) ? $membership->role : null;
    }

    private function membershipCategory(User $user, Subject $subject): string
    {
        if ((int) $subject->created_by === (int) $user->id) {
            return 'creator';
        }

        $role = $this->membershipRole($user, $subject);

        return $role ? 'active_' . $role : 'none';
    }

    private function upsertMembership(Subject $subject, User $user, string $role, string $source): void
    {
        DB::table('subject_user')->updateOrInsert(
            [
                'subject_id' => $subject->id,
                'user_id' => $user->id,
                'role' => $role,
            ],
            [
                'status' => 'active',
                'source' => $source,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function resolveSubject(string $subject): Subject
    {
        $subjectId = Str::startsWith($subject, 'uc-') ? Str::after($subject, 'uc-') : $subject;

        abort_unless(ctype_digit((string) $subjectId), 404);

        return Subject::query()->findOrFail($subjectId);
    }

    private function loadSubjectForResponse(Subject $subject): Subject
    {
        return $subject
            ->loadMissing(['creator', 'teachers'])
            ->loadCount(['students', 'sections', 'materials']);
    }

    private function formatSubject(Subject $subject, int $index, ?string $membershipRole = null): array
    {
        $acronym = $subject->acronym ?: $this->generateAcronym($subject->name);
        $code = $subject->enrollment_code ?: $this->fallbackEnrollmentCode($subject);
        $enrolledStudentsCount = (int) ($subject->students_count ?? 0);

        return [
            'id' => 'uc-' . $subject->id,
            'subject_id' => $subject->id,
            'name' => $subject->name,
            'url' => $subject->url,
            'teacher' => $this->teacherLabelFor($subject),
            'teacherNote' => null,
            'year' => $subject->year ?? 'Ano nao definido',
            'semester' => $subject->semester ?? 'Semestre nao definido',
            'academicYear' => $subject->academic_year ?? '2025/2026',
            'type' => 'mandatory',
            'electiveGroup' => null,
            'cover' => $this->covers[$index % count($this->covers)],
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

    private function generateAcronym(string $name): string
    {
        $ascii = Str::ascii(mb_strtolower($name));
        $words = preg_split('/\s+/', trim($ascii)) ?: [];

        $letters = collect($words)
            ->map(fn(string $word) => preg_replace('/[^a-z0-9]/', '', $word) ?: '')
            ->filter(fn(string $word) => $word !== '' && !in_array($word, $this->stopwords, true))
            ->map(fn(string $word) => strtoupper(substr($word, 0, 1)))
            ->implode('');

        if ($letters !== '') {
            return substr($letters, 0, 8);
        }

        $fallback = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)) ?: 'UC', 0, 4));

        return strlen($fallback) >= 2 ? $fallback : str_pad($fallback, 2, 'X');
    }

    private function generateEnrollmentCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = '';

            for ($i = 0; $i < 7; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            if (!Subject::query()->where('enrollment_code', $code)->exists()) {
                return $code;
            }
        }

        throw ValidationException::withMessages([
            'enrollment_code' => ['Nao foi possivel gerar um codigo unico para a UC. Tenta novamente.'],
        ]);
    }

    private function fallbackEnrollmentCode(Subject $subject): string
    {
        return 'UC' . str_pad((string) $subject->id, 5, '0', STR_PAD_LEFT);
    }

    private function logForbidden(string $action, User $user, ?Subject $subject = null): void
    {
        Log::warning('[TUTS][Subjects] forbidden UC action', [
            'action' => $action,
            'user_id' => $user->id,
            'subject_id' => $subject?->id,
        ]);
    }
}
