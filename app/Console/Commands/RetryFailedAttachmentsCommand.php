<?php

namespace App\Console\Commands;

use App\Enums\ProcessingStatusEnum;
use App\Jobs\ProcessAttachmentJob;
use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RetryFailedAttachmentsCommand extends Command
{
    protected $signature = 'attachments:retry
                            {--status=* : Statuses to retry (default: processing,failed)}
                            {--force : Also retry attachments whose temp file is missing (will mark as failed)}';

    protected $description = 'Reset stuck attachments and re-dispatch processing jobs';

    public function handle(): int
    {
        $statuses = $this->option('status') ?: ['processing', 'failed'];

        $attachments = Attachment::whereIn('processing_status', $statuses)->get();

        if ($attachments->isEmpty()) {
            $this->info("No attachments found with status: " . implode(', ', $statuses));
            return self::SUCCESS;
        }

        $this->info("Found {$attachments->count()} attachment(s) to retry.");

        $dispatched = 0;
        $skipped = 0;

        foreach ($attachments as $attachment) {
            // If already on supabase disk, something went partially wrong — skip
            if ($attachment->disk === 'supabase' && $attachment->storage_path) {
                $this->warn("  Skipping #{$attachment->id} ({$attachment->uuid}) — already has supabase path, manual review needed.");
                $skipped++;
                continue;
            }

            // Check temp file still exists
            if ($attachment->storage_path && !Storage::disk('local')->exists($attachment->storage_path)) {
                if ($this->option('force')) {
                    $this->warn("  Marking #{$attachment->id} ({$attachment->uuid}) as failed — temp file missing.");
                    $attachment->update([
                        'processing_status' => ProcessingStatusEnum::FAILED,
                        'metadata' => array_merge($attachment->metadata ?? [], [
                            'error' => 'Temp file missing during retry',
                            'failed_at' => now()->toISOString(),
                        ]),
                    ]);
                } else {
                    $this->warn("  Skipping #{$attachment->id} ({$attachment->uuid}) — temp file missing (use --force to mark failed).");
                }
                $skipped++;
                continue;
            }

            // Reset to pending and re-dispatch
            $attachment->update(['processing_status' => ProcessingStatusEnum::PENDING]);
            ProcessAttachmentJob::dispatch($attachment);
            $this->line("  Dispatched #{$attachment->id} ({$attachment->original_name})");
            $dispatched++;
        }

        $this->newLine();
        $this->info("Done. Dispatched: {$dispatched}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
