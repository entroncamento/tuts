<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subject;
use App\Services\StudyPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StudyPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_plan_generation_requires_authentication(): void
    {
        $response = $this->postJson('/api/study-plans', [
            'subject_id' => 1,
            'subject_name' => 'Test Subject',
            'context' => 'routing',
            'material_ids' => [1, 2],
            'duration_weeks' => 2,
            'sessions_per_week' => 3,
        ]);

        $response->assertStatus(401);
    }

    public function test_study_plan_validation_fails_with_missing_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/study-plans', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'subject_id',
            'subject_name',
            'context',
            'material_ids',
            'duration_weeks',
            'sessions_per_week'
        ]);
    }

    public function test_study_plan_generation_success(): void
    {
        $user = User::factory()->create();
        $subject = Subject::create([
            'name' => 'Test Subject',
            'url' => 'https://example.com'
        ]);

        $mockService = Mockery::mock(StudyPlanService::class);
        $mockService->shouldReceive('generate')
            ->once()
            ->with(Mockery::on(function ($data) use ($subject) {
                return $data['subject_id'] == $subject->id &&
                    $data['subject_name'] === 'Test Subject' &&
                    $data['context'] === 'routing' &&
                    $data['material_ids'] === [1, 2] &&
                    $data['duration_weeks'] == 2 &&
                    $data['sessions_per_week'] == 3;
            }))
            ->andReturn([
                'title' => 'Test Plan',
                'summary' => 'Summary',
                'sessions' => [],
                'checkpoints' => [],
                'recommended_materials' => [],
                'warnings' => []
            ]);

        $this->app->instance(StudyPlanService::class, $mockService);

        $response = $this->actingAs($user)
            ->postJson('/api/study-plans', [
                'subject_id' => $subject->id,
                'subject_name' => 'Test Subject',
                'context' => 'routing',
                'material_ids' => [1, 2],
                'duration_weeks' => 2,
                'sessions_per_week' => 3,
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'title' => 'Test Plan',
            'summary' => 'Summary'
        ]);
    }

    public function test_study_plan_generation_handles_rag_warning_422(): void
    {
        $user = User::factory()->create();
        $subject = Subject::create([
            'name' => 'Test Subject',
            'url' => 'https://example.com'
        ]);

        $mockService = Mockery::mock(StudyPlanService::class);
        $mockService->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('{"detail":{"warnings":["Não existem documentos indexados para a UC Test Subject."]}}', 422));

        $this->app->instance(StudyPlanService::class, $mockService);

        $response = $this->actingAs($user)
            ->postJson('/api/study-plans', [
                'subject_id' => $subject->id,
                'subject_name' => 'Test Subject',
                'context' => 'routing',
                'material_ids' => [1, 2],
                'duration_weeks' => 2,
                'sessions_per_week' => 3,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'erro',
            'message' => 'Erro ao gerar plano de estudo.',
            'details' => 'Não existem documentos indexados para a UC Test Subject.'
        ]);
    }
}
