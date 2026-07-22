<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['portal_id', 'help_center_category_id', 'author_id', 'title', 'slug', 'content', 'locale', 'status', 'position', 'published_at'])]
class HelpCenterArticle extends Model
{
    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCenterCategory::class, 'help_center_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
