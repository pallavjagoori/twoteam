<?php

namespace App\Support;

use App\Models\Message;

class MessagePayload
{
    public static function make(Message $message): array
    {
        $payload = [
            'id' => $message->id, 'content' => $message->content, 'inbox_id' => $message->inbox_id,
            'conversation_id' => $message->conversation->display_id, 'message_type' => $message->message_type,
            'content_type' => $message->content_type, 'status' => $message->status,
            'content_attributes' => $message->content_attributes ?? [], 'created_at' => $message->created_at->timestamp,
            'private' => $message->private, 'source_id' => $message->source_id,
        ];
        if ($message->echo_id) {
            $payload['echo_id'] = $message->echo_id;
        }
        if ($message->sender) {
            $payload['sender'] = ['id' => $message->sender->id, 'name' => $message->sender->name, 'email' => $message->sender->email, 'type' => 'user'];
        }
        if ($message->attachments->isNotEmpty()) {
            $payload['attachments'] = $message->attachments->map(fn ($attachment) => AttachmentPayload::make($attachment))->all();
        }

        return $payload;
    }
}
