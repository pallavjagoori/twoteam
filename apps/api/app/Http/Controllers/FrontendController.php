<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Js;

class FrontendController extends Controller
{
    public function app(Request $request): View|Response
    {
        $entrypoint = $request->is('app/login*', 'app/auth*') ? 'v3app' : 'dashboard';
        $manifestPath = (string) config('frontend.manifest_path');

        if (! is_file($manifestPath)) {
            return response('Chatwoot frontend assets are not built.', 503);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $entry = $manifest["app/javascript/entrypoints/{$entrypoint}.js"] ?? null;
        if (! is_array($entry) || ! isset($entry['file'])) {
            return response("Chatwoot {$entrypoint} entrypoint is unavailable.", 503);
        }

        return view('frontend', [
            'runtimeConfig' => Js::encode($this->runtimeConfig()),
            'script' => asset('build/chatwoot/'.$entry['file']),
            'styles' => array_map(
                fn (string $file) => asset('build/chatwoot/'.$file),
                $entry['css'] ?? [],
            ),
        ]);
    }

    private function runtimeConfig(): array
    {
        return [
            'chatwootConfig' => [
                'hostURL' => config('app.url'),
                'apiHost' => '',
                'allowedLoginMethods' => ['email'],
                'signupEnabled' => 'false',
                'isEnterprise' => 'false',
                'isMfaEnabled' => 'false',
                'selectedLocale' => config('app.locale'),
                'enabledLanguages' => [['iso_639_1_code' => 'en', 'name' => 'English']],
            ],
            'globalConfig' => [
                'INSTALLATION_NAME' => config('app.name'),
                'LOGO' => '',
                'LOGO_DARK' => '',
                'LOGO_THUMBNAIL' => '',
                'HCAPTCHA_SITE_KEY' => '',
                'DISABLE_USER_PROFILE_UPDATE' => false,
                'IS_ENTERPRISE' => false,
                'DEPLOYMENT_ENV' => 'self-hosted',
                'MAXIMUM_FILE_UPLOAD_SIZE' => 40,
                'ACTIVE_PLATFORM_BANNERS' => [],
            ],
            'errorLoggingConfig' => config('frontend.sentry_dsn'),
        ];
    }
}
