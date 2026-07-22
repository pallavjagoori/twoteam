<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\PrioritizedDelivery;
use App\Support\AutomationEngine;
use App\Support\MessagePayload;
use App\Support\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrioritizedWebhookController extends Controller
{
    public function store(Request $request, Channel $channel): JsonResponse
    {
        abort_unless(in_array($channel->type, ['telegram', 'line', 'sms'], true), 404);
        $signature = (string) $request->header('X-Twoteam-Signature');
        abort_unless(hash_equals(hash_hmac('sha256', $request->getContent(), $channel->hmac_token), $signature), 401);
        $data = $request->json()->all();
        $normalized = match ($channel->type) {
            'telegram' => ['id' => (string) data_get($data, 'message.message_id'), 'from' => (string) data_get($data, 'message.chat.id'), 'text' => data_get($data, 'message.text'), 'status' => null],
            'line' => ['id' => data_get($data, 'events.0.message.id'), 'from' => data_get($data, 'events.0.source.userId'), 'text' => data_get($data, 'events.0.message.text'), 'status' => null],
            default => ['id' => $data['id'] ?? null, 'from' => $data['from'] ?? null, 'text' => $data['text'] ?? null, 'status' => $data['status'] ?? null],
        };
        if ($normalized['status']) {
            $delivery = PrioritizedDelivery::where('external_message_id', $normalized['id'])->whereHas('message', fn ($query) => $query->where('account_id', $channel->account_id))->first();
            if ($delivery) {
                $updates = ['status' => $normalized['status']];
                if ($normalized['status'] === 'delivered') {
                    $updates['delivered_at'] = now();
                } $delivery->update($updates);
                $delivery->message->update(['status' => $normalized['status']]);
            }

            return response()->json(['status' => 'accepted']);
        }
        abort_unless($normalized['id'] && $normalized['from'], 422);
        $existing = DB::table('inbound_prioritized_messages')->where('channel_id', $channel->id)->where('external_message_id', $normalized['id'])->first();
        if ($existing) {
            return response()->json(MessagePayload::make($channel->account->messages()->with(['conversation', 'sender', 'attachments'])->findOrFail($existing->message_id)));
        }
        $identity = $channel->type === 'sms' ? ['phone_number' => $normalized['from']] : ['identifier' => $normalized['from']];
        $contact = $channel->account->contacts()->firstOrCreate($identity, ['name' => ucfirst($channel->type).' user']);
        $conversation = $channel->account->conversations()->where('inbox_id', $channel->inbox->id)->where('contact_id', $contact->id)->latest()->first();
        if (! $conversation) {
            $conversation = $channel->account->conversations()->create(['inbox_id' => $channel->inbox->id, 'contact_id' => $contact->id, 'display_id' => ($channel->account->conversations()->max('display_id') ?? 0) + 1, 'uuid' => Str::uuid(), 'last_activity_at' => now()]);
        }
        $message = $conversation->messages()->create(['account_id' => $channel->account_id, 'inbox_id' => $channel->inbox->id, 'content' => $normalized['text'], 'message_type' => 0, 'status' => 'sent', 'source_id' => $normalized['id'], 'content_attributes' => ['provider' => $channel->type]]);
        DB::table('inbound_prioritized_messages')->insert(['channel_id' => $channel->id, 'message_id' => $message->id, 'external_message_id' => $normalized['id'], 'payload' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
        $payload = MessagePayload::make($message->load(['conversation', 'sender', 'attachments']));
        RealtimePublisher::publish($channel->account_id, 'message.created', $payload);
        AutomationEngine::dispatch('message_created', $conversation, $channel->type.':'.$normalized['id'], $message);

        return response()->json($payload);
    }
}
