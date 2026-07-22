<?php

namespace App\Jobs;

use App\Mail\OutboundConversationMail;
use App\Models\EmailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEmailMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public EmailDelivery $delivery) {}

    public function handle(): void
    {
        $this->delivery->increment('attempts');
        $message = $this->delivery->message()->with('conversation.contact')->firstOrFail();
        Mail::to($message->conversation->contact->email)->send(new OutboundConversationMail($this->delivery->fresh()->load('message.conversation.inbox.channel.emailChannel')));
        $this->delivery->update(['status' => 'sent', 'delivered_at' => now(), 'last_error' => null]);
    }

    public function failed(Throwable $exception): void
    {
        $this->delivery->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
    }
}
