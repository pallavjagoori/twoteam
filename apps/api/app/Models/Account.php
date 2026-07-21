<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'locale', 'domain', 'support_email', 'status', 'settings', 'custom_attributes', 'features'])]
class Account extends Model
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_users')
            ->withPivot(['role', 'availability', 'auto_offline', 'permissions', 'active_at'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return ['settings' => 'array', 'custom_attributes' => 'array', 'features' => 'array'];
    }
}
