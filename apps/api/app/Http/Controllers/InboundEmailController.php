<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\EmailDelivery;
use App\Models\Message;
use App\Support\AutomationEngine;
use App\Support\MessagePayload;
use App\Support\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InboundEmailController extends Controller
{
    public function store(Request $request, Channel $channel): JsonResponse
    {
        abort_unless($channel->type === 'email', 404);
        $signature = (string) $request->header('X-Twoteam-Signature');
        abort_unless(hash_equals(hash_hmac('sha256', $request->getContent(), $channel->hmac_token), $signature), 401);
        $data = $request->validate(['message_id' => ['required', 'string', 'max:998'], 'in_reply_to' => ['nullable', 'string', 'max:998'], 'from' => ['required', 'email'], 'to' => ['required', 'email'], 'subject' => ['required', 'string'], 'text' => ['required', 'string']]);
        $existingId = DB::table('inbound_emails')->where('channel_id', $channel->id)->where('external_message_id', $data['message_id'])->value('message_id');
        if ($existingId) {
            return response()->json(MessagePayload::make(Message::with(['conversation', 'sender', 'attachments'])->findOrFail($existingId)));
        }

        $message = DB::transaction(function () use ($channel, $data) {
            $conversation = $this->thread($channel, $data);
            $message = $conversation->messages()->create(['account_id' => $channel->account_id, 'inbox_id' => $channel->inbox->id, 'content' => $data['text'], 'message_type' => 0, 'status' => 'sent', 'source_id' => $data['message_id'], 'content_attributes' => ['email' => ['message_id' => $data['message_id'], 'in_reply_to' => $data['in_reply_to'] ?? null, 'subject' => $data['subject'], 'from' => $data['from'], 'to' => $data['to']]]]);
            DB::table('inbound_emails')->insert(['channel_id' => $channel->id, 'message_id' => $message->id, 'external_message_id' => $data['message_id'], 'in_reply_to' => $data['in_reply_to'] ?? null, 'payload' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
            $conversation->update(['last_activity_at' => now()]);

            return $message;
        });
        $payload = MessagePayload::make($message->load(['conversation', 'sender', 'attachments']));
        RealtimePublisher::publish($channel->account_id, 'message.created', $payload);
        AutomationEngine::dispatch('message_created', $message->conversation, 'email:'.$data['message_id'], $message);

        return response()->json($payload);
    }

    private function thread(Channel $channel, array $data): Conversation
    {
        if (! empty($data['in_reply_to'])) {
            $delivery = EmailDelivery::where('message_id_header', $data['in_reply_to'])->with('message.conversation')->first();
            if ($delivery && $delivery->message->conversation->account_id === $channel->account_id) {
                return $delivery->message->conversation;
            }
        }
        if (preg_match('/reply\+([0-9a-f-]{36})@/i', $data['to'], $matches)) {
            $conversation = $channel->account->conversations()->where('uuid', $matches[1])->first();
            if ($conversation) {
                return $conversation;
            }
        }
        $contact = $channel->account->contacts()->firstOrCreate(['email' => Str::lower($data['from'])], ['name' => Str::before($data['from'], '@'), 'last_activity_at' => now()]);
        $displayId = ((int) Conversation::where('account_id', $channel->account_id)->lockForUpdate()->max('display_id')) + 1;

        return $channel->account->conversations()->create(['inbox_id' => $channel->inbox->id, 'contact_id' => $contact->id, 'display_id' => $displayId, 'uuid' => (string) Str::uuid(), 'status' => 'open', 'additional_attributes' => ['mail_subject' => $data['subject']], 'last_activity_at' => now()]);
    }
}
