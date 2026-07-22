<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Support\AutomationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomationsBusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_updates_timezone_aware_business_hours(): void
    {
        [$account, $headers, $inbox] = $this->fixture('administrator');
        $url = "/api/v1/accounts/{$account->id}/inboxes/{$inbox->id}";
        $schedule = [['day_of_week' => 1, 'closed_all_day' => false, 'open_all_day' => false, 'open_hour' => 9, 'open_minutes' => 30, 'close_hour' => 17, 'close_minutes' => 0]];
        $this->withHeaders($headers)->patchJson($url, ['timezone' => 'Asia/Kolkata', 'working_hours_enabled' => true, 'out_of_office_message' => 'Back tomorrow', 'working_hours' => $schedule])->assertOk()->assertJsonPath('working_hours.1.open_hour', 9)->assertJsonPath('timezone', 'Asia/Kolkata');

        $hour = $inbox->workingHours()->where('day_of_week', 1)->firstOrFail();
        $this->assertTrue($hour->inbox->is($inbox));
        $this->assertTrue($hour->isOpenAt(Carbon::parse('2026-07-27 05:00:00', 'UTC')));
        $this->assertFalse($hour->isOpenAt(Carbon::parse('2026-07-27 02:00:00', 'UTC')));
        $this->assertFalse($inbox->workingHours()->where('day_of_week', 0)->firstOrFail()->isOpenAt(Carbon::parse('2026-07-26 05:00:00', 'UTC')));

        $openAll = [['day_of_week' => 2, 'closed_all_day' => false, 'open_all_day' => true, 'open_hour' => null, 'open_minutes' => null, 'close_hour' => null, 'close_minutes' => null]];
        $this->withHeaders($headers)->patchJson($url, ['working_hours' => $openAll])->assertOk()->assertJsonPath('working_hours.2.close_minutes', 59);
        $this->assertTrue($inbox->workingHours()->where('day_of_week', 2)->firstOrFail()->isOpenAt(Carbon::parse('2026-07-28 00:00:00', 'UTC')));
        $invalid = [['day_of_week' => 3, 'closed_all_day' => true, 'open_all_day' => true, 'open_hour' => null, 'open_minutes' => null, 'close_hour' => null, 'close_minutes' => null]];
        $this->withHeaders($headers)->patchJson($url, ['working_hours' => $invalid])->assertUnprocessable();
        $invalid[0] = ['day_of_week' => 3, 'closed_all_day' => false, 'open_all_day' => false, 'open_hour' => 17, 'open_minutes' => 0, 'close_hour' => 9, 'close_minutes' => 0];
        $this->withHeaders($headers)->patchJson($url, ['working_hours' => $invalid])->assertUnprocessable();
        $this->withHeaders($headers)->patchJson($url, ['timezone' => 'Mars/Olympus'])->assertUnprocessable();
    }

    public function test_only_administrator_manages_scoped_automation_rules(): void
    {
        [$account, $adminHeaders] = $this->fixture('administrator');
        [, $agentHeaders] = $this->member('agent', $account);
        $base = "/api/v1/accounts/{$account->id}/automation_rules";
        $created = $this->withHeaders($adminHeaders)->postJson($base, $this->ruleData())->assertOk()->assertJsonPath('name', 'Route urgent messages');
        $id = $created->json('id');
        $rule = $account->automationRules()->findOrFail($id);
        $this->assertTrue($rule->account->is($account));
        $this->withHeaders($adminHeaders)->getJson($base)->assertOk()->assertJsonPath('payload.0.id', $id);
        $this->withHeaders($adminHeaders)->getJson($base.'/'.$id)->assertOk()->assertJsonPath('payload.id', $id);
        $this->withHeaders($adminHeaders)->putJson($base.'/'.$id, array_merge($this->ruleData(), ['name' => 'Updated']))->assertOk()->assertJsonPath('payload.name', 'Updated');
        $copy = $this->withHeaders($adminHeaders)->postJson($base.'/'.$id.'/clone')->assertOk();
        $this->assertNotSame($id, $copy->json('payload.id'));
        $this->withHeaders($agentHeaders)->getJson($base)->assertForbidden();
        $this->withHeaders($adminHeaders)->postJson($base, array_merge($this->ruleData(), ['event_name' => 'bad']))->assertUnprocessable();

        [$other] = $this->fixture('administrator');
        $foreign = $other->automationRules()->create($this->ruleData());
        $this->withHeaders($adminHeaders)->getJson($base.'/'.$foreign->id)->assertNotFound();
        $this->withHeaders($adminHeaders)->deleteJson($base.'/'.$copy->json('payload.id'))->assertOk();
    }

    public function test_automation_conditions_mutate_once_per_event(): void
    {
        [$account, , $inbox, $conversation] = $this->fixture('administrator');
        $label = $account->labels()->create(['title' => 'vip', 'color' => '#ffffff', 'show_on_sidebar' => true]);
        $conversation->labels()->attach($label);
        $conversation->update(['priority' => 'high']);
        $message = $conversation->messages()->create(['account_id' => $account->id, 'inbox_id' => $inbox->id, 'content' => 'urgent help', 'message_type' => 0, 'status' => 'sent']);
        $conditions = [
            ['attribute_key' => 'content', 'filter_operator' => 'contains', 'query_operator' => null, 'values' => ['urgent help']],
            ['attribute_key' => 'message_type', 'filter_operator' => 'equal_to', 'query_operator' => 'AND', 'values' => ['0']],
            ['attribute_key' => 'status', 'filter_operator' => 'equal_to', 'query_operator' => 'AND', 'values' => ['open']],
            ['attribute_key' => 'priority', 'filter_operator' => 'not_equal_to', 'query_operator' => 'AND', 'values' => ['low']],
            ['attribute_key' => 'inbox_id', 'filter_operator' => 'equal_to', 'query_operator' => 'AND', 'values' => [(string) $inbox->id]],
            ['attribute_key' => 'labels', 'filter_operator' => 'contains', 'query_operator' => 'AND', 'values' => ['vip']],
        ];
        $account->automationRules()->create(['name' => 'Match', 'event_name' => 'message_created', 'active' => true, 'conditions' => $conditions, 'actions' => [['action_name' => 'add_private_note', 'action_params' => ['Automated']]]]);
        $account->automationRules()->create(['name' => 'No match', 'event_name' => 'message_created', 'active' => true, 'conditions' => [['attribute_key' => 'content', 'filter_operator' => 'does_not_contain', 'query_operator' => null, 'values' => ['urgent help']], ['attribute_key' => 'status', 'filter_operator' => 'equal_to', 'query_operator' => 'OR', 'values' => ['pending']]], 'actions' => [['action_name' => 'mute_conversation', 'action_params' => []]]]);
        $account->automationRules()->create(['name' => 'Empty', 'event_name' => 'message_created', 'active' => true, 'conditions' => [], 'actions' => [['action_name' => 'change_priority', 'action_params' => ['urgent']]]]);
        $account->automationRules()->create(['name' => 'Inactive', 'event_name' => 'message_created', 'active' => false, 'conditions' => [], 'actions' => [['action_name' => 'mute_conversation', 'action_params' => []]]]);

        $this->assertSame(2, AutomationEngine::dispatch('message_created', $conversation, 'message:'.$message->id, $message));
        $this->assertSame(0, AutomationEngine::dispatch('message_created', $conversation, 'message:'.$message->id, $message));
        $this->assertSame('urgent', $conversation->fresh()->priority);
        $this->assertFalse($conversation->fresh()->muted);
        $this->assertSame('Automated', $conversation->messages()->where('private', true)->value('content'));
        $this->assertDatabaseCount('automation_executions', 2);
    }

    private function ruleData(): array
    {
        return ['name' => 'Route urgent messages', 'description' => 'Test', 'event_name' => 'message_created', 'active' => true, 'conditions' => [['attribute_key' => 'content', 'filter_operator' => 'contains', 'query_operator' => null, 'values' => ['urgent']]], 'actions' => [['action_name' => 'mute_conversation', 'action_params' => []]]];
    }

    private function member(string $role, Account $account): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account->users()->attach($user, ['role' => $role]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$user, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email]];
    }

    private function fixture(string $role): array
    {
        $account = Account::create(['name' => 'Tenant']);
        [$user, $headers] = $this->member($role, $account);
        $channel = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => (string) Str::uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Inbox', 'channel_id' => $channel->id]);
        $contact = $account->contacts()->create(['name' => 'Visitor']);
        $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'assignee_id' => $user->id, 'display_id' => 1, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);

        return [$account, $headers, $inbox, $conversation, $user];
    }
}
