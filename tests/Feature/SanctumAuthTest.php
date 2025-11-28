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
        $payload = [
            'username' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password_hash' => hash('sha256', 'password'),
            'password_hash_confirmation' => hash('sha256', 'password'),
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_login_returns_token()
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make(hash('sha256', 'secret'))]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password_hash' => hash('sha256', 'secret'),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);
    }

    public function test_protected_route_requires_token_and_returns_user()
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make(hash('sha256', 'secret'))]);

        // create token
        $token = $user->createToken('test-token')->plainTextToken;

        // call protected route
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        $response->assertStatus(200)->assertJson(['status' => 'success', 'message' => 'User profile']);
    }
}
