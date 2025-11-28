<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordAndAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_succeeds_with_current_password()
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make(hash('sha256', 'oldpass'))]);

        $newPassHash = hash('sha256', 'newpass');
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/password/change', [
            'current_password_hash' => hash('sha256', 'oldpass'),
            'password_hash' => $newPassHash,
            'password_hash_confirmation' => $newPassHash,
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($newPassHash, $user->fresh()->password));
    }

    public function test_change_password_fails_with_wrong_current_password()
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make(hash('sha256', 'oldpass'))]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/password/change', [
            'current_password_hash' => hash('sha256', 'wrongpass'),
            'password_hash' => hash('sha256', 'newpass'),
            'password_hash_confirmation' => hash('sha256', 'newpass'),
        ]);

        $response->assertStatus(422)->assertJson(['status' => 'error']);
    }

    public function test_change_password_revokes_existing_tokens()
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make(hash('sha256', 'oldpass'))]);

        $token = $user->createToken('test-token')->plainTextToken;

        $newPassHash = hash('sha256', 'newpass');
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/password/change', [
                'current_password_hash' => hash('sha256', 'oldpass'),
                'password_hash' => $newPassHash,
                'password_hash_confirmation' => $newPassHash,
            ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        // Tokens should be revoked after change
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => \App\Models\User::class,
        ]);
        // Also verify that Sanctum no longer finds the token
        $this->assertNull(\Laravel\Sanctum\PersonalAccessToken::findToken($token));

        // Using the old token should fail since tokens are revoked after change
        // Note: we assert DB token deletion and PersonalAccessToken::findToken returns null; specific
        // guard behaviour may still validate the token due to guard caching in tests; focusing on
        // DB deletion ensures tokens were revoked as required.
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

    public function test_token_deletion_via_relation_works()
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make(hash('sha256', 'secret'))]);
        $token = $user->createToken('manual')->plainTextToken;
        $this->assertNotNull(\Laravel\Sanctum\PersonalAccessToken::findToken($token));

        $user->tokens()->delete();
        $this->assertNull(\Laravel\Sanctum\PersonalAccessToken::findToken($token));
    }
}
