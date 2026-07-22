<?php

namespace App\Jobs;

use App\Models\WhatsappDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public WhatsappDelivery $delivery) {}

    public function handle(): void
    {
        $this->delivery->increment('attempts');
        $message = $this->delivery->message()->with('conversation.contact', 'attachments')->firstOrFail();
        $channel = $message->conversation->inbox->channel->whatsappChannel;
        $payload = ['messaging_product' => 'whatsapp', 'to' => $message->conversation->contact->phone_number, 'type' => 'text', 'text' => ['body' => $message->content]];
        if ($message->attachments->isNotEmpty()) {
            $attachment = $message->attachments->first();
            $payload = ['messaging_product' => 'whatsapp', 'to' => $message->conversation->contact->phone_number, 'type' => 'document', 'document' => ['link' => URL::temporarySignedRoute('attachments.download', now()->addMinutes(10), ['attachment' => $attachment]), 'caption' => $message->content, 'filename' => $attachment->file_name]];
        }
        $response = Http::withToken($channel->encrypted_credentials['api_key'])->post('https://graph.facebook.com/v23.0/'.$channel->phone_number_id.'/messages', $payload)->throw();
        $this->delivery->update(['external_message_id' => data_get($response->json(), 'messages.0.id'), 'status' => 'sent']);
    }

    public function failed(Throwable $exception): void
    {
        $this->delivery->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
    }
}
