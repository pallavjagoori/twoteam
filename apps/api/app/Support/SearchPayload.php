<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;

class SearchPayload
{
    public static function contact(Contact $contact): array
    {
        return ['email' => $contact->email, 'id' => $contact->id, 'name' => $contact->name, 'phone_number' => $contact->phone_number, 'identifier' => $contact->identifier, 'additional_attributes' => $contact->additional_attributes ?? [], 'last_activity_at' => $contact->last_activity_at?->timestamp];
    }

    public static function conversation(Conversation $conversation): array
    {
        return [
            'id' => $conversation->display_id, 'account_id' => $conversation->account_id, 'created_at' => $conversation->created_at->timestamp,
            'message' => $conversation->messages->first() ? self::message($conversation->messages->first()) : null,
            'contact' => self::contact($conversation->contact),
            'inbox' => ['id' => $conversation->inbox->id, 'channel_id' => $conversation->inbox->channel_id, 'name' => $conversation->inbox->name, 'channel_type' => 'Channel::'.($conversation->inbox->channel->type === 'web_widget' ? 'WebWidget' : 'Api')],
            'agent' => $conversation->assignee ? ['id' => $conversation->assignee->id, 'available_name' => $conversation->assignee->display_name ?: $conversation->assignee->name, 'email' => $conversation->assignee->email, 'name' => $conversation->assignee->name] : null,
            'additional_attributes' => $conversation->additional_attributes ?? [],
        ];
    }

    public static function message(Message $message): array
    {
        return MessagePayload::make($message);
    }
}
