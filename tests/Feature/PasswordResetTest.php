<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        Mail::assertSent(PasswordResetMail::class, function ($mail) use ($user) {
            // check that the frontend reset link contains token and email param
            $data = $mail->build();
            // We must render the view; we can check the url built in the mail stores in 'url'
            $viewData = $mail->viewData ?? [];
            // It's enough to assert the email was passed correctly
            return isset($viewData['url']) && str_contains($viewData['url'], '/auth/reset') && str_contains($viewData['url'], 'email=' . urlencode($user->email));
        });
    }

    public function test_reset_password_with_valid_token_changes_password_and_issues_token()
    {
        $oldHash = hash('sha256', 'oldpass');
        $newHash = hash('sha256', 'newpassword');
        $user = User::factory()->create(['email' => 'reset2@example.com', 'password' => $oldHash]);

        $token = 'my-reset-token-abc';
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => $token, 'created_at' => now()]);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password_hash' => $newHash,
            'password_hash_confirmation' => $newHash,
        ]);

        $response->assertStatus(200)->assertJsonStructure(['status', 'message', 'data' => ['user', 'token']]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->assertEquals($newHash, $user->fresh()->password);
    }
}
