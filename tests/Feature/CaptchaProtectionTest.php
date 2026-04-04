<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CaptchaProtectionTest extends TestCase
{
    public function test_contact_requires_captcha_when_enabled()
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => 'test-secret']);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => false], 200)]);

        $response = $this->postJson('/api/mail/contact', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'message' => 'Hi',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_contact_passes_with_valid_captcha()
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => 'test-secret', 'mail.from.address' => 'admin@example.test']);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'contact'], 200)]);

        Mail::fake();

        $response = $this->postJson('/api/mail/contact', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'message' => 'Hi',
            'turnstile_token' => 'valid'
        ]);

        $response->assertStatus(200);
        Mail::assertSent(\App\Mail\ContactMail::class);
    }

    public function test_contact_passes_with_valid_recaptcha()
    {
        config(['services.recaptcha.enabled' => true, 'services.recaptcha.secret' => 'test-secret', 'mail.from.address' => 'admin@example.test']);
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true], 200)]);

        Mail::fake();

        $response = $this->postJson('/api/mail/contact', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'message' => 'Hi',
            'recaptcha_token' => 'valid'
        ]);

        $response->assertStatus(200);
        Mail::assertSent(\App\Mail\ContactMail::class);
    }
}
