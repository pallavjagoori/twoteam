<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Conversation;
use App\Support\ConversationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account);
        $query = $account->conversations()->with(['contact', 'inbox.channel']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        $all = $query->latest('last_activity_at')->get();
        $userId = $request->user()->id;
        $meta = ['mine_count' => $all->where('assignee_id', $userId)->count(), 'assigned_count' => $all->whereNotNull('assignee_id')->count(), 'unassigned_count' => $all->whereNull('assignee_id')->count(), 'all_count' => $all->count()];

        return response()->json(['data' => ['meta' => $meta, 'payload' => $all->map(fn ($item) => ConversationPayload::make($item))]]);
    }

    public function show(Request $request, Account $account, int $conversation): JsonResponse
    {
        $this->auth($request, $account);

        return response()->json(ConversationPayload::make($this->find($account, $conversation)));
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account);
        $data = $request->validate(['inbox_id' => ['required', 'integer'], 'contact_id' => ['required', 'integer'], 'status' => ['nullable', 'in:open,pending,resolved,snoozed'], 'assignee_id' => ['nullable', 'integer']]);
        abort_unless($account->inboxes()->whereKey($data['inbox_id'])->exists() && $account->contacts()->whereKey($data['contact_id'])->exists(), 404);
        $conversation = $account->conversations()->create($data + ['display_id' => ((int) $account->conversations()->max('display_id')) + 1, 'uuid' => (string) Str::uuid(), 'last_activity_at' => now()]);

        return response()->json(ConversationPayload::make($conversation->load(['contact', 'inbox.channel'])));
    }

    public function update(Request $request, Account $account, int $conversation): JsonResponse
    {
        $this->auth($request, $account);
        $item = $this->find($account, $conversation);
        $item->update($request->validate(['priority' => ['nullable', 'in:urgent,high,medium,low']]));

        return response()->json(ConversationPayload::make($item));
    }

    public function toggleStatus(Request $request, Account $account, int $conversation): JsonResponse
    {
        $this->auth($request, $account);
        $item = $this->find($account, $conversation);
        $data = $request->validate(['status' => ['required', 'in:open,pending,resolved,snoozed'], 'snoozed_until' => ['nullable', 'date']]);
        $item->update($data);

        return response()->json(['status' => $item->status]);
    }

    public function togglePriority(Request $request, Account $account, int $conversation): JsonResponse
    {
        return $this->update($request, $account, $conversation);
    }

    private function find(Account $account, int $displayId): Conversation
    {
        return $account->conversations()->with(['contact', 'inbox.channel'])->where('display_id', $displayId)->firstOrFail();
    }

    private function auth(Request $request, Account $account): void
    {
        Gate::forUser($request->user())->authorize('view', $account);
    }
}
