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

            if ($subjects->isNotEmpty()) {
                Log::info('[TUTS][Subjects] using subject_user memberships', [
                    'role' => 'teacher',
                    'count' => $subjects->count(),
                ]);
            } else {
                Log::info('[TUTS][Subjects] using teacher fallback');

                $subjects = $this->legacyAllSubjects();
            }
        } else {
            $subjects = $user->studentSubjects()
                ->with(['creator', 'teachers'])
                ->withCount(['students', 'sections', 'materials'])
                ->orderBy('subjects.name')
                ->get();

            if ($subjects->isNotEmpty()) {
                Log::info('[TUTS][Subjects] using subject_user memberships', [
                    'role' => 'student',
                    'count' => $subjects->count(),
                ]);
            } else {
                Log::info('[TUTS][Subjects] using course curriculum fallback');

                if (!$user->course_id) {
                    return response()->json([
                        'status' => 'sucesso',
                        'subjects' => [],
                        'message' => 'O utilizador ainda não tem curso associado.',
                    ]);
                }

                $subjects = $this->legacyCourseSubjects((int) $user->course_id);
            }
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
        $metadata = $this->metadataForSubject($subject);
        $acronym = $subject->acronym ?: $this->shortCode($subject->name);
        $code = $subject->enrollment_code ?: $this->fallbackEnrollmentCode($subject);
        $membershipRole = $this->membershipRoleFor($subject);
        $enrolledStudentsCount = (int) ($subject->students_count ?? 0);

        return [
            'id' => 'uc-' . $subject->id,
            'subject_id' => $subject->id,
            'name' => $subject->name,
            'url' => $metadata['url'] ?? $subject->url,
            'teacher' => $this->teacherLabelFor($subject, $metadata['teacher'] ?? null),
            'teacherNote' => $metadata['teacher_note'] ?? null,
            'year' => $subject->year ?? $metadata['year'] ?? 'Ano não definido',
            'semester' => $subject->semester ?? $metadata['semester'] ?? 'Semestre não definido',
            'academicYear' => $subject->academic_year ?? '2025/2026',
            'type' => $metadata['type'] ?? 'mandatory',
            'electiveGroup' => $metadata['elective_group'] ?? null,
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

    private function membershipRoleFor(Subject $subject): ?string
    {
        $role = $subject->pivot?->role ?? null;

        return is_string($role) && $role !== '' ? $role : null;
    }

    private function fallbackEnrollmentCode(Subject $subject): string
    {
        return 'UC' . str_pad((string) $subject->id, 5, '0', STR_PAD_LEFT);
    }

    private function teacherLabelFor(Subject $subject, ?string $metadataTeacher): string
    {
        if ($subject->relationLoaded('teachers') && $subject->teachers->isNotEmpty()) {
            return $this->formatTeacher($subject->teachers->pluck('name')->implode(', '));
        }

        if ($subject->relationLoaded('creator') && $subject->creator) {
            return $this->formatTeacher($subject->creator->name);
        }

        return $this->formatTeacher($metadataTeacher);
    }

    private function legacyAllSubjects()
    {
        return Subject::query()
            ->with(['creator', 'teachers'])
            ->withCount(['students', 'sections', 'materials'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function legacyCourseSubjects(int $courseId)
    {
        $course = Course::query()
            ->with(['subjects' => function ($query) {
                $query
                    ->with(['creator', 'teachers'])
                    ->withCount(['students', 'sections', 'materials'])
                    ->where('subjects.status', 'active')
                    ->orderBy('subjects.name');
            }])
            ->find($courseId);

        return $course?->subjects ?? collect();
    }

    private function metadataForSubject(Subject $subject): array
    {
        $subjects = $this->subjectMetadata();

        $subjectName = $this->normalizeName($subject->name);
        $subjectUrl = $this->normalizeUrl((string) $subject->url);
        $subjectUaId = $this->extractUaUcId((string) $subject->url);

        foreach ($subjects as $item) {
            $itemName = $this->normalizeName($item['name'] ?? '');
            $itemUrl = $this->normalizeUrl($item['url'] ?? '');
            $itemUaId = $this->extractUaUcId($item['url'] ?? '');

            if ($itemName !== '' && $itemName === $subjectName) {
                return $item;
            }

            if ($subjectUrl !== '' && $itemUrl !== '' && $subjectUrl === $itemUrl) {
                return $item;
            }

            if ($subjectUaId !== null && $itemUaId !== null && $subjectUaId === $itemUaId) {
                return $item;
            }
        }

        return [];
    }

    private function subjectMetadata(): array
    {
        $path = resource_path('js/cadeiras_mtc.json');

        if (!file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return [];
        }

        $items = $this->flattenMetadataItems($decoded);

        return collect($items)
            ->map(fn(array $item) => $this->normalizeMetadataItem($item))
            ->filter(fn(array $item) => !empty($item['name']))
            ->values()
            ->all();
    }

    private function flattenMetadataItems(array $data): array
    {
        $items = [];

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            if (
                array_key_exists('name', $value) ||
                array_key_exists('nome_uc', $value) ||
                array_key_exists('uc', $value)
            ) {
                $items[] = $value;
                continue;
            }

            $items = array_merge($items, $this->flattenMetadataItems($value));
        }

        return $items;
    }

    private function normalizeMetadataItem(array $item): array
    {
        $name = $item['name']
            ?? $item['nome_uc']
            ?? $item['uc']
            ?? null;

        $url = $item['url']
            ?? $item['url_uc']
            ?? null;

        $teacher = $item['teacher']
            ?? $item['docente']
            ?? $item['professor']
            ?? null;

        $teacherNote = $item['teacher_note']
            ?? $item['teacherNote']
            ?? $item['nota_docente']
            ?? null;

        $year = $item['year']
            ?? $item['ano']
            ?? null;

        $semester = $item['semester']
            ?? $item['semestre']
            ?? null;

        $type = $item['type']
            ?? $item['tipo']
            ?? 'mandatory';

        $electiveGroup = $item['elective_group']
            ?? $item['electiveGroup']
            ?? $item['grupo_opcao']
            ?? $item['grupo_opção']
            ?? null;

        return [
            'name' => is_string($name) ? trim($name) : null,
            'url' => is_string($url) ? trim($url) : null,
            'teacher' => is_string($teacher) ? trim($teacher) : null,
            'teacher_note' => is_string($teacherNote) ? trim($teacherNote) : null,
            'year' => is_string($year) ? trim($year) : null,
            'semester' => is_string($semester) ? trim($semester) : null,
            'type' => $this->normalizeType((string) $type),
            'elective_group' => is_string($electiveGroup) ? trim($electiveGroup) : null,
        ];
    }

    private function normalizeType(string $type): string
    {
        $type = $this->normalizeName($type);

        if (in_array($type, ['opcional', 'opcao', 'opcao livre', 'elective', 'optional'], true)) {
            return 'elective';
        }

        return 'mandatory';
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

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        $replacements = [
            'á' => 'a',
            'à' => 'a',
            'ã' => 'a',
            'â' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
            '&' => 'e',
            'projecto' => 'projeto',
            'portfólio' => 'portfolio',
        ];

        $name = strtr($name, $replacements);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $url = preg_replace('/\?.*$/', '', $url) ?? $url;
        $url = rtrim($url, '/');

        return mb_strtolower($url);
    }

    private function extractUaUcId(string $url): ?string
    {
        if (preg_match('/\/uc\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
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
