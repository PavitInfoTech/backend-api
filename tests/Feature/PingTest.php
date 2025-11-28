<?php

namespace Tests\Feature;

use Tests\TestCase;

class PingTest extends TestCase
{
    public function test_ping_endpoint_returns_ok()
    {
        $response = $this->getJson(route('ping'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }
}
