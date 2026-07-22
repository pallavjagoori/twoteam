<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['portal_id', 'name', 'slug', 'locale', 'position'])]
class HelpCenterCategory extends Model
{
    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(HelpCenterArticle::class);
    }
}
