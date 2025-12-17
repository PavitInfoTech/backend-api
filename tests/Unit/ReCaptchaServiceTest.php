<?php

namespace Tests\Unit;

use App\Services\ReCaptchaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReCaptchaServiceTest extends TestCase
{
    public function test_successful_verification()
    {
        config(['services.recaptcha.secret' => 'test-secret']);
        Http::fake(['https://www.google.com/*' => Http::response(['success' => true], 200)]);

        $svc = new ReCaptchaService();
        $res = $svc->verify('token');

        $this->assertTrue($res['success']);
    }

    public function test_failed_verification()
    {
        config(['services.recaptcha.secret' => 'test-secret']);
        Http::fake(['https://www.google.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200)]);

        $svc = new ReCaptchaService();
        $res = $svc->verify('bad');

        $this->assertFalse($res['success']);
    }
}
