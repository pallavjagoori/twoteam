<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Contact;
use App\Support\ContactPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'view');

        return $this->page($account->contacts()->getQuery()->orderBy('name'), $request, false);
    }

    public function search(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'view');
        $request->validate(['q' => ['required', 'string']]);
        $term = '%'.strtolower(trim($request->string('q')->toString())).'%';
        $query = $account->contacts()->getQuery()->where(function (Builder $query) use ($term) {
            $query->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term])->orWhereRaw('LOWER(phone_number) LIKE ?', [$term])->orWhereRaw('LOWER(identifier) LIKE ?', [$term]);
        })->orderBy('name');

        return $this->page($query, $request, true);
    }

    public function show(Request $request, Account $account, Contact $contact): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'view');
        $this->scoped($account, $contact);

        return response()->json(['payload' => ContactPayload::make($contact)]);
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'view');
        $contact = $account->contacts()->create($this->validated($request));

        return response()->json(['payload' => ['contact' => ContactPayload::make($contact), 'contact_inbox' => ['inbox' => null, 'source_id' => null]]]);
    }

    public function update(Request $request, Account $account, Contact $contact): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'view');
        $this->scoped($account, $contact);
        $data = $this->validated($request);
        $data['custom_attributes'] = array_merge($contact->custom_attributes ?? [], $data['custom_attributes'] ?? []);
        $data['additional_attributes'] = array_merge($contact->additional_attributes ?? [], $data['additional_attributes'] ?? []);
        $contact->update($data);

        return response()->json(['payload' => ContactPayload::make($contact)]);
    }

    public function destroy(Request $request, Account $account, Contact $contact): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'update');
        $this->scoped($account, $contact);
        $contact->delete();

        return response()->json([]);
    }

    private function page(Builder $query, Request $request, bool $hasMore): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $total = $query->count();
        $contacts = $query->forPage($page, 15)->get()->map(fn (Contact $contact) => ContactPayload::make($contact));
        $meta = ['count' => $hasMore ? $contacts->count() : $total, 'current_page' => $page];
        if ($hasMore) {
            $meta['has_more'] = $total > $page * 15;
        }

        return response()->json(['meta' => $meta, 'payload' => $contacts]);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['sometimes', 'nullable', 'string'], 'identifier' => ['sometimes', 'nullable', 'string'], 'email' => ['sometimes', 'nullable', 'email'], 'phone_number' => ['sometimes', 'nullable', 'string'], 'blocked' => ['sometimes', 'boolean'], 'additional_attributes' => ['sometimes', 'array'], 'custom_attributes' => ['sometimes', 'array']]);
    }

    private function authorizeAccount(Request $request, Account $account, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $account);
    }

    private function scoped(Account $account, Contact $contact): void
    {
        abort_unless($contact->account_id === $account->id, 404);
    }
}
