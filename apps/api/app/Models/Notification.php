<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'user_id', 'notification_type', 'primary_actor_type', 'primary_actor_id', 'secondary_actor_type', 'secondary_actor_id', 'read_at', 'snoozed_until', 'last_activity_at', 'meta'])]
class Notification extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'primary_actor_id');
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'snoozed_until' => 'datetime', 'last_activity_at' => 'datetime', 'meta' => 'array'];
    }
}
