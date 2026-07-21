<?php

namespace App\Support;

use App\Models\Conversation;

class ConversationPayload
{
    public static function make(Conversation $conversation): array
    {
        return [
            'meta' => ['sender' => ContactPayload::make($conversation->contact), 'channel' => 'Channel::'.($conversation->inbox->channel->type === 'web_widget' ? 'WebWidget' : 'Api'), 'hmac_verified' => false],
            'id' => $conversation->display_id, 'messages' => [], 'account_id' => $conversation->account_id,
            'uuid' => $conversation->uuid, 'additional_attributes' => $conversation->additional_attributes ?? [],
            'agent_last_seen_at' => $conversation->agent_last_seen_at?->timestamp ?? 0,
            'assignee_last_seen_at' => $conversation->assignee_last_seen_at?->timestamp ?? 0,
            'can_reply' => true, 'contact_last_seen_at' => $conversation->contact_last_seen_at?->timestamp ?? 0,
            'custom_attributes' => $conversation->custom_attributes ?? [], 'inbox_id' => $conversation->inbox_id,
            'labels' => [], 'muted' => $conversation->muted, 'snoozed_until' => $conversation->snoozed_until,
            'status' => $conversation->status, 'created_at' => $conversation->created_at->timestamp,
            'updated_at' => (float) $conversation->updated_at->format('U.u'), 'timestamp' => $conversation->last_activity_at->timestamp,
            'first_reply_created_at' => 0, 'unread_count' => 0, 'last_non_activity_message' => null,
            'last_activity_at' => $conversation->last_activity_at->timestamp, 'priority' => $conversation->priority,
            'waiting_since' => 0, 'sla_policy_id' => null,
        ];
    }
}
