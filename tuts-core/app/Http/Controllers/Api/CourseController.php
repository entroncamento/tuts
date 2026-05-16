<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        // 1. Limitação de Input (Prevenção de DoS na Base de Dados)
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page'     => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['per_page'] ?? 50;
        $page = $validated['page'] ?? 1;

        // 2. Sistema de Cache Distribuída
        // Como a estrutura letiva não muda diariamente, guardamos isto em Cache durante 24 horas.
        // A chave de cache inclui a página e os itens por página para não misturar resultados.
        $cacheKey = "courses_with_subjects_page_{$page}_per_{$perPage}";

        $courses = Cache::remember($cacheKey, now()->addHours(24), function () use ($perPage) {
            return Course::select('id', 'name')
                ->with(['subjects' => function ($query) {
                    $query->select('id', 'course_id', 'name');
                }])
                ->orderBy('name', 'asc')
                ->paginate($perPage);
        });

        return response()->json($courses);
    }
}
