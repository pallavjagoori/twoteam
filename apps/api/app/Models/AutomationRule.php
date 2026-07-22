<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'name', 'description', 'event_name', 'active', 'conditions', 'actions'])]
class AutomationRule extends Model
{
    public const EVENTS = ['conversation_created', 'conversation_updated', 'message_created'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'conditions' => 'array', 'actions' => 'array'];
    }
}
