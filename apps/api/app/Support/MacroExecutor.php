<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Macro;
use App\Models\User;

class MacroExecutor
{
    public static function run(Macro $macro, Conversation $conversation, User $user): void
    {
        foreach ($macro->actions as $action) {
            self::apply($conversation, $user, $action['action_name'], $action['action_params'] ?? []);
        }
        RealtimePublisher::publish($conversation->account_id, 'conversation.updated', ConversationPayload::make($conversation->fresh(['contact', 'inbox.channel'])));
    }

    private static function apply(Conversation $conversation, User $user, string $name, array $params): void
    {
        match ($name) {
            'send_message' => self::message($conversation, $user, $params, false),
            'add_private_note' => self::message($conversation, $user, $params, true),
            'add_label' => $conversation->labels()->syncWithoutDetaching($conversation->account->labels()->whereIn('title', $params)->pluck('id')),
            'remove_label' => $conversation->labels()->detach($conversation->account->labels()->whereIn('title', $params)->pluck('id')),
            'assign_agent' => $conversation->update(['assignee_id' => self::memberId($conversation, $user, $params[0] ?? null)]),
            'assign_team' => $conversation->update(['team_id' => $conversation->account->teams()->whereKey($params[0] ?? null)->value('id')]),
            'remove_assigned_agent' => $conversation->update(['assignee_id' => null]),
            'remove_assigned_team' => $conversation->update(['team_id' => null]),
            'mute_conversation' => $conversation->update(['muted' => true]),
            'resolve_conversation' => $conversation->update(['status' => 'resolved']),
            'snooze_conversation' => $conversation->update(['status' => 'snoozed']),
            'change_status' => $conversation->update(['status' => $params[0]]),
            'change_priority' => $conversation->update(['priority' => $params[0]]),
        };
    }

    private static function message(Conversation $conversation, User $user, array $params, bool $private): void
    {
        $message = $conversation->messages()->create(['account_id' => $conversation->account_id, 'inbox_id' => $conversation->inbox_id, 'sender_id' => $user->id, 'content' => $params[0] ?? '', 'private' => $private, 'message_type' => 1, 'status' => 'sent']);
        RealtimePublisher::publish($conversation->account_id, 'message.created', MessagePayload::make($message->load(['conversation', 'sender'])));
    }

    private static function memberId(Conversation $conversation, User $user, mixed $id): ?int
    {
        $id = $id === 'self' ? $user->id : $id;

        return $conversation->account->users()->whereKey($id)->value('users.id');
    }
}
