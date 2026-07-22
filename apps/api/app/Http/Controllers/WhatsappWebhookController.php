<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\WhatsappDelivery;
use App\Support\AutomationEngine;
use App\Support\MessagePayload;
use App\Support\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request, Channel $channel)
    {
        abort_unless($channel->type === 'whatsapp' && hash_equals($channel->secret, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'));
    }

    public function store(Request $request, Channel $channel): JsonResponse
    {
        abort_unless($channel->type === 'whatsapp', 404);
        $signature = preg_replace('/^sha256=/', '', (string) $request->header('X-Hub-Signature-256'));
        abort_unless(hash_equals(hash_hmac('sha256', $request->getContent(), $channel->hmac_token), $signature), 401);
        $value = data_get($request->json()->all(), 'entry.0.changes.0.value', []);
        foreach ($value['statuses'] ?? [] as $status) {
            $delivery = WhatsappDelivery::where('external_message_id', $status['id'])->whereHas('message', fn ($query) => $query->where('account_id', $channel->account_id))->first();
            if ($delivery) {
                $updates = ['status' => $status['status']];
                if ($status['status'] === 'delivered') {
                    $updates['delivered_at'] = now();
                }
                if ($status['status'] === 'read') {
                    $updates['read_at'] = now();
                }
                if ($status['status'] === 'failed') {
                    $updates['last_error'] = data_get($status, 'errors.0.title', 'WhatsApp delivery failed');
                }
                $delivery->update($updates);
                $delivery->message->update(['status' => $status['status'], 'external_error' => $updates['last_error'] ?? null]);
            }
        }
        $external = data_get($value, 'messages.0.id');
        if (! $external) {
            return response()->json(['status' => 'accepted']);
        }
        $existing = DB::table('inbound_whatsapp_messages')->where('channel_id', $channel->id)->where('external_message_id', $external)->first();
        if ($existing) {
            return response()->json(MessagePayload::make($channel->account->messages()->with(['conversation', 'sender', 'attachments'])->findOrFail($existing->message_id)));
        }
        $from = data_get($value, 'messages.0.from');
        $contact = $channel->account->contacts()->firstOrCreate(['phone_number' => $from], ['name' => data_get($value, 'contacts.0.profile.name', $from)]);
        $conversation = $channel->account->conversations()->where('inbox_id', $channel->inbox->id)->where('contact_id', $contact->id)->latest()->first();
        if (! $conversation) {
            $conversation = $channel->account->conversations()->create(['inbox_id' => $channel->inbox->id, 'contact_id' => $contact->id, 'display_id' => ($channel->account->conversations()->max('display_id') ?? 0) + 1, 'uuid' => Str::uuid(), 'last_activity_at' => now()]);
        }
        $messageData = data_get($value, 'messages.0');
        $type = $messageData['type'];
        $content = $type === 'text' ? data_get($messageData, 'text.body') : data_get($messageData, $type.'.caption');
        $message = $conversation->messages()->create(['account_id' => $channel->account_id, 'inbox_id' => $channel->inbox->id, 'content' => $content, 'message_type' => 0, 'status' => 'sent', 'source_id' => $external, 'content_attributes' => ['whatsapp' => ['type' => $type, 'media_id' => data_get($messageData, $type.'.id')]]]);
        DB::table('inbound_whatsapp_messages')->insert(['channel_id' => $channel->id, 'message_id' => $message->id, 'external_message_id' => $external, 'payload' => json_encode($request->json()->all()), 'created_at' => now(), 'updated_at' => now()]);
        $payload = MessagePayload::make($message->load(['conversation', 'sender', 'attachments']));
        RealtimePublisher::publish($channel->account_id, 'message.created', $payload);
        AutomationEngine::dispatch('message_created', $conversation, 'whatsapp:'.$external, $message);

        return response()->json($payload);
    }
}
