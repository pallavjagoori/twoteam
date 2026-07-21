<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Support\RealtimePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_replays_events_and_updates_presence(): void
    {
        $user = User::factory()->create(['pubsub_token' => 'pubsub-reference']);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user);
        $event = RealtimePublisher::publish($account->id, 'message.created', ['id' => 7]);
        $this->assertTrue($event->account->is($account));
        $query = ['pubsub_token' => 'pubsub-reference', 'account_id' => $account->id, 'user_id' => $user->id, 'after' => 0];
        $this->getJson('/api/cable/events?'.http_build_query($query))->assertOk()->assertJsonPath('events.0.event', 'message.created')->assertJsonPath('events.0.data.id', 7)->assertJsonPath('cursor', $event->id);
        $query['after'] = $event->id;
        $this->getJson('/api/cable/events?'.http_build_query($query))->assertOk()->assertJsonCount(0, 'events')->assertJsonPath('cursor', $event->id);
        $this->postJson('/api/cable/presence', ['pubsub_token' => 'pubsub-reference'])->assertOk()->assertJsonPath('success', true);
    }

    public function test_invalid_and_cross_account_subscriptions_are_hidden(): void
    {
        $user = User::factory()->create(['pubsub_token' => 'valid']);
        $account = Account::create(['name' => 'Other']);
        $this->getJson('/api/cable/events?'.http_build_query(['pubsub_token' => 'wrong', 'account_id' => $account->id, 'user_id' => $user->id]))->assertNotFound();
        $this->getJson('/api/cable/events?'.http_build_query(['pubsub_token' => 'valid', 'account_id' => $account->id, 'user_id' => $user->id]))->assertNotFound();
        $this->postJson('/api/cable/presence', ['pubsub_token' => 'wrong'])->assertUnprocessable();
    }
}
