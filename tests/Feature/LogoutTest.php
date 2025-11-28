<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_with_bearer_token_revokes_token_and_clears_cookie()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // Token should be deleted from DB
        $hashed = hash('sha256', $token);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $hashed]);

        // Response should instruct cookie removal
        $cookies = $response->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'api_token') {
                $found = true; // cookie should be present as deletion
                break;
            }
        }
        $this->assertTrue($found, 'api_token cookie not cleared in JSON logout response');
    }

    public function test_logout_with_cookie_token_revokes_token_and_redirects_and_clears_cookie()
    {
        config(['app.frontend_url' => 'http://frontend.test']);

        $user = User::factory()->create();
        $token = $user->createToken('test-token-2')->plainTextToken;

        $response = $this->withCookie('api_token', $token)->withHeader('Accept', 'text/html')->post('/api/auth/logout');

        $response->assertStatus(302);
        $response->assertRedirect('http://frontend.test/auth/logout');

        $hashed = hash('sha256', $token);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $hashed]);

        $cookies = $response->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'api_token') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'api_token cookie not cleared in redirect logout response');
    }
}
