<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['channel_id', 'account_id', 'phone_number', 'phone_number_id', 'business_account_id', 'encrypted_credentials', 'provider'])]
class WhatsappChannel extends Model
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
