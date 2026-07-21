<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxesTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_manages_web_widget_and_api_inboxes(): void
    {
        [$account, $headers] = $this->member('administrator');
        $web = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes", ['name' => 'Website', 'channel' => ['type' => 'web_widget', 'website_url' => 'https://example.test', 'widget_color' => '#123456']])->assertOk()->assertJsonPath('channel_type', 'Channel::WebWidget')->assertJsonPath('website_url', 'https://example.test');
        $webInbox = $account->inboxes()->findOrFail($web->json('id'));
        $this->assertTrue($webInbox->account->is($account));
        $this->assertTrue($webInbox->channel->account->is($account));
        $this->assertTrue($webInbox->channel->inbox->is($webInbox));
        $this->withHeaders($headers)->patchJson("/api/v1/accounts/{$account->id}/inboxes/{$webInbox->id}", ['name' => 'Helpdesk', 'greeting_enabled' => true, 'greeting_message' => 'Hello'])->assertOk()->assertJsonPath('name', 'Helpdesk')->assertJsonPath('greeting_enabled', true);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/inboxes/{$webInbox->id}")->assertOk()->assertJsonPath('widget_color', '#123456');
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes/{$webInbox->id}/reset_secret")->assertNotFound();

        $api = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes", ['name' => 'API', 'channel' => ['type' => 'api', 'webhook_url' => 'https://example.test/hook']])->assertOk()->assertJsonPath('channel_type', 'Channel::Api');
        $apiId = $api->json('id');
        $oldSecret = $api->json('secret');
        $reset = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes/{$apiId}/reset_secret")->assertOk();
        $this->assertNotSame($oldSecret, $reset->json('secret'));
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/inboxes")->assertOk()->assertJsonCount(2, 'payload');
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/inboxes/{$apiId}")->assertOk()->assertJsonPath('message', 'Inbox deletion has been queued.');
    }

    public function test_agent_lists_inbox_without_secrets_and_cross_tenant_is_denied(): void
    {
        [$account, $adminHeaders] = $this->member('administrator');
        $created = $this->withHeaders($adminHeaders)->postJson("/api/v1/accounts/{$account->id}/inboxes", ['name' => 'API', 'channel' => ['type' => 'api']]);
        [, $agentHeaders] = $this->member('agent', $account);
        $this->withHeaders($agentHeaders)->getJson("/api/v1/accounts/{$account->id}/inboxes")->assertOk()->assertJsonMissingPath('payload.0.secret');
        $other = Account::create(['name' => 'Other']);
        $this->withHeaders($agentHeaders)->getJson("/api/v1/accounts/{$other->id}/inboxes/{$created->json('id')}")->assertForbidden();
    }

    private function member(string $role, ?Account $account = null): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account ??= Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => $role]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email]];
    }
}
