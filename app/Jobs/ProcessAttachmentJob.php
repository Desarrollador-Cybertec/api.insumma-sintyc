<?php

namespace App\Jobs;

use App\Enums\ProcessingStatusEnum;
use App\Models\Attachment;
use App\Services\AttachmentProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAttachmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public Attachment $attachment,
    ) {}

    public function handle(AttachmentProcessingService $service): void
    {
        Log::info('[AttachmentJob] Attempt ' . $this->attempts() . '/' . $this->tries, [
            'attachment_id' => $this->attachment->id,
            'uuid' => $this->attachment->uuid,
        ]);

        try {
            $service->process($this->attachment);
        } catch (\Throwable $e) {
            Log::error('[AttachmentJob] Attempt ' . $this->attempts() . ' failed', [
                'attachment_id' => $this->attachment->id,
                'uuid' => $this->attachment->uuid,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        // Preserve the real error from metadata if MaxAttemptsExceededException overwrote it
        $realError = $this->attachment->metadata['error'] ?? $e->getMessage();

        Log::error('[AttachmentJob] PERMANENTLY FAILED', [
            'attachment_id' => $this->attachment->id,
            'uuid' => $this->attachment->uuid,
            'real_error' => $realError,
            'final_exception' => $e->getMessage(),
        ]);

        $this->attachment->update([
            'processing_status' => ProcessingStatusEnum::FAILED,
            'metadata' => array_merge($this->attachment->metadata ?? [], [
                'error' => $realError,
                'final_exception' => get_class($e) . ': ' . $e->getMessage(),
                'failed_at' => now()->toISOString(),
            ]),
        ]);
    }
}
