<?php

namespace App\Http\Controllers;

use App\Models\RealtimeEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['pubsub_token' => ['required', 'string'], 'account_id' => ['required', 'integer'], 'user_id' => ['required', 'integer'], 'after' => ['nullable', 'integer']]);
        $user = User::query()->whereKey($data['user_id'])->where('pubsub_token', $data['pubsub_token'])->firstOrFail();
        $account = $user->accounts()->whereKey($data['account_id'])->firstOrFail();
        $events = RealtimeEvent::query()->where('account_id', $account->id)->where('id', '>', $data['after'] ?? 0)->orderBy('id')->limit(100)->get()->map(fn ($item) => ['id' => $item->id, 'event' => $item->event, 'data' => $item->data]);

        return response()->json(['events' => $events, 'cursor' => (int) ($events->last()['id'] ?? ($data['after'] ?? 0))]);
    }

    public function presence(Request $request): JsonResponse
    {
        $request->validate(['pubsub_token' => ['required', 'exists:users,pubsub_token']]);

        return response()->json(['success' => true, 'timestamp' => now()->timestamp]);
    }
}
