<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ReCaptchaService
{
    protected string $verifyUrl;
    protected string $secret;

    public function __construct()
    {
        $this->verifyUrl = (string) config('services.recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');
        $this->secret = (string) config('services.recaptcha.secret');
    }

    public function verify(string $token, ?string $remoteIp = null): array
    {
        if (empty($this->secret)) {
            return ['success' => false, 'error' => 'no_secret', 'message' => 'reCAPTCHA secret not configured'];
        }

        $payload = ['secret' => $this->secret, 'response' => $token];
        if ($remoteIp) {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $resp = Http::asForm()->timeout(3)->post($this->verifyUrl, $payload);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'verification_failed', 'message' => $e->getMessage()];
        }

        if (!$resp->ok()) {
            return ['success' => false, 'error' => 'provider_error', 'status' => $resp->status(), 'body' => $resp->body()];
        }

        $json = $resp->json();
        $success = !empty($json['success']);

        return array_merge(['success' => $success], $json ?: []);
    }
}
