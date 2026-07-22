<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Demo Agent',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'pubsub_token' => 'twoteam-demo-agent',
            'email_verified_at' => now(),
        ]);
        $account = Account::query()->firstOrCreate(['name' => 'Twoteam Demo']);
        $account->users()->syncWithoutDetaching([$user->id => ['role' => 'administrator']]);
        $channel = $account->channels()->updateOrCreate(['identifier' => 'twoteam-demo-widget'], [
            'type' => 'web_widget', 'settings' => ['website_url' => 'http://127.0.0.1:8000', 'widget_color' => '#1f93ff'],
            'secret' => 'demo-secret', 'hmac_token' => 'demo-hmac',
        ]);
        $account->inboxes()->firstOrCreate(['channel_id' => $channel->id], ['name' => 'Twoteam Website']);
    }
}
