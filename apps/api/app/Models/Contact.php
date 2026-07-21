<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'identifier', 'email', 'phone_number', 'blocked', 'additional_attributes', 'custom_attributes', 'last_activity_at'])]
class Contact extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return ['blocked' => 'boolean', 'additional_attributes' => 'array', 'custom_attributes' => 'array', 'last_activity_at' => 'datetime'];
    }
}
