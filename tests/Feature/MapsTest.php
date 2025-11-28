<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_pin_requires_authentication()
    {
        $response = $this->postJson('/api/maps/pin', ['lat' => 1, 'lng' => 2]);
        $response->assertStatus(401)->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp']);
    }

    public function test_maps_pin_validates_lat_lng()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', []);
        $response->assertStatus(422)->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', ['lat' => 'not-a-number', 'lng' => 'not-a-number']);
        $response->assertStatus(422);
    }

    public function test_maps_pin_returns_500_when_api_key_missing()
    {
        $user = User::factory()->create();

        // Ensure env key not set
        putenv('GOOGLE_MAPS_API_KEY=');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', ['lat' => 37.7, 'lng' => -122.4]);
        $response->assertStatus(500)->assertJson(['status' => 'error', 'code' => 500]);
    }

    public function test_maps_pin_success_returns_static_map_url()
    {
        $user = User::factory()->create();

        // Set API key for this test (fake key is fine for URL creation)
        putenv('GOOGLE_MAPS_API_KEY=FAKE-KEY-123');
        $_ENV['GOOGLE_MAPS_API_KEY'] = 'FAKE-KEY-123';
        $_SERVER['GOOGLE_MAPS_API_KEY'] = 'FAKE-KEY-123';
        config()->set('GOOGLE_MAPS_API_KEY', 'FAKE-KEY-123');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', [
            'lat' => 37.7749,
            'lng' => -122.4194,
            'label' => 'Home',
            'zoom' => 14,
            'width' => 600,
            'height' => 300,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['status', 'message', 'data' => ['map_url'], 'code', 'timestamp']);

        $mapUrl = $response->json('data.map_url');
        $this->assertStringContainsString('maps.googleapis.com', $mapUrl);
    }

    public function test_maps_pin_with_address_geocodes_and_returns_map()
    {
        $user = User::factory()->create();

        // Fake geocoding API response
        \Illuminate\Support\Facades\Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => \Illuminate\Support\Facades\Http::response([
                'status' => 'OK',
                'results' => [[
                    'geometry' => ['location' => ['lat' => 37.7749, 'lng' => -122.4194]],
                ]],
            ], 200),
        ]);

        $_ENV['GOOGLE_MAPS_API_KEY'] = 'FAKE-KEY-123';
        $_SERVER['GOOGLE_MAPS_API_KEY'] = 'FAKE-KEY-123';
        putenv('GOOGLE_MAPS_API_KEY=FAKE-KEY-123');
        config()->set('GOOGLE_MAPS_API_KEY', 'FAKE-KEY-123');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', [
            'address' => '1600 Amphitheatre Parkway, Mountain View, CA',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['status', 'message', 'data' => ['map_url'], 'code', 'timestamp']);

        $mapUrl = $response->json('data.map_url');
        $this->assertStringContainsString('maps.googleapis.com', $mapUrl);
    }

    public function test_maps_pin_address_not_found_returns_422()
    {
        $user = User::factory()->create();

        // Fake zero results
        \Illuminate\Support\Facades\Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => \Illuminate\Support\Facades\Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ], 200),
        ]);

        $_ENV['GOOGLE_MAPS_API_KEY'] = 'FAKE-KEY-123';
        $_SERVER['GOOGLE_MAPS_API_KEY'] = 'FAKE-KEY-123';
        putenv('GOOGLE_MAPS_API_KEY=FAKE-KEY-123');
        config()->set('GOOGLE_MAPS_API_KEY', 'FAKE-KEY-123');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', [
            'address' => 'This Address Does Not Exist 12345',
        ]);

        $response->assertStatus(422)->assertJson(['status' => 'error']);
    }
}
