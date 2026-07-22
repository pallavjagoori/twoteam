<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailMessage;
use App\Jobs\SendMetaMessage;
use App\Jobs\SendPrioritizedMessage;
use App\Jobs\SendWhatsappMessage;
use App\Models\Account;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\ContactPayload;
use App\Support\MessagePayload;
use App\Support\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function index(Request $request, Account $account, int $conversation): JsonResponse
    {
        $item = $this->conversation($request, $account, $conversation);
        $query = $item->messages()->with(['conversation', 'sender', 'attachments.message.sender']);
        if ($request->filled('before')) {
            $query->where('id', '<', $request->integer('before'));
        }
        if ($request->filled('after')) {
            $query->where('id', '>', $request->integer('after'));
        }

        return response()->json(['meta' => ['labels' => [], 'additional_attributes' => $item->additional_attributes ?? [], 'contact' => ContactPayload::make($item->contact), 'assignee' => $item->assignee ? ['id' => $item->assignee->id, 'name' => $item->assignee->name] : null, 'agent_last_seen_at' => $item->agent_last_seen_at, 'assignee_last_seen_at' => $item->assignee_last_seen_at], 'payload' => $query->orderBy('id')->get()->map(fn ($message) => MessagePayload::make($message))]);
    }

    public function store(Request $request, Account $account, int $conversation): JsonResponse
    {
        $item = $this->conversation($request, $account, $conversation);
        if ($item->inbox->channel->type === 'email') {
            abort_unless($item->contact->email && $item->inbox->channel->emailChannel?->verified_for_sending, 422, 'Email channel is not ready for sending');
        }
        if ($item->inbox->channel->type === 'whatsapp') {
            abort_unless($item->contact->phone_number && $item->inbox->channel->whatsappChannel, 422, 'WhatsApp channel is not ready for sending');
        }
        if (in_array($item->inbox->channel->type, ['facebook', 'instagram'], true)) {
            abort_unless($item->contact->identifier && $item->inbox->channel->metaChannel, 422, 'Meta channel is not ready for sending');
        }
        if (in_array($item->inbox->channel->type, ['telegram', 'line', 'sms'], true)) {
            $ready = $item->inbox->channel->type === 'sms' ? $item->contact->phone_number : $item->contact->identifier;
            abort_unless($ready && $item->inbox->channel->prioritizedChannel, 422, 'Provider channel is not ready for sending');
        }
        if (is_string($request->input('content_attributes'))) {
            $request->merge(['content_attributes' => json_decode($request->input('content_attributes'), true)]);
        }
        $data = $request->validate([
            'content' => ['nullable', 'string'], 'private' => ['sometimes', 'boolean'],
            'echo_id' => ['nullable', 'string'], 'content_attributes' => ['sometimes', 'array'],
            'attachments' => ['sometimes', 'array', 'max:10'], 'attachments.*' => ['file', 'max:20480'],
        ]);
        $files = $data['attachments'] ?? [];
        unset($data['attachments']);
        $message = $item->messages()->create($data + ['account_id' => $account->id, 'inbox_id' => $item->inbox_id, 'sender_id' => $request->user()->id, 'message_type' => 1, 'status' => 'sent']);
        foreach ($files as $file) {
            $path = $file->storeAs("attachments/{$account->id}", Str::uuid().'.'.$file->extension());
            $message->attachments()->create([
                'account_id' => $account->id, 'disk' => config('filesystems.default'), 'path' => $path,
                'file_name' => $file->getClientOriginalName(), 'content_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);
        }
        $item->update(['last_activity_at' => now()]);

        $payload = MessagePayload::make($message->load(['conversation', 'sender', 'attachments.message.sender']));
        RealtimePublisher::publish($account->id, 'message.created', $payload);
        if ($item->inbox->channel->type === 'email') {
            $domain = $account->domain ?: 'inbound.twoteam.local';
            $delivery = $message->emailDelivery()->create(['message_id_header' => '<twoteam-message-'.$message->id.'@'.$domain.'>', 'status' => 'pending']);
            SendEmailMessage::dispatch($delivery);
        }
        if ($item->inbox->channel->type === 'whatsapp') {
            $delivery = $message->whatsappDelivery()->create(['status' => 'pending']);
            SendWhatsappMessage::dispatch($delivery);
        }
        if (in_array($item->inbox->channel->type, ['facebook', 'instagram'], true)) {
            $delivery = $message->metaDelivery()->create(['status' => 'pending']);
            SendMetaMessage::dispatch($delivery);
        }
        if (in_array($item->inbox->channel->type, ['telegram', 'line', 'sms'], true)) {
            $delivery = $message->prioritizedDelivery()->create(['status' => 'pending']);
            SendPrioritizedMessage::dispatch($delivery);
        }

        return response()->json($payload);
    }

    public function update(Request $request, Account $account, int $conversation, Message $message): JsonResponse
    {
        $item = $this->conversation($request, $account, $conversation);
        $this->scoped($item, $message);
        abort_unless($item->inbox->channel->type === 'api', 403, 'Message status update is only allowed for API inboxes');
        $message->update($request->validate(['status' => ['required', 'in:sent,delivered,read,failed'], 'external_error' => ['nullable', 'string']]));

        return response()->json(MessagePayload::make($message));
    }

    public function destroy(Request $request, Account $account, int $conversation, Message $message): JsonResponse
    {
        $item = $this->conversation($request, $account, $conversation);
        $this->scoped($item, $message);
        foreach ($message->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
        $message->attachments()->delete();
        $message->update(['content' => 'This message was deleted', 'content_type' => 'text', 'content_attributes' => ['deleted' => true]]);

        return response()->json(MessagePayload::make($message));
    }

    public function retry(Request $request, Account $account, int $conversation, Message $message): JsonResponse
    {
        $item = $this->conversation($request, $account, $conversation);
        $this->scoped($item, $message);
        $message->update(['status' => 'sent', 'content_attributes' => [], 'external_error' => null]);

        return response()->json(MessagePayload::make($message));
    }

    private function conversation(Request $request, Account $account, int $displayId): Conversation
    {
        Gate::forUser($request->user())->authorize('view', $account);

        return $account->conversations()->with(['contact', 'assignee', 'inbox.channel'])->where('display_id', $displayId)->firstOrFail();
    }

    private function scoped(Conversation $conversation, Message $message): void
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        $message->loadMissing(['conversation', 'sender', 'attachments.message.sender']);
    }
}
