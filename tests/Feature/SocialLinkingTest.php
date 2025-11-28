<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_google_to_authenticated_user()
    {
        $user = User::factory()->create();

        $social = Mockery::mock(SocialiteUserContract::class);
        $social->shouldReceive('getId')->andReturn('google-link-123');
        $social->shouldReceive('getName')->andReturn('Google Link');
        $social->shouldReceive('getEmail')->andReturn('google-link@example.com');
        $social->shouldReceive('getAvatar')->andReturn('https://avatar.example.com/link.png');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($social);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/link/google/callback');
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'provider_name' => 'google', 'provider_id' => 'google-link-123']);
    }

    public function test_unlink_provider_detaches_provider_fields()
    {
        $user = User::factory()->create(['provider_name' => 'github', 'provider_id' => 'gh-123']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/unlink', ['provider' => 'github']);
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'provider_name' => null]);
    }
}
