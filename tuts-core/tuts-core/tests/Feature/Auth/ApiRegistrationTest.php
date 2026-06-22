<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ApiRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_registration_creates_student_for_non_whitelisted_email(): void
    {
        Event::fake();
        config(['services.api_registration.teacher_email_whitelist' => ['teacher@ua.pt']]);

        $response = $this->postJson('/api/register', $this->validPayload([
            'email' => 'student@ua.pt',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'student@ua.pt')
            ->assertJsonPath('user.role', 'aluno');

        $this->assertDatabaseHas('users', [
            'email' => 'student@ua.pt',
            'role' => 'aluno',
            'email_verified_at' => null,
        ]);
    }

    public function test_api_registration_creates_professor_for_whitelisted_email(): void
    {
        Event::fake();
        config(['services.api_registration.teacher_email_whitelist' => ['teacher@ua.pt']]);

        $response = $this->postJson('/api/register', $this->validPayload([
            'email' => 'teacher@ua.pt',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'teacher@ua.pt')
            ->assertJsonPath('user.role', 'professor');

        $this->assertDatabaseHas('users', [
            'email' => 'teacher@ua.pt',
            'role' => 'professor',
            'email_verified_at' => null,
        ]);
    }

    public function test_api_registration_rejects_frontend_role(): void
    {
        Event::fake();

        $response = $this->postJson('/api/register', $this->validPayload([
            'email' => 'student@ua.pt',
            'role' => 'professor',
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['role']);
        $this->assertDatabaseMissing('users', ['email' => 'student@ua.pt']);
    }

    public function test_api_registration_rejects_frontend_professor_key(): void
    {
        Event::fake();

        $response = $this->postJson('/api/register', $this->validPayload([
            'email' => 'student@ua.pt',
            'professor_key' => 'secret',
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['professor_key']);
        $this->assertDatabaseMissing('users', ['email' => 'student@ua.pt']);
    }

    public function test_api_registration_normalizes_email_before_whitelist_check(): void
    {
        Event::fake();
        config(['services.api_registration.teacher_email_whitelist' => [' teacher@ua.pt ']]);

        $response = $this->postJson('/api/register', $this->validPayload([
            'email' => '  TEACHER@UA.PT  ',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'teacher@ua.pt')
            ->assertJsonPath('user.role', 'professor');

        $this->assertDatabaseHas('users', [
            'email' => 'teacher@ua.pt',
            'role' => 'professor',
        ]);
    }

    public function test_api_registration_does_not_promote_non_whitelisted_professor_like_email(): void
    {
        Event::fake();
        config(['services.api_registration.teacher_email_whitelist' => ['approved.professor@ua.pt']]);

        $response = $this->postJson('/api/register', $this->validPayload([
            'email' => 'professor@ua.pt',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('user.role', 'aluno');

        $this->assertDatabaseHas('users', [
            'email' => 'professor@ua.pt',
            'role' => 'aluno',
        ]);
    }

    public function test_api_registration_dispatches_registered_event(): void
    {
        Event::fake();
        config(['services.api_registration.teacher_email_whitelist' => []]);

        $this->postJson('/api/register', $this->validPayload([
            'email' => 'student@ua.pt',
        ]))->assertCreated();

        Event::assertDispatched(Registered::class, function (Registered $event): bool {
            return $event->user instanceof User
                && $event->user->email === 'student@ua.pt'
                && $event->user->email_verified_at === null;
        });
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'student@ua.pt',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }
}
