<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['account_id', 'name', 'slug', 'custom_domain', 'default_locale', 'archived'])]
class Portal extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(HelpCenterCategory::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(HelpCenterArticle::class);
    }

    protected function casts(): array
    {
        return ['archived' => 'boolean'];
    }
}
