<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\User;
use App\Policies\AccountPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_covers_member_admin_and_outsider_permissions(): void
    {
        $account = Account::create(['name' => 'Tenant']);
        $agent = User::factory()->create();
        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        $account->users()->attach($agent, ['role' => 'agent']);
        $account->users()->attach($admin, ['role' => 'administrator']);
        $policy = new AccountPolicy;

        $this->assertTrue($policy->view($agent, $account));
        $this->assertTrue($policy->updateActiveAt($agent, $account));
        $this->assertFalse($policy->update($agent, $account));
        $this->assertTrue($policy->update($admin, $account));
        $this->assertFalse($policy->view($outsider, $account));
        $this->assertFalse($policy->updateActiveAt($outsider, $account));
    }
}
