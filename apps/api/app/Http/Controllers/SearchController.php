<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\SearchPayload;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $data = $this->validated($request, $account);

        return response()->json(['payload' => ['conversations' => $this->conversationResults($account, $data), 'contacts' => $this->contactResults($account, $data), 'messages' => $this->messageResults($account, $data), 'articles' => []]]);
    }

    public function contacts(Request $request, Account $account): JsonResponse
    {
        $data = $this->validated($request, $account);

        return response()->json(['payload' => ['contacts' => $this->contactResults($account, $data)]]);
    }

    public function conversations(Request $request, Account $account): JsonResponse
    {
        $data = $this->validated($request, $account);

        return response()->json(['payload' => ['conversations' => $this->conversationResults($account, $data)]]);
    }

    public function messages(Request $request, Account $account): JsonResponse
    {
        $data = $this->validated($request, $account);

        return response()->json(['payload' => ['messages' => $this->messageResults($account, $data)]]);
    }

    public function articles(Request $request, Account $account): JsonResponse
    {
        $this->validated($request, $account);

        return response()->json(['payload' => ['articles' => []]]);
    }

    private function validated(Request $request, Account $account): array
    {
        Gate::forUser($request->user())->authorize('view', $account);

        return $request->validate(['q' => ['nullable', 'string', 'max:200'], 'page' => ['sometimes', 'integer', 'min:1'], 'since' => ['nullable', 'integer'], 'until' => ['nullable', 'integer'], 'from' => ['nullable', 'regex:/\A(contact|agent):\d+\z/'], 'inbox_id' => ['nullable', 'integer']]);
    }

    private function contactResults(Account $account, array $data): array
    {
        $term = mb_strtolower(trim($data['q'] ?? ''));
        $query = $account->contacts()->where(fn ($item) => $item->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])->orWhereRaw('LOWER(phone_number) LIKE ?', ["%{$term}%"])->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$term}%"]));
        $this->timeFilter($query, 'last_activity_at', $data);

        return $this->page($query->orderByDesc('last_activity_at')->orderByDesc('id'), $data)->map(fn ($contact) => SearchPayload::contact($contact))->all();
    }

    private function conversationResults(Account $account, array $data): array
    {
        $term = mb_strtolower(trim($data['q'] ?? ''));
        $query = $account->conversations()->whereHas('contact', fn ($contact) => $contact->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])->orWhereRaw('LOWER(phone_number) LIKE ?', ["%{$term}%"])->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$term}%"]))->orWhere(fn ($item) => $item->where('account_id', $account->id)->whereRaw('CAST(display_id AS TEXT) LIKE ?', ["%{$term}%"]));
        $this->timeFilter($query, 'last_activity_at', $data);
        $query->with(['contact', 'inbox.channel', 'assignee', 'messages' => fn ($messages) => $messages->with(['conversation', 'sender', 'attachments'])->oldest()->limit(1)]);

        return $this->page($query->orderByDesc('created_at')->orderByDesc('id'), $data)->map(fn ($conversation) => SearchPayload::conversation($conversation))->all();
    }

    private function messageResults(Account $account, array $data): array
    {
        $term = mb_strtolower(trim($data['q'] ?? ''));
        $query = $account->messages()->where('created_at', '>=', now()->subDays(90))->whereRaw('LOWER(content) LIKE ?', ["%{$term}%"]);
        $this->timeFilter($query, 'created_at', $data);
        if (isset($data['inbox_id']) && $account->inboxes()->whereKey($data['inbox_id'])->exists()) {
            $query->where('inbox_id', $data['inbox_id']);
        }
        if (isset($data['from'])) {
            [$type, $id] = explode(':', $data['from']);
            $type === 'agent' ? $query->where('sender_id', $id) : $query->whereHas('conversation', fn ($conversation) => $conversation->where('contact_id', $id));
        }
        $query->with(['conversation', 'sender', 'attachments']);

        return $this->page($query->orderByDesc('created_at')->orderByDesc('id'), $data)->map(fn ($message) => SearchPayload::message($message))->all();
    }

    private function timeFilter(Builder|Relation $query, string $column, array $data): void
    {
        if (isset($data['since'])) {
            $query->where($column, '>=', Carbon::createFromTimestamp($data['since'])->max(now()->subDays(90)));
        }
        if (isset($data['until'])) {
            $query->where($column, '<=', Carbon::createFromTimestamp($data['until'])->min(now()->addDays(90)));
        }
    }

    private function page(Builder|Relation $query, array $data)
    {
        return $query->offset((((int) ($data['page'] ?? 1)) - 1) * 15)->limit(15)->get();
    }
}
