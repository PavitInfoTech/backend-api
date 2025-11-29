<?php

namespace App\Http\Controllers\Api;

use App\Services\GorqService;
use App\Models\AiRequest;
use App\Jobs\ProcessAiRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
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
            'prompt' => 'required_without:messages|string|max:5000',
            'messages' => 'required_without:prompt|array',
            'messages.*.role' => 'sometimes|string',
            'messages.*.content' => 'sometimes',
            'model' => 'sometimes|string|max:255',
            'max_tokens' => 'sometimes|integer|min:1|max:2048',
            'async' => 'sometimes|boolean',
        ]);

        // Basic sanitization: handle prompt or messages
        $prompt = null;
        $messages = null;
        if (! empty($validated['messages'])) {
            $messages = $validated['messages'];
            foreach ($messages as $i => $m) {
                $content = $m['content'] ?? '';
                if (is_array($content)) {
                    // join array to string
                    $content = implode("\n", $content);
                }
                $content = preg_replace('/[\x00-\x1F\x7F]/u', '', $content);
                $messages[$i]['content'] = $content;
            }
            // If first message is user, set prompt for DB convenience
            $firstUser = collect($messages)->firstWhere('role', 'user');
            $prompt = $firstUser['content'] ?? (string) ($messages[0]['content'] ?? '');
        } else {
            $raw = $validated['prompt'] ?? $request->input('prompt') ?? '';
            $prompt = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);
            $messages = [
                ['role' => 'user', 'content' => $prompt],
            ];
        }

        // Create an AiRequest log entry
        $aiRequest = AiRequest::create([
            'user_id' => $request->user()?->id,
            'model' => $validated['model'] ?? env('GORQ_DEFAULT_MODEL'),
            'prompt' => $prompt,
            'status' => 'pending',
            'meta' => [
                'max_tokens' => $validated['max_tokens'] ?? 256,
                'messages' => $messages,
            ],
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
            'messages' => $aiRequest->meta['messages'] ?? [['role' => 'user', 'content' => $aiRequest->prompt]],
            'model' => $aiRequest->model,
            'max_tokens' => $aiRequest->meta['max_tokens'] ?? 256,
        ];

        $aiRequest->update(['status' => 'running']);

        $result = $this->gorq->generate($payload);

        if (isset($result['error'])) {
            $aiRequest->update(['status' => 'failed', 'error' => json_encode($result)]);

            // Build a structured error response with the original payload and Gorq response
            $gorqResponse = [
                'status' => $result['status'] ?? null,
                'body' => $result['body'] ?? null,
                'json' => $result['json'] ?? null,
            ];

            $errors = is_array($result) ? $result : ['message' => (string) ($result['error'] ?? $result)];
            $errors['payload'] = $payload;
            $errors['gorq_response'] = $gorqResponse;

            // Log error for debugging
            Log::error('AI provider error', ['message' => $result['error'] ?? 'Unknown', 'gorq' => $gorqResponse]);

            return $this->error('AI provider error', 500, $errors);
        }

        // store result and mark finished; append gorq response into meta rather than overwrite
        $newMeta = is_array($aiRequest->meta) ? $aiRequest->meta : [];
        $newMeta['gorq_response'] = $result;
        $aiRequest->update(['status' => 'finished', 'result' => json_encode($result), 'meta' => $newMeta]);

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
