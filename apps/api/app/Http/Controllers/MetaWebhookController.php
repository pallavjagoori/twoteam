<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\MetaDelivery;
use App\Support\AutomationEngine;
use App\Support\MessagePayload;
use App\Support\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MetaWebhookController extends Controller
{
    public function verify(Request $request, Channel $channel)
    {
        abort_unless(in_array($channel->type, ['facebook', 'instagram'], true) && hash_equals($channel->secret, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'));
    }

    public function store(Request $request, Channel $channel): JsonResponse
    {
        abort_unless(in_array($channel->type, ['facebook', 'instagram'], true), 404);
        $signature = preg_replace('/^sha256=/', '', (string) $request->header('X-Hub-Signature-256'));
        abort_unless(hash_equals(hash_hmac('sha256', $request->getContent(), $channel->hmac_token), $signature), 401);
        $event = data_get($request->json()->all(), 'entry.0.messaging.0', []);
        if (isset($event['delivery']) || isset($event['read'])) {
            $ids = $event['delivery']['mids'] ?? [$event['read']['mid'] ?? null];
            foreach (array_filter($ids) as $id) {
                $delivery = MetaDelivery::where('external_message_id', $id)->whereHas('message', fn ($query) => $query->where('account_id', $channel->account_id))->first();
                if ($delivery) {
                    $isRead = isset($event['read']);
                    $delivery->update(['status' => $isRead ? 'read' : 'delivered', $isRead ? 'read_at' : 'delivered_at' => now()]);
                    $delivery->message->update(['status' => $isRead ? 'read' : 'delivered']);
                }
            }
        }
        $external = data_get($event, 'message.mid');
        if (! $external) {
            return response()->json(['status' => 'accepted']);
        }
        $existing = DB::table('inbound_meta_messages')->where('channel_id', $channel->id)->where('external_message_id', $external)->first();
        if ($existing) {
            return response()->json(MessagePayload::make($channel->account->messages()->with(['conversation', 'sender', 'attachments'])->findOrFail($existing->message_id)));
        }
        $sender = data_get($event, 'sender.id');
        $contact = $channel->account->contacts()->firstOrCreate(['identifier' => $sender], ['name' => ucfirst($channel->type).' user']);
        $conversation = $channel->account->conversations()->where('inbox_id', $channel->inbox->id)->where('contact_id', $contact->id)->latest()->first();
        if (! $conversation) {
            $conversation = $channel->account->conversations()->create(['inbox_id' => $channel->inbox->id, 'contact_id' => $contact->id, 'display_id' => ($channel->account->conversations()->max('display_id') ?? 0) + 1, 'uuid' => Str::uuid(), 'last_activity_at' => now()]);
        }
        $attachments = data_get($event, 'message.attachments', []);
        $content = data_get($event, 'message.text', data_get($attachments, '0.payload.url'));
        $message = $conversation->messages()->create(['account_id' => $channel->account_id, 'inbox_id' => $channel->inbox->id, 'content' => $content, 'message_type' => 0, 'status' => 'sent', 'source_id' => $external, 'content_attributes' => ['meta' => ['platform' => $channel->type, 'attachments' => $attachments]]]);
        DB::table('inbound_meta_messages')->insert(['channel_id' => $channel->id, 'message_id' => $message->id, 'external_message_id' => $external, 'payload' => json_encode($request->json()->all()), 'created_at' => now(), 'updated_at' => now()]);
        $payload = MessagePayload::make($message->load(['conversation', 'sender', 'attachments']));
        RealtimePublisher::publish($channel->account_id, 'message.created', $payload);
        AutomationEngine::dispatch('message_created', $conversation, $channel->type.':'.$external, $message);

        return response()->json($payload);
    }
}
