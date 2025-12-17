<?php

namespace Tests\Unit;

use App\Services\TurnstileService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileServiceTest extends TestCase
{
    public function test_successful_verification()
    {
        config(['services.turnstile.secret' => 'test-secret']);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true, 'action' => 'contact'], 200)]);

        $svc = new TurnstileService();
        $res = $svc->verify('token-value', 'contact');

        $this->assertTrue($res['success']);
        $this->assertEquals('contact', $res['action']);
    }

    public function test_failed_verification()
    {
        config(['services.turnstile.secret' => 'test-secret']);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200)]);

        $svc = new TurnstileService();
        $res = $svc->verify('bad-token', 'contact');

        $this->assertFalse($res['success']);
        $this->assertEquals('invalid-input-response', $res['error-codes'][0]);
    }
}
