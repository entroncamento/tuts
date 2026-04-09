<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);

        $courses = Course::select('id', 'name')
            ->with(['subjects' => function ($query) {
                $query->select('id', 'course_id', 'name');
            }])
            ->orderBy('name', 'asc')
            ->paginate($perPage);

        return response()->json($courses);
    }
}
