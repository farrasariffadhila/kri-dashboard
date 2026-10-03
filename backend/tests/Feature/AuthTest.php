<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** Requests from the SPA origin get session (stateful) handling, like the real frontend. */
    private const SPA = ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/departments/risk-summary')->assertUnauthorized();
        $this->getJson('/api/audit-issues')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_api_returns_json_401_even_without_an_accept_header(): void
    {
        // e.g. someone opening an API URL directly in the browser
        $this->get('/api/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_login_returns_profile_and_starts_a_session(): void
    {
        $user = User::factory()->auditor()->create(['email' => 'auditor@kri.test', 'password' => 'rahasia-uji-123']);

        $this->withHeaders(self::SPA)
            ->postJson('/api/login', ['email' => 'auditor@kri.test', 'password' => 'rahasia-uji-123'])
            ->assertOk()
            ->assertJsonPath('user.email', 'auditor@kri.test')
            ->assertJsonPath('user.role', 'auditor')
            ->assertJsonPath('user.role_label', 'Auditor internal')
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_generic_message(): void
    {
        User::factory()->create(['email' => 'direksi@kri.test', 'password' => 'rahasia-uji-123']);

        $wrongPassword = $this->withHeaders(self::SPA)->postJson('/api/login', ['email' => 'direksi@kri.test', 'password' => 'salah']);
        $unknownEmail = $this->withHeaders(self::SPA)->postJson('/api/login', ['email' => 'tidakada@kri.test', 'password' => 'salah']);

        $wrongPassword->assertStatus(422)->assertExactJson(['message' => 'Email atau kata sandi tidak sesuai.']);
        $unknownEmail->assertStatus(422)->assertExactJson(['message' => 'Email atau kata sandi tidak sesuai.']);
        $this->assertGuest();
        $this->assertSame(2, ActivityLog::where('action', 'auth.login_failed')->count());
    }

    public function test_messages_follow_the_accept_language_header(): void
    {
        $this->postJson('/api/login', ['email' => 'tidakada@kri.test', 'password' => 'salah'], [...self::SPA, 'Accept-Language' => 'en'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Incorrect email or password.']);

        $user = User::factory()->auditor()->create();
        $this->actingAs($user)->getJson('/api/me', ['Accept-Language' => 'en'])->assertJsonPath('user.role_label', 'Internal auditor');
        $this->actingAs($user)->getJson('/api/me')->assertJsonPath('user.role_label', 'Auditor internal');
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        User::factory()->create(['email' => 'direksi@kri.test', 'password' => 'rahasia-uji-123']);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(self::SPA)->postJson('/api/login', ['email' => 'direksi@kri.test', 'password' => 'salah']);
        }

        // Even the correct password is refused while the account is locked out.
        $this->withHeaders(self::SPA)
            ->postJson('/api/login', ['email' => 'direksi@kri.test', 'password' => 'rahasia-uji-123'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create(['email' => 'direksi@kri.test', 'password' => 'rahasia-uji-123']);
        $this->withHeaders(self::SPA)->postJson('/api/login', ['email' => 'direksi@kri.test', 'password' => 'rahasia-uji-123'])->assertOk();

        $this->withHeaders(self::SPA)->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'X', 'email' => 'x@kri.test', 'password' => 'p', 'role' => 'auditor']);

        $this->assertNull($user->role);
    }
}
