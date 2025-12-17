<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaptchaVerifyTest extends TestCase
{
    public function test_turnstile_verify_success()
    {
        config(['services.turnstile.secret' => 'test-secret']);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'contact'], 200)]);

        $response = $this->postJson('/api/captcha/verify', ['token' => 'tok', 'action' => 'contact']);
        $response->assertStatus(200)->assertJson(['status' => 'success']);
    }

    public function test_recaptcha_verify_success()
    {
        config(['services.recaptcha.secret' => 'test-secret']);
        Http::fake(['https://www.google.com/*' => Http::response(['success' => true], 200)]);

        $response = $this->postJson('/api/captcha/verify', ['provider' => 'recaptcha', 'token' => 'tok']);
        $response->assertStatus(200)->assertJson(['status' => 'success']);
    }
}
