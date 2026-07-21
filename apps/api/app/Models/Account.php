<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'locale', 'domain', 'support_email', 'status', 'settings', 'custom_attributes', 'features'])]
class Account extends Model
{
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function inboxes(): HasMany
    {
        return $this->hasMany(Inbox::class);
    }

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
