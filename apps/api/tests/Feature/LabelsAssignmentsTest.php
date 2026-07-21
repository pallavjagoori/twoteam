<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabelsAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_manages_labels_and_conversation_labels(): void
    {
        [$account, $headers, $conversation] = $this->fixture();
        $created = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/labels", ['title' => 'vip', 'description' => 'Priority', 'color' => '#ff0000', 'show_on_sidebar' => true])->assertOk()->assertJsonPath('title', 'vip');
        $label = $account->labels()->findOrFail($created->json('id'));
        $this->assertTrue($label->account->is($account));
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/labels")->assertOk()->assertJsonCount(1, 'payload');
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/labels/{$label->id}")->assertOk()->assertJsonPath('color', '#ff0000');
        $this->withHeaders($headers)->putJson("/api/v1/accounts/{$account->id}/labels/{$label->id}", ['title' => 'priority'])->assertOk()->assertJsonPath('title', 'priority');
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/labels", ['labels' => ['priority']])->assertOk()->assertJsonPath('payload.0', 'priority');
        $this->assertTrue($label->conversations()->first()->is($conversation));
        $this->assertTrue($conversation->labels()->first()->is($label));
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/conversations/1/labels")->assertOk()->assertJsonPath('payload.0', 'priority');
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/labels/{$label->id}")->assertOk();
    }

    public function test_assigns_agent_team_and_clears_assignments(): void
    {
        [$account, $headers, $conversation] = $this->fixture();
        $agent = User::factory()->create();
        $account->users()->attach($agent, ['role' => 'agent']);
        $team = $account->teams()->create(['name' => 'Support']);
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/assignments", ['assignee_id' => $agent->id])->assertOk()->assertJsonPath('id', $agent->id);
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/assignments", ['team_id' => $team->id])->assertOk()->assertJsonPath('id', $team->id);
        $this->assertSame($agent->id, $conversation->fresh()->assignee_id);
        $this->assertSame($team->id, $conversation->fresh()->team_id);
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/assignments", ['assignee_id' => null])->assertOk()->assertExactJson([]);
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/assignments", [])->assertOk()->assertExactJson([]);
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => 'administrator']);
        $channel = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => (string) Str::uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Inbox', 'channel_id' => $channel->id]);
        $contact = $account->contacts()->create(['name' => 'Visitor']);
        $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'display_id' => 1, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email], $conversation];
    }
}
