<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_requires_auth()
    {
        $response = $this->putJson('/api/user', ['first_name' => 'New', 'last_name' => 'Name']);

        $response->assertStatus(401)
            ->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp'])
            ->assertJson(['status' => 'error', 'code' => 401]);
    }

    public function test_update_validates_input()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // email invalid
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/user', ['email' => 'not-an-email']);

        $response->assertStatus(422)
            ->assertJsonStructure(['status', 'message', 'errors', 'code', 'timestamp'])
            ->assertJson(['status' => 'error', 'code' => 422]);

        // name too long
        $long = str_repeat('x', 300);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/user', ['first_name' => $long]);

        $response->assertStatus(422);
    }

    public function test_update_success_and_unique_email_constraint()
    {
        $user = User::factory()->create(['email' => 'user1@example.com', 'first_name' => 'Old', 'last_name' => 'Name']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $token = $user->createToken('test-token')->plainTextToken;

        // Attempt to set email to another user's email: should 422
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/user', ['email' => 'other@example.com']);

        $response->assertStatus(422);

        // Successful update
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/user', ['first_name' => 'New', 'last_name' => 'Name', 'email' => 'newemail@example.com']);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success', 'message' => 'Profile updated']);

        $this->assertDatabaseHas('users', ['email' => 'newemail@example.com', 'first_name' => 'New', 'last_name' => 'Name']);
    }
}
