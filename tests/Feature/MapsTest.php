<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_pin_allows_unauthenticated_requests()
    {
        $response = $this->postJson('/api/maps/pin', ['address' => '123 Main St']);
        $response->assertStatus(200)->assertJsonStructure([
            'status',
            'message',
            'data' => ['embed_url', 'maps_link', 'iframe', 'address', 'zoom'],
            'code',
            'timestamp',
        ]);
    }

    public function test_maps_pin_validates_address_required()
    {
        $response = $this->postJson('/api/maps/pin', []);
        $response->assertStatus(422)->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp']);
        $response->assertJsonPath('errors.address.0', 'The address field is required.');
    }

    public function test_maps_pin_success_returns_embed_and_maps_link()
    {
        $response = $this->postJson('/api/maps/pin', [
            'address' => '1600 Amphitheatre Parkway, Mountain View, CA',
            'zoom' => 14,
            'width' => 600,
            'height' => 400,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['embed_url', 'maps_link', 'iframe', 'address', 'zoom'],
                'code',
                'timestamp',
            ]);

        $embedUrl = $response->json('data.embed_url');
        $mapsLink = $response->json('data.maps_link');
        $iframe = $response->json('data.iframe');

        $this->assertStringContainsString('maps.google.com/maps', $embedUrl);
        $this->assertStringContainsString('output=embed', $embedUrl);
        $this->assertStringContainsString('z=14', $embedUrl);
        $this->assertStringContainsString('google.com/maps/search', $mapsLink);
        $this->assertStringContainsString('<iframe', $iframe);
        $this->assertStringContainsString('width="600"', $iframe);
        $this->assertStringContainsString('height="400"', $iframe);
    }

    public function test_maps_pin_uses_default_values()
    {
        $response = $this->postJson('/api/maps/pin', [
            'address' => 'Sydney Opera House',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $embedUrl = $response->json('data.embed_url');
        $iframe = $response->json('data.iframe');
        $zoom = $response->json('data.zoom');

        // Default zoom is 15
        $this->assertEquals(15, $zoom);
        $this->assertStringContainsString('z=15', $embedUrl);
        // Default dimensions
        $this->assertStringContainsString('width="600"', $iframe);
        $this->assertStringContainsString('height="450"', $iframe);
    }

    public function test_maps_pin_validates_zoom_range()
    {
        $response = $this->postJson('/api/maps/pin', [
            'address' => '123 Main St',
            'zoom' => 0,
        ]);

        $response->assertStatus(422);

        $response = $this->postJson('/api/maps/pin', [
            'address' => '123 Main St',
            'zoom' => 22,
        ]);

        $response->assertStatus(422);
    }

    public function test_maps_pin_embed_url_contains_encoded_address()
    {
        $address = 'Eiffel Tower, Paris';

        $response = $this->postJson('/api/maps/pin', [
            'address' => $address,
        ]);

        $response->assertStatus(200);
        $embedUrl = $response->json('data.embed_url');

        $this->assertStringContainsString(urlencode($address), $embedUrl);
    }
}
