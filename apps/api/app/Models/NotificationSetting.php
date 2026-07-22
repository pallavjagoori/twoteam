<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'user_id', 'selected_email_flags', 'selected_push_flags'])]
class NotificationSetting extends Model
{
    public const FLAGS = [
        'conversation_creation', 'conversation_assignment', 'assigned_conversation_new_message',
        'conversation_mention', 'participating_conversation_new_message', 'sla_missed_first_response',
        'sla_missed_next_response', 'sla_missed_resolution',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['selected_email_flags' => 'array', 'selected_push_flags' => 'array'];
    }
}
