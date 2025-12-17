<?php

namespace App\Http\Controllers\Api;

use App\Services\TurnstileService;
use App\Services\ReCaptchaService;
use Illuminate\Http\Request;

class CaptchaController extends ApiController
{
    protected TurnstileService $turnstile;
    protected ReCaptchaService $recaptcha;

    public function __construct(TurnstileService $turnstile, ReCaptchaService $recaptcha)
    {
        $this->turnstile = $turnstile;
        $this->recaptcha = $recaptcha;
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'provider' => 'sometimes|string|in:turnstile,recaptcha',
            'token' => 'required|string',
            'action' => 'sometimes|string',
        ]);

        $provider = $data['provider'] ?? 'turnstile';

        if ($provider === 'recaptcha') {
            $result = $this->recaptcha->verify($data['token'], $request->ip());
        } else {
            $result = $this->turnstile->verify($data['token'], $data['action'] ?? null, $request->ip());
        }

        if (empty($result['success'])) {
            return $this->error('Captcha verification failed', 422, ['error' => $result]);
        }

        return $this->success($result, 'Captcha verified');
    }
}
