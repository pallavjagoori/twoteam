<?php

namespace App\Http\Controllers;

use App\Models\RealtimeEvent;
use App\Models\User;
use App\Models\WidgetSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['pubsub_token' => ['required', 'string'], 'account_id' => ['nullable', 'integer'], 'user_id' => ['nullable', 'integer'], 'after' => ['nullable', 'integer']]);
        if (isset($data['user_id'], $data['account_id'])) {
            $user = User::query()->whereKey($data['user_id'])->where('pubsub_token', $data['pubsub_token'])->firstOrFail();
            $accountId = $user->accounts()->whereKey($data['account_id'])->firstOrFail()->id;
        } else {
            $accountId = WidgetSession::query()->where('pubsub_token', $data['pubsub_token'])->where('expires_at', '>', now())->firstOrFail()->account_id;
        }
        $events = RealtimeEvent::query()->where('account_id', $accountId)->where('id', '>', $data['after'] ?? 0)->orderBy('id')->limit(100)->get()->map(fn ($item) => ['id' => $item->id, 'event' => $item->event, 'data' => $item->data]);

        return response()->json(['events' => $events, 'cursor' => (int) ($events->last()['id'] ?? ($data['after'] ?? 0))]);
    }

    public function presence(Request $request): JsonResponse
    {
        $data = $request->validate(['pubsub_token' => ['required', 'string']]);
        $exists = User::query()->where('pubsub_token', $data['pubsub_token'])->exists()
            || WidgetSession::query()->where('pubsub_token', $data['pubsub_token'])->where('expires_at', '>', now())->exists();
        abort_unless($exists, 422);

        return response()->json(['success' => true, 'timestamp' => now()->timestamp]);
    }
}
