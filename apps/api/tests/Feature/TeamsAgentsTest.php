<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeamsAgentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_manages_agents_and_availability(): void
    {
        [$account, $headers] = $this->admin();
        $created = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/agents", ['name' => 'Second Agent', 'email' => 'second@example.test', 'availability' => 'busy'])->assertOk()->assertJsonPath('availability_status', 'busy');
        $id = $created->json('id');
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/agents")->assertOk()->assertJsonCount(2);
        $this->withHeaders($headers)->patchJson("/api/v1/accounts/{$account->id}/agents/{$id}", ['name' => 'Renamed', 'role' => 'administrator', 'auto_offline' => false])->assertOk()->assertJsonPath('name', 'Renamed')->assertJsonPath('role', 'administrator');
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/agents/{$id}")->assertOk();
        $this->assertDatabaseMissing('account_users', ['account_id' => $account->id, 'user_id' => $id]);
    }

    public function test_administrator_manages_teams_and_scope_isolated(): void
    {
        [$account, $headers, $admin] = $this->admin();
        $created = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/teams", ['name' => 'Support', 'description' => 'Primary'])->assertOk()->assertJsonPath('is_member', false);
        $team = $account->teams()->findOrFail($created->json('id'));
        $this->assertTrue($team->account->is($account));
        $team->users()->attach($admin);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/teams")->assertOk()->assertJsonCount(1)->assertJsonPath('0.is_member', true);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/teams/{$team->id}")->assertOk();
        $this->withHeaders($headers)->patchJson("/api/v1/accounts/{$account->id}/teams/{$team->id}", ['name' => 'Escalations'])->assertOk()->assertJsonPath('name', 'Escalations');
        $other = Account::create(['name' => 'Other']);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$other->id}/teams/{$team->id}")->assertForbidden();
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/teams/{$team->id}")->assertOk();
    }

    private function admin(): array
    {
        $admin = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($admin, ['role' => 'administrator']);
        $login = $this->postJson('/auth/sign_in', ['email' => $admin->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $admin->email], $admin];
    }
}
