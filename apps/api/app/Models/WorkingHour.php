<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'inbox_id', 'day_of_week', 'closed_all_day', 'open_all_day', 'open_hour', 'open_minutes', 'close_hour', 'close_minutes'])]
class WorkingHour extends Model
{
    public function inbox(): BelongsTo
    {
        return $this->belongsTo(Inbox::class);
    }

    public function isOpenAt(CarbonInterface $instant): bool
    {
        $local = $instant->copy()->setTimezone($this->inbox->timezone);
        if ($local->dayOfWeek !== $this->day_of_week || $this->closed_all_day) {
            return false;
        }
        if ($this->open_all_day) {
            return true;
        }
        $minutes = ($local->hour * 60) + $local->minute;

        return $minutes >= (($this->open_hour * 60) + $this->open_minutes) && $minutes <= (($this->close_hour * 60) + $this->close_minutes);
    }

    protected function casts(): array
    {
        return ['closed_all_day' => 'boolean', 'open_all_day' => 'boolean'];
    }
}
