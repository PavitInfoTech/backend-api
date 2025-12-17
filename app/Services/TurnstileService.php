<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TurnstileService
{
    protected string $verifyUrl;
    protected string $secret;

    public function __construct()
    {
        $this->verifyUrl = (string) config('services.turnstile.verify_url', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
        $this->secret = (string) config('services.turnstile.secret');
    }

    /**
     * Verify a turnstile token
     *
     * @param string $token
     * @param string|null $expectedAction
     * @param string|null $remoteIp
     * @return array
     */
    public function verify(string $token, ?string $expectedAction = null, ?string $remoteIp = null): array
    {
        $payload = [
            'secret' => $this->secret,
            'response' => $token,
        ];

        if ($remoteIp) {
            $payload['remoteip'] = $remoteIp;
        }

        if (empty($this->secret)) {
            return ['success' => false, 'error' => 'no_secret', 'message' => 'Turnstile secret not configured'];
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

        if ($success && $expectedAction && (!isset($json['action']) || $json['action'] !== $expectedAction)) {
            return ['success' => false, 'error' => 'action_mismatch', 'action' => $json['action'] ?? null];
        }

        return array_merge(['success' => $success], $json ?: []);
    }
}
