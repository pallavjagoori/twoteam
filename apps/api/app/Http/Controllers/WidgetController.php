<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WidgetSession;
use App\Support\NotificationPublisher;
use App\Support\RealtimePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WidgetController extends Controller
{
    public function page(Request $request): Response
    {
        $session = $this->session($request);
        $channel = $session->inbox->channel;
        $settings = $channel->settings ?? [];
        $runtime = [
            'authToken' => $request->query('cw_conversation'),
            'chatwootPubsubToken' => $session->pubsub_token,
            'chatwootWebChannel' => [
                'avatarUrl' => '', 'locale' => 'en', 'websiteName' => $session->inbox->name,
                'websiteToken' => $channel->identifier, 'welcomeTagline' => $settings['welcome_tagline'] ?? 'How can we help?',
                'welcomeTitle' => $settings['welcome_title'] ?? 'Hi there!', 'widgetColor' => $settings['widget_color'] ?? '#1f93ff',
                'portal' => null, 'enabledFeatures' => ['attachments', 'emoji_picker'],
                'enabledLanguages' => [['iso_639_1_code' => 'en', 'name' => 'English']], 'replyTime' => 'in_a_few_minutes',
                'preChatFormEnabled' => false, 'preChatFormOptions' => [], 'workingHoursEnabled' => false,
                'csatSurveyEnabled' => false, 'workingHours' => [], 'outOfOfficeMessage' => null,
                'utcOffset' => '+00:00', 'timezone' => 'UTC', 'allowMessagesAfterResolved' => true, 'disableBranding' => false,
            ],
            'globalConfig' => ['INSTALLATION_NAME' => 'Twoteam', 'BRAND_NAME' => 'Twoteam'],
        ];
        $json = json_encode($runtime, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $asset = e(config('app.chatwoot_widget_asset_url'));

        return response("<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Twoteam Widget</title></head><body><div id=\"app\" class=\"h-full\"></div><script id=\"twoteam-runtime-config\" type=\"application/json\">{$json}</script><script type=\"module\" src=\"{$asset}\"></script></body></html>");
    }

    public function config(Request $request): JsonResponse
    {
        $channel = $this->channel($request);
        $inbox = $channel->inbox;
        $plainToken = Str::random(64);
        $contact = $channel->account->contacts()->create(['name' => null]);
        $session = WidgetSession::create([
            'account_id' => $channel->account_id, 'inbox_id' => $inbox->id, 'contact_id' => $contact->id,
            'source_id' => Str::uuid(), 'token_hash' => hash('sha256', $plainToken),
            'pubsub_token' => Str::uuid(), 'expires_at' => now()->addDays(180),
        ]);
        $settings = $channel->settings ?? [];

        return response()->json([
            'website_channel_config' => [
                'api_host' => config('app.url'), 'auth_token' => $plainToken, 'website_name' => $inbox->name,
                'website_token' => $channel->identifier, 'widget_color' => $settings['widget_color'] ?? '#1f93ff',
                'welcome_title' => $settings['welcome_title'] ?? 'Hi there!',
                'welcome_tagline' => $settings['welcome_tagline'] ?? 'How can we help?',
                'enabled_features' => ['attachments', 'emoji_picker'], 'locale' => 'en',
            ],
            'contact' => ['id' => $contact->id, 'name' => null, 'email' => null, 'identifier' => null, 'phone_number' => null, 'pubsub_token' => $session->pubsub_token],
            'global_config' => [],
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $session = $this->session($request);
        $conversation = $this->conversation($session);

        return response()->json($conversation ? $this->conversationPayload($conversation) : []);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $session = $this->session($request);
        $data = $request->validate([
            'contact.name' => ['nullable', 'string'], 'contact.email' => ['nullable', 'email'],
            'contact.phone_number' => ['nullable', 'string'], 'contact.custom_attributes' => ['sometimes', 'array'],
            'message.content' => ['required', 'string'], 'message.referer_url' => ['nullable', 'url'],
            'custom_attributes' => ['sometimes', 'array'],
        ]);
        $session->contact->update([
            'name' => data_get($data, 'contact.name'), 'email' => data_get($data, 'contact.email'),
            'phone_number' => data_get($data, 'contact.phone_number'),
            'custom_attributes' => data_get($data, 'contact.custom_attributes', []),
        ]);
        $conversation = DB::transaction(function () use ($session, $data) {
            $conversation = $this->createConversationRecord($session, data_get($data, 'message.referer_url'), $data['custom_attributes'] ?? []);
            $this->createVisitorMessage($session, $conversation, data_get($data, 'message.content'));

            return $conversation;
        });

        return response()->json($this->conversationPayload($conversation->load('messages')));
    }

    public function messages(Request $request): JsonResponse
    {
        $session = $this->session($request);
        $conversation = $this->conversation($session);
        $query = $conversation?->messages()->with(['conversation', 'sender', 'attachments.message.sender']);
        if ($query && $request->filled('before')) {
            $query->where('id', '<', $request->integer('before'));
        }
        if ($query && $request->filled('after')) {
            $query->where('id', '>', $request->integer('after'));
        }

        return response()->json(['payload' => $query?->orderBy('id')->get()->map(fn (Message $message) => $this->messagePayload($message)) ?? [], 'meta' => ['contact_last_seen_at' => $conversation?->contact_last_seen_at?->timestamp]]);
    }

    public function createMessage(Request $request): JsonResponse
    {
        $session = $this->session($request);
        $data = $request->validate(['message.content' => ['nullable', 'string'], 'message.referer_url' => ['nullable', 'url'], 'custom_attributes' => ['sometimes', 'array']]);
        $conversation = $this->conversation($session);
        if (! $conversation) {
            $conversation = DB::transaction(fn () => $this->createConversationRecord($session, data_get($data, 'message.referer_url'), $data['custom_attributes'] ?? []));
        }
        $message = $this->createVisitorMessage($session, $conversation, data_get($data, 'message.content') ?? '');

        return response()->json($this->messagePayload($message));
    }

    public function inboxMembers(Request $request): JsonResponse
    {
        $session = $this->session($request);
        $members = $session->account->users()->get()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'available_name' => $user->display_name ?: $user->name, 'avatar_url' => '', 'availability_status' => 'online']);

        return response()->json(['payload' => $members]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $this->session($request);

        return response()->json([]);
    }

    private function channel(Request $request): Channel
    {
        $data = $request->validate(['website_token' => ['required', 'string']]);

        return Channel::query()->where('type', 'web_widget')->where('identifier', $data['website_token'])->with(['account', 'inbox'])->firstOrFail();
    }

    private function session(Request $request): WidgetSession
    {
        $channel = $this->channel($request);
        $token = (string) ($request->header('X-Auth-Token') ?: $request->query('cw_conversation'));

        return WidgetSession::query()->where('token_hash', hash('sha256', $token))->where('inbox_id', $channel->inbox->id)->where('expires_at', '>', now())->with(['contact', 'inbox'])->firstOrFail();
    }

    private function conversation(WidgetSession $session): ?Conversation
    {
        return Conversation::query()->where('account_id', $session->account_id)->where('inbox_id', $session->inbox_id)->where('contact_id', $session->contact_id)->latest('id')->first();
    }

    private function createVisitorMessage(WidgetSession $session, Conversation $conversation, string $content): Message
    {
        $message = $conversation->messages()->create(['account_id' => $session->account_id, 'inbox_id' => $session->inbox_id, 'content' => $content, 'message_type' => 0, 'status' => 'sent']);
        $conversation->update(['last_activity_at' => now()]);
        RealtimePublisher::publish($session->account_id, 'message.created', $this->messagePayload($message));
        NotificationPublisher::incomingMessage($conversation, $message);

        return $message;
    }

    private function createConversationRecord(WidgetSession $session, ?string $referer, array $customAttributes): Conversation
    {
        $displayId = ((int) Conversation::where('account_id', $session->account_id)->lockForUpdate()->max('display_id')) + 1;

        return Conversation::create([
            'account_id' => $session->account_id, 'inbox_id' => $session->inbox_id, 'contact_id' => $session->contact_id,
            'display_id' => $displayId, 'uuid' => Str::uuid(), 'status' => 'open',
            'additional_attributes' => ['referer' => $referer], 'custom_attributes' => $customAttributes, 'last_activity_at' => now(),
        ]);
    }

    private function conversationPayload(Conversation $conversation): array
    {
        return ['id' => $conversation->display_id, 'inbox_id' => $conversation->inbox_id, 'contact_last_seen_at' => $conversation->contact_last_seen_at?->timestamp, 'status' => $conversation->status, 'messages' => $conversation->messages->map(fn (Message $message) => $this->messagePayload($message)), 'custom_attributes' => $conversation->custom_attributes ?? [], 'contact' => $conversation->contact];
    }

    private function messagePayload(Message $message): array
    {
        return ['id' => $message->id, 'content' => $message->content, 'inbox_id' => $message->inbox_id, 'conversation_id' => $message->conversation->display_id, 'message_type' => $message->message_type, 'content_type' => $message->content_type, 'content_attributes' => $message->content_attributes ?? [], 'created_at' => $message->created_at->timestamp, 'private' => $message->private, 'source_id' => $message->source_id, 'sender' => $message->sender ? ['id' => $message->sender->id, 'name' => $message->sender->name, 'type' => 'user'] : ['id' => $message->conversation->contact_id, 'name' => $message->conversation->contact->name, 'type' => 'contact']];
    }
}
