<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'conversation_id', 'uuid', 'rating', 'feedback_message', 'responded_at'])]
class CsatSurveyResponse extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }
}
