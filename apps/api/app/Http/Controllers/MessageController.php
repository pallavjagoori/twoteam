<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\ContactPayload;
use App\Support\MessagePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function index(Request $request, Account $account, int $conversation): JsonResponse
    {
        $item = $this->conversation($request, $account, $conversation);
        $query = $item->messages()->with(['conversation', 'sender']);
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
        $data = $request->validate(['content' => ['nullable', 'string'], 'private' => ['sometimes', 'boolean'], 'echo_id' => ['nullable', 'string'], 'content_attributes' => ['sometimes', 'array']]);
        $message = $item->messages()->create($data + ['account_id' => $account->id, 'inbox_id' => $item->inbox_id, 'sender_id' => $request->user()->id, 'message_type' => 1, 'status' => 'sent']);
        $item->update(['last_activity_at' => now()]);

        return response()->json(MessagePayload::make($message->load(['conversation', 'sender'])));
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
        $message->loadMissing(['conversation', 'sender']);
    }
}
