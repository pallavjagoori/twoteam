<?php

namespace App\Support;

use App\Models\Inbox;

class InboxPayload
{
    public static function make(Inbox $inbox, bool $administrator): array
    {
        $channel = $inbox->channel;
        $settings = $channel->settings ?? [];
        $payload = [
            'id' => $inbox->id, 'avatar_url' => '', 'channel_id' => $channel->id,
            'name' => $inbox->name, 'channel_type' => 'Channel::'.($channel->type === 'web_widget' ? 'WebWidget' : 'Api'),
            'greeting_enabled' => $inbox->greeting_enabled, 'greeting_message' => $inbox->greeting_message,
            'working_hours_enabled' => $inbox->working_hours_enabled, 'enable_email_collect' => $inbox->enable_email_collect,
            'csat_survey_enabled' => $inbox->csat_survey_enabled, 'csat_config' => $inbox->csat_config ?? [],
            'enable_auto_assignment' => $inbox->enable_auto_assignment, 'out_of_office_message' => $inbox->out_of_office_message,
            'working_hours' => [], 'timezone' => $inbox->timezone, 'callback_webhook_url' => null,
            'allow_messages_after_resolved' => $inbox->allow_messages_after_resolved,
            'lock_to_single_conversation' => $inbox->lock_to_single_conversation,
        ];
        $payload += $channel->type === 'web_widget'
            ? ['website_url' => $settings['website_url'] ?? null, 'website_token' => $channel->identifier, 'widget_color' => $settings['widget_color'] ?? '#1f93ff', 'hmac_mandatory' => $settings['hmac_mandatory'] ?? false]
            : ['webhook_url' => $settings['webhook_url'] ?? null, 'inbox_identifier' => $channel->identifier, 'additional_attributes' => $settings['additional_attributes'] ?? []];
        if ($administrator) {
            $payload += ['hmac_token' => $channel->hmac_token, 'secret' => $channel->secret];
        }

        return $payload;
    }
}
