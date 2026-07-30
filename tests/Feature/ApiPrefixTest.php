<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiPrefixTest extends TestCase
{
    public function test_api_health_endpoint_requires_the_api_prefix(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');

        $this->getJson('/ping')->assertNotFound();
    }

    public function test_api_domain_does_not_create_root_path_aliases(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'api.example.com'])
            ->getJson('/api/ping')
            ->assertOk();

        $this->withServerVariables(['HTTP_HOST' => 'api.example.com'])
            ->getJson('/ping')
            ->assertNotFound();
    }
}
