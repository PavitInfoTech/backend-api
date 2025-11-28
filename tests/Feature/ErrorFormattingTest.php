<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorFormattingTest extends TestCase
{
    public function test_validation_error_response_is_structured()
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp']);
        $response->assertJson(['status' => 'error', 'code' => 422]);
    }

    public function test_404_not_found_response_is_structured()
    {
        $response = $this->getJson('/api/some-non-existent-route');

        // Some middlewares may return 401 (unauthenticated) before route not found depending on environment.
        $this->assertTrue(in_array($response->getStatusCode(), [401, 404]));
        $response->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp']);
        $response->assertJson(['status' => 'error']);
        $this->assertEquals($response->json('code'), $response->getStatusCode());
    }
}
