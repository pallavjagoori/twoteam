<?php

namespace App\Jobs;

use App\Models\MetaDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendMetaMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public MetaDelivery $delivery) {}

    public function handle(): void
    {
        $this->delivery->increment('attempts');
        $message = $this->delivery->message()->with('conversation.contact')->firstOrFail();
        $channel = $message->conversation->inbox->channel->metaChannel;
        $response = Http::withToken($channel->encrypted_credentials['access_token'])->post('https://graph.facebook.com/v23.0/'.$channel->external_account_id.'/messages', ['recipient' => ['id' => $message->conversation->contact->identifier], 'message' => ['text' => $message->content]])->throw();
        $this->delivery->update(['external_message_id' => $response->json('message_id'), 'status' => 'sent']);
    }

    public function failed(Throwable $exception): void
    {
        $this->delivery->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
    }
}
