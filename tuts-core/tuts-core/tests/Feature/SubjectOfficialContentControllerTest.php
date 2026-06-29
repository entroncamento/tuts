<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\SubjectMaterial;
use App\Models\User;
use App\Services\RagIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SubjectOfficialContentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $professor;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->professor = User::factory()->create(['role' => 'professor']);
        $this->subject = Subject::create([
            'name' => 'Engenharia de Software',
            'url' => 'https://example.com',
            'created_by' => $this->professor->id,
        ]);

        Storage::fake('r2');
    }

    /**
     * Test PDF upload where storage succeeds but RagIngestionService throws:
     * material upload still returns success/safe warning and material row exists.
     */
    public function test_pdf_upload_success_even_if_rag_throws(): void
    {
        $file = UploadedFile::fake()->create('lecture.pdf', 100, 'application/pdf');

        // Mock RagIngestionService to throw an exception
        $mockRag = Mockery::mock(RagIngestionService::class);
        $mockRag->shouldReceive('ingestSubjectMaterial')
            ->once()
            ->andThrow(new \RuntimeException('RAG connection timed out'));

        $this->app->instance(RagIngestionService::class, $mockRag);

        $response = $this->actingAs($this->professor)
            ->postJson("/api/subjects/{$this->subject->id}/materials", [
                'file' => $file,
                'name' => 'Lecture PDF',
            ]);

        // Response should be successful (201 Created)
        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'sucesso',
            'rag_ingestion' => [
                'status' => 'failed',
                'reason' => 'ingestion_crash',
            ],
        ]);

        // Material row should exist in database
        $this->assertDatabaseHas('subject_materials', [
            'subject_id' => $this->subject->id,
            'name' => 'Lecture PDF',
            'mime_type' => 'application/pdf',
        ]);
    }

    /**
     * Test manual ingest endpoint where RagIngestionService throws:
     * returns structured failed result, not 500.
     */
    public function test_manual_ingest_failure_caught_gracefully(): void
    {
        $material = SubjectMaterial::create([
            'subject_id' => $this->subject->id,
            'name' => 'Existing Material',
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'path' => 'subject-materials/subjects/1/materials/uuid/Existing.pdf',
            'disk' => 'r2',
            'source' => 'official',
            'verified_by_teacher' => true,
        ]);

        $mockRag = Mockery::mock(RagIngestionService::class);
        $mockRag->shouldReceive('ingestSubjectMaterial')
            ->once()
            ->andThrow(new \RuntimeException('Service unavailable'));

        $this->app->instance(RagIngestionService::class, $mockRag);

        $response = $this->actingAs($this->professor)
            ->postJson("/api/subjects/{$this->subject->id}/materials/{$material->id}/ingest");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'sucesso',
            'rag_ingestion' => [
                'status' => 'failed',
                'reason' => 'ingestion_crash',
            ],
        ]);
    }

    /**
     * Test non-PDF upload does not call ingestion.
     */
    public function test_non_pdf_upload_does_not_call_ingestion(): void
    {
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $mockRag = Mockery::mock(RagIngestionService::class);
        $mockRag->shouldNotReceive('ingestSubjectMaterial');

        $this->app->instance(RagIngestionService::class, $mockRag);

        $response = $this->actingAs($this->professor)
            ->postJson("/api/subjects/{$this->subject->id}/materials", [
                'file' => $file,
                'name' => 'Notes TXT',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'sucesso',
            'rag_ingestion' => [
                'status' => 'skipped',
                'reason' => 'unsupported_type',
            ],
        ]);

        $this->assertDatabaseHas('subject_materials', [
            'subject_id' => $this->subject->id,
            'name' => 'Notes TXT',
            'mime_type' => 'text/plain',
        ]);
    }

    /**
     * Test storage failure still returns safe error response (500).
     */
    public function test_storage_failure_returns_safe_500(): void
    {
        $file = UploadedFile::fake()->create('lecture2.pdf', 100, 'application/pdf');

        // Force a storage write failure by making Storage disk putFileAs return false or throw exception
        Storage::shouldReceive('disk')
            ->with('r2')
            ->andReturn($mockDisk = Mockery::mock());
        
        $mockDisk->shouldReceive('putFileAs')
            ->andReturn(false);

        $response = $this->actingAs($this->professor)
            ->postJson("/api/subjects/{$this->subject->id}/materials", [
                'file' => $file,
                'name' => 'Storage Fail PDF',
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'message' => 'Failed to upload subject material.',
        ]);
    }

    public function test_enrolled_student_can_view_subject_material_pdf(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('subject-materials/test.pdf', '%PDF-subject-test');

        $student = User::factory()->create(['role' => 'aluno']);
        \Illuminate\Support\Facades\DB::table('subject_user')->insert([
            'subject_id' => $this->subject->id,
            'user_id' => $student->id,
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $material = SubjectMaterial::create([
            'subject_id' => $this->subject->id,
            'name' => 'Test Material PDF',
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 17,
            'path' => 'subject-materials/test.pdf',
            'disk' => 'r2',
            'source' => 'official',
            'verified_by_teacher' => true,
        ]);

        $response = $this->actingAs($student)->get(
            "/api/subjects/uc-{$this->subject->id}/materials/{$material->id}/view",
            [
                'Accept' => 'application/pdf',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="Test Material PDF"');
        $this->assertSame('%PDF-subject-test', $response->getContent());
    }

    public function test_non_enrolled_user_cannot_view_subject_material(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('subject-materials/test.pdf', '%PDF-subject-test');

        $otherStudent = User::factory()->create(['role' => 'aluno']);

        $material = SubjectMaterial::create([
            'subject_id' => $this->subject->id,
            'name' => 'Test Material PDF',
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 17,
            'path' => 'subject-materials/test.pdf',
            'disk' => 'r2',
            'source' => 'official',
            'verified_by_teacher' => true,
        ]);

        $response = $this->actingAs($otherStudent)->getJson(
            "/api/subjects/uc-{$this->subject->id}/materials/{$material->id}/view"
        );

        $response->assertStatus(403);
    }

    public function test_missing_subject_material_file_returns_404(): void
    {
        Storage::fake('r2');

        $student = User::factory()->create(['role' => 'aluno']);
        \Illuminate\Support\Facades\DB::table('subject_user')->insert([
            'subject_id' => $this->subject->id,
            'user_id' => $student->id,
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $material = SubjectMaterial::create([
            'subject_id' => $this->subject->id,
            'name' => 'Missing PDF',
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 17,
            'path' => 'subject-materials/missing.pdf',
            'disk' => 'r2',
            'source' => 'official',
            'verified_by_teacher' => true,
        ]);

        $response = $this->actingAs($student)->getJson(
            "/api/subjects/uc-{$this->subject->id}/materials/{$material->id}/view"
        );

        $response->assertStatus(404);
    }
}

