<?php

namespace Tests\Feature;

use App\Jobs\DeliverOutgoingWebhook;
use App\Models\Account;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Support\OutgoingWebhookPublisher;
use App\Support\RealtimePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class OutgoingWebhooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_manages_scoped_webhook_subscriptions(): void
    {
        [$account, $headers] = $this->member('administrator');
        $created = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/webhooks", ['name' => 'CRM', 'url' => 'https://hooks.example.test/events', 'events' => ['message.created']])->assertOk()->assertJsonPath('name', 'CRM');
        $this->assertSame(48, strlen($created->json('secret')));
        $item = $account->webhookSubscriptions()->firstOrFail();
        $this->assertTrue($item->account->is($account));
        $this->assertStringNotContainsString($created->json('secret'), $item->getRawOriginal('encrypted_secret'));
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/webhooks")->assertOk()->assertJsonCount(1, 'payload');
        $this->withHeaders($headers)->patchJson("/api/v1/accounts/{$account->id}/webhooks/{$item->id}", ['name' => 'Updated', 'events' => ['conversation.updated'], 'active' => false])->assertOk()->assertJsonPath('name', 'Updated')->assertJsonPath('active', false);
        [$other, $otherHeaders] = $this->member('administrator');
        $this->withHeaders($otherHeaders)->patchJson("/api/v1/accounts/{$other->id}/webhooks/{$item->id}", ['name' => 'Cross'])->assertNotFound();
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/webhooks/{$item->id}")->assertOk()->assertJsonPath('message', 'Webhook deleted');
    }

    public function test_delivery_is_signed_logged_retried_and_failure_aware(): void
    {
        [$account, $headers] = $this->member('administrator');
        $secret = '0123456789abcdef';
        $created = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/webhooks", ['name' => 'Events', 'url' => 'https://hooks.example.test/events', 'events' => ['message.created'], 'secret' => $secret])->assertOk();
        Queue::fake();
        RealtimePublisher::publish($account->id, 'message.created', ['id' => 42]);
        Queue::assertPushed(DeliverOutgoingWebhook::class);
        $delivery = WebhookDelivery::firstOrFail();
        $this->assertTrue($delivery->subscription->deliveries->first()->is($delivery));
        Http::fakeSequence()->push(['ok' => true], 202)->push([], 503);
        $job = new DeliverOutgoingWebhook($delivery);
        $job->handle();
        Http::assertSent(function ($request) use ($secret, $delivery) {
            $body = $request->body();

            return $request->hasHeader('X-Twoteam-Event-Id', $delivery->event_id) && $request->hasHeader('X-Twoteam-Signature-256', 'sha256='.hash_hmac('sha256', $body, $secret));
        });
        $fresh = $delivery->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertSame(202, $fresh->response_status);
        $this->assertNotNull($fresh->delivered_at);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/webhooks")->assertOk()->assertJsonPath('payload.0.deliveries.0.status', 'delivered');
        $failed = $delivery->subscription->deliveries()->create(['event_id' => fake()->uuid(), 'event' => 'message.created', 'payload' => ['event' => 'message.created']]);
        $this->expectException(RuntimeException::class);
        try {
            (new DeliverOutgoingWebhook($failed))->handle();
        } finally {
            (new DeliverOutgoingWebhook($failed))->failed(new RuntimeException('Retries exhausted'));
            $this->assertSame('failed', $failed->fresh()->status);
        }
    }

    public function test_inactive_unsubscribed_invalid_and_unauthorized_webhooks_are_rejected(): void
    {
        [$account, $admin] = $this->member('administrator');
        [, $agent] = $this->member('agent', $account);
        $this->withHeaders($agent)->postJson("/api/v1/accounts/{$account->id}/webhooks", ['name' => 'No', 'url' => 'https://hooks.example.test', 'events' => ['message.created']])->assertForbidden();
        $this->withHeaders($admin)->postJson("/api/v1/accounts/{$account->id}/webhooks", ['name' => 'Bad', 'url' => 'http://hooks.example.test', 'events' => ['unknown'], 'secret' => 'short'])->assertUnprocessable();
        $account->webhookSubscriptions()->create(['name' => 'Inactive', 'url' => 'https://inactive.test', 'events' => ['message.created'], 'encrypted_secret' => str_repeat('a', 16), 'active' => false]);
        $account->webhookSubscriptions()->create(['name' => 'Other event', 'url' => 'https://other.test', 'events' => ['conversation.updated'], 'encrypted_secret' => str_repeat('b', 16)]);
        Queue::fake();
        $this->assertSame([], OutgoingWebhookPublisher::publish($account->id, 'message.created', ['id' => 1]));
        Queue::assertNothingPushed();
    }

    private function member(string $role, ?Account $account = null): array
    {
        $user = User::factory()->create(['password' => Hash::make('Password1!')]);
        $account ??= Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => $role]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email]];
    }
}
