<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $this->role($user, $account) !== null;
    }

    public function update(User $user, Account $account): bool
    {
        return $this->role($user, $account) === 'administrator';
    }

    public function updateActiveAt(User $user, Account $account): bool
    {
        return $this->view($user, $account);
    }

    private function role(User $user, Account $account): ?string
    {
        return $user->accounts()->whereKey($account->id)->value('account_users.role');
    }
}
