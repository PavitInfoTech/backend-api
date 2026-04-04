<?php

namespace App\Rules;

use App\Services\ReCaptchaService;
use App\Services\TurnstileService;
use Illuminate\Contracts\Validation\Rule;

class CaptchaRequired implements Rule
{
    protected string $expectedAction;
    protected ?string $message = null;

    public function __construct(?string $expectedAction = null)
    {
        $this->expectedAction = $expectedAction;
    }

    public function passes($attribute, $value)
    {
        $request = request();

        $turnstileEnabled = config('services.turnstile.enabled', false);
        $recaptchaEnabled = config('services.recaptcha.enabled', false);

        // If neither CAPTCHA system is enabled, validation passes
        if (!$turnstileEnabled && !$recaptchaEnabled) {
            return true;
        }

        $turnstileToken = $request->input('turnstile_token');
        $recaptchaToken = $request->input('recaptcha_token');

        // Check if at least one token is provided
        if (empty($turnstileToken) && empty($recaptchaToken)) {
            $this->message = 'CAPTCHA verification is required. Please provide either turnstile_token or recaptcha_token.';
            return false;
        }

        // Validate the provided tokens
        $turnstileService = app(TurnstileService::class);
        $recaptchaService = app(ReCaptchaService::class);

        $errors = [];

        if (!empty($turnstileToken)) {
            $result = $turnstileService->verify($turnstileToken, $this->expectedAction, $request->ip());
            if (!$result['success']) {
                $errors[] = 'Turnstile verification failed: ' . ($result['error'] ?? 'Unknown error');
            }
        }

        if (!empty($recaptchaToken)) {
            $result = $recaptchaService->verify($recaptchaToken, $request->ip());
            if (!$result['success']) {
                $errors[] = 'reCAPTCHA verification failed: ' . ($result['error'] ?? 'Unknown error');
            }
        }

        if (!empty($errors)) {
            $this->message = implode(', ', $errors);
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message ?? 'CAPTCHA verification failed';
    }
}