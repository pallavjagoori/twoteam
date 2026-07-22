<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'short_code', 'content'])]
class CannedResponse extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
