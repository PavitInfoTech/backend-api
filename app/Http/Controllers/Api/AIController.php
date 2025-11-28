<?php

namespace App\Http\Controllers\Api;

use App\Services\GorqService;
use App\Models\AiRequest;
use App\Jobs\ProcessAiRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Http\Request;

class AIController extends ApiController
{
    protected GorqService $gorq;

    public function __construct(GorqService $gorq)
    {
        $this->gorq = $gorq;
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:5000',
            'model' => 'sometimes|string|max:255',
            'max_tokens' => 'sometimes|integer|min:1|max:2048',
            'async' => 'sometimes|boolean',
        ]);

        // Basic sanitization: strip control characters
        $prompt = preg_replace('/[\x00-\x1F\x7F]/u', '', $validated['prompt']);

        // Create an AiRequest log entry
        $aiRequest = AiRequest::create([
            'user_id' => $request->user()?->id,
            'model' => $validated['model'] ?? env('GORQ_DEFAULT_MODEL'),
            'prompt' => $prompt,
            'status' => 'pending',
            'meta' => ['max_tokens' => $validated['max_tokens'] ?? 256],
        ]);

        // If client asked for async processing, dispatch a job and return job-id
        if (! empty($validated['async'])) {
            // dispatch job to queue
            ProcessAiRequest::dispatch($aiRequest->id);

            // Build a status URL that depends on whether the API is served on a special subdomain
            $apiDomain = env('API_DOMAIN');
            if (! empty($apiDomain)) {
                $apiDomain = preg_match('/^https?:\/\//', $apiDomain) ? $apiDomain : ('https://' . $apiDomain);
                $statusUrl = rtrim($apiDomain, '/') . '/ai/jobs/' . $aiRequest->id . '/status';
            } else {
                $statusUrl = url('/api/ai/jobs/' . $aiRequest->id . '/status');
            }

            return response()->json([
                'status' => 'accepted',
                'message' => 'Request accepted, processing',
                'data' => [
                    'job_id' => $aiRequest->id,
                    'status_url' => $statusUrl,
                ],
            ], 202);
        }

        // Synchronous processing: call Gorq and store result
        $payload = [
            'prompt' => $prompt,
            'model' => $aiRequest->model,
            'max_tokens' => $aiRequest->meta['max_tokens'] ?? 256,
        ];

        $aiRequest->update(['status' => 'running']);

        $result = $this->gorq->generate($payload);

        if (isset($result['error'])) {
            $aiRequest->update(['status' => 'failed', 'error' => json_encode($result)]);
            return $this->error('AI provider error', 500, $result['error'] ?? $result);
        }

        // store result and mark finished
        $aiRequest->update(['status' => 'finished', 'result' => json_encode($result), 'meta' => $result]);

        return $this->success($result, 'AI generation result');
    }

    public function jobStatus(Request $request, $id)
    {
        $ai = AiRequest::find($id);
        if (! $ai) {
            return $this->error('Job not found', 404);
        }

        return $this->success([
            'id' => $ai->id,
            'status' => $ai->status,
            'result' => $ai->result ? json_decode($ai->result, true) : null,
            'error' => $ai->error ? json_decode($ai->error, true) : $ai->error,
            'meta' => $ai->meta,
            'created_at' => $ai->created_at,
            'updated_at' => $ai->updated_at,
        ], 'AI job status');
    }
}
