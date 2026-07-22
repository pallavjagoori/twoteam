<?php

namespace App\Jobs;

use App\Models\PrioritizedDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendPrioritizedMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public PrioritizedDelivery $delivery) {}

    public function handle(): void
    {
        $this->delivery->increment('attempts');
        $message = $this->delivery->message()->with('conversation.contact')->firstOrFail();
        $channel = $message->conversation->inbox->channel->prioritizedChannel;
        $credentials = $channel->encrypted_credentials;
        [$url, $payload, $token] = match ($channel->provider) {
            'telegram' => ['https://api.telegram.org/bot'.$credentials['bot_token'].'/sendMessage', ['chat_id' => $message->conversation->contact->identifier, 'text' => $message->content], null],
            'line' => ['https://api.line.me/v2/bot/message/push', ['to' => $message->conversation->contact->identifier, 'messages' => [['type' => 'text', 'text' => $message->content]]], $credentials['channel_token']],
            default => ['https://sms.twoteam.local/messages', ['to' => $message->conversation->contact->phone_number, 'from' => $channel->external_identity, 'text' => $message->content], $credentials['api_key']],
        };
        $request = $token ? Http::withToken($token) : Http::acceptJson();
        $response = $request->post($url, $payload)->throw();
        $external = $response->json('result.message_id') ?? $response->json('message_id') ?? $response->json('id');
        $this->delivery->update(['external_message_id' => (string) $external, 'status' => 'sent']);
    }

    public function failed(Throwable $exception): void
    {
        $this->delivery->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
    }
}
