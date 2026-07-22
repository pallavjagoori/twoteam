<?php

namespace App\Support;

use App\Models\Notification;

class NotificationPayload
{
    public static function make(Notification $notification): array
    {
        $conversation = $notification->conversation;
        $body = $notification->secondary_actor_id ? 'New message in conversation #'.$conversation->display_id : 'Conversation #'.$conversation->display_id.' was assigned to you';

        return [
            'id' => $notification->id, 'notification_type' => $notification->notification_type,
            'push_message_title' => $body, 'push_message_body' => $body,
            'primary_actor_type' => $notification->primary_actor_type, 'primary_actor_id' => $notification->primary_actor_id,
            'primary_actor' => ConversationPayload::make($conversation), 'read_at' => $notification->read_at,
            'secondary_actor' => $notification->secondary_actor_id ? ['id' => $notification->secondary_actor_id] : null,
            'user' => ['id' => $notification->user->id, 'name' => $notification->user->name, 'email' => $notification->user->email],
            'created_at' => $notification->created_at->timestamp, 'last_activity_at' => $notification->last_activity_at->timestamp,
            'snoozed_until' => $notification->snoozed_until, 'meta' => $notification->meta ?? [],
        ];
    }
}
