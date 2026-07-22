<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_dataset_metrics_summary_and_filters_match(): void
    {
        [$account, $headers, $inbox, $contact] = $this->fixture();
        $otherInbox = $account->inboxes()->create(['name' => 'Other', 'channel_id' => $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => 'other', 'secret' => 's', 'hmac_token' => 'h'])->id]);
        $first = $this->conversation($account, $inbox, $contact, 1, 'resolved');
        $second = $this->conversation($account, $otherInbox, $contact, 2, 'open');
        $first->messages()->create(['account_id' => $account->id, 'inbox_id' => $inbox->id, 'content' => 'in', 'message_type' => 0]);
        $first->messages()->create(['account_id' => $account->id, 'inbox_id' => $inbox->id, 'content' => 'out', 'message_type' => 1]);
        $range = '&since='.now()->subDay()->timestamp.'&until='.now()->addDay()->timestamp;
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports?metric=conversations_count{$range}")->assertOk()->assertJsonPath('0.value', 2);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports?metric=incoming_messages_count&group_by=hour{$range}")->assertOk()->assertJsonPath('0.value', 1);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports?metric=outgoing_messages_count{$range}")->assertJsonPath('0.value', 1);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports?metric=resolutions_count{$range}")->assertJsonPath('0.value', 1);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports?metric=avg_first_response_time{$range}")->assertJsonPath('0.value', 0);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports/summary?type=inbox&id={$inbox->id}{$range}")->assertOk()->assertJsonPath('conversations_count', 1)->assertJsonPath('incoming_messages_count', 1)->assertJsonPath('outgoing_messages_count', 1)->assertJsonPath('resolutions_count', 1);
    }

    public function test_drilldowns_grouped_live_summary_and_csv_are_tenant_scoped(): void
    {
        [$account, $headers, $inbox, $contact] = $this->fixture();
        $conversation = $this->conversation($account, $inbox, $contact, 1, 'pending');
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports/drilldown?per_page=1&page=1")->assertOk()->assertJsonPath('data.0.id', 1)->assertJsonPath('meta.total_count', 1);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/live_reports/conversation_metrics")->assertOk()->assertJsonPath('pending', 1)->assertJsonPath('unassigned', 1);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/live_reports/grouped_conversation_metrics?group_by=team_id")->assertOk()->assertJsonPath('0.conversations_count', 1);
        foreach (['inbox', 'agent', 'team', 'label'] as $type) {
            $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/summary_reports/{$type}")->assertOk()->assertJsonPath('0.conversations_count', 1);
        }
        foreach (['agents', 'conversations_summary', 'labels', 'inboxes', 'teams', 'conversation_traffic'] as $type) {
            $this->withHeaders($headers)->get("/api/v2/accounts/{$account->id}/reports/{$type}")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertSee('id,status');
        }
        [$other, $otherHeaders] = $this->member();
        $this->withHeaders($otherHeaders)->getJson("/api/v2/accounts/{$other->id}/reports/drilldown")->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame($account->id, $conversation->account_id);
    }

    public function test_validation_and_authorization_fail_closed(): void
    {
        [$account, $headers] = $this->fixture();
        [, $agentHeaders] = $this->member('agent', $account);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports?since=20&until=10")->assertUnprocessable();
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports?metric=unknown")->assertUnprocessable();
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/summary_reports/nope")->assertNotFound();
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports/nope")->assertNotFound();
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/live_reports/grouped_conversation_metrics?group_by=nope")->assertUnprocessable();
        $this->withHeaders($agentHeaders)->getJson("/api/v2/accounts/{$account->id}/reports")->assertOk();
    }

    public function test_reports_are_query_bounded_for_two_hundred_conversations(): void
    {
        [$account, $headers, $inbox, $contact] = $this->fixture();
        for ($id = 1; $id <= 200; $id++) {
            $this->conversation($account, $inbox, $contact, $id, $id % 2 ? 'open' : 'resolved');
        }
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $started = microtime(true);
        $this->withHeaders($headers)->getJson("/api/v2/accounts/{$account->id}/reports/summary")->assertOk()->assertJsonPath('conversations_count', 200);
        $this->assertLessThanOrEqual(12, $queries);
        $this->assertLessThan(0.5, microtime(true) - $started);
    }

    private function conversation(Account $account, $inbox, $contact, int $displayId, string $status)
    {
        return $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'display_id' => $displayId, 'uuid' => fake()->uuid(), 'status' => $status, 'last_activity_at' => now()]);
    }

    private function fixture(): array
    {
        [$account, $headers] = $this->member();
        $channel = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => fake()->uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Inbox', 'channel_id' => $channel->id]);
        $contact = $account->contacts()->create(['name' => 'Customer', 'email' => fake()->safeEmail()]);

        return [$account, $headers, $inbox, $contact];
    }

    private function member(string $role = 'administrator', ?Account $account = null): array
    {
        $user = User::factory()->create(['password' => Hash::make('Password1!')]);
        $account ??= Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => $role]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email]];
    }
}
