<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WebhookSubscriptionController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'view');

        return response()->json(['payload' => $account->webhookSubscriptions()->with('deliveries')->orderBy('id')->get()->map(fn ($item) => $this->payload($item))]);
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $data = $request->validate(['name' => ['required', 'string'], 'url' => ['required', 'url', 'starts_with:https://'], 'events' => ['required', 'array', 'min:1'], 'events.*' => ['in:message.created,conversation.updated,notification.created'], 'secret' => ['nullable', 'string', 'min:16'], 'active' => ['sometimes', 'boolean']]);
        $secret = $data['secret'] ?? Str::random(48);
        unset($data['secret']);
        $item = $account->webhookSubscriptions()->create($data + ['encrypted_secret' => $secret]);

        return response()->json($this->payload($item) + ['secret' => $secret]);
    }

    public function update(Request $request, Account $account, WebhookSubscription $webhookSubscription): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $webhookSubscription);
        $webhookSubscription->update($request->validate(['name' => ['sometimes', 'string'], 'url' => ['sometimes', 'url', 'starts_with:https://'], 'events' => ['sometimes', 'array', 'min:1'], 'events.*' => ['in:message.created,conversation.updated,notification.created'], 'active' => ['sometimes', 'boolean']]));

        return response()->json($this->payload($webhookSubscription));
    }

    public function destroy(Request $request, Account $account, WebhookSubscription $webhookSubscription): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $webhookSubscription);
        $webhookSubscription->delete();

        return response()->json(['message' => 'Webhook deleted']);
    }

    private function payload(WebhookSubscription $item): array
    {
        return ['id' => $item->id, 'name' => $item->name, 'url' => $item->url, 'events' => $item->events, 'active' => $item->active, 'deliveries' => $item->deliveries->map(fn ($delivery) => ['id' => $delivery->id, 'event_id' => $delivery->event_id, 'event' => $delivery->event, 'status' => $delivery->status, 'attempts' => $delivery->attempts, 'response_status' => $delivery->response_status, 'last_error' => $delivery->last_error, 'delivered_at' => $delivery->delivered_at?->toISOString()])];
    }

    private function auth(Request $request, Account $account, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $account);
    }

    private function scoped(Account $account, WebhookSubscription $item): void
    {
        abort_unless($item->account_id === $account->id, 404);
    }
}
