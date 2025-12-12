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

    public function test_callback_sets_cookie_and_redirects_for_browser()
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
        $response->assertRedirect('http://frontend.test/auth/complete');

        // Verify cookie exists on the response
        $cookies = $response->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'api_token') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'api_token cookie was not set on callback redirect response');
        $this->assertDatabaseHas('users', ['email' => 'browser@example.com', 'provider_id' => 'google-333']);
    }
}
