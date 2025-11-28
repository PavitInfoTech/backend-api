<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_password_reset_creates_token_and_sends_mail()
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/auth/password/forgot', ['email' => $user->email]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        Mail::assertSent(PasswordResetMail::class);
    }

    public function test_reset_password_with_valid_token_changes_password_and_issues_token()
    {
        $user = User::factory()->create(['email' => 'reset2@example.com', 'password' => Hash::make('oldpass')]);

        $token = 'my-reset-token-abc';
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => $token, 'created_at' => now()]);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
    }
}
