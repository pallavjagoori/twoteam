<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_validation_include_membership_bootstrap(): void
    {
        [$user, $account] = $this->member('administrator');
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Reference1!']);

        $login->assertOk()
            ->assertJsonPath('data.account_id', $account->id)
            ->assertJsonPath('data.role', 'administrator')
            ->assertJsonPath('data.accounts.0.name', 'Twoteam Reference')
            ->assertJsonPath('data.accounts.0.availability', 'online');

        $this->withHeaders($this->headers($login))->getJson('/auth/validate_token')
            ->assertOk()->assertJsonPath('payload.data.accounts.0.id', $account->id);
    }

    public function test_member_can_show_account_and_non_member_gets_not_found(): void
    {
        [$user, $account] = $this->member('agent');
        $headers = $this->login($user);

        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}")
            ->assertOk()->assertJsonPath('name', 'Twoteam Reference')
            ->assertJsonPath('cache_keys.label', null);

        $other = Account::create(['name' => 'Other tenant']);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$other->id}")->assertNotFound();
    }

    public function test_only_administrator_can_update_account(): void
    {
        [$agent, $account] = $this->member('agent');
        $this->withHeaders($this->login($agent))
            ->patchJson("/api/v1/accounts/{$account->id}", ['name' => 'Forbidden'])
            ->assertForbidden();

        [$admin, $adminAccount] = $this->member('administrator');
        $this->withHeaders($this->login($admin))
            ->patchJson("/api/v1/accounts/{$adminAccount->id}", ['name' => 'Renamed', 'locale' => 'fr'])
            ->assertOk()->assertJsonPath('name', 'Renamed')->assertJsonPath('locale', 'fr');
    }

    public function test_member_can_update_active_timestamp(): void
    {
        [$user, $account] = $this->member('agent');
        $this->withHeaders($this->login($user))
            ->postJson("/api/v1/accounts/{$account->id}/update_active_at")
            ->assertOk();
        $this->assertNotNull($user->accounts()->first()->pivot->active_at);
    }

    public function test_non_member_cannot_update_activity(): void
    {
        [$user] = $this->member('agent');
        $other = Account::create(['name' => 'Other tenant']);

        $this->withHeaders($this->login($user))
            ->postJson("/api/v1/accounts/{$other->id}/update_active_at")
            ->assertNotFound();
    }

    private function member(string $role): array
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('Reference1!'),
        ]);
        $account = Account::create(['name' => 'Twoteam Reference', 'features' => ['inbox_view' => true]]);
        $account->users()->attach($user, [
            'role' => $role,
            'availability' => 'online',
            'permissions' => json_encode([]),
        ]);

        return [$user, $account];
    }

    private function login(User $user): array
    {
        return $this->headers($this->postJson('/auth/sign_in', [
            'email' => $user->email,
            'password' => 'Reference1!',
        ]));
    }

    private function headers($response): array
    {
        return [
            'access-token' => $response->headers->get('access-token'),
            'client' => $response->headers->get('client'),
            'token-type' => 'Bearer',
            'uid' => $response->headers->get('uid'),
        ];
    }
}
