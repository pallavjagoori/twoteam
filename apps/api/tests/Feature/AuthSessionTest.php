<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_user_can_sign_in_with_normalized_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Reference Agent',
            'display_name' => 'Agent',
            'email' => 'agent@twoteam.test',
            'password' => Hash::make('Reference1!'),
            'provider' => 'email',
            'uid' => 'agent@twoteam.test',
            'pubsub_token' => 'reference-pubsub-token',
        ]);

        $response = $this->postJson('/auth/sign_in', [
            'email' => '  AGENT@TWOTEAM.TEST ',
            'password' => 'Reference1!',
        ]);

        $response
            ->assertOk()
            ->assertHeader('token-type', 'Bearer')
            ->assertHeader('uid', 'agent@twoteam.test')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'agent@twoteam.test')
            ->assertJsonPath('data.available_name', 'Agent')
            ->assertJsonPath('data.confirmed', true)
            ->assertJsonPath('data.accounts', []);

        $plainToken = $response->headers->get('access-token');
        $this->assertNotEmpty($plainToken);
        $this->assertNotEmpty($response->headers->get('client'));
        $this->assertGreaterThan(now()->timestamp, (int) $response->headers->get('expiry'));
        $this->assertDatabaseHas('auth_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
        ]);
        $this->assertDatabaseMissing('auth_tokens', ['token_hash' => $plainToken]);
    }

    public function test_invalid_credentials_match_chatwoot_error_shape(): void
    {
        $this->postJson('/auth/sign_in', [
            'email' => 'missing@twoteam.test',
            'password' => 'wrong',
        ])->assertUnauthorized()->assertExactJson([
            'success' => false,
            'errors' => ['Invalid login credentials. Please try again.'],
        ]);
    }

    public function test_unconfirmed_user_receives_stable_error_code(): void
    {
        User::factory()->unverified()->create([
            'email' => 'unconfirmed@twoteam.test',
            'password' => Hash::make('Reference1!'),
        ]);

        $this->postJson('/auth/sign_in', [
            'email' => 'unconfirmed@twoteam.test',
            'password' => 'Reference1!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'user_not_confirmed')
            ->assertJsonPath('success', false);
    }

    public function test_token_can_validate_and_then_sign_out(): void
    {
        User::factory()->create([
            'email' => 'agent@twoteam.test',
            'password' => Hash::make('Reference1!'),
            'uid' => 'agent@twoteam.test',
        ]);
        $login = $this->postJson('/auth/sign_in', [
            'email' => 'agent@twoteam.test',
            'password' => 'Reference1!',
        ]);
        $headers = $this->authHeaders($login);

        $this->withHeaders($headers)
            ->getJson('/auth/validate_token')
            ->assertOk()
            ->assertJsonPath('payload.success', true)
            ->assertJsonPath('payload.data.email', 'agent@twoteam.test');

        $this->withHeaders($headers)
            ->deleteJson('/auth/sign_out')
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->withHeaders($headers)
            ->getJson('/auth/validate_token')
            ->assertUnauthorized();
    }

    public function test_token_rejects_a_mismatched_uid(): void
    {
        User::factory()->create([
            'email' => 'agent@twoteam.test',
            'password' => Hash::make('Reference1!'),
        ]);
        $login = $this->postJson('/auth/sign_in', [
            'email' => 'agent@twoteam.test',
            'password' => 'Reference1!',
        ]);
        $headers = $this->authHeaders($login);
        $headers['uid'] = 'other@twoteam.test';

        $this->withHeaders($headers)
            ->getJson('/auth/validate_token')
            ->assertUnauthorized();
    }

    public function test_validation_rejects_missing_headers(): void
    {
        $this->getJson('/auth/validate_token')->assertUnauthorized();
    }

    public function test_validation_rejects_an_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'agent@twoteam.test']);
        $plainToken = 'expired-reference-token';
        $user->authTokens()->create([
            'client_id' => '108cc96c-7664-48b5-a3a7-a277eb6a8e44',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withHeaders([
            'access-token' => $plainToken,
            'client' => '108cc96c-7664-48b5-a3a7-a277eb6a8e44',
            'token-type' => 'Bearer',
            'uid' => 'agent@twoteam.test',
        ])->getJson('/auth/validate_token')->assertUnauthorized();
    }

    private function authHeaders($response): array
    {
        return [
            'access-token' => $response->headers->get('access-token'),
            'client' => $response->headers->get('client'),
            'token-type' => $response->headers->get('token-type'),
            'uid' => $response->headers->get('uid'),
        ];
    }
}
