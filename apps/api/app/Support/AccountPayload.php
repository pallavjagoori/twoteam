<?php

namespace App\Support;

use App\Models\Account;

class AccountPayload
{
    public static function make(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'locale' => $account->locale,
            'domain' => $account->domain,
            'support_email' => $account->support_email,
            'status' => $account->status,
            'settings' => $account->settings ?? [],
            'custom_attributes' => $account->custom_attributes ?? [],
            'features' => $account->features ?? [],
            'cache_keys' => ['label' => null, 'inbox' => null, 'team' => null],
            'created_at' => $account->created_at,
        ];
    }
}
