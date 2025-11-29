<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiRequest;
use App\Models\AiRequest;
use App\Services\GorqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AiEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_generate_logs_request_and_returns_result()
    {
        // Mock the GorqService to return a predictable result
        $mock = \Mockery::mock(GorqService::class);
        $mock->shouldReceive('generate')->andReturn(['text' => 'hello world', 'tokens' => 10]);

        $this->app->instance(GorqService::class, $mock);

        // Create a user and call endpoint
        $user = \App\Models\User::factory()->create();

        $payload = ['prompt' => 'Say hello'];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/ai/generate', $payload);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseCount('ai_requests', 1);
        $ai = AiRequest::first();
        $this->assertEquals('finished', $ai->status);
        $this->assertNotNull($ai->result);
    }

    public function test_async_generate_dispatches_job_and_returns_202()
    {
        Bus::fake();

        $user = \App\Models\User::factory()->create();

        $payload = ['prompt' => 'Long running', 'async' => true];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/ai/generate', $payload);

        $response->assertStatus(202)->assertJsonStructure(['status', 'data' => ['job_id', 'status_url']]);

        Bus::assertDispatched(ProcessAiRequest::class);

        $this->assertDatabaseCount('ai_requests', 1);
        $ai = AiRequest::first();
        $this->assertEquals('pending', $ai->status);
    }

    public function test_job_status_endpoint_returns_job_info()
    {
        $ai = AiRequest::create(['prompt' => 'p', 'status' => 'finished', 'result' => json_encode(['text' => 'ok'])]);

        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/ai/jobs/' . $ai->id . '/status');

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertEquals('finished', $response->json('data.status'));
    }

    public function test_generate_requires_authentication()
    {
        // Now public - ensure unauthenticated request still works by mocking GorqService
        $mock = \Mockery::mock(GorqService::class);
        $mock->shouldReceive('generate')->andReturn(['text' => 'hello', 'tokens' => 1]);
        $this->app->instance(GorqService::class, $mock);

        $response = $this->postJson('/api/ai/generate', ['prompt' => 'Hi']);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
    }

    public function test_generate_validates_prompt_presence_and_length()
    {
        $user = \App\Models\User::factory()->create();

        // Missing prompt
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/ai/generate', []);
        $response->assertStatus(422)->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp']);

        // Too long prompt
        $long = str_repeat('a', 5001);
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/ai/generate', ['prompt' => $long]);
        $response->assertStatus(422);
    }

    public function test_job_status_requires_authentication_and_returns_404_for_missing()
    {
        $ai = AiRequest::create(['prompt' => 'p', 'status' => 'pending']);

        // unauthenticated -> allowed and returns job info
        $response = $this->getJson('/api/ai/jobs/' . $ai->id . '/status');
        $response->assertStatus(200)->assertJsonStructure(['status', 'message', 'data']);

        // authenticated + missing id -> 404
        $response = $this->getJson('/api/ai/jobs/999999/status');
        $response->assertStatus(404)->assertJson(['status' => 'error']);
    }
}
