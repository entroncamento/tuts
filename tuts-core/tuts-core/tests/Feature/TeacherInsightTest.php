<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Course;
use App\Models\Message;
use App\Models\Subject;
use App\Models\User;
use App\Models\StudentMessageAnalysis;
use App\Jobs\AnalyzeStudentMessageForTeacherInsights;
use App\Services\TeacherInsightAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class TeacherInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_enviar_pergunta_stream_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Redes de Computadores']);
        
        // Associate course with subject
        $course = Course::create(['name' => 'Engenharia Informática']);
        $user->course_id = $course->id;
        $user->save();
        $subject->courses()->attach($course->id);

        $response = $this->actingAs($user)
            ->postJson('/api/chat/stream', [
                'texto' => 'Como funciona o protocolo OSPF?',
                'uc' => $subject->name,
                'preferencia' => 'default'
            ]);

        // Assert job is dispatched
        Queue::assertPushed(AnalyzeStudentMessageForTeacherInsights::class);
    }

    public function test_job_creates_analysis_successfully(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Redes de Computadores']);
        $chat = Chat::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'title' => 'Dúvida OSPF'
        ]);
        $message = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Não percebo subnetting e estou muito confuso.'
        ]);

        // Mock RAG response
        Http::fake([
            '*/api/teacher-insights/analyze-message' => Http::response([
                'topic' => 'Subnetting',
                'subtopic' => 'CIDR',
                'intent' => 'conceptual_question',
                'difficulty_level' => 'high',
                'confusion_score' => 0.85,
                'frustration_score' => 0.40,
                'urgency_score' => 0.60,
                'priority' => 'high',
                'sentiment' => 'negative',
                'is_recurring' => true,
                'needs_teacher_attention' => true,
                'summary' => 'O aluno tem dificuldades em compreender sub-redes e CIDR.',
                'suggested_teacher_action' => 'Rever cálculo de sub-redes na próxima aula.'
            ], 200)
        ]);

        $job = new AnalyzeStudentMessageForTeacherInsights($message->id);
        app()->call([$job, 'handle']);

        // Assert database record exists
        $this->assertDatabaseHas('student_message_analyses', [
            'message_id' => $message->id,
            'topic' => 'Subnetting',
            'subtopic' => 'CIDR',
            'priority' => 'high',
            'needs_teacher_attention' => true
        ]);
    }

    public function test_job_fallback_runs_when_rag_fails(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Redes de Computadores']);
        $chat = Chat::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'title' => 'Dúvida OSPF'
        ]);
        $message = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Não entendo nada disto, estou mesmo frustrado com isto'
        ]);

        // Mock RAG failure (500 Internal Server Error)
        Http::fake([
            '*/api/teacher-insights/analyze-message' => Http::response([], 500)
        ]);

        $job = new AnalyzeStudentMessageForTeacherInsights($message->id);
        app()->call([$job, 'handle']);

        // Assert database record exists with fallback analysis
        $this->assertDatabaseHas('student_message_analyses', [
            'message_id' => $message->id,
            'analysis_provider' => 'rules_fallback',
            'needs_teacher_attention' => true,
            'sentiment' => 'negative'
        ]);
    }

    public function test_professor_can_access_dashboard_insights(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);
        $student = User::factory()->create(['role' => 'aluno']);
        
        $subject = Subject::create(['name' => 'Redes de Computadores']);
        $chat = Chat::create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'title' => 'Dúvida Routing'
        ]);

        // Seed some analyses
        StudentMessageAnalysis::create([
            'message_id' => 1,
            'chat_id' => $chat->id,
            'subject_id' => $subject->id,
            'student_hash' => hash_hmac('sha256', (string) $student->id, config('app.key')),
            'topic' => 'Routing',
            'subtopic' => 'OSPF',
            'confusion_score' => 0.8,
            'frustration_score' => 0.2,
            'urgency_score' => 0.5,
            'priority' => 'high',
            'needs_teacher_attention' => true
        ]);

        $response = $this->actingAs($professor)
            ->getJson('/api/teacher/dashboard/insights');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary' => [
                'total_questions',
                'active_students_estimate',
                'high_confusion_count',
                'high_frustration_count',
                'needs_attention_count'
            ],
            'subjects',
            'top_topics',
            'recurring_questions',
            'trends',
            'suggested_actions',
            'recent_anonymous_examples'
        ]);

        // Assert privacy: does not expose student id/email/name
        $json = $response->json();
        $this->assertStringNotContainsString($student->name, json_encode($json));
        $this->assertStringNotContainsString($student->email, json_encode($json));
        $this->assertStringNotContainsString('"student_id"', json_encode($json));
    }

    public function test_aluno_cannot_access_dashboard_insights(): void
    {
        $aluno = User::factory()->create(['role' => 'aluno']);

        $response = $this->actingAs($aluno)
            ->getJson('/api/teacher/dashboard/insights');

        $response->assertStatus(403);
    }
}
