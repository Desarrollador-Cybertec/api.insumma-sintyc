<?php

namespace App\Services;

use App\Enums\ProcessingStatusEnum;
use App\Enums\VisibilityScopeEnum;
use App\Jobs\ProcessAttachmentJob;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AttachmentUploadService
{
    public function upload(
        UploadedFile $file,
        User $user,
        ?int $taskId = null,
        ?int $areaId = null,
    ): Attachment {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();

        // Store temporarily in local private disk
        $tmpPath = $file->storeAs(
            'tmp',
            "{$uuid}.{$extension}",
            'local'
        );

        $visibilityScope = match (true) {
            $taskId !== null => VisibilityScopeEnum::TASK,
            $areaId !== null => VisibilityScopeEnum::AREA,
            default => VisibilityScopeEnum::USER,
        };

        $attachment = Attachment::create([
            'uuid' => $uuid,
            'task_id' => $taskId,
            'area_id' => $areaId,
            'owner_user_id' => $user->id,
            'uploaded_by' => $user->id,
            'disk' => 'local',
            'storage_path' => $tmpPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_original' => $file->getSize(),
            'processing_status' => ProcessingStatusEnum::PENDING,
            'visibility_scope' => $visibilityScope,
        ]);

        ProcessAttachmentJob::dispatch($attachment);

        return $attachment;
    }
}
