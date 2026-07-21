<?php

namespace Tests\Feature;

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
}
