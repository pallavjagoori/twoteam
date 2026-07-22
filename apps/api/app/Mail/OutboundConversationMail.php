<?php

namespace App\Mail;

use App\Models\EmailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class OutboundConversationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmailDelivery $delivery) {}

    public function build(): self
    {
        $message = $this->delivery->message;
        $conversation = $message->conversation;
        $emailChannel = $conversation->inbox->channel->emailChannel;
        $subject = data_get($message->content_attributes, 'email.subject') ?: 'Re: '.data_get($conversation->additional_attributes, 'mail_subject', 'Conversation #'.$conversation->display_id);

        $domain = $conversation->account->domain ?: 'inbound.twoteam.local';

        return $this->from($emailChannel->email, $conversation->inbox->name)->replyTo('reply+'.$conversation->uuid.'@'.$domain)->subject($subject)->view('mail.conversation-reply', ['body' => $message->content])->withSymfonyMessage(function (Email $email) use ($conversation) {
            $email->getHeaders()->addIdHeader('Message-ID', trim($this->delivery->message_id_header, '<>'));
            $email->getHeaders()->addTextHeader('X-Twoteam-Conversation', $conversation->uuid);
        });
    }
}
