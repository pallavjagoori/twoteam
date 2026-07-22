<?php

namespace App\Support;

use App\Models\Macro;
use App\Models\User;

class MacroPayload
{
    public static function make(Macro $macro): array
    {
        return [
            'id' => $macro->id, 'name' => $macro->name, 'visibility' => $macro->visibility,
            'created_by' => self::user($macro->createdBy), 'updated_by' => self::user($macro->updatedBy),
            'account_id' => $macro->account_id, 'actions' => $macro->actions, 'files' => [],
        ];
    }

    private static function user(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null;
    }
}
