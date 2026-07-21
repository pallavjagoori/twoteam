<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConversationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creates_lists_shows_and_updates_conversation(): void
    {
        [$account, $headers, $user] = $this->fixture();
        $inbox = $account->inboxes()->firstOrFail();
        $contact = $account->contacts()->firstOrFail();
        $created = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations", ['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'assignee_id' => $user->id, 'status' => 'open'])->assertOk()->assertJsonPath('id', 1)->assertJsonPath('meta.sender.name', 'Visitor');
        $conversation = $account->conversations()->firstOrFail();
        $this->assertTrue($conversation->account->is($account));
        $this->assertTrue($conversation->inbox->is($inbox));
        $this->assertTrue($conversation->contact->is($contact));
        $this->assertTrue($conversation->assignee->is($user));
        $this->assertNull($conversation->team);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/conversations?status=open")->assertOk()->assertJsonPath('data.meta.mine_count', 1)->assertJsonPath('data.meta.assigned_count', 1)->assertJsonPath('data.meta.unassigned_count', 0)->assertJsonCount(1, 'data.payload');
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/conversations/1")->assertOk()->assertJsonPath('status', 'open');
        $this->withHeaders($headers)->patchJson("/api/v1/accounts/{$account->id}/conversations/1", ['priority' => 'urgent'])->assertOk()->assertJsonPath('priority', 'urgent');
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/toggle_status", ['status' => 'pending'])->assertOk()->assertJsonPath('status', 'pending');
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/toggle_priority", ['priority' => 'low'])->assertOk()->assertJsonPath('priority', 'low');
    }

    public function test_unassigned_counts_and_invalid_cross_tenant_creation(): void
    {
        [$account, $headers] = $this->fixture();
        $inbox = $account->inboxes()->firstOrFail();
        $contact = $account->contacts()->firstOrFail();
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations", ['inbox_id' => $inbox->id, 'contact_id' => $contact->id])->assertOk();
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/conversations")->assertOk()->assertJsonPath('data.meta.unassigned_count', 1)->assertJsonPath('data.meta.all_count', 1);
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations", ['inbox_id' => 999, 'contact_id' => $contact->id])->assertNotFound();
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => 'agent']);
        $channel = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => fake()->uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $account->inboxes()->create(['name' => 'API', 'channel_id' => $channel->id]);
        $account->contacts()->create(['name' => 'Visitor', 'email' => 'visitor@example.test']);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email], $user];
    }
}
