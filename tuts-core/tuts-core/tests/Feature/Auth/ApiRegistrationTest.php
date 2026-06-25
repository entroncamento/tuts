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
        Event::fake([Registered::class]);
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
        ]);

        $this->assertNotNull(User::where('email', 'student@ua.pt')->firstOrFail()->email_verified_at);
    }

    public function test_api_registration_creates_professor_for_whitelisted_email(): void
    {
        Event::fake([Registered::class]);
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
        ]);

        $this->assertNotNull(User::where('email', 'teacher@ua.pt')->firstOrFail()->email_verified_at);
    }

    public function test_registered_student_can_login_immediately(): void
    {
        Event::fake([Registered::class]);
        config(['services.api_registration.teacher_email_whitelist' => ['teacher@ua.pt']]);

        $this->postJson('/api/register', $this->validPayload([
            'email' => 'student@ua.pt',
        ]))->assertCreated();

        $response = $this->postJson('/api/login', [
            'email' => 'student@ua.pt',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.email', 'student@ua.pt')
            ->assertJsonPath('user.role', 'aluno');
    }

    public function test_registered_whitelisted_professor_can_login_immediately(): void
    {
        Event::fake([Registered::class]);
        config(['services.api_registration.teacher_email_whitelist' => ['teacher@ua.pt']]);

        $this->postJson('/api/register', $this->validPayload([
            'email' => 'teacher@ua.pt',
        ]))->assertCreated();

        $response = $this->postJson('/api/login', [
            'email' => 'teacher@ua.pt',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.email', 'teacher@ua.pt')
            ->assertJsonPath('user.role', 'professor');
    }

    public function test_api_login_does_not_fail_when_email_verified_at_is_null(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@ua.pt',
            'role' => 'aluno',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.email', 'unverified@ua.pt')
            ->assertJsonPath('user.role', 'aluno');
    }

    public function test_api_me_after_login_returns_user_role(): void
    {
        User::factory()->create([
            'email' => 'student@ua.pt',
            'role' => 'aluno',
        ]);

        $this->postJson('/api/login', [
            'email' => 'student@ua.pt',
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'student@ua.pt')
            ->assertJsonPath('user.role', 'aluno');
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

    public function test_api_registration_does_not_dispatch_registered_event(): void
    {
        Event::fake([Registered::class]);
        config(['services.api_registration.teacher_email_whitelist' => []]);

        $this->postJson('/api/register', $this->validPayload([
            'email' => 'student@ua.pt',
        ]))->assertCreated();

        Event::assertNotDispatched(Registered::class);
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
