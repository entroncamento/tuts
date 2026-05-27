<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            'page'     => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['per_page'] ?? 50;
        $page = $validated['page'] ?? 1;

        $cacheKey = "courses_with_subjects_page_{$page}_per_{$perPage}";

        $courses = Cache::remember($cacheKey, now()->addHours(24), function () use ($perPage) {
            return Course::select('id', 'name')
                ->with(['subjects' => function ($query) {
                    $query->select('subjects.id', 'subjects.name', 'subjects.url');
                }])
                ->orderBy('name', 'asc')
                ->paginate($perPage);
        });

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
                    $query->select('subjects.id', 'subjects.name', 'subjects.url')->orderBy('subjects.name');
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
        return [
            'id' => 'uc-' . $subject->id,
            'subject_id' => $subject->id,
            'name' => $subject->name,
            'url' => $subject->url,
            'teacher' => 'Docente a definir',
            'year' => 'Ano não definido',
            'academicYear' => '2025/2026',
            'cover' => $this->covers[$index % count($this->covers)],
            'shortCode' => $this->shortCode($subject->name),
            'description' => 'Unidade curricular de ' . $subject->name . '.',
        ];
    }

    private function shortCode(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];

        $letters = collect($words)
            ->filter(fn ($word) => mb_strlen($word) > 2)
            ->take(4)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : mb_strtoupper(mb_substr($name, 0, 3));
    }
}
