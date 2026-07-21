<?php

namespace App\Support;

use App\Models\Attachment;
use Illuminate\Support\Facades\URL;

class AttachmentPayload
{
    public static function make(Attachment $attachment): array
    {
        $url = URL::temporarySignedRoute('attachments.download', now()->addMinutes(5), ['attachment' => $attachment->id]);
        $mediaType = str($attachment->content_type)->before('/')->toString();
        $fileType = in_array($mediaType, ['image', 'audio', 'video'], true) ? $mediaType : 'file';

        return [
            'id' => $attachment->id,
            'message_id' => $attachment->message_id,
            'account_id' => $attachment->account_id,
            'file_type' => $fileType,
            'content_type' => $attachment->content_type,
            'extension' => pathinfo($attachment->file_name, PATHINFO_EXTENSION),
            'file_size' => $attachment->file_size,
            'data_url' => $url,
            'thumb_url' => $fileType === 'image' ? $url : '',
            'width' => null,
            'height' => null,
            'created_at' => $attachment->message->created_at->timestamp,
            'sender' => $attachment->message->sender ? [
                'id' => $attachment->message->sender->id,
                'name' => $attachment->message->sender->name,
                'email' => $attachment->message->sender->email,
                'type' => 'user',
            ] : null,
        ];
    }
}
