<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAuthTest extends TestCase
{
    use RefreshDatabase;

    private const SPA = ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];

    private function demoUser(User $user): User
    {
        $user->is_demo = true; // not mass assignable on purpose
        $user->save();

        return $user;
    }

    public function test_demo_endpoints_do_not_exist_when_demo_mode_is_off(): void
    {
        config(['kri.demo_mode' => false]);
        $user = $this->demoUser(User::factory()->create());

        $this->getJson('/api/demo/accounts')->assertNotFound();
        $this->postJson('/api/demo/login', ['user_id' => $user->id], self::SPA)->assertNotFound();
        $this->assertGuest();
    }

    public function test_lists_only_demo_accounts_with_role_and_department(): void
    {
        config(['kri.demo_mode' => true]);
        $cards = Department::create(['name' => 'Kartu Kredit']);
        $this->demoUser(User::factory()->unitOwner($cards)->create(['name' => 'Rina Kartika']));
        $this->demoUser(User::factory()->create(['name' => 'Hendra Wijaya']));
        User::factory()->auditor()->create(['name' => 'Akun Sungguhan']); // not a demo account

        $this->getJson('/api/demo/accounts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Hendra Wijaya') // directors first
            ->assertJsonPath('data.1.role', 'unit_owner')
            ->assertJsonPath('data.1.department.name', 'Kartu Kredit')
            ->assertJsonMissing(['name' => 'Akun Sungguhan']);
    }

    public function test_signs_in_as_a_demo_persona_and_can_switch_persona(): void
    {
        config(['kri.demo_mode' => true]);
        $director = $this->demoUser(User::factory()->create());
        $auditor = $this->demoUser(User::factory()->auditor()->create());

        $this->postJson('/api/demo/login', ['user_id' => $director->id], self::SPA)
            ->assertOk()
            ->assertJsonPath('user.role', 'director');
        $this->assertAuthenticatedAs($director);

        $this->postJson('/api/demo/login', ['user_id' => $auditor->id], self::SPA)
            ->assertOk()
            ->assertJsonPath('user.role', 'auditor');
        $this->assertAuthenticatedAs($auditor);

        $this->assertSame(
            ['auth.demo_login', 'auth.logout', 'auth.demo_login'],
            ActivityLog::orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_cannot_enter_a_non_demo_account(): void
    {
        config(['kri.demo_mode' => true]);
        $real = User::factory()->auditor()->create();

        $this->postJson('/api/demo/login', ['user_id' => $real->id], self::SPA)->assertNotFound();
        $this->postJson('/api/demo/login', [], self::SPA)->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_is_demo_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'X', 'email' => 'x@kri.test', 'password' => 'p', 'is_demo' => true]);

        $this->assertNull($user->is_demo);
    }
}
