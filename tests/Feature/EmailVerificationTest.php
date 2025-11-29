<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_verification_sends_email()
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'verify@example.com', 'email_verified_at' => null]);

        $response = $this->postJson('/api/auth/verify/send', ['email' => $user->email]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        Mail::assertQueued(EmailVerificationMail::class);

        $this->assertDatabaseHas('email_verification_tokens', ['email' => $user->email]);
    }

    public function test_send_verification_for_already_verified_user()
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'verified@example.com', 'email_verified_at' => now()]);

        $response = $this->postJson('/api/auth/verify/send', ['email' => $user->email]);

        $response->assertStatus(200)->assertJson(['status' => 'success', 'message' => 'Email already verified']);

        Mail::assertNothingQueued();
    }

    public function test_send_verification_for_nonexistent_email_does_not_reveal()
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/verify/send', ['email' => 'nonexistent@example.com']);

        // Should return success to not reveal whether email exists
        $response->assertStatus(200)->assertJson(['status' => 'success']);

        Mail::assertNothingQueued();
    }

    public function test_verify_email_redirects_and_marks_verified()
    {
        $user = User::factory()->create(['email' => 'verify2@example.com', 'email_verified_at' => null]);

        $token = 'verify-token-123';

        DB::table('email_verification_tokens')->insert(['email' => $user->email, 'token' => $token, 'created_at' => now()]);

        config(['app.frontend_url' => 'http://frontend.test']);

        $response = $this->get('/api/auth/verify/' . $token);

        $response->assertStatus(302)->assertRedirect('http://frontend.test/auth/verified');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_tokens', ['email' => $user->email]);
    }
}
