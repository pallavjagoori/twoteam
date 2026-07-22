<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['channel_id', 'account_id', 'email', 'forward_to_email', 'provider', 'encrypted_credentials', 'verified_for_sending'])]
class EmailChannel extends Model
{
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    protected function casts(): array
    {
        return ['encrypted_credentials' => 'encrypted:array', 'verified_for_sending' => 'boolean'];
    }
}
