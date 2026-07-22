<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;

class NotificationPublisher
{
    public static function incomingMessage(Conversation $conversation, Message $message): ?Notification
    {
        if (! $conversation->assignee_id) {
            return null;
        }

        $notification = Notification::updateOrCreate(
            ['account_id' => $conversation->account_id, 'user_id' => $conversation->assignee_id, 'primary_actor_type' => 'Conversation', 'primary_actor_id' => $conversation->id],
            ['notification_type' => 'assigned_conversation_new_message', 'secondary_actor_type' => 'Message', 'secondary_actor_id' => $message->id, 'read_at' => null, 'snoozed_until' => null, 'last_activity_at' => now(), 'meta' => []],
        );
        $notification->load(['conversation.contact', 'conversation.inbox.channel', 'user']);
        RealtimePublisher::publish($conversation->account_id, 'notification.created', NotificationPayload::make($notification));

        return $notification;
    }
}
