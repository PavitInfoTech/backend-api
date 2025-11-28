<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GithubOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_creates_user_and_returns_token_for_new_user()
    {
        $social = Mockery::mock(SocialiteUserContract::class);
        $social->shouldReceive('getId')->andReturn('github-123');
        $social->shouldReceive('getName')->andReturn('Github Test');
        $social->shouldReceive('getEmail')->andReturn('github@example.com');
        $social->shouldReceive('getAvatar')->andReturn('https://avatar.example.com/1.png');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($social);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $response = $this->getJson('/api/auth/github/callback');

        $response->assertStatus(200)->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'github@example.com', 'provider_id' => 'github-123']);
    }

    public function test_callback_uses_existing_user_by_email_and_returns_token()
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $social = Mockery::mock(SocialiteUserContract::class);
        $social->shouldReceive('getId')->andReturn('github-222');
        $social->shouldReceive('getName')->andReturn('Existing Github');
        $social->shouldReceive('getEmail')->andReturn('existing@example.com');
        $social->shouldReceive('getAvatar')->andReturn('https://avatar.example.com/2.png');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($social);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $response = $this->getJson('/api/auth/github/callback');

        $response->assertStatus(200)->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'existing@example.com', 'provider_id' => 'github-222']);
    }

    public function test_callback_sets_cookie_and_redirects_for_browser()
    {
        // Ensure frontend URL used by callback redirect
        config(['app.frontend_url' => 'http://frontend.test']);

        $social = Mockery::mock(SocialiteUserContract::class);
        $social->shouldReceive('getId')->andReturn('github-333');
        $social->shouldReceive('getName')->andReturn('Browser User');
        $social->shouldReceive('getEmail')->andReturn('browser@example.com');
        $social->shouldReceive('getAvatar')->andReturn('https://avatar.example.com/3.png');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($social);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $response = $this->get('/api/auth/github/callback', ['Accept' => 'text/html']);

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
        $this->assertDatabaseHas('users', ['email' => 'browser@example.com', 'provider_id' => 'github-333']);
    }
}
