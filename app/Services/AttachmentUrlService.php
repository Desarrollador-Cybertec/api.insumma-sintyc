<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentUrlService
{
    private const READ_EXPIRATION_MINUTES = 5;
    private const DOWNLOAD_EXPIRATION_MINUTES = 15;

    public function signedUrl(Attachment $attachment, bool $download = false): ?string
    {
        if (!$attachment->isReady() || !$attachment->storage_path) {
            return null;
        }

        $minutes = $download
            ? self::DOWNLOAD_EXPIRATION_MINUTES
            : self::READ_EXPIRATION_MINUTES;

        $options = [];
        if ($download) {
            $options['ResponseContentDisposition'] = "attachment; filename=\"{$attachment->original_name}\"";
        }

        return Storage::disk($attachment->disk)
            ->temporaryUrl($attachment->storage_path, now()->addMinutes($minutes), $options);
    }

    public function expiresAt(bool $download = false): string
    {
        $minutes = $download
            ? self::DOWNLOAD_EXPIRATION_MINUTES
            : self::READ_EXPIRATION_MINUTES;

        return now()->addMinutes($minutes)->toISOString();
    }
}
