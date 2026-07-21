<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_sends_notes_reads_history_updates_retries_and_deletes(): void
    {
        [$account, $headers, $conversation, $user] = $this->fixture('api');
        $first = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/messages", ['content' => 'Hello', 'echo_id' => 'echo-1'])->assertOk()->assertJsonPath('status', 'sent')->assertJsonPath('sender.id', $user->id);
        $second = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/messages", ['content' => 'Internal', 'private' => true, 'content_attributes' => ['kind' => 'note']])->assertOk()->assertJsonPath('private', true);
        $message = $conversation->messages()->findOrFail($first->json('id'));
        $this->assertTrue($message->conversation->is($conversation));
        $this->assertTrue($message->sender->is($user));
        $this->assertTrue($message->inbox->is($conversation->inbox));
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/conversations/1/messages")->assertOk()->assertJsonCount(2, 'payload')->assertJsonPath('meta.contact.name', 'Visitor');
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/conversations/1/messages?after={$first->json('id')}&before=999")->assertOk()->assertJsonCount(1, 'payload');
        $this->withHeaders($headers)->putJson("/api/v1/accounts/{$account->id}/conversations/1/messages/{$message->id}", ['status' => 'failed', 'external_error' => 'timeout'])->assertOk()->assertJsonPath('status', 'failed');
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/messages/{$message->id}/retry")->assertOk()->assertJsonPath('status', 'sent');
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/conversations/1/messages/{$second->json('id')}")->assertOk()->assertJsonPath('content_attributes.deleted', true);
        $this->assertNotNull($conversation->fresh()->last_activity_at);
    }

    public function test_status_update_is_forbidden_for_web_widget(): void
    {
        [$account, $headers, $conversation] = $this->fixture('web_widget');
        $message = $conversation->messages()->create(['account_id' => $account->id, 'inbox_id' => $conversation->inbox_id, 'content' => 'Incoming', 'message_type' => 0, 'status' => 'sent']);
        $this->withHeaders($headers)->putJson("/api/v1/accounts/{$account->id}/conversations/1/messages/{$message->id}", ['status' => 'delivered'])->assertForbidden();
    }

    private function fixture(string $channelType): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => 'agent']);
        $channel = $account->channels()->create(['type' => $channelType, 'settings' => [], 'identifier' => (string) Str::uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Inbox', 'channel_id' => $channel->id]);
        $contact = $account->contacts()->create(['name' => 'Visitor']);
        $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'assignee_id' => $user->id, 'display_id' => 1, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email], $conversation, $user];
    }
}
