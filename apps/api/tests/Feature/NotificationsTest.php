<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Support\NotificationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_updates_notification_preferences(): void
    {
        [$account, $headers, , $user] = $this->fixture();
        $url = "/api/v1/accounts/{$account->id}/notification_settings";
        $show = $this->withHeaders($headers)->getJson($url)->assertOk()->assertJsonPath('selected_email_flags', []);
        $this->assertTrue($user->notificationSettings()->findOrFail($show->json('id'))->account->is($account));
        $this->assertTrue($account->notificationSettings()->firstOrFail()->user->is($user));
        $this->withHeaders($headers)->patchJson($url, ['notification_settings' => ['selected_email_flags' => ['conversation_assignment'], 'selected_push_flags' => ['assigned_conversation_new_message']]])->assertOk()->assertJsonPath('selected_email_flags.0', 'conversation_assignment');
        $this->withHeaders($headers)->patchJson($url, ['notification_settings' => ['selected_email_flags' => ['invalid'], 'selected_push_flags' => []]])->assertUnprocessable();
        $other = Account::create(['name' => 'Other']);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$other->id}/notification_settings")->assertForbidden();
    }

    public function test_incoming_messages_are_deduplicated_and_published(): void
    {
        [$account, , $conversation, $user] = $this->fixture();
        $first = $this->message($conversation, 'First');
        $notification = NotificationPublisher::incomingMessage($conversation, $first);
        $second = $this->message($conversation, 'Second');
        $same = NotificationPublisher::incomingMessage($conversation, $second);
        $this->assertSame($notification->id, $same->id);
        $this->assertSame($second->id, $same->secondary_actor_id);
        $this->assertTrue($same->account->is($account));
        $this->assertTrue($same->user->is($user));
        $this->assertTrue($same->conversation->is($conversation));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('realtime_events', 2);
        $conversation->update(['assignee_id' => null]);
        $this->assertNull(NotificationPublisher::incomingMessage($conversation->fresh(), $second));
    }

    public function test_member_manages_only_their_notifications(): void
    {
        [$account, $headers, $conversation] = $this->fixture();
        $notification = NotificationPublisher::incomingMessage($conversation, $this->message($conversation, 'Incoming'));
        $base = "/api/v1/accounts/{$account->id}/notifications";
        $this->withHeaders($headers)->getJson($base)->assertOk()->assertJsonPath('data.meta.unread_count', 1)->assertJsonPath('data.payload.0.id', $notification->id);
        $this->withHeaders($headers)->getJson($base.'?sort_order=asc')->assertOk();
        $this->withHeaders($headers)->getJson($base.'/unread_count')->assertOk()->assertContent('1');
        $this->withHeaders($headers)->patchJson($base.'/'.$notification->id)->assertOk();
        $this->withHeaders($headers)->postJson($base.'/'.$notification->id.'/unread')->assertOk()->assertJsonPath('read_at', null);
        $this->withHeaders($headers)->postJson($base.'/read_all', ['primary_actor_type' => 'Conversation', 'primary_actor_id' => $conversation->id])->assertOk();
        $this->withHeaders($headers)->postJson($base.'/'.$notification->id.'/snooze')->assertOk();
        $this->withHeaders($headers)->postJson($base.'/'.$notification->id.'/snooze', ['snoozed_until' => now()->addHour()->toIso8601String()])->assertOk();
        $this->withHeaders($headers)->getJson($base.'/unread_count')->assertOk()->assertContent('0');

        $notification->fresh()->update(['snoozed_until' => null]);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->withHeaders($headers)->postJson($base.'/destroy_all', ['type' => 'read'])->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
        $notification = NotificationPublisher::incomingMessage($conversation, $this->message($conversation, 'Again'));
        $this->withHeaders($headers)->postJson($base.'/read_all')->assertOk();
        $this->withHeaders($headers)->deleteJson($base.'/'.$notification->id)->assertOk();
        NotificationPublisher::incomingMessage($conversation, $this->message($conversation, 'Last'));
        $this->withHeaders($headers)->postJson($base.'/destroy_all')->assertOk();
        $this->assertDatabaseCount('notifications', 0);

        [$otherAccount, $otherHeaders, $otherConversation] = $this->fixture();
        $otherNotification = NotificationPublisher::incomingMessage($otherConversation, $this->message($otherConversation, 'Other'));
        $this->withHeaders($headers)->patchJson($base.'/'.$otherNotification->id)->assertNotFound();
        $this->withHeaders($otherHeaders)->getJson($base)->assertForbidden();
        $this->assertNotSame($account->id, $otherAccount->id);
    }

    private function message($conversation, string $content)
    {
        return $conversation->messages()->create(['account_id' => $conversation->account_id, 'inbox_id' => $conversation->inbox_id, 'content' => $content, 'message_type' => 0, 'status' => 'sent']);
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => 'agent']);
        $channel = $account->channels()->create(['type' => 'web_widget', 'settings' => [], 'identifier' => (string) Str::uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Inbox', 'channel_id' => $channel->id]);
        $contact = $account->contacts()->create(['name' => 'Visitor']);
        $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'assignee_id' => $user->id, 'display_id' => 1, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);
        $headers = ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email];

        return [$account, $headers, $conversation, $user];
    }
}
