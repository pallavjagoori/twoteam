<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['channel_id', 'name', 'greeting_enabled', 'greeting_message', 'enable_email_collect', 'csat_survey_enabled', 'enable_auto_assignment', 'working_hours_enabled', 'out_of_office_message', 'timezone', 'allow_messages_after_resolved', 'lock_to_single_conversation', 'csat_config'])]
class Inbox extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    protected function casts(): array
    {
        return ['greeting_enabled' => 'boolean', 'enable_email_collect' => 'boolean', 'csat_survey_enabled' => 'boolean', 'enable_auto_assignment' => 'boolean', 'working_hours_enabled' => 'boolean', 'allow_messages_after_resolved' => 'boolean', 'lock_to_single_conversation' => 'boolean', 'csat_config' => 'array'];
    }
}
