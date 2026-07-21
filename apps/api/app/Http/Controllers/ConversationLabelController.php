<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationLabelController extends Controller
{
    public function index(Request $request, Account $account, int $conversation): JsonResponse
    {
        $item = $this->find($request, $account, $conversation);

        return response()->json(['payload' => $item->labels()->orderBy('title')->pluck('title')]);
    }

    public function store(Request $request, Account $account, int $conversation): JsonResponse
    {
        $item = $this->find($request, $account, $conversation);
        $titles = $request->validate(['labels' => ['required', 'array'], 'labels.*' => ['string']])['labels'];
        $ids = $account->labels()->whereIn('title', $titles)->pluck('id');
        $item->labels()->sync($ids);

        return response()->json(['payload' => $item->labels()->orderBy('title')->pluck('title')]);
    }

    private function find(Request $request, Account $account, int $displayId): Conversation
    {
        Gate::forUser($request->user())->authorize('view', $account);

        return $account->conversations()->where('display_id', $displayId)->firstOrFail();
    }
}
