<?php

namespace Tests\Feature;

use App\Jobs\SendEmailMessage;
use App\Mail\OutboundConversationMail;
use App\Models\Account;
use App\Models\Channel;
use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_email_channel_with_encrypted_credentials(): void
    {
        [$account, $headers] = $this->member('administrator');
        $response = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes", ['name' => 'Support Email', 'channel' => ['type' => 'email', 'email' => 'support@example.test', 'provider' => 'smtp', 'credentials' => ['username' => 'support', 'password' => 'top-secret'], 'verified_for_sending' => true]])->assertOk()->assertJsonPath('channel_type', 'Channel::Email')->assertJsonPath('email', 'support@example.test');
        $channel = Channel::findOrFail($response->json('channel_id'));
        $email = $channel->emailChannel;
        $this->assertTrue($email->channel->is($channel));
        $this->assertSame('top-secret', $email->encrypted_credentials['password']);
        $this->assertStringNotContainsString('top-secret', (string) $email->getRawOriginal('encrypted_credentials'));
        $this->assertStringContainsString('@', $response->json('forward_to_email'));
    }

    public function test_verified_inbound_email_is_idempotent_and_threads_replies(): void
    {
        [$account, $headers, $channel] = $this->emailFixture();
        $first = ['message_id' => '<incoming-1@example.test>', 'from' => 'visitor@example.test', 'to' => $channel->emailChannel->forward_to_email, 'subject' => 'Need help', 'text' => 'First email'];
        $created = $this->inbound($channel, $first)->assertOk()->assertJsonPath('content', 'First email');
        $this->inbound($channel, $first)->assertOk()->assertJsonPath('id', $created->json('id'));
        $this->assertDatabaseCount('inbound_emails', 1);
        $conversation = $account->conversations()->firstOrFail();

        Queue::fake();
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/{$conversation->display_id}/messages", ['content' => 'Agent response', 'content_attributes' => ['email' => ['subject' => 'Re: Need help']]])->assertOk();
        $delivery = EmailDelivery::firstOrFail();
        $this->assertTrue($delivery->message->emailDelivery->is($delivery));
        Queue::assertPushed(SendEmailMessage::class);

        Mail::fake();
        $job = new SendEmailMessage($delivery);
        $job->handle();
        Mail::assertSent(OutboundConversationMail::class, fn ($mail) => $mail->hasTo('visitor@example.test'));
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $mail = (new OutboundConversationMail($delivery->fresh()->load('message.conversation.inbox.channel.emailChannel')))->build();
        $symfonyMessage = new Email;
        foreach ((fn () => $this->callbacks)->call($mail) as $callback) {
            $callback($symfonyMessage);
        }
        $this->assertSame(trim($delivery->message_id_header, '<>'), $symfonyMessage->getHeaders()->get('Message-ID')->getId());
        $this->assertSame($conversation->uuid, $symfonyMessage->getHeaders()->get('X-Twoteam-Conversation')->getBodyAsString());

        $reply = ['message_id' => '<incoming-2@example.test>', 'in_reply_to' => $delivery->message_id_header, 'from' => 'visitor@example.test', 'to' => $channel->emailChannel->forward_to_email, 'subject' => 'Re: Need help', 'text' => 'Threaded reply'];
        $this->inbound($channel, $reply)->assertOk()->assertJsonPath('conversation_id', $conversation->display_id);
        $uuidReply = ['message_id' => '<incoming-3@example.test>', 'from' => 'visitor@example.test', 'to' => 'reply+'.$conversation->uuid.'@'.$account->domain, 'subject' => 'Re: Need help', 'text' => 'UUID reply'];
        $this->inbound($channel, $uuidReply)->assertOk()->assertJsonPath('conversation_id', $conversation->display_id);
        $this->assertDatabaseCount('conversations', 1);

        $job->failed(new RuntimeException('SMTP unavailable'));
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame('SMTP unavailable', $delivery->fresh()->last_error);
    }

    public function test_email_security_readiness_and_tenant_threading_are_enforced(): void
    {
        [$account, $headers, $channel] = $this->emailFixture(false);
        $payload = ['message_id' => '<blocked@example.test>', 'from' => 'visitor@example.test', 'to' => $channel->emailChannel->forward_to_email, 'subject' => 'Blocked', 'text' => 'Body'];
        $this->postJson("/api/v1/email/inbound/{$channel->id}", $payload, ['X-Twoteam-Signature' => 'wrong'])->assertUnauthorized();
        $conversation = $this->inbound($channel, $payload)->assertOk();
        $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/{$conversation->json('conversation_id')}/messages", ['content' => 'Cannot send'])->assertUnprocessable();

        $apiChannel = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => 'api', 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $this->postJson("/api/v1/email/inbound/{$apiChannel->id}", $payload)->assertNotFound();

        [$other, , $otherChannel] = $this->emailFixture();
        $foreignContact = $other->contacts()->create(['name' => 'Other', 'email' => 'other@example.test']);
        $foreignConversation = $other->conversations()->create(['inbox_id' => $otherChannel->inbox->id, 'contact_id' => $foreignContact->id, 'display_id' => 1, 'uuid' => fake()->uuid(), 'last_activity_at' => now()]);
        $foreignMessage = $foreignConversation->messages()->create(['account_id' => $other->id, 'inbox_id' => $otherChannel->inbox->id, 'content' => 'Other', 'message_type' => 1, 'status' => 'sent']);
        $foreignDelivery = $foreignMessage->emailDelivery()->create(['message_id_header' => '<foreign@example.test>']);
        $cross = ['message_id' => '<cross@example.test>', 'in_reply_to' => $foreignDelivery->message_id_header, 'from' => 'new@example.test', 'to' => $channel->emailChannel->forward_to_email, 'subject' => 'Cross', 'text' => 'Own tenant'];
        $this->inbound($channel, $cross)->assertOk();
        $this->assertDatabaseHas('conversations', ['account_id' => $account->id, 'contact_id' => $account->contacts()->where('email', 'new@example.test')->firstOrFail()->id]);
    }

    private function inbound(Channel $channel, array $payload)
    {
        $signature = hash_hmac('sha256', json_encode($payload), $channel->hmac_token);

        return $this->postJson("/api/v1/email/inbound/{$channel->id}", $payload, ['X-Twoteam-Signature' => $signature]);
    }

    private function emailFixture(bool $verified = true): array
    {
        [$account, $headers] = $this->member('administrator');
        $account->update(['domain' => 'mail.example.test']);
        $response = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/inboxes", ['name' => 'Email', 'channel' => ['type' => 'email', 'email' => fake()->unique()->safeEmail(), 'credentials' => ['password' => 'encrypted'], 'verified_for_sending' => $verified]])->assertOk();

        return [$account, $headers, Channel::with(['emailChannel', 'inbox'])->findOrFail($response->json('channel_id'))];
    }

    private function member(string $role): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => $role]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email]];
    }
}
