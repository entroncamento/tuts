<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        // Aceita o parâmetro ?per_page= no URL (default: 50)
        $perPage = $request->input('per_page', 50);

        // 🔥 Otimização extrema: 
        // 1. Trazemos apenas o 'id' e o 'name' do Curso.
        // 2. Na relação 'subjects', trazemos apenas 'id', 'course_id' (obrigatório para o Laravel fazer o match) e 'name'.
        $courses = Course::select('id', 'name')
            ->with(['subjects' => function ($query) {
                $query->select('id', 'course_id', 'name');
            }])
            ->orderBy('name', 'asc')
            ->paginate($perPage);

        return response()->json($courses);
    }
}
