<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GorqService
{
    public function generate(array $payload): array
    {
        $apiKey = config('services.gorq.key', env('GORQ_API_KEY'));
        $base = config('services.gorq.base_url', env('GORQ_BASE_URL', 'https://api.gorq.ai'));

        if (! $apiKey) {
            return ['error' => 'GORQ_API_KEY not configured'];
        }

        $response = Http::withToken($apiKey)
            ->post(rtrim($base, '/') . '/v1/generate', $payload);

        if (! $response->successful()) {
            return ['error' => 'Gorq request failed', 'details' => $response->body()];
        }

        return $response->json();
    }
}
