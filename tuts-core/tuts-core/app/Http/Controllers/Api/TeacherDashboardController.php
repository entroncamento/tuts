<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\StudentMessageAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeacherDashboardController extends Controller
{
    public function insights(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'professor') {
            abort(403, 'Acesso negado. Apenas professores podem aceder a esta dashboard.');
        }

        $request->validate([
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'topic' => 'nullable|string|max:80',
        ]);

        $subjectId = $request->query('subject_id');
        $from = $request->query('from');
        $to = $request->query('to');
        $priorityFilter = $request->query('priority');
        $topicFilter = $request->query('topic');

        // Base query
        $query = StudentMessageAnalysis::query();

        $teacherSubjectIds = $user->teachingSubjects()->pluck('subjects.id')->toArray();

        // Apply filters
        if ($subjectId) {
            if (!in_array((int) $subjectId, $teacherSubjectIds, true)) {
                abort(403, 'Acesso negado a esta UC.');
            }
            $query->where('subject_id', $subjectId);
        } else {
            $query->whereIn('subject_id', $teacherSubjectIds);
        }

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        if ($priorityFilter) {
            $query->where('priority', $priorityFilter);
        }

        if ($topicFilter) {
            $query->where('topic', $topicFilter);
        }

        // Get matching analyses
        $analyses = $query->orderBy('created_at', 'desc')->get();

        // 1. Summary Metrics
        $totalQuestions = $analyses->count();
        $activeStudentsEstimate = $analyses->pluck('student_hash')->filter()->unique()->count();
        $highConfusionCount = $analyses->where('confusion_score', '>=', 0.7)->count();
        $highFrustrationCount = $analyses->where('frustration_score', '>=', 0.7)->count();
        $needsAttentionCount = $analyses->where('needs_teacher_attention', true)->count();

        $summary = [
            'total_questions' => $totalQuestions,
            'active_students_estimate' => $activeStudentsEstimate,
            'high_confusion_count' => $highConfusionCount,
            'high_frustration_count' => $highFrustrationCount,
            'needs_attention_count' => $needsAttentionCount,
        ];

        // 2. Subjects Breakdown
        $subjectsBreakdown = [];
        $analysesBySubject = $analyses->groupBy('subject_id');
        
        // Load names of all relevant subjects
        $subjects = Subject::whereIn('id', $analyses->pluck('subject_id')->unique())->get()->keyBy('id');

        foreach ($analysesBySubject as $subId => $items) {
            $subModel = $subjects->get($subId);
            $subName = $subModel ? $subModel->name : 'Geral';
            
            $subjectsBreakdown[] = [
                'id' => (int) $subId,
                'name' => $subName,
                'total_questions' => $items->count(),
                'confusion_score_avg' => round($items->avg('confusion_score') ?? 0, 2),
                'frustration_score_avg' => round($items->avg('frustration_score') ?? 0, 2),
                'priority' => $this->resolveMaxPriority($items),
            ];
        }

        // 3. Top Topics
        $topTopics = [];
        $analysesByTopic = $analyses->groupBy('topic');
        foreach ($analysesByTopic as $topicName => $items) {
            if (empty($topicName)) continue;
            $topTopics[] = [
                'topic' => $topicName,
                'count' => $items->count(),
                'confusion_score_avg' => round($items->avg('confusion_score') ?? 0, 2),
            ];
        }
        usort($topTopics, fn($a, $b) => $b['count'] <=> $a['count']);
        $topTopics = array_slice($topTopics, 0, 5);

        // 4. Recurring Questions (topics with multiple questions)
        $recurringQuestions = [];
        foreach ($topTopics as $topTopic) {
            if ($topTopic['count'] >= 2) {
                $topicItems = $analysesByTopic->get($topTopic['topic']);
                // Get the latest LLM summary or default
                $latestSummary = $topicItems->whereNotNull('llm_summary')->first()?->llm_summary ?? "Dúvidas recorrentes sobre o tópico {$topTopic['topic']}.";
                $recurringQuestions[] = [
                    'topic' => $topTopic['topic'],
                    'count' => $topTopic['count'],
                    'summary' => $latestSummary,
                ];
            }
        }

        // 5. Trends (grouped by date)
        $trends = [];
        $analysesByDate = $analyses->groupBy(fn($item) => $item->created_at->format('Y-m-d'));
        foreach ($analysesByDate as $date => $items) {
            $trends[] = [
                'date' => $date,
                'questions' => $items->count(),
                'high_confusion' => $items->where('confusion_score', '>=', 0.7)->count(),
                'high_frustration' => $items->where('frustration_score', '>=', 0.7)->count(),
            ];
        }
        usort($trends, fn($a, $b) => strcmp($a['date'], $b['date']));
        $trends = array_slice($trends, -7); // Last 7 days with activity

        // 6. Suggested Actions
        $suggestedActions = [];
        foreach ($recurringQuestions as $req) {
            $topicItems = $analysesByTopic->get($req['topic']);
            $subjectIdOfTopic = $topicItems->first()?->subject_id;
            
            // Resolve action from latest item or generate default
            $latestAction = $topicItems->whereNotNull('suggested_teacher_action')->first()?->suggested_teacher_action;
            if (!$latestAction) {
                $latestAction = "Rever conceitos de {$req['topic']} e disponibilizar exercícios práticos de reforço.";
            }

            $suggestedActions[] = [
                'subject_id' => $subjectIdOfTopic ? (int) $subjectIdOfTopic : null,
                'title' => "Rever {$req['topic']}",
                'reason' => "Vários alunos demonstram dificuldades persistentes (total: {$req['count']} dúvidas).",
                'priority' => $this->resolveMaxPriority($topicItems),
                'action' => $latestAction,
            ];
        }

        // 7. Recent Anonymous Examples
        $recentExamples = [];
        $recentAnalyses = $analyses->take(10);
        foreach ($recentAnalyses as $item) {
            $recentExamples[] = [
                'topic' => $item->topic ?? 'Geral',
                'excerpt' => $item->question_excerpt ? mb_substr($item->question_excerpt, 0, 120) . '...' : 'Sem excerto disponível.',
                'priority' => $item->priority ?? 'low',
                'created_at' => $item->created_at->toIso8601String(),
            ];
        }

        return response()->json([
            'summary' => $summary,
            'subjects' => $subjectsBreakdown,
            'top_topics' => $topTopics,
            'recurring_questions' => $recurringQuestions,
            'trends' => $trends,
            'suggested_actions' => $suggestedActions,
            'recent_anonymous_examples' => $recentExamples,
        ]);
    }

    private function resolveMaxPriority($items): string
    {
        $priorities = $items->pluck('priority')->unique();
        if ($priorities->contains('critical')) return 'critical';
        if ($priorities->contains('high')) return 'high';
        if ($priorities->contains('medium')) return 'medium';
        return 'low';
    }
}
