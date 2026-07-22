<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverOutgoingWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public WebhookDelivery $delivery) {}

    public function handle(): void
    {
        $this->delivery->increment('attempts');
        $subscription = $this->delivery->subscription;
        $body = json_encode($this->delivery->payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, $subscription->encrypted_secret);
        $response = Http::withHeaders(['Content-Type' => 'application/json', 'X-Twoteam-Event' => $this->delivery->event, 'X-Twoteam-Event-Id' => $this->delivery->event_id, 'X-Twoteam-Signature-256' => 'sha256='.$signature])->withBody($body, 'application/json')->post($subscription->url);
        if ($response->failed()) {
            throw new \RuntimeException('Webhook returned HTTP '.$response->status());
        }
        $this->delivery->update(['status' => 'delivered', 'response_status' => $response->status(), 'last_error' => null, 'delivered_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        $this->delivery->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
    }
}
