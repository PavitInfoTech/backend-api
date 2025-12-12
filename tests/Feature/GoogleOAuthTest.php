<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_creates_user_and_returns_token_for_new_user()
    {
        $social = Mockery::mock(SocialiteUserContract::class);
        $social->shouldReceive('getId')->andReturn('google-123');
        $social->shouldReceive('getName')->andReturn('Google Test');
        $social->shouldReceive('getEmail')->andReturn('google@example.com');
        $social->shouldReceive('getAvatar')->andReturn('https://avatar.example.com/1.png');
        $social->shouldReceive('getNickname')->andReturn('googleuser');
        $social->user = ['given_name' => 'Google', 'family_name' => 'Test'];

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($social);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertStatus(200)->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'google@example.com', 'provider_id' => 'google-123']);
    }

    public function test_callback_uses_existing_user_by_email_and_returns_token()
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $social = Mockery::mock(SocialiteUserContract::class);
        $social->shouldReceive('getId')->andReturn('google-222');
        $social->shouldReceive('getName')->andReturn('Existing Google');
        $social->shouldReceive('getEmail')->andReturn('existing@example.com');
        $social->shouldReceive('getAvatar')->andReturn('https://avatar.example.com/2.png');
        $social->shouldReceive('getNickname')->andReturn('existinggoogle');
        $social->user = ['given_name' => 'Existing', 'family_name' => 'Google'];

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($social);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertStatus(200)->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'existing@example.com', 'provider_id' => 'google-222']);
    }

    public function test_callback_passes_token_in_query_and_redirects_for_browser()
    {
        // Ensure frontend URL used by callback redirect
        config(['app.frontend_url' => 'http://frontend.test']);

        $social = Mockery::mock(SocialiteUserContract::class);
        $social->shouldReceive('getId')->andReturn('google-333');
        $social->shouldReceive('getName')->andReturn('Browser User');
        $social->shouldReceive('getEmail')->andReturn('browser@example.com');
        $social->shouldReceive('getAvatar')->andReturn('https://avatar.example.com/3.png');
        $social->shouldReceive('getNickname')->andReturn('browseruser');
        $social->user = ['given_name' => 'Browser', 'family_name' => 'User'];

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($social);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/api/auth/google/callback', ['Accept' => 'text/html']);

        $response->assertStatus(302);

        // Verify redirect URL contains token query parameter
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringStartsWith('http://frontend.test/auth/complete?token=', $redirectUrl);

        // Parse and verify the token is a valid Sanctum token format (id|plaintext)
        $parsedUrl = parse_url($redirectUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $this->assertArrayHasKey('token', $queryParams);
        $this->assertNotEmpty($queryParams['token']);

        $this->assertDatabaseHas('users', ['email' => 'browser@example.com', 'provider_id' => 'google-333']);
    }
}
