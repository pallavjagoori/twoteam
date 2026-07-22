<?php

namespace Tests\Feature;

use App\Jobs\SendPrioritizedMessage;
use App\Models\Account;
use App\Models\Channel;
use App\Models\PrioritizedDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PrioritizedChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prioritized_channel_credentials_are_encrypted(): void
    {
        foreach (['telegram' => 'Telegram', 'line' => 'Line', 'sms' => 'Sms'] as $provider => $type) {
            [, , $channel, $response] = $this->fixture($provider);
            $response->assertJsonPath('channel_type', 'Channel::'.$type)->assertJsonMissingPath('provider_credentials');
            $this->assertSame('secret', $channel->prioritizedChannel->encrypted_credentials[$provider === 'telegram' ? 'bot_token' : ($provider === 'line' ? 'channel_token' : 'api_key')]);
            $this->assertStringNotContainsString('secret', $channel->prioritizedChannel->getRawOriginal('encrypted_credentials'));
            $this->assertTrue($channel->prioritizedChannel->channel->is($channel));
        }
    }

    public function test_each_provider_ingests_idempotently_and_sends_replies(): void
    {
        Http::fakeSequence()->push(['result' => ['message_id' => 'out-telegram']])->push(['message_id' => 'out-line'])->push(['id' => 'out-sms']);
        foreach (['telegram', 'line', 'sms'] as $provider) {
            [$account, $headers, $channel] = $this->fixture($provider);
            $payload = $this->inbound($provider, 'in-'.$provider);
            $created = $this->send($channel, $payload)->assertOk()->assertJsonPath('content', 'Hello '.$provider);
            $this->send($channel, $payload)->assertOk()->assertJsonPath('id', $created->json('id'));
            Queue::fake();
            $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/{$created->json('conversation_id')}/messages", ['content' => 'Reply '.$provider])->assertOk();
            Queue::assertPushed(SendPrioritizedMessage::class);
            $delivery = PrioritizedDelivery::whereHas('message', fn ($query) => $query->where('account_id', $account->id))->firstOrFail();
            $job = new SendPrioritizedMessage($delivery);
            $job->handle();
            $this->assertSame('sent', $delivery->fresh()->status);
            $this->assertTrue($delivery->message->prioritizedDelivery->is($delivery));
            Http::assertSent(fn ($request) => str_contains($request->url(), $provider === 'telegram' ? 'telegram.org' : ($provider === 'line' ? 'line.me' : 'sms.twoteam.local')));
            if ($provider === 'sms') {
                $this->send($channel, ['id' => 'out-sms', 'status' => 'delivered'])->assertOk()->assertJsonPath('status', 'accepted');
                $this->assertNotNull($delivery->fresh()->delivered_at);
                $this->send($channel, ['id' => 'unknown', 'status' => 'failed'])->assertOk();
            }
            $job->failed(new RuntimeException('provider unavailable'));
            $this->assertSame('provider unavailable', $delivery->fresh()->last_error);
        }
        $this->assertDatabaseCount('inbound_prioritized_messages', 3);
    }

    public function test_signatures_payloads_and_delivery_tenants_are_enforced(): void
    {
        [$account, , $channel] = $this->fixture('telegram');
        $this->postJson("/api/v1/channels/webhook/{$channel->id}", $this->inbound('telegram', 'bad'), ['X-Twoteam-Signature' => 'wrong'])->assertUnauthorized();
        $this->send($channel, ['message' => []])->assertUnprocessable();
        $api = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => 'api', 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $this->postJson("/api/v1/channels/webhook/{$api->id}", [])->assertNotFound();
        [$other, , $otherChannel] = $this->fixture('sms');
        $otherCreated = $this->send($otherChannel, $this->inbound('sms', 'other-in'))->json('conversation_id');
        $foreignMessage = $other->conversations()->where('display_id', $otherCreated)->firstOrFail()->messages()->create(['account_id' => $other->id, 'inbox_id' => $otherChannel->inbox->id, 'content' => 'foreign', 'message_type' => 1]);
        $foreign = $foreignMessage->prioritizedDelivery()->create(['external_message_id' => 'foreign-out']);
        [, , $sms] = $this->fixture('sms');
        $this->send($sms, ['id' => 'foreign-out', 'status' => 'delivered'])->assertOk();
        $this->assertSame('pending', $foreign->fresh()->status);
    }

    private function send(Channel $channel, array $payload)
    {
        return $this->postJson("/api/v1/channels/webhook/{$channel->id}", $payload, ['X-Twoteam-Signature' => hash_hmac('sha256', json_encode($payload), $channel->hmac_token)]);
    }

    private function inbound(string $provider, string $id): array
    {
        return match ($provider) {
            'telegram' => ['message' => ['message_id' => $id, 'chat' => ['id' => 'chat-1'], 'text' => 'Hello telegram']], 'line' => ['events' => [['message' => ['id' => $id, 'text' => 'Hello line'], 'source' => ['userId' => 'line-1']]]], default => ['id' => $id, 'from' => '+15551234567', 'text' => 'Hello sms']
        };
    }

    private function fixture(string $provider): array
    {
        [$account, $headers] = $this->member();
        $credentials = match ($provider) {
            'telegram' => ['bot_token' => 'secret'], 'line' => ['channel_token' => 'secret'], default => ['api_key' => 'secret']
        };
        $response = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes", ['name' => ucfirst($provider), 'channel' => ['type' => $provider, 'external_identity' => $provider === 'sms' ? '+15550000000' : $provider.'-bot', 'provider_credentials' => $credentials]])->assertOk();

        return [$account, $headers, Channel::with(['prioritizedChannel', 'inbox'])->findOrFail($response->json('channel_id')), $response];
    }

    private function member(): array
    {
        $user = User::factory()->create(['password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => 'administrator']);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email]];
    }
}
