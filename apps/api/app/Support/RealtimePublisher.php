<?php

namespace App\Support;

use App\Models\RealtimeEvent;

class RealtimePublisher
{
    public static function publish(int $accountId, string $event, array $data): RealtimeEvent
    {
        $realtime = RealtimeEvent::create(['account_id' => $accountId, 'event' => $event, 'data' => $data]);
        OutgoingWebhookPublisher::publish($accountId, $event, $data);

        return $realtime;
    }
}
