<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Inbox;
use App\Support\InboxPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InboxController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'view');

        return response()->json(['payload' => $account->inboxes()->with('channel')->orderBy('name')->get()->map(fn ($inbox) => $this->payload($inbox, $request, $account))]);
    }

    public function show(Request $request, Account $account, Inbox $inbox): JsonResponse
    {
        $this->auth($request, $account, 'view');
        $this->scoped($account, $inbox);

        return response()->json($this->payload($inbox, $request, $account));
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $data = $request->validate(['name' => ['required', 'string'], 'channel.type' => ['required', 'in:web_widget,api,email,whatsapp,facebook,instagram,telegram,line,sms'], 'channel.website_url' => ['nullable', 'url'], 'channel.webhook_url' => ['nullable', 'url'], 'channel.widget_color' => ['nullable', 'string'], 'channel.email' => ['required_if:channel.type,email', 'nullable', 'email'], 'channel.provider' => ['nullable', 'string'], 'channel.credentials' => ['nullable', 'array'], 'channel.verified_for_sending' => ['nullable', 'boolean'], 'channel.phone_number' => ['required_if:channel.type,whatsapp', 'nullable', 'string'], 'channel.provider_config.api_key' => ['required_if:channel.type,whatsapp', 'nullable', 'string'], 'channel.provider_config.phone_number_id' => ['required_if:channel.type,whatsapp', 'nullable', 'string'], 'channel.provider_config.business_account_id' => ['required_if:channel.type,whatsapp', 'nullable', 'string'], 'channel.external_account_id' => ['required_if:channel.type,facebook,instagram', 'nullable', 'string'], 'channel.external_account_name' => ['nullable', 'string'], 'channel.access_token' => ['required_if:channel.type,facebook,instagram', 'nullable', 'string'], 'channel.provider_credentials' => ['required_if:channel.type,telegram,line,sms', 'nullable', 'array'], 'channel.external_identity' => ['nullable', 'string']]);
        $inbox = DB::transaction(function () use ($account, $data) {
            $settings = array_diff_key($data['channel'], ['type' => true, 'credentials' => true, 'provider_config' => true, 'access_token' => true, 'provider_credentials' => true]);
            $channel = $account->channels()->create(['type' => $data['channel']['type'], 'settings' => $settings, 'identifier' => Str::random(24), 'secret' => Str::random(48), 'hmac_token' => Str::random(48)]);
            if ($channel->type === 'email') {
                $domain = $account->domain ?: 'inbound.twoteam.local';
                $channel->emailChannel()->create(['account_id' => $account->id, 'email' => $data['channel']['email'], 'forward_to_email' => Str::lower(Str::random(24)).'@'.$domain, 'provider' => $data['channel']['provider'] ?? 'smtp', 'encrypted_credentials' => $data['channel']['credentials'] ?? [], 'verified_for_sending' => $data['channel']['verified_for_sending'] ?? false]);
            }
            if ($channel->type === 'whatsapp') {
                $config = $data['channel']['provider_config'];
                $channel->whatsappChannel()->create(['account_id' => $account->id, 'phone_number' => $data['channel']['phone_number'], 'phone_number_id' => $config['phone_number_id'], 'business_account_id' => $config['business_account_id'], 'encrypted_credentials' => ['api_key' => $config['api_key']], 'provider' => $data['channel']['provider'] ?? 'whatsapp_cloud']);
            }
            if (in_array($channel->type, ['facebook', 'instagram'], true)) {
                $channel->metaChannel()->create(['account_id' => $account->id, 'platform' => $channel->type, 'external_account_id' => $data['channel']['external_account_id'], 'external_account_name' => $data['channel']['external_account_name'] ?? null, 'encrypted_credentials' => ['access_token' => $data['channel']['access_token']]]);
            }
            if (in_array($channel->type, ['telegram', 'line', 'sms'], true)) {
                $channel->prioritizedChannel()->create(['account_id' => $account->id, 'provider' => $channel->type, 'external_identity' => $data['channel']['external_identity'] ?? null, 'encrypted_credentials' => $data['channel']['provider_credentials']]);
            }

            return $account->inboxes()->create(['name' => $data['name'], 'channel_id' => $channel->id]);
        });

        return response()->json($this->payload($inbox, $request, $account));
    }

    public function update(Request $request, Account $account, Inbox $inbox): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $inbox);
        $data = $request->validate([
            'name' => ['sometimes', 'string'], 'greeting_enabled' => ['sometimes', 'boolean'], 'greeting_message' => ['sometimes', 'nullable', 'string'], 'enable_auto_assignment' => ['sometimes', 'boolean'],
            'working_hours_enabled' => ['sometimes', 'boolean'], 'out_of_office_message' => ['sometimes', 'nullable', 'string'], 'timezone' => ['sometimes', 'timezone'],
            'working_hours' => ['sometimes', 'array'], 'working_hours.*.day_of_week' => ['required', 'integer', 'between:0,6'], 'working_hours.*.closed_all_day' => ['required', 'boolean'],
            'working_hours.*.open_all_day' => ['required', 'boolean'], 'working_hours.*.open_hour' => ['nullable', 'integer', 'between:0,23'], 'working_hours.*.open_minutes' => ['nullable', 'integer', 'between:0,59'],
            'working_hours.*.close_hour' => ['nullable', 'integer', 'between:0,23'], 'working_hours.*.close_minutes' => ['nullable', 'integer', 'between:0,59'],
        ]);
        $schedule = $data['working_hours'] ?? [];
        unset($data['working_hours']);
        foreach ($schedule as $hour) {
            $this->validateWorkingHour($hour);
            if ($hour['open_all_day']) {
                $hour = array_merge($hour, ['open_hour' => 0, 'open_minutes' => 0, 'close_hour' => 23, 'close_minutes' => 59]);
            }
            $inbox->workingHours()->where('day_of_week', $hour['day_of_week'])->update($hour);
        }
        $inbox->update($data);

        return response()->json($this->payload($inbox, $request, $account));
    }

    public function destroy(Request $request, Account $account, Inbox $inbox): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $inbox);
        $inbox->channel->delete();

        return response()->json(['message' => 'Inbox deletion has been queued.']);
    }

    public function resetSecret(Request $request, Account $account, Inbox $inbox): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $inbox);
        abort_unless($inbox->channel->type === 'api', 404);
        $inbox->channel->update(['secret' => Str::random(48)]);

        return response()->json($this->payload($inbox, $request, $account));
    }

    private function payload(Inbox $inbox, Request $request, Account $account): array
    {
        $role = $request->user()->accounts()->whereKey($account->id)->value('account_users.role');

        return InboxPayload::make($inbox->loadMissing('channel'), $role === 'administrator');
    }

    private function auth(Request $request, Account $account, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $account);
    }

    private function scoped(Account $account, Inbox $inbox): void
    {
        abort_unless($inbox->account_id === $account->id, 404);
    }

    private function validateWorkingHour(array $hour): void
    {
        if ($hour['closed_all_day'] && $hour['open_all_day']) {
            throw ValidationException::withMessages(['working_hours' => 'A day cannot be both open and closed all day.']);
        }
        if (! $hour['closed_all_day'] && ! $hour['open_all_day']) {
            $open = (($hour['open_hour'] ?? -1) * 60) + ($hour['open_minutes'] ?? -1);
            $close = (($hour['close_hour'] ?? -1) * 60) + ($hour['close_minutes'] ?? -1);
            if ($open < 0 || $close <= $open) {
                throw ValidationException::withMessages(['working_hours' => 'Closing time must be after opening time.']);
            }
        }
    }
}
