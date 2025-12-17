<?php

namespace App\Rules;

use App\Services\TurnstileService;
use Illuminate\Contracts\Validation\Rule;

class Turnstile implements Rule
{
    protected ?string $action;
    protected TurnstileService $service;
    protected ?string $message = null;

    public function __construct(?string $action = null)
    {
        $this->action = $action;
        $this->service = app(TurnstileService::class);
    }

    public function passes($attribute, $value)
    {
        if (!config('services.turnstile.enabled')) {
            return true;
        }

        $result = $this->service->verify($value, $this->action, request()->ip());

        if (!isset($result['success']) || !$result['success']) {
            $this->message = $result['error'] ?? 'Captcha verification failed';
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message ?? 'Captcha verification failed';
    }
}
