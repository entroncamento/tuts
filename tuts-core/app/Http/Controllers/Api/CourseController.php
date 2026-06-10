<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Http\Request;
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

        if ($user->role === 'professor') {
            $subjects = Subject::query()
                ->select('id', 'name', 'url')
                ->orderBy('name')
                ->get();
        } else {
            if (!$user->course_id) {
                return response()->json([
                    'status' => 'sucesso',
                    'subjects' => [],
                    'message' => 'O utilizador ainda não tem curso associado.',
                ]);
            }

            $course = Course::query()
                ->with(['subjects' => function ($query) {
                    $query
                        ->select('subjects.id', 'subjects.name', 'subjects.url')
                        ->orderBy('subjects.name');
                }])
                ->find($user->course_id);

            $subjects = $course?->subjects ?? collect();
        }

        return response()->json([
            'status' => 'sucesso',
            'subjects' => $subjects->values()->map(function (Subject $subject, int $index) {
                return $this->formatSubject($subject, $index);
            }),
        ]);
    }

    private function formatSubject(Subject $subject, int $index): array
    {
        $metadata = $this->metadataForSubject($subject);

        return [
            'id' => 'uc-' . $subject->id,
            'subject_id' => $subject->id,
            'name' => $subject->name,
            'url' => $metadata['url'] ?? $subject->url,
            'teacher' => $this->formatTeacher($metadata['teacher'] ?? null),
            'teacherNote' => $metadata['teacher_note'] ?? null,
            'year' => $metadata['year'] ?? 'Ano não definido',
            'semester' => $metadata['semester'] ?? 'Semestre não definido',
            'academicYear' => '2025/2026',
            'type' => $metadata['type'] ?? 'mandatory',
            'electiveGroup' => $metadata['elective_group'] ?? null,
            'cover' => $this->covers[$index % count($this->covers)],
            'shortCode' => $this->shortCode($subject->name),
            'description' => 'Unidade curricular de ' . $subject->name . '.',
        ];
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
