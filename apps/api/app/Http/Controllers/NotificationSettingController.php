<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NotificationSettingController extends Controller
{
    public function show(Request $request, Account $account): JsonResponse
    {
        return response()->json($this->payload($this->setting($request, $account)));
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $data = $request->validate([
            'notification_settings' => ['required', 'array'],
            'notification_settings.selected_email_flags' => ['required', 'array'],
            'notification_settings.selected_email_flags.*' => [Rule::in(NotificationSetting::FLAGS)],
            'notification_settings.selected_push_flags' => ['required', 'array'],
            'notification_settings.selected_push_flags.*' => [Rule::in(NotificationSetting::FLAGS)],
        ]);
        $setting = $this->setting($request, $account);
        $setting->update($data['notification_settings']);

        return response()->json($this->payload($setting));
    }

    private function setting(Request $request, Account $account): NotificationSetting
    {
        Gate::forUser($request->user())->authorize('view', $account);

        return NotificationSetting::firstOrCreate(
            ['account_id' => $account->id, 'user_id' => $request->user()->id],
            ['selected_email_flags' => [], 'selected_push_flags' => []],
        );
    }

    private function payload(NotificationSetting $setting): array
    {
        return [
            'id' => $setting->id, 'user_id' => $setting->user_id, 'account_id' => $setting->account_id,
            'all_email_flags' => NotificationSetting::FLAGS, 'selected_email_flags' => $setting->selected_email_flags,
            'all_push_flags' => NotificationSetting::FLAGS, 'selected_push_flags' => $setting->selected_push_flags,
        ];
    }
}
