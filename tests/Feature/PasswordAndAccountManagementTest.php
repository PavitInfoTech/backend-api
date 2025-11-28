<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordAndAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_succeeds_with_current_password()
    {
        $oldHash = hash('sha256', 'oldpass');
        $newHash = hash('sha256', 'newpass');
        $user = User::factory()->create(['password' => $oldHash]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/password/change', [
            'current_password_hash' => $oldHash,
            'password_hash' => $newHash,
            'password_hash_confirmation' => $newHash,
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertEquals($newHash, $user->fresh()->password);
    }

    public function test_change_password_fails_with_wrong_current_password()
    {
        $oldHash = hash('sha256', 'oldpass');
        $wrongHash = hash('sha256', 'wrongpass');
        $newHash = hash('sha256', 'newpass');
        $user = User::factory()->create(['password' => $oldHash]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/password/change', [
            'current_password_hash' => $wrongHash,
            'password_hash' => $newHash,
            'password_hash_confirmation' => $newHash,
        ]);

        $response->assertStatus(422)->assertJson(['status' => 'error']);
    }

    public function test_authenticated_user_can_delete_account()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/user');

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_account_requires_authentication()
    {
        $response = $this->deleteJson('/api/user');
        $response->assertStatus(401)->assertJson(['status' => 'error']);
    }
}
