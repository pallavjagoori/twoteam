<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CannedResponsesMacrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_manages_scoped_canned_responses(): void
    {
        [$account, $headers] = $this->fixture();
        $base = "/api/v1/accounts/{$account->id}/canned_responses";
        $created = $this->withHeaders($headers)->postJson($base, ['canned_response' => ['short_code' => 'hello', 'content' => 'Hello there']])->assertOk()->assertJsonPath('short_code', 'hello');
        $item = $account->cannedResponses()->findOrFail($created->json('id'));
        $this->assertTrue($item->account->is($account));
        $this->withHeaders($headers)->getJson($base)->assertOk()->assertJsonCount(1);
        $this->withHeaders($headers)->getJson($base.'?search=THERE')->assertOk()->assertJsonPath('0.id', $item->id);
        $this->withHeaders($headers)->putJson($base.'/'.$item->id, ['canned_response' => ['short_code' => 'welcome', 'content' => 'Welcome']])->assertOk()->assertJsonPath('content', 'Welcome');
        $this->withHeaders($headers)->postJson($base, ['canned_response' => ['short_code' => 'welcome', 'content' => 'Duplicate']])->assertUnprocessable();

        [$other] = $this->fixture();
        $otherItem = $other->cannedResponses()->create(['short_code' => 'other', 'content' => 'Other']);
        $this->withHeaders($headers)->putJson($base.'/'.$otherItem->id, ['canned_response' => ['short_code' => 'bad', 'content' => 'Bad']])->assertNotFound();
        $this->withHeaders($headers)->deleteJson($base.'/'.$item->id)->assertOk();
        $this->assertDatabaseMissing('canned_responses', ['id' => $item->id]);
    }

    public function test_personal_and_global_macro_authorization_matches_roles(): void
    {
        [$account, $agentHeaders, , $agent] = $this->fixture();
        $otherAgent = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account->users()->attach($otherAgent, ['role' => 'agent']);
        $otherHeaders = $this->headers($otherAgent);
        $admin = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account->users()->attach($admin, ['role' => 'administrator']);
        $adminHeaders = $this->headers($admin);
        $base = "/api/v1/accounts/{$account->id}/macros";

        $personal = $this->withHeaders($agentHeaders)->postJson($base, $this->macroData('Mine', 'global'))->assertOk()->assertJsonPath('payload.visibility', 'personal');
        $personalId = $personal->json('payload.id');
        $this->assertTrue($account->macros()->findOrFail($personalId)->account->is($account));
        $this->withHeaders($agentHeaders)->getJson($base)->assertOk()->assertJsonPath('payload.0.id', $personalId);
        $this->withHeaders($agentHeaders)->getJson($base.'/'.$personalId)->assertOk();
        $this->withHeaders($agentHeaders)->putJson($base.'/'.$personalId, $this->macroData('Renamed', 'personal'))->assertOk()->assertJsonPath('payload.updated_by.id', $agent->id);
        $this->withHeaders($otherHeaders)->getJson($base.'/'.$personalId)->assertForbidden();

        $global = $this->withHeaders($adminHeaders)->postJson($base, $this->macroData('Global', 'global'))->assertOk()->assertJsonPath('payload.visibility', 'global');
        $globalId = $global->json('payload.id');
        $this->withHeaders($otherHeaders)->getJson($base.'/'.$globalId)->assertOk();
        $this->withHeaders($agentHeaders)->putJson($base.'/'.$globalId, $this->macroData('Blocked', 'global'))->assertForbidden();
        $this->withHeaders($adminHeaders)->putJson($base.'/'.$globalId, $this->macroData('Updated', 'global'))->assertOk();
        $this->withHeaders($agentHeaders)->postJson($base, ['name' => 'Bad', 'visibility' => 'personal', 'actions' => [['action_name' => 'unsupported']]])->assertUnprocessable();

        [$otherAccount] = $this->fixture();
        $foreign = $otherAccount->macros()->create(['name' => 'Foreign', 'visibility' => 'global', 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id, 'actions' => [['action_name' => 'mute_conversation', 'action_params' => []]]]);
        $this->withHeaders($agentHeaders)->getJson($base.'/'.$foreign->id)->assertNotFound();
        $this->withHeaders($adminHeaders)->deleteJson($base.'/'.$globalId)->assertOk();
        $this->withHeaders($agentHeaders)->deleteJson($base.'/'.$personalId)->assertOk();
    }

    public function test_macro_executes_agent_workflow_for_scoped_conversations(): void
    {
        [$account, $headers, $conversation, $agent] = $this->fixture();
        $label = $account->labels()->create(['title' => 'priority', 'color' => '#ffffff', 'show_on_sidebar' => true]);
        $team = $account->teams()->create(['name' => 'Support']);
        $actions = [
            ['action_name' => 'send_message', 'action_params' => ['Reply']],
            ['action_name' => 'add_private_note', 'action_params' => ['Note']],
            ['action_name' => 'add_label', 'action_params' => [$label->title]],
            ['action_name' => 'remove_label', 'action_params' => [$label->title]],
            ['action_name' => 'assign_agent', 'action_params' => ['self']],
            ['action_name' => 'assign_team', 'action_params' => [$team->id]],
            ['action_name' => 'remove_assigned_agent', 'action_params' => []],
            ['action_name' => 'remove_assigned_team', 'action_params' => []],
            ['action_name' => 'mute_conversation', 'action_params' => []],
            ['action_name' => 'resolve_conversation', 'action_params' => []],
            ['action_name' => 'snooze_conversation', 'action_params' => []],
            ['action_name' => 'change_status', 'action_params' => ['resolved']],
            ['action_name' => 'change_priority', 'action_params' => ['urgent']],
        ];
        $base = "/api/v1/accounts/{$account->id}/macros";
        $macro = $this->withHeaders($headers)->postJson($base, ['name' => 'Resolve', 'visibility' => 'personal', 'actions' => $actions])->assertOk();
        $this->withHeaders($headers)->postJson($base.'/'.$macro->json('payload.id').'/execute', ['conversation_ids' => [$conversation->id, 999999]])->assertOk();

        $conversation->refresh();
        $this->assertSame('resolved', $conversation->status);
        $this->assertSame('urgent', $conversation->priority);
        $this->assertTrue($conversation->muted, 'conversation should be muted');
        $this->assertNull($conversation->assignee_id);
        $this->assertNull($conversation->team_id);
        $this->assertCount(0, $conversation->labels);
        $this->assertSame(['Reply', 'Note'], $conversation->messages()->orderBy('id')->pluck('content')->all());
        $this->assertFalse($conversation->messages()->oldest()->first()->private);
        $this->assertTrue($conversation->messages()->latest('id')->first()->private, 'latest message should be private');
        $this->assertDatabaseCount('realtime_events', 3);
    }

    private function macroData(string $name, string $visibility): array
    {
        return ['name' => $name, 'visibility' => $visibility, 'actions' => [['action_name' => 'mute_conversation', 'action_params' => []]]];
    }

    private function headers(User $user): array
    {
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email];
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => 'agent']);
        $channel = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => (string) Str::uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Inbox', 'channel_id' => $channel->id]);
        $contact = $account->contacts()->create(['name' => 'Visitor']);
        $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'assignee_id' => $user->id, 'display_id' => 1, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);

        return [$account, $this->headers($user), $conversation, $user];
    }
}
