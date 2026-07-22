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
            'name' => $inbox->name, 'channel_type' => 'Channel::'.match ($channel->type) {
                'web_widget' => 'WebWidget', 'email' => 'Email', 'whatsapp' => 'Whatsapp', default => 'Api'
            },
            'greeting_enabled' => $inbox->greeting_enabled, 'greeting_message' => $inbox->greeting_message,
            'working_hours_enabled' => $inbox->working_hours_enabled, 'enable_email_collect' => $inbox->enable_email_collect,
            'csat_survey_enabled' => $inbox->csat_survey_enabled, 'csat_config' => $inbox->csat_config ?? [],
            'enable_auto_assignment' => $inbox->enable_auto_assignment, 'out_of_office_message' => $inbox->out_of_office_message,
            'working_hours' => $inbox->workingHours()->orderBy('day_of_week')->get()->map(fn ($hour) => [
                'day_of_week' => $hour->day_of_week, 'closed_all_day' => $hour->closed_all_day, 'open_all_day' => $hour->open_all_day,
                'open_hour' => $hour->open_hour, 'open_minutes' => $hour->open_minutes, 'close_hour' => $hour->close_hour, 'close_minutes' => $hour->close_minutes,
            ]), 'timezone' => $inbox->timezone, 'callback_webhook_url' => null,
            'allow_messages_after_resolved' => $inbox->allow_messages_after_resolved,
            'lock_to_single_conversation' => $inbox->lock_to_single_conversation,
        ];
        $payload += $channel->type === 'web_widget'
            ? ['website_url' => $settings['website_url'] ?? null, 'website_token' => $channel->identifier, 'widget_color' => $settings['widget_color'] ?? '#1f93ff', 'hmac_mandatory' => $settings['hmac_mandatory'] ?? false]
            : ['webhook_url' => $settings['webhook_url'] ?? null, 'inbox_identifier' => $channel->identifier, 'additional_attributes' => $settings['additional_attributes'] ?? []];
        if ($channel->type === 'email') {
            $email = $channel->emailChannel;
            $payload += ['email' => $email->email, 'forward_to_email' => $email->forward_to_email, 'provider' => $email->provider, 'verified_for_sending' => $email->verified_for_sending];
        }
        if ($channel->type === 'whatsapp') {
            $whatsapp = $channel->whatsappChannel;
            $payload += ['phone_number' => $whatsapp->phone_number, 'provider' => $whatsapp->provider, 'provider_config' => ['phone_number_id' => $whatsapp->phone_number_id, 'business_account_id' => $whatsapp->business_account_id]];
        }
        if ($administrator) {
            $payload += ['hmac_token' => $channel->hmac_token, 'secret' => $channel->secret];
        }

        return $payload;
    }
}
