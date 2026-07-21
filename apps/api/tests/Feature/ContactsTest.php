<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creates_lists_shows_updates_and_searches_contacts(): void
    {
        [$account, $headers] = $this->member('administrator');
        $created = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/contacts", [
            'name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'phone_number' => '+441234',
            'identifier' => 'reference-ada', 'custom_attributes' => ['tier' => 'gold'],
        ])->assertOk()->assertJsonPath('payload.contact.availability_status', 'offline');
        $id = $created->json('payload.contact.id');
        $contact = $account->contacts()->findOrFail($id);
        $this->assertTrue($contact->account->is($account));

        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/contacts")
            ->assertOk()->assertJsonPath('meta.count', 1)->assertJsonPath('payload.0.email', 'ada@example.test');
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/contacts/{$id}")
            ->assertOk()->assertJsonPath('payload.identifier', 'reference-ada');
        $this->withHeaders($headers)->patchJson("/api/v1/accounts/{$account->id}/contacts/{$id}", [
            'name' => 'Ada Byron', 'custom_attributes' => ['region' => 'eu'], 'additional_attributes' => ['city' => 'London'],
        ])->assertOk()->assertJsonPath('payload.custom_attributes.tier', 'gold')->assertJsonPath('payload.custom_attributes.region', 'eu');
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/contacts/search?q=BYRON")
            ->assertOk()->assertJsonCount(1, 'payload')->assertJsonPath('meta.has_more', false);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/contacts/search")
            ->assertUnprocessable();
    }

    public function test_search_paginates_and_administrator_deletes_with_tenant_isolation(): void
    {
        [$account, $headers] = $this->member('administrator');
        foreach (range(1, 16) as $number) {
            $account->contacts()->create(['name' => "Search Person {$number}", 'email' => "person{$number}@example.test"]);
        }
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/contacts/search?q=person&page=1")
            ->assertOk()->assertJsonCount(15, 'payload')->assertJsonPath('meta.has_more', true);
        $contact = $account->contacts()->firstOrFail();
        $other = Account::create(['name' => 'Other']);
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$other->id}/contacts/{$contact->id}")->assertForbidden();
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/contacts/{$contact->id}")->assertOk();
        $this->assertSoftDeletedMissingOrDeleted($contact->id);
    }

    public function test_agent_cannot_delete_contact(): void
    {
        [$account, $headers] = $this->member('agent');
        $contact = $account->contacts()->create(['name' => 'Protected']);
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/contacts/{$contact->id}")->assertForbidden();
    }

    private function assertSoftDeletedMissingOrDeleted(int $id): void
    {
        $this->assertDatabaseMissing('contacts', ['id' => $id]);
    }

    private function member(string $role): array
    {
        $user = User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => Hash::make('Password1!')]);
        $account = Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => $role]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email]];
    }
}
