<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendCutoverTest extends TestCase
{
    private string $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = tempnam(sys_get_temp_dir(), 'twoteam-manifest-');
        file_put_contents($this->manifest, json_encode([
            'app/javascript/entrypoints/v3app.js' => [
                'file' => 'assets/v3app.js',
                'css' => ['assets/v3app.css'],
            ],
            'app/javascript/entrypoints/dashboard.js' => [
                'file' => 'assets/dashboard.js',
            ],
        ]));
        config([
            'app.name' => 'Twoteam',
            'frontend.manifest_path' => $this->manifest,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->manifest);
        parent::tearDown();
    }

    public function test_root_redirects_to_the_laravel_hosted_login(): void
    {
        $this->get('/')->assertRedirect('/app/login');
    }

    public function test_login_uses_the_unmodified_v3_entrypoint_and_inert_runtime_config(): void
    {
        $this->get('/app/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('id="twoteam-runtime-config"', false)
            ->assertSee('"allowedLoginMethods":["email"]', false)
            ->assertSee('"INSTALLATION_NAME":"Twoteam"', false)
            ->assertSee('/build/chatwoot/assets/v3app.js', false)
            ->assertSee('/build/chatwoot/assets/v3app.css', false);
    }

    public function test_authenticated_application_paths_use_the_dashboard_entrypoint(): void
    {
        $this->get('/app/accounts/1/dashboard')
            ->assertOk()
            ->assertSee('/build/chatwoot/assets/dashboard.js', false);
    }

    public function test_missing_or_incomplete_builds_fail_closed(): void
    {
        config(['frontend.manifest_path' => '/missing/manifest.json']);
        $this->get('/app/login')->assertStatus(503);

        file_put_contents($this->manifest, '{}');
        config(['frontend.manifest_path' => $this->manifest]);
        $this->get('/app/login')->assertStatus(503);
    }
}
