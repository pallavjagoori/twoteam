<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['channel_id', 'account_id', 'provider', 'external_identity', 'encrypted_credentials'])]
class PrioritizedChannel extends Model
{
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    protected function casts(): array
    {
        return ['encrypted_credentials' => 'encrypted:array'];
    }
}
