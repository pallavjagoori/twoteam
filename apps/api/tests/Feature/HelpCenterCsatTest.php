<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\HelpCenterArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HelpCenterCsatTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_manages_portals_categories_and_articles(): void
    {
        [$account, $headers] = $this->member('administrator');
        $portalResponse = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/portals", ['name' => 'Docs', 'slug' => 'docs', 'default_locale' => 'en'])->assertOk()->assertJsonPath('slug', 'docs');
        $portal = $account->portals()->findOrFail($portalResponse->json('id'));
        $this->assertTrue($portal->account->is($account));
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/portals")->assertOk()->assertJsonCount(1, 'payload');
        $category = $this->withHeaders($headers)->postJson('/api/v1/portals/docs/categories', ['name' => 'Guides', 'slug' => 'guides', 'locale' => 'en'])->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/portals/docs/categories')->assertOk()->assertJsonCount(1, 'payload');
        $this->withHeaders($headers)->patchJson('/api/v1/portals/docs/categories/'.$category->json('id'), ['name' => 'Updated', 'position' => 2])->assertOk()->assertJsonPath('name', 'Updated');
        $article = $this->withHeaders($headers)->postJson('/api/v1/portals/docs/articles', ['title' => 'Getting Started', 'content' => 'Install Twoteam', 'locale' => 'en', 'status' => 'draft', 'category_id' => $category->json('id')])->assertOk()->assertJsonPath('slug', 'getting-started')->assertJsonPath('status', 'draft');
        $articleModel = HelpCenterArticle::with(['portal', 'category.articles', 'author'])->findOrFail($article->json('id'));
        $this->assertTrue($articleModel->portal->is($portal));
        $this->assertTrue($articleModel->category->portal->is($portal));
        $this->assertTrue($articleModel->category->articles->first()->is($articleModel));
        $this->assertSame($article->json('author_id'), $articleModel->author->id);
        $this->withHeaders($headers)->getJson('/api/v1/portals/docs/articles?query=Install&status=draft&locale=en&author_id='.$article->json('author_id'))->assertOk()->assertJsonPath('payload.data.0.id', $article->json('id'));
        $this->withHeaders($headers)->getJson('/api/v1/portals/docs/articles/'.$article->json('id'))->assertOk()->assertJsonPath('title', 'Getting Started');
        $this->withHeaders($headers)->patchJson('/api/v1/portals/docs/articles/'.$article->json('id'), ['status' => 'published', 'slug' => 'start', 'category_id' => null])->assertOk()->assertJsonPath('status', 'published');
        $this->assertNotNull(HelpCenterArticle::findOrFail($article->json('id'))->published_at);
        $this->withHeaders($headers)->patchJson("/api/v1/accounts/{$account->id}/portals/{$portal->id}", ['name' => 'Knowledge', 'custom_domain' => 'help.example.test'])->assertOk()->assertJsonPath('name', 'Knowledge');
        $this->withHeaders($headers)->deleteJson('/api/v1/portals/docs/articles/'.$article->json('id'))->assertOk();
        $this->withHeaders($headers)->deleteJson('/api/v1/portals/docs/categories/'.$category->json('id'))->assertOk();
        $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$account->id}/portals/{$portal->id}")->assertOk();
    }

    public function test_public_help_center_only_exposes_published_locale_content(): void
    {
        [$account, $headers] = $this->member('administrator');
        $portal = $account->portals()->create(['name' => 'Docs', 'slug' => 'public-docs']);
        $category = $portal->categories()->create(['name' => 'Guides', 'slug' => 'guides']);
        $published = $portal->articles()->create(['title' => 'Public Guide', 'slug' => 'public-guide', 'content' => 'Searchable answer', 'locale' => 'en', 'status' => 'published', 'published_at' => now(), 'help_center_category_id' => $category->id]);
        $portal->articles()->create(['title' => 'Draft Secret', 'slug' => 'secret', 'content' => 'Never public', 'locale' => 'en', 'status' => 'draft']);
        $portal->articles()->create(['title' => 'French', 'slug' => 'french', 'content' => 'Bonjour', 'locale' => 'fr', 'status' => 'published', 'published_at' => now()]);
        $this->getJson('/api/hc/public-docs/en/articles.json?query=%20answer%20')->assertOk()->assertJsonCount(1, 'payload')->assertJsonPath('payload.0.id', $published->id)->assertJsonMissing(['Draft Secret', 'French']);
        $this->getJson('/api/hc/public-docs/en/articles/public-guide')->assertOk()->assertJsonPath('article.title', 'Public Guide');
        $this->getJson('/api/hc/public-docs/en/articles/secret')->assertNotFound();
        $portal->update(['archived' => true]);
        $this->getJson('/api/hc/public-docs/en/articles.json')->assertNotFound();
        [$other, $otherHeaders] = $this->member('administrator');
        $this->withHeaders($otherHeaders)->patchJson("/api/v1/accounts/{$other->id}/portals/{$portal->id}", ['name' => 'Cross'])->assertNotFound();
        $foreign = $other->portals()->create(['name' => 'Other', 'slug' => 'other'])->categories()->create(['name' => 'Other', 'slug' => 'other']);
        $portal->update(['archived' => false]);
        $this->withHeaders($headers)->postJson('/api/v1/portals/public-docs/articles', ['title' => 'Bad category', 'content' => 'No', 'category_id' => $foreign->id])->assertUnprocessable();
    }

    public function test_csat_is_issued_once_submitted_once_and_reported_per_tenant(): void
    {
        [$account, $headers, $conversation] = $this->conversationFixture();
        $issued = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/{$conversation->display_id}/csat_survey")->assertOk();
        $surveyModel = $conversation->csatSurveyResponse;
        $this->assertTrue($surveyModel->account->is($account));
        $this->assertTrue($surveyModel->conversation->is($conversation));
        $again = $this->withHeaders($headers)->postJson("/api/v1/accounts/{$account->id}/conversations/{$conversation->display_id}/csat_survey")->assertOk();
        $this->assertSame($issued->json('uuid'), $again->json('uuid'));
        $this->getJson('/api/public/v1/csat_survey/'.$issued->json('uuid'))->assertOk()->assertJsonPath('csat_survey_response.rating', null);
        $this->patchJson('/api/public/v1/csat_survey/'.$issued->json('uuid'), ['csat_survey_response' => ['rating' => 5, 'feedback_message' => 'Excellent']])->assertOk()->assertJsonPath('csat_survey_response.rating', 5);
        $this->patchJson('/api/public/v1/csat_survey/'.$issued->json('uuid'), ['csat_survey_response' => ['rating' => 1]])->assertConflict();
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/csat_survey_responses")->assertOk()->assertJsonCount(1, 'payload');
        $this->withHeaders($headers)->getJson("/api/v1/accounts/{$account->id}/csat_survey_responses/metrics")->assertOk()->assertJsonPath('total_count', 1)->assertJsonPath('average_rating', 5)->assertJsonPath('ratings.5', 1);
        $this->withHeaders($headers)->get("/api/v1/accounts/{$account->id}/csat_survey_responses/download")->assertOk()->assertSee('Excellent');
        [$other, $otherHeaders] = $this->member('administrator');
        $this->withHeaders($otherHeaders)->getJson("/api/v1/accounts/{$other->id}/csat_survey_responses")->assertOk()->assertJsonCount(0, 'payload');
    }

    public function test_help_center_and_csat_authorization_and_validation_fail_closed(): void
    {
        [$account, $admin, $conversation] = $this->conversationFixture();
        [, $agent] = $this->member('agent', $account);
        $this->withHeaders($agent)->postJson("/api/v1/accounts/{$account->id}/portals", ['name' => 'No', 'slug' => 'no'])->assertForbidden();
        $this->withHeaders($admin)->postJson("/api/v1/accounts/{$account->id}/portals", ['name' => 'Docs', 'slug' => 'bad slug'])->assertUnprocessable();
        $survey = $this->withHeaders($admin)->postJson("/api/v1/accounts/{$account->id}/conversations/{$conversation->display_id}/csat_survey")->assertOk();
        $this->patchJson('/api/public/v1/csat_survey/'.$survey->json('uuid'), ['csat_survey_response' => ['rating' => 6]])->assertUnprocessable();
        $this->getJson('/api/public/v1/csat_survey/'.fake()->uuid())->assertNotFound();
    }

    private function conversationFixture(): array
    {
        [$account, $headers] = $this->member('administrator');
        $channel = $account->channels()->create(['type' => 'api', 'settings' => [], 'identifier' => fake()->uuid(), 'secret' => 'secret', 'hmac_token' => 'hmac']);
        $inbox = $account->inboxes()->create(['name' => 'Inbox', 'channel_id' => $channel->id]);
        $contact = $account->contacts()->create(['name' => 'Customer']);
        $conversation = $account->conversations()->create(['inbox_id' => $inbox->id, 'contact_id' => $contact->id, 'display_id' => 1, 'uuid' => fake()->uuid(), 'last_activity_at' => now()]);

        return [$account, $headers, $conversation];
    }

    private function member(string $role, ?Account $account = null): array
    {
        $user = User::factory()->create(['password' => Hash::make('Password1!')]);
        $account ??= Account::create(['name' => 'Tenant']);
        $account->users()->attach($user, ['role' => $role]);
        $login = $this->postJson('/auth/sign_in', ['email' => $user->email, 'password' => 'Password1!']);

        return [$account, ['access-token' => $login->headers->get('access-token'), 'client' => $login->headers->get('client'), 'token-type' => 'Bearer', 'uid' => $user->email]];
    }
}
