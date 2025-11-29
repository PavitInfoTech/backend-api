<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Services\GorqService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAiRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $requestId;
    protected GorqService $gorq;

    public function __construct(int $requestId)
    {
        $this->requestId = $requestId;
    }

    public function handle(GorqService $gorq)
    {
        $this->gorq = $gorq;

        $aiReq = AiRequest::find($this->requestId);
        if (! $aiReq) {
            return;
        }

        $aiReq->update(['status' => 'running']);

        try {
            // Build payload using 'messages' if present in meta, otherwise fallback to prompt
            $payload = [
                'messages' => $aiReq->meta['messages'] ?? [['role' => 'user', 'content' => $aiReq->prompt]],
                'model' => $aiReq->model ?? env('GORQ_DEFAULT_MODEL'),
            ];

            $result = $this->gorq->generate($payload);

            if (isset($result['error'])) {
                $aiReq->update(['status' => 'failed', 'error' => json_encode($result)]);
            } else {
                // append gorq_response into meta rather than overwrite
                $meta = is_array($aiReq->meta) ? $aiReq->meta : [];
                $meta['gorq_response'] = $result;
                $aiReq->update(['status' => 'finished', 'result' => json_encode($result), 'meta' => $meta]);
            }
        } catch (Exception $e) {
            $aiReq->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
