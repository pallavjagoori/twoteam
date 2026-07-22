<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappMessage;
use App\Models\Account;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsappDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class WhatsappChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_whatsapp_channel_with_encrypted_credentials(): void
    {
        [$account, $headers] = $this->member();
        $response = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes", $this->inboxPayload())->assertOk()->assertJsonPath('channel_type', 'Channel::Whatsapp')->assertJsonPath('phone_number', '+15550001111')->assertJsonMissingPath('provider_config.api_key');
        $channel = Channel::findOrFail($response->json('channel_id'));
        $this->assertSame('token-secret', $channel->whatsappChannel->encrypted_credentials['api_key']);
        $this->assertStringNotContainsString('token-secret', $channel->whatsappChannel->getRawOriginal('encrypted_credentials'));
        $this->assertTrue($channel->whatsappChannel->channel->is($channel));
        $this->get("/api/v1/whatsapp/webhook/{$channel->id}?hub_verify_token=wrong&hub_challenge=123")->assertForbidden();
        $this->get("/api/v1/whatsapp/webhook/{$channel->id}?hub_verify_token={$channel->secret}&hub_challenge=123")->assertOk()->assertSee('123');
    }

    public function test_signed_inbound_text_and_media_are_idempotent(): void
    {
        [$account, , $channel] = $this->fixture();
        $text = $this->webhook('wamid.in.1', 'text', ['body' => 'Hello']);
        $created = $this->send($channel, $text)->assertOk()->assertJsonPath('content', 'Hello');
        $this->send($channel, $text)->assertOk()->assertJsonPath('id', $created->json('id'));
        $media = $this->webhook('wamid.in.2', 'image', ['id' => 'media-1', 'caption' => 'Screenshot']);
        $this->send($channel, $media)->assertOk()->assertJsonPath('content_attributes.whatsapp.media_id', 'media-1');
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('inbound_whatsapp_messages', 2);
        $this->postJson("/api/v1/whatsapp/webhook/{$channel->id}", $text, ['X-Hub-Signature-256' => 'sha256=wrong'])->assertUnauthorized();
        $api = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => 'api', 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $this->postJson("/api/v1/whatsapp/webhook/{$api->id}", $text)->assertNotFound();
        $this->get("/api/v1/whatsapp/webhook/{$api->id}?hub_verify_token=secret&hub_challenge=1")->assertForbidden();
    }

    public function test_outbound_text_media_retries_and_delivery_callbacks_are_scoped(): void
    {
        [$account, $headers, $channel] = $this->fixture();
        $conversation = $this->send($channel, $this->webhook('wamid.seed', 'text', ['body' => 'Start']))->json('conversation_id');
        Queue::fake();
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/{$conversation}/messages", ['content' => 'Text reply'])->assertOk();
        Queue::assertPushed(SendWhatsappMessage::class);
        $delivery = WhatsappDelivery::firstOrFail();
        Http::fakeSequence()->push(['messages' => [['id' => 'wamid.out.1']]])->push(['messages' => [['id' => 'wamid.out.2']]]);
        $job = new SendWhatsappMessage($delivery);
        $job->handle();
        Http::assertSent(fn ($request) => $request['type'] === 'text' && $request['to'] === '15551234567');
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertTrue($delivery->message->whatsappDelivery->is($delivery));

        Storage::fake('local');
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/{$conversation}/messages", ['content' => 'File', 'attachments' => [UploadedFile::fake()->create('guide.pdf', 2, 'application/pdf')]])->assertOk();
        $media = WhatsappDelivery::latest('id')->firstOrFail();
        (new SendWhatsappMessage($media))->handle();
        Http::assertSent(fn ($request) => $request['type'] === 'document' && $request['document']['filename'] === 'guide.pdf');

        foreach (['delivered', 'read', 'failed'] as $status) {
            $payload = $this->statusWebhook('wamid.out.1', $status);
            $this->send($channel, $payload)->assertOk()->assertJsonPath('status', 'accepted');
        }
        $fresh = $delivery->fresh();
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNotNull($fresh->read_at);
        $this->assertSame('Provider rejected', $fresh->last_error);
        $this->send($channel, $this->statusWebhook('unknown', 'sent'))->assertOk();
        $job->failed(new RuntimeException('network down'));
        $this->assertSame('network down', $delivery->fresh()->last_error);

        [$other, , $otherChannel] = $this->fixture();
        $foreignConversation = $this->send($otherChannel, $this->webhook('foreign-in', 'text', ['body' => 'Foreign']))->json('conversation_id');
        $foreign = $other->conversations()->where('display_id', $foreignConversation)->firstOrFail()->messages()->create(['account_id' => $other->id, 'inbox_id' => $otherChannel->inbox->id, 'content' => 'Foreign out', 'message_type' => 1]);
        $foreignDelivery = $foreign->whatsappDelivery()->create(['external_message_id' => 'foreign-out']);
        $this->send($channel, $this->statusWebhook('foreign-out', 'read'))->assertOk();
        $this->assertSame('pending', $foreignDelivery->fresh()->status);
    }

    private function send(Channel $channel, array $payload)
    {
        $signature = hash_hmac('sha256', json_encode($payload), $channel->hmac_token);

        return $this->postJson("/api/v1/whatsapp/webhook/{$channel->id}", $payload, ['X-Hub-Signature-256' => 'sha256='.$signature]);
    }

    private function webhook(string $id, string $type, array $body): array
    {
        $value = ['contacts' => [['profile' => ['name' => 'Visitor']]], 'messages' => [['id' => $id, 'from' => '15551234567', 'type' => $type, $type => $body]]];

        return ['entry' => [['changes' => [['value' => $value]]]]];
    }

    private function statusWebhook(string $id, string $status): array
    {
        $value = ['statuses' => [['id' => $id, 'status' => $status, 'errors' => [['title' => 'Provider rejected']]]]];

        return ['entry' => [['changes' => [['value' => $value]]]]];
    }

    private function fixture(): array
    {
        [$account, $headers] = $this->member();
        $response = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes", $this->inboxPayload())->assertOk();

        return [$account, $headers, Channel::with(['whatsappChannel', 'inbox'])->findOrFail($response->json('channel_id'))];
    }

    private function inboxPayload(): array
    {
        return ['name' => 'WhatsApp', 'channel' => ['type' => 'whatsapp', 'phone_number' => '+15550001111', 'provider' => 'whatsapp_cloud', 'provider_config' => ['api_key' => 'token-secret', 'phone_number_id' => fake()->unique()->numerify('########'), 'business_account_id' => '999']]];
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
