<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['message_id', 'message_id_header', 'status', 'attempts', 'last_error', 'delivered_at'])]
class EmailDelivery extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }
}
