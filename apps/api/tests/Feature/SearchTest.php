<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_searches_scoped_contacts_conversations_and_messages(): void
    {
        [$account, $headers, $inbox, $user] = $this->fixture();
        $contact = $account->contacts()->create(['name' => 'Alice Visitor', 'email' => 'alice@example.test', 'identifier' => 'alice-1', 'last_activity_at' => now()]);
        $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'assignee_id' => $user->id, 'display_id' => 42, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);
        $message = $conversation->messages()->create(['account_id' => $account->id, 'inbox_id' => $inbox->id, 'sender_id' => $user->id, 'content' => 'Alice needs urgent help', 'message_type' => 1, 'status' => 'sent']);
        $this->assertTrue($account->messages()->findOrFail($message->id)->is($message));

        [$other] = $this->fixture();
        $otherContact = $other->contacts()->create(['name' => 'Alice Hidden', 'email' => 'hidden@example.test', 'last_activity_at' => now()]);
        $otherInbox = $other->inboxes()->firstOrFail();
        $other->conversations()->create(['inbox_id' => $otherInbox->id, 'contact_id' => $otherContact->id, 'display_id' => 42, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);

        $base = "/api/v1/accounts/{$account->id}/search";
        $this->withHeaders($headers)->getJson($base.'?q=Alice')->assertOk()->assertJsonCount(1, 'payload.contacts')->assertJsonCount(1, 'payload.conversations')->assertJsonCount(1, 'payload.messages')->assertJsonPath('payload.articles', [])->assertJsonPath('payload.conversations.0.agent.id', $user->id);
        $this->withHeaders($headers)->getJson($base.'/conversations?q=42')->assertOk()->assertJsonPath('payload.conversations.0.id', 42);
        $this->withHeaders($headers)->getJson($base.'/articles?q=anything')->assertOk()->assertJsonPath('payload.articles', []);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$other->id}/search?q=Alice")->assertForbidden();
    }

    public function test_search_filters_dates_senders_inboxes_and_pages(): void
    {
        [$account, $headers, $inbox, $user] = $this->fixture();
        $contact = $account->contacts()->create(['name' => 'Filter Contact', 'last_activity_at' => now()]);
        $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'display_id' => 7, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);
        $agentMessage = $conversation->messages()->create(['account_id' => $account->id, 'inbox_id' => $inbox->id, 'sender_id' => $user->id, 'content' => 'needle agent', 'message_type' => 1, 'status' => 'sent']);
        $conversation->messages()->create(['account_id' => $account->id, 'inbox_id' => $inbox->id, 'content' => 'needle contact', 'message_type' => 0, 'status' => 'sent']);
        for ($i = 0; $i < 16; $i++) {
            $account->contacts()->create(['name' => 'Paged '.$i, 'identifier' => 'paged-'.$i, 'last_activity_at' => now()]);
        }
        $base = "/api/v1/accounts/{$account->id}/search";
        $since = now()->subHour()->timestamp;
        $until = now()->addHour()->timestamp;
        $this->withHeaders($headers)->getJson($base."/contacts?q=Paged&page=2&since={$since}&until={$until}")->assertOk()->assertJsonCount(1, 'payload.contacts');
        $this->withHeaders($headers)->getJson($base."/conversations?q=Filter&since={$since}&until={$until}")->assertOk()->assertJsonCount(1, 'payload.conversations');
        $this->withHeaders($headers)->getJson($base."/messages?q=needle&from=agent:{$user->id}&inbox_id={$inbox->id}&since={$since}&until={$until}")->assertOk()->assertJsonCount(1, 'payload.messages')->assertJsonPath('payload.messages.0.id', $agentMessage->id);
        $this->withHeaders($headers)->getJson($base."/messages?q=needle&from=contact:{$contact->id}&inbox_id=999999")->assertOk()->assertJsonCount(2, 'payload.messages');
        $this->withHeaders($headers)->getJson($base.'/messages?from=invalid')->assertUnprocessable();
    }

    public function test_search_query_count_stays_bounded_with_result_size(): void
    {
        [$account, $headers, $inbox] = $this->fixture();
        for ($i = 0; $i < 100; $i++) {
            $contact = $account->contacts()->create(['name' => 'Scale '.$i, 'identifier' => 'scale-'.$i, 'last_activity_at' => now()]);
            $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'display_id' => $i + 1, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);
            $conversation->messages()->create(['account_id' => $account->id, 'inbox_id' => $inbox->id, 'content' => 'scale result '.$i, 'message_type' => 0, 'status' => 'sent']);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $started = hrtime(true);
        $response = $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/search/conversations?q=Scale")->assertOk()->assertJsonCount(15, 'payload.conversations');
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;
        $this->assertLessThanOrEqual(12, count(DB::getQueryLog()), 'search must not issue per-result queries');
        $this->assertLessThan(500, $elapsedMs, '100-record search should complete within 500 ms in the test environment');
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => 'agent']);
        $channel = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => (string) Str::uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Inbox', 'channel_id' => $channel->id]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);
        $headers = ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email];

        return [$account, $headers, $inbox, $user];
    }
}
