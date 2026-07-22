<?php

namespace App\Support;

use App\Jobs\DeliverOutgoingWebhook;
use App\Models\WebhookSubscription;
use Illuminate\Support\Str;

class OutgoingWebhookPublisher
{
    public static function publish(int $accountId, string $event, array $data): array
    {
        $eventId = (string) Str::uuid();
        $payload = ['event' => $event, 'event_id' => $eventId, 'account_id' => $accountId, 'data' => $data];

        return WebhookSubscription::where('account_id', $accountId)->where('active', true)->get()->filter(fn ($subscription) => in_array($event, $subscription->events, true))->map(function ($subscription) use ($eventId, $event, $payload) {
            $delivery = $subscription->deliveries()->create(['event_id' => $eventId, 'event' => $event, 'payload' => $payload]);
            DeliverOutgoingWebhook::dispatch($delivery);

            return $delivery;
        })->all();
    }
}
