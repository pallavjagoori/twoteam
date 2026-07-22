<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }

    protected static function booted(): void
    {
        static::created(function (Inbox $inbox) {
            foreach (range(0, 6) as $day) {
                $weekend = in_array($day, [0, 6], true);
                $inbox->workingHours()->create(['account_id' => $inbox->account_id, 'day_of_week' => $day, 'closed_all_day' => $weekend, 'open_all_day' => false, 'open_hour' => $weekend ? null : 9, 'open_minutes' => $weekend ? null : 0, 'close_hour' => $weekend ? null : 17, 'close_minutes' => $weekend ? null : 0]);
            }
        });
    }

    protected function casts(): array
    {
        return ['greeting_enabled' => 'boolean', 'enable_email_collect' => 'boolean', 'csat_survey_enabled' => 'boolean', 'enable_auto_assignment' => 'boolean', 'working_hours_enabled' => 'boolean', 'allow_messages_after_resolved' => 'boolean', 'lock_to_single_conversation' => 'boolean', 'csat_config' => 'array'];
    }
}
