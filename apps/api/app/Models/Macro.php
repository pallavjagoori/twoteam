<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'name', 'visibility', 'created_by_id', 'updated_by_id', 'actions'])]
class Macro extends Model
{
    public const ACTIONS = ['send_message', 'add_private_note', 'add_label', 'remove_label', 'assign_agent', 'assign_team', 'remove_assigned_agent', 'remove_assigned_team', 'mute_conversation', 'resolve_conversation', 'snooze_conversation', 'change_status', 'change_priority'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    protected function casts(): array
    {
        return ['actions' => 'array'];
    }
}
