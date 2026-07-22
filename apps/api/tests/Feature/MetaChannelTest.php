<?php

namespace Tests\Feature;

use App\Jobs\SendMetaMessage;
use App\Models\Account;
use App\Models\Channel;
use App\Models\MetaDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class MetaChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_and_instagram_credentials_are_encrypted_and_verifiable(): void
    {
        foreach (['facebook' => 'Channel::FacebookPage', 'instagram' => 'Channel::Instagram'] as $platform => $type) {
            [, , $channel, $response] = $this->fixture($platform);
            $response->assertJsonPath('channel_type', $type)->assertJsonPath('external_account_name', ucfirst($platform))->assertJsonMissingPath('access_token');
            $this->assertSame('meta-secret', $channel->metaChannel->encrypted_credentials['access_token']);
            $this->assertStringNotContainsString('meta-secret', $channel->metaChannel->getRawOriginal('encrypted_credentials'));
            $this->assertTrue($channel->metaChannel->channel->is($channel));
            $this->get("/api/v1/meta/webhook/{$channel->id}?hub_verify_token=wrong&hub_challenge=42")->assertForbidden();
            $this->get("/api/v1/meta/webhook/{$channel->id}?hub_verify_token={$channel->secret}&hub_challenge=42")->assertOk()->assertSee('42');
        }
    }

    public function test_signed_meta_messages_are_idempotent_and_normalize_attachments(): void
    {
        [$account, , $channel] = $this->fixture('facebook');
        $text = $this->message('mid.in.1', ['text' => 'Hello']);
        $created = $this->send($channel, $text)->assertOk()->assertJsonPath('content', 'Hello');
        $this->send($channel, $text)->assertOk()->assertJsonPath('id', $created->json('id'));
        $attachment = $this->message('mid.in.2', ['attachments' => [['type' => 'image', 'payload' => ['url' => 'https://cdn.example/image.jpg']]]]);
        $this->send($channel, $attachment)->assertOk()->assertJsonPath('content', 'https://cdn.example/image.jpg')->assertJsonPath('content_attributes.meta.platform', 'facebook');
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('inbound_meta_messages', 2);
        $this->postJson("/api/v1/meta/webhook/{$channel->id}", $text, ['X-Hub-Signature-256' => 'sha256=wrong'])->assertUnauthorized();
        $api = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => 'api', 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $this->postJson("/api/v1/meta/webhook/{$api->id}", $text)->assertNotFound();
        $this->get("/api/v1/meta/webhook/{$api->id}?hub_verify_token=secret&hub_challenge=1")->assertForbidden();
    }

    public function test_outbound_delivery_callbacks_and_tenant_isolation(): void
    {
        [$account, $headers, $channel] = $this->fixture('instagram');
        $displayId = $this->send($channel, $this->message('mid.seed', ['text' => 'Start']))->json('conversation_id');
        Queue::fake();
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/{$displayId}/messages", ['content' => 'Reply'])->assertOk();
        Queue::assertPushed(SendMetaMessage::class);
        $delivery = MetaDelivery::firstOrFail();
        Http::fake(['*' => Http::response(['message_id' => 'mid.out.1'])]);
        $job = new SendMetaMessage($delivery);
        $job->handle();
        Http::assertSent(fn ($request) => $request['recipient']['id'] === 'sender-1' && $request['message']['text'] === 'Reply');
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertTrue($delivery->message->metaDelivery->is($delivery));
        $this->send($channel, $this->metaCallback('delivery', ['mids' => ['mid.out.1']]))->assertOk()->assertJsonPath('status', 'accepted');
        $this->send($channel, $this->metaCallback('read', ['mid' => 'mid.out.1']))->assertOk();
        $fresh = $delivery->fresh();
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNotNull($fresh->read_at);
        $this->assertSame('read', $fresh->status);
        $this->send($channel, $this->metaCallback('delivery', ['mids' => ['unknown']]))->assertOk();
        $job->failed(new RuntimeException('Meta unavailable'));
        $this->assertSame('Meta unavailable', $delivery->fresh()->last_error);

        [$other, , $otherChannel] = $this->fixture('facebook');
        $otherDisplay = $this->send($otherChannel, $this->message('other-in', ['text' => 'Other']))->json('conversation_id');
        $foreignMessage = $other->conversations()->where('display_id', $otherDisplay)->firstOrFail()->messages()->create(['account_id' => $other->id, 'inbox_id' => $otherChannel->inbox->id, 'content' => 'Foreign', 'message_type' => 1]);
        $foreign = $foreignMessage->metaDelivery()->create(['external_message_id' => 'foreign-out']);
        $this->send($channel, $this->metaCallback('read', ['mid' => 'foreign-out']))->assertOk();
        $this->assertSame('pending', $foreign->fresh()->status);
    }

    private function send(Channel $channel, array $payload)
    {
        $signature = hash_hmac('sha256', json_encode($payload), $channel->hmac_token);

        return $this->postJson("/api/v1/meta/webhook/{$channel->id}", $payload, ['X-Hub-Signature-256' => 'sha256='.$signature]);
    }

    private function message(string $id, array $message): array
    {
        return ['entry' => [['messaging' => [['sender' => ['id' => 'sender-1'], 'message' => ['mid' => $id] + $message]]]]];
    }

    private function metaCallback(string $type, array $value): array
    {
        return ['entry' => [['messaging' => [[$type => $value]]]]];
    }

    private function fixture(string $platform): array
    {
        [$account, $headers] = $this->member();
        $response = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes", ['name' => ucfirst($platform), 'channel' => ['type' => $platform, 'external_account_id' => fake()->unique()->numerify('########'), 'external_account_name' => ucfirst($platform), 'access_token' => 'meta-secret']])->assertOk();

        return [$account, $headers, Channel::with(['metaChannel', 'inbox'])->findOrFail($response->json('channel_id')), $response];
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
