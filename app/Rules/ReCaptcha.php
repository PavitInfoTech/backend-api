<?php

namespace App\Rules;

use App\Services\ReCaptchaService;
use Illuminate\Contracts\Validation\Rule;

class ReCaptcha implements Rule
{
    protected ReCaptchaService $service;
    protected ?string $message = null;

    public function __construct()
    {
        $this->service = app(ReCaptchaService::class);
    }

    public function passes($attribute, $value)
    {
        if (!config('services.recaptcha.enabled')) {
            return true;
        }

        $result = $this->service->verify($value, request()->ip());

        if (!isset($result['success']) || !$result['success']) {
            $this->message = $result['error'] ?? 'reCAPTCHA verification failed';
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message ?? 'reCAPTCHA verification failed';
    }
}
