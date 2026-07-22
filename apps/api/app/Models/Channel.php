<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['type', 'settings', 'identifier', 'secret', 'hmac_token'])]
class Channel extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function inbox(): HasOne
    {
        return $this->hasOne(Inbox::class);
    }

    public function emailChannel(): HasOne
    {
        return $this->hasOne(EmailChannel::class);
    }

    public function whatsappChannel(): HasOne
    {
        return $this->hasOne(WhatsappChannel::class);
    }

    public function metaChannel(): HasOne
    {
        return $this->hasOne(MetaChannel::class);
    }

    public function prioritizedChannel(): HasOne
    {
        return $this->hasOne(PrioritizedChannel::class);
    }

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
