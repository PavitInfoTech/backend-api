<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_token()
    {
        $passwordHash = hash('sha256', 'password');
        $payload = [
            'username' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password_hash' => $passwordHash,
            'password_hash_confirmation' => $passwordHash,
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_login_returns_token()
    {
        $passwordHash = hash('sha256', 'secret');
        $user = User::factory()->create(['password' => $passwordHash]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password_hash' => $passwordHash,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);
    }

    public function test_protected_route_requires_token_and_returns_user()
    {
        $passwordHash = hash('sha256', 'secret');
        $user = User::factory()->create(['password' => $passwordHash]);

        // create token
        $token = $user->createToken('test-token')->plainTextToken;

        // call protected route
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        $response->assertStatus(200)->assertJson(['status' => 'success', 'message' => 'User profile']);
    }
}
