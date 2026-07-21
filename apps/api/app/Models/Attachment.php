<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'disk', 'path', 'file_name', 'content_type', 'file_size'])]
class Attachment extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
