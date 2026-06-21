<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subject;
use App\Models\SubjectMaterial;
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
            'context',
            'duration_weeks',
            'sessions_per_week'
        ]);
    }

    public function test_study_plan_generation_success(): void
    {
        $user = User::factory()->create(['role' => 'professor']);
        $subject = Subject::create([
            'name' => 'Test Subject',
            'url' => 'https://example.com',
            'created_by' => $user->id,
        ]);

        $mat1 = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Material 1',
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'path' => 'pdfs/mat1.pdf',
        ]);
        $mat2 = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Material 2',
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'path' => 'pdfs/mat2.pdf',
        ]);
        $materialIds = [$mat1->id, $mat2->id];

        $mockService = Mockery::mock(StudyPlanService::class);
        $mockService->shouldReceive('generate')
            ->once()
            ->with(Mockery::on(function ($data) use ($subject, $materialIds) {
                return $data['subject']['id'] == $subject->id &&
                    $data['subject']['name'] === 'Test Subject' &&
                    $data['context'] === 'routing' &&
                    collect($data['materials'])->pluck('id')->all() === $materialIds &&
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
                'material_ids' => $materialIds,
                'duration_weeks' => 2,
                'sessions_per_week' => 3,
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'sucesso',
            'plan' => [
                'title' => 'Test Plan',
                'summary' => 'Summary',
            ]
        ]);
    }

    public function test_study_plan_generation_handles_rag_warning_422(): void
    {
        $user = User::factory()->create(['role' => 'professor']);
        $subject = Subject::create([
            'name' => 'Test Subject',
            'url' => 'https://example.com',
            'created_by' => $user->id,
        ]);

        $mat1 = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Material 1',
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'path' => 'pdfs/mat1.pdf',
        ]);
        $mat2 = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Material 2',
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'path' => 'pdfs/mat2.pdf',
        ]);
        $materialIds = [$mat1->id, $mat2->id];

        $mockService = Mockery::mock(StudyPlanService::class);
        $mockService->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('Não existem documentos indexados para a UC Test Subject.', 422));

        $this->app->instance(StudyPlanService::class, $mockService);

        $response = $this->actingAs($user)
            ->postJson('/api/study-plans', [
                'subject_id' => $subject->id,
                'subject_name' => 'Test Subject',
                'context' => 'routing',
                'material_ids' => $materialIds,
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
