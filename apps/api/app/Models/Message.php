<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['account_id', 'inbox_id', 'sender_id', 'content', 'echo_id', 'message_type', 'content_type', 'status', 'content_attributes', 'private', 'source_id', 'external_error'])]
class Message extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(Inbox::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function emailDelivery(): HasOne
    {
        return $this->hasOne(EmailDelivery::class);
    }

    public function whatsappDelivery(): HasOne
    {
        return $this->hasOne(WhatsappDelivery::class);
    }

    public function metaDelivery(): HasOne
    {
        return $this->hasOne(MetaDelivery::class);
    }

    public function prioritizedDelivery(): HasOne
    {
        return $this->hasOne(PrioritizedDelivery::class);
    }

    protected function casts(): array
    {
        return ['content_attributes' => 'array', 'private' => 'boolean'];
    }
}
