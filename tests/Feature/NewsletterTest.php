<?php

namespace Tests\Feature;

use App\Mail\AdminNewsletterNotificationMail;
use App\Mail\NewsletterSignupMail;
use App\Mail\NewsletterVerificationMail;
use App\Mail\PasswordResetMail;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_subscribe_sends_verification_email()
    {
        Mail::fake();

        $email = 'test@example.com';
        $name = 'John Doe';

        $response = $this->postJson('/api/mail/newsletter', ['email' => $email, 'name' => $name]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        // Check DB - subscriber should be created with verification token
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => $email, 'name' => $name]);

        $subscriber = NewsletterSubscriber::where('email', $email)->first();
        $this->assertNotNull($subscriber->verification_token);
        $this->assertNotNull($subscriber->unsubscribe_token);
        $this->assertNull($subscriber->verified_at);

        // Verification mail sent, not welcome mail yet
        Mail::assertQueued(NewsletterVerificationMail::class);
        Mail::assertNotQueued(NewsletterSignupMail::class);
        Mail::assertQueued(AdminNewsletterNotificationMail::class);
    }

    public function test_newsletter_verify_marks_verified_and_sends_welcome()
    {
        Mail::fake();

        $subscriber = NewsletterSubscriber::create([
            'email' => 'verify@example.com',
            'name' => 'Jane',
            'verification_token' => 'test-verify-token-123',
            'unsubscribe_token' => 'test-unsub-token-123',
        ]);

        $response = $this->getJson('/api/mail/newsletter/verify/test-verify-token-123');

        $response->assertStatus(200)->assertJson(['status' => 'success', 'message' => 'Subscription verified successfully']);

        $subscriber->refresh();
        $this->assertNotNull($subscriber->verified_at);
        $this->assertNull($subscriber->verification_token);

        // Welcome mail now sent
        Mail::assertQueued(NewsletterSignupMail::class);
    }

    public function test_newsletter_verify_invalid_token_returns_404()
    {
        $response = $this->getJson('/api/mail/newsletter/verify/invalid-token');

        $response->assertStatus(404)->assertJson(['status' => 'error']);
    }

    public function test_newsletter_unsubscribe_deletes_subscriber()
    {
        $subscriber = NewsletterSubscriber::create([
            'email' => 'unsub@example.com',
            'name' => 'Bob',
            'verification_token' => null,
            'verified_at' => now(),
            'unsubscribe_token' => 'unsub-token-abc',
        ]);

        $response = $this->getJson('/api/mail/newsletter/unsubscribe/unsub-token-abc');

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'unsub@example.com']);
    }

    public function test_newsletter_unsubscribe_invalid_token_returns_404()
    {
        $response = $this->getJson('/api/mail/newsletter/unsubscribe/invalid-token');

        $response->assertStatus(404)->assertJson(['status' => 'error']);
    }

    public function test_newsletter_duplicate_subscription_returns_success_without_sending()
    {
        Mail::fake();

        $email = 'duplicate@example.com';
        NewsletterSubscriber::create([
            'email' => $email,
            'verification_token' => 'token',
            'unsubscribe_token' => 'unsub',
        ]);

        $response = $this->postJson('/api/mail/newsletter', ['email' => $email]);

        $response->assertStatus(200)->assertJson(['status' => 'success', 'message' => 'Email already subscribed']);

        Mail::assertNothingQueued();
    }

    public function test_password_reset_sends_email_for_existing_user()
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/mail/password-reset', ['email' => 'reset@example.com']);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        Mail::assertQueued(PasswordResetMail::class, function ($mail) {
            return $mail->email === 'reset@example.com';
        });
    }

    public function test_password_reset_does_not_reveal_nonexistent_user()
    {
        Mail::fake();

        $response = $this->postJson('/api/mail/password-reset', ['email' => 'nonexistent@example.com']);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        Mail::assertNothingQueued();
    }
}
