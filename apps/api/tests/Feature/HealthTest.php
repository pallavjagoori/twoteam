<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_api_health_endpoint_reports_the_service_status(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'twoteam-api',
            ]);
    }

    public function test_framework_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_readiness_reports_database_and_cache_health(): void
    {
        $this->getJson('/api/health/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'service' => 'twoteam-api',
                'dependencies' => ['database' => 'ok', 'cache' => 'ok'],
            ]);
    }

    public function test_readiness_fails_closed_when_database_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('offline'));

        $this->getJson('/api/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('dependencies.database', 'unavailable')
            ->assertJsonPath('dependencies.cache', 'ok');
    }

    public function test_readiness_fails_closed_when_cache_is_unavailable(): void
    {
        Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('offline'));

        $this->getJson('/api/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('dependencies.database', 'ok')
            ->assertJsonPath('dependencies.cache', 'unavailable');
    }

    public function test_api_responses_include_security_and_request_context_headers(): void
    {
        $response = $this->withHeader('X-Request-ID', 'release-check_123')
            ->getJson('/api/health');

        $response->assertHeader('X-Request-ID', 'release-check_123')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_invalid_request_ids_are_replaced_and_https_is_pinned(): void
    {
        $response = $this->withHeader('X-Request-ID', 'invalid request id')
            ->getJson('https://localhost/api/health');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/',
            (string) $response->headers->get('X-Request-ID'),
        );
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_api_rate_limit_rejects_excess_requests(): void
    {
        RateLimiter::for('api', fn () => Limit::perMinute(1)->by('health-test'));

        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/health')->assertStatus(429);
    }
}
