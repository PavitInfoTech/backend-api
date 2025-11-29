<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_pin_allows_unauthenticated_requests()
    {
        // Ensure we have API key so the endpoint can return a valid URL
        putenv('GOOGLE_MAPS_API_KEY=FAKE-KEY-123');
        config()->set('services.google.maps_api_key', 'FAKE-KEY-123');

        $response = $this->postJson('/api/maps/pin', ['address' => '123 Main St']);
        $response->assertStatus(200)->assertJsonStructure(['status', 'message', 'data' => ['embed_url', 'maps_link', 'iframe', 'address'], 'code', 'timestamp']);
    }

    public function test_maps_pin_validates_address_required()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', []);
        $response->assertStatus(422)->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp']);
        $response->assertJsonPath('errors.address.0', 'The address field is required.');
    }

    public function test_maps_pin_returns_500_when_api_key_missing()
    {
        $user = User::factory()->create();

        // Ensure env key not set
        putenv('GOOGLE_MAPS_API_KEY=');
        config()->set('services.google.maps_api_key', null);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', ['address' => '123 Main St']);
        $response->assertStatus(500)->assertJson(['status' => 'error', 'code' => 500]);
    }

    public function test_maps_pin_success_returns_embed_and_link_urls()
    {
        $user = User::factory()->create();

        // Set API key for this test
        putenv('GOOGLE_MAPS_API_KEY=FAKE-KEY-123');
        $_ENV['GOOGLE_MAPS_API_KEY'] = 'FAKE-KEY-123';
        $_SERVER['GOOGLE_MAPS_API_KEY'] = 'FAKE-KEY-123';
        config()->set('services.google.maps_api_key', 'FAKE-KEY-123');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', [
            'address' => '1600 Amphitheatre Parkway, Mountain View, CA',
            'zoom' => 14,
            'width' => 600,
            'height' => 400,
            'map_type' => 'roadmap',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['status', 'message', 'data' => ['embed_url', 'maps_link', 'iframe', 'address'], 'code', 'timestamp']);

        $embedUrl = $response->json('data.embed_url');
        $mapsLink = $response->json('data.maps_link');
        $iframe = $response->json('data.iframe');

        $this->assertStringContainsString('google.com/maps/embed', $embedUrl);
        $this->assertStringContainsString('FAKE-KEY-123', $embedUrl);
        $this->assertStringContainsString('google.com/maps/search', $mapsLink);
        $this->assertStringContainsString('<iframe', $iframe);
        $this->assertStringContainsString('width="600"', $iframe);
        $this->assertStringContainsString('height="400"', $iframe);
    }

    public function test_maps_pin_with_satellite_map_type()
    {
        $user = User::factory()->create();

        putenv('GOOGLE_MAPS_API_KEY=FAKE-KEY-123');
        config()->set('services.google.maps_api_key', 'FAKE-KEY-123');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', [
            'address' => 'Eiffel Tower, Paris',
            'map_type' => 'satellite',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $embedUrl = $response->json('data.embed_url');
        $this->assertStringContainsString('maptype=satellite', $embedUrl);
    }

    public function test_maps_pin_validates_map_type()
    {
        $user = User::factory()->create();

        putenv('GOOGLE_MAPS_API_KEY=FAKE-KEY-123');
        config()->set('services.google.maps_api_key', 'FAKE-KEY-123');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', [
            'address' => '123 Main St',
            'map_type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_maps_pin_uses_default_values()
    {
        $user = User::factory()->create();

        putenv('GOOGLE_MAPS_API_KEY=FAKE-KEY-123');
        config()->set('services.google.maps_api_key', 'FAKE-KEY-123');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/maps/pin', [
            'address' => 'Sydney Opera House',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $embedUrl = $response->json('data.embed_url');
        $iframe = $response->json('data.iframe');

        // Default zoom is 15
        $this->assertStringContainsString('zoom=15', $embedUrl);
        // Default map type is roadmap
        $this->assertStringContainsString('maptype=roadmap', $embedUrl);
        // Default dimensions
        $this->assertStringContainsString('width="600"', $iframe);
        $this->assertStringContainsString('height="450"', $iframe);
    }
}
