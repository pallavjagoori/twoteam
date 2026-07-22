<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\WidgetSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_bootstraps_conversation_and_exchanges_messages_with_agent(): void
    {
        [$account, $channel, $inbox, $headers] = $this->fixture();
        $config = $this->postJson('/api/v1/widget/config', ['website_token' => $channel->identifier])
            ->assertOk()->assertJsonPath('website_channel_config.website_name', 'Website')
            ->assertJsonPath('website_channel_config.widget_color', '#123456');
        $token = $config->json('website_channel_config.auth_token');
        $widgetHeaders = ['X-Auth-Token' => $token];
        $query = '?website_token='.$channel->identifier;
        $session = WidgetSession::firstOrFail();
        $this->assertTrue($session->account->is($account));
        $this->assertTrue($session->inbox->is($inbox));
        $this->getJson('/api/cable/events?'.http_build_query(['pubsub_token' => $session->pubsub_token, 'after' => 0]))->assertOk();
        $this->postJson('/api/cable/presence', ['pubsub_token' => $session->pubsub_token])->assertOk();

        $this->withHeaders($widgetHeaders)->getJson('/api/v1/widget/conversations'.$query)->assertOk()->assertExactJson([]);
        $this->withHeaders($widgetHeaders)->getJson('/api/v1/widget/messages'.$query)->assertOk()->assertJsonCount(0, 'payload');
        $this->withHeaders($widgetHeaders)->getJson('/api/v1/widget/inbox_members'.$query)->assertOk()->assertJsonCount(1, 'payload');
        $this->withHeaders($widgetHeaders)->getJson('/api/v1/widget/campaigns'.$query)->assertOk()->assertExactJson([]);
        $this->get('/widget'.$query.'&cw_conversation='.$token)->assertOk()->assertSee('twoteam-runtime-config')->assertSee('assets/widget.js');
        $created = $this->withHeaders($widgetHeaders)->postJson('/api/v1/widget/conversations'.$query, [
            'contact' => ['name' => 'Visitor', 'email' => 'visitor@example.test', 'phone_number' => '+15550000', 'custom_attributes' => ['plan' => 'trial']],
            'message' => ['content' => 'Hello team', 'referer_url' => 'https://example.test/pricing'],
            'custom_attributes' => ['topic' => 'sales'],
        ])->assertOk()->assertJsonPath('id', 1)->assertJsonPath('messages.0.message_type', 0)->assertJsonPath('contact.name', 'Visitor');

        $conversation = $account->conversations()->where('display_id', $created->json('id'))->firstOrFail();
        $this->assertSame($inbox->id, $conversation->inbox_id);
        $this->assertSame('sales', $conversation->custom_attributes['topic']);
        $this->assertSame('trial', $conversation->contact->custom_attributes['plan']);
        $visitorReply = $this->withHeaders($widgetHeaders)->postJson('/api/v1/widget/messages'.$query, ['message' => ['content' => 'Anyone there?']])
            ->assertOk()->assertJsonPath('sender.type', 'contact');

        $agentReply = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/1/messages", ['content' => 'Yes, how can I help?'])->assertOk();
        $this->withHeaders($widgetHeaders)->getJson('/api/v1/widget/conversations'.$query)->assertOk()->assertJsonPath('status', 'open');
        $this->withHeaders($widgetHeaders)->getJson('/api/v1/widget/messages'.$query.'&after='.$visitorReply->json('id'))
            ->assertOk()->assertJsonCount(1, 'payload')->assertJsonPath('payload.0.id', $agentReply->json('id'))->assertJsonPath('payload.0.sender.type', 'user');
        $this->withHeaders($widgetHeaders)->getJson('/api/v1/widget/messages'.$query.'&before='.$agentReply->json('id'))
            ->assertOk()->assertJsonCount(2, 'payload');
        $this->assertDatabaseCount('realtime_events', 3);
    }

    public function test_widget_tokens_are_required_scoped_and_expiring(): void
    {
        [, $channel] = $this->fixture();
        $this->postJson('/api/v1/widget/config', [])->assertUnprocessable();
        $this->postJson('/api/v1/widget/config', ['website_token' => 'missing'])->assertNotFound();
        $config = $this->postJson('/api/v1/widget/config', ['website_token' => $channel->identifier])->assertOk();
        $query = '?website_token='.$channel->identifier;
        $this->getJson('/api/v1/widget/conversations'.$query)->assertNotFound();
        $this->withHeaders(['X-Auth-Token' => $config->json('website_channel_config.auth_token')])
            ->postJson('/api/v1/widget/messages'.$query, ['message' => ['content' => null, 'referer_url' => 'https://example.test'], 'custom_attributes' => ['source' => 'widget']])
            ->assertOk()->assertJsonPath('content', '');

        WidgetSession::query()->update(['expires_at' => now()->subMinute()]);
        $this->withHeaders(['X-Auth-Token' => $config->json('website_channel_config.auth_token')])
            ->getJson('/api/v1/widget/conversations'.$query)->assertNotFound();
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => 'agent']);
        $channel = $account->channels()->create(['type' => 'web_widget', 'settings' => ['website_url' => 'https://example.test', 'widget_color' => '#123456'], 'identifier' => (string) Str::uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Website', 'channel_id' => $channel->id]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);
        $headers = ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email];

        return [$account, $channel, $inbox, $headers];
    }
}
