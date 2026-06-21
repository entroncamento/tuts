<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Message;
use App\Models\StudentMessageAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeacherInsightAnalysisService
{
    public function analyze(Message $message, Chat $chat, ?string $studentHash): StudentMessageAnalysis
    {
        $subject = $chat->subject;
        $subjectName = $subject ? $subject->name : 'Geral';
        $subjectId = $chat->subject_id;

        $urlPython = config('services.python.url', 'http://127.0.0.1:8001');
        // Strip trailing slash if present, and add the path
        $urlPython = rtrim($urlPython, '/') . '/api/teacher-insights/analyze-message';
        $internalToken = trim((string) config('services.python.internal_token', ''));

        $payload = [
            'message' => $message->content,
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'context' => [
                'chat_id' => $chat->id,
                'language' => 'pt',
            ],
        ];

        $analysisData = null;
        $provider = 'rag';

        try {
            if ($internalToken !== '') {
                $response = Http::withHeaders([
                    'X-Internal-Token' => $internalToken,
                    'Accept' => 'application/json',
                ])
                ->timeout(12)
                ->post($urlPython, $payload);

                if ($response->successful()) {
                    $analysisData = $response->json();
                } else {
                    Log::warning("[TeacherInsightAnalysisService] RAG API failed with status {$response->status()}. Using fallback.");
                }
            } else {
                Log::warning("[TeacherInsightAnalysisService] Internal token not configured. Using fallback.");
            }
        } catch (\Exception $e) {
            Log::error("[TeacherInsightAnalysisService] Connection to RAG failed: " . $e->getMessage() . ". Using fallback.");
        }

        if (!$analysisData) {
            $analysisData = $this->obterFallbackAnalise($message->content);
            $provider = 'rules_fallback';
        }

        // Save or update analysis
        return StudentMessageAnalysis::updateOrCreate(
            ['message_id' => $message->id],
            [
                'chat_id' => $chat->id,
                'subject_id' => $subjectId,
                'course_id' => $chat->user?->course_id,
                'student_hash' => $studentHash,
                'role' => 'user',
                'language' => 'pt',
                'question_excerpt' => mb_substr($message->content, 0, 200),
                'topic' => $analysisData['topic'] ?? 'Geral',
                'subtopic' => $analysisData['subtopic'] ?? 'Dúvida Geral',
                'intent' => $analysisData['intent'] ?? 'conceptual_question',
                'difficulty_level' => $analysisData['difficulty_level'] ?? 'medium',
                'confusion_score' => $analysisData['confusion_score'] ?? 0.3,
                'frustration_score' => $analysisData['frustration_score'] ?? 0.1,
                'urgency_score' => $analysisData['urgency_score'] ?? 0.2,
                'priority' => $analysisData['priority'] ?? 'low',
                'sentiment' => $analysisData['sentiment'] ?? 'neutral',
                'is_recurring' => $analysisData['is_recurring'] ?? false,
                'needs_teacher_attention' => $analysisData['needs_teacher_attention'] ?? false,
                'llm_summary' => $analysisData['summary'] ?? ($analysisData['llm_summary'] ?? 'Dúvida sobre a matéria.'),
                'suggested_teacher_action' => $analysisData['suggested_teacher_action'] ?? 'Prestar apoio regular ao aluno.',
                'analysis_provider' => $provider,
                'analysis_version' => '1.0',
                'raw_analysis' => $analysisData,
                'processed_at' => now(),
            ]
        );
    }

    protected function obterFallbackAnalise(string $message): array
    {
        $msgLower = mb_strtolower($message);
        
        $confusionScore = 0.3;
        $frustrationScore = 0.1;
        $urgencyScore = 0.2;
        $difficultyLevel = 'medium';
        $needsTeacherAttention = false;
        $intent = 'conceptual_question';
        $sentiment = 'neutral';

        if (str_contains($msgLower, 'não percebo') || str_contains($msgLower, 'não entendo') || str_contains($msgLower, 'confuso') || str_contains($msgLower, 'dificuldade') || str_contains($msgLower, 'perdi')) {
            $confusionScore = 0.8;
            $difficultyLevel = 'high';
            $needsTeacherAttention = true;
        }

        if (str_contains($msgLower, 'frustrado') || str_contains($msgLower, 'irritado') || str_contains($msgLower, 'farto') || str_contains($msgLower, 'raiva')) {
            $frustrationScore = 0.7;
            $needsTeacherAttention = true;
            $sentiment = 'negative';
        }

        if (str_contains($msgLower, 'urgente') || str_contains($msgLower, 'amanhã') || str_contains($msgLower, 'teste') || str_contains($msgLower, 'exame')) {
            $urgencyScore = 0.9;
            $intent = 'exam_prep';
            $needsTeacherAttention = true;
        }

        $priority = 'low';
        $maxScore = max($confusionScore, $frustrationScore, $urgencyScore);
        if ($maxScore > 0.8) {
            $priority = 'high';
        } elseif ($maxScore > 0.5) {
            $priority = 'medium';
        }

        return [
            'topic' => 'Geral',
            'subtopic' => 'Dúvida Geral',
            'intent' => $intent,
            'difficulty_level' => $difficultyLevel,
            'confusion_score' => $confusionScore,
            'frustration_score' => $frustrationScore,
            'urgency_score' => $urgencyScore,
            'priority' => $priority,
            'sentiment' => $sentiment,
            'is_recurring' => false,
            'needs_teacher_attention' => $needsTeacherAttention,
            'summary' => 'Dúvida sobre a matéria (Fallback).',
            'suggested_teacher_action' => 'Prestar apoio regular ao aluno.',
        ];
    }
}
