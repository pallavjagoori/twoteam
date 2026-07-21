<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['inbox_id', 'contact_id', 'assignee_id', 'team_id', 'display_id', 'uuid', 'status', 'priority', 'muted', 'additional_attributes', 'custom_attributes', 'agent_last_seen_at', 'assignee_last_seen_at', 'contact_last_seen_at', 'last_activity_at', 'snoozed_until'])]
class Conversation extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(Inbox::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    protected function casts(): array
    {
        return ['muted' => 'boolean', 'additional_attributes' => 'array', 'custom_attributes' => 'array', 'agent_last_seen_at' => 'datetime', 'assignee_last_seen_at' => 'datetime', 'contact_last_seen_at' => 'datetime', 'last_activity_at' => 'datetime', 'snoozed_until' => 'datetime'];
    }
}
