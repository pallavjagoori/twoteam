<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['title', 'description', 'color', 'show_on_sidebar'])]
class Label extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class);
    }

    protected function casts(): array
    {
        return ['show_on_sidebar' => 'boolean'];
    }
}
