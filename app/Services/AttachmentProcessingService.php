<?php

namespace App\Services;

use App\Enums\ProcessingStatusEnum;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttachmentProcessingService
{
    private const MAX_IMAGE_WIDTH = 2048;
    private const WEBP_QUALITY = 80;

    public function process(Attachment $attachment): void
    {
        $context = ['attachment_id' => $attachment->id, 'uuid' => $attachment->uuid];

        Log::info('[Attachment] Step 1/7: Starting processing', $context);
        $attachment->update(['processing_status' => ProcessingStatusEnum::PROCESSING]);

        // Step 2: Read temp file
        Log::info('[Attachment] Step 2/7: Reading temp file', array_merge($context, [
            'disk' => 'local',
            'path' => $attachment->storage_path,
        ]));

        $localDisk = Storage::disk('local');
        $tmpPath = $attachment->storage_path;

        if (!$localDisk->exists($tmpPath)) {
            $this->fail($attachment, "Temp file not found: {$tmpPath}");
            throw new \RuntimeException("Temporary file not found: {$tmpPath}");
        }

        $fileContents = $localDisk->get($tmpPath);
        $fileSize = strlen($fileContents);
        $finalExtension = $attachment->extension;
        $mimeType = $attachment->mime_type;

        Log::info('[Attachment] Step 3/7: File read OK', array_merge($context, [
            'size_bytes' => $fileSize,
            'mime_type' => $mimeType,
            'is_image' => $attachment->isImage(),
        ]));

        // Step 4: Process image if applicable (only if processable type AND >= 1MB)
        $shouldProcess = $attachment->isImage()
            && $this->isProcessableImage($mimeType)
            && $fileSize >= 1_048_576;

        if ($shouldProcess) {
            Log::info('[Attachment] Step 4/7: Processing image (GD)', $context);

            try {
                [$fileContents, $finalExtension, $mimeType] = $this->processImage($fileContents);
                Log::info('[Attachment] Step 4/7: Image processed OK', array_merge($context, [
                    'original_size' => $fileSize,
                    'processed_size' => strlen($fileContents),
                    'output_format' => $mimeType,
                ]));
            } catch (\Throwable $e) {
                Log::warning('[Attachment] Step 4/7: Image processing failed, uploading original', array_merge($context, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]));
                // Fall back to uploading original file without conversion
                $fileContents = $localDisk->get($tmpPath);
            }
        } else {
            $reason = !$attachment->isImage()
                ? 'not an image'
                : (!$this->isProcessableImage($mimeType) ? 'unsupported mime type' : 'under 1MB, skipping processing');
            Log::info('[Attachment] Step 4/7: Skipped — ' . $reason, array_merge($context, [
                'size_bytes' => $fileSize,
                'size_mb' => round($fileSize / 1_048_576, 2),
            ]));
        }

        // Step 5: Build storage path
        $storedName = "{$attachment->uuid}.{$finalExtension}";
        $storagePath = $this->buildStoragePath($attachment, $storedName);

        Log::info('[Attachment] Step 5/7: Uploading to Supabase S3', array_merge($context, [
            'storage_path' => $storagePath,
            'content_type' => $mimeType,
            'size_bytes' => strlen($fileContents),
        ]));

        // Step 6: Upload to Supabase
        try {
            $result = Storage::disk('supabase')->put($storagePath, $fileContents, [
                'ContentType' => $mimeType,
            ]);

            if (!$result) {
                $this->fail($attachment, "S3 put returned false for path: {$storagePath}");
                throw new \RuntimeException("S3 put returned false for path: {$storagePath}");
            }

            Log::info('[Attachment] Step 6/7: Upload OK', $context);
        } catch (\Throwable $e) {
            $errorMsg = "S3 upload failed: {$e->getMessage()}";
            Log::error('[Attachment] Step 6/7: Upload FAILED', array_merge($context, [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]));
            $this->fail($attachment, $errorMsg);
            throw $e;
        }

        // Step 7: Update DB and cleanup
        $checksum = hash('sha256', $fileContents);

        $attachment->update([
            'disk' => 'supabase',
            'bucket' => config('filesystems.disks.supabase.bucket'),
            'storage_path' => $storagePath,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'extension' => $finalExtension,
            'size_processed' => strlen($fileContents),
            'checksum' => $checksum,
            'processing_status' => ProcessingStatusEnum::READY,
            'processed_at' => now(),
            'uploaded_at' => now(),
        ]);

        $localDisk->delete($tmpPath);

        Log::info('[Attachment] Step 7/7: DONE', array_merge($context, [
            'final_path' => $storagePath,
            'final_size' => strlen($fileContents),
        ]));
    }

    private function fail(Attachment $attachment, string $error): void
    {
        $attachment->update([
            'processing_status' => ProcessingStatusEnum::FAILED,
            'metadata' => array_merge($attachment->metadata ?? [], [
                'error' => $error,
                'failed_at' => now()->toISOString(),
            ]),
        ]);
    }

    private function isProcessableImage(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ]);
    }

    private function processImage(string $fileContents): array
    {
        Log::info('[Attachment] processImage: creating GD resource from string (' . strlen($fileContents) . ' bytes)');

        $source = @imagecreatefromstring($fileContents);

        if ($source === false) {
            throw new \RuntimeException('Could not read image data with GD.');
        }

        Log::info('[Attachment] processImage: GD resource created OK');

        // Fix EXIF orientation for JPEG
        $source = $this->fixExifOrientation($source, $fileContents);

        $width = imagesx($source);
        $height = imagesy($source);
        Log::info("[Attachment] processImage: dimensions {$width}x{$height}");

        // Resize if exceeds max width, maintaining aspect ratio
        if ($width > self::MAX_IMAGE_WIDTH) {
            $newWidth = self::MAX_IMAGE_WIDTH;
            $newHeight = (int) round($height * ($newWidth / $width));

            Log::info("[Attachment] processImage: resizing to {$newWidth}x{$newHeight}");
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        // Check WebP support and encode
        $supportsWebp = function_exists('imagewebp') && (imagetypes() & IMG_WEBP);
        Log::info('[Attachment] processImage: WebP support = ' . ($supportsWebp ? 'yes' : 'no'));

        if ($supportsWebp) {
            ob_start();
            $ok = @imagewebp($source, null, self::WEBP_QUALITY);
            $encoded = ob_get_clean();

            if ($ok && strlen($encoded) > 0) {
                imagedestroy($source);
                Log::info('[Attachment] processImage: encoded as WebP (' . strlen($encoded) . ' bytes)');
                return [$encoded, 'webp', 'image/webp'];
            }

            Log::warning('[Attachment] processImage: WebP encoding failed, falling back to JPEG');
        }

        // Fallback: encode as JPEG
        ob_start();
        imagejpeg($source, null, self::WEBP_QUALITY);
        $encoded = ob_get_clean();
        imagedestroy($source);

        Log::info('[Attachment] processImage: encoded as JPEG (' . strlen($encoded) . ' bytes)');
        return [$encoded, 'jpg', 'image/jpeg'];
    }

    private function fixExifOrientation(\GdImage $image, string $fileContents): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $fileContents);
            rewind($stream);
            $exif = @exif_read_data($stream);
            fclose($stream);
        } catch (\Throwable) {
            return $image;
        }

        if (empty($exif['Orientation'])) {
            return $image;
        }

        return match ((int) $exif['Orientation']) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    private function buildStoragePath(Attachment $attachment, string $storedName): string
    {
        if ($attachment->task_id && $attachment->area_id) {
            return "areas/{$attachment->area_id}/tasks/{$attachment->task_id}/{$storedName}";
        }

        if ($attachment->task_id) {
            $areaId = $attachment->task?->area_id;
            if ($areaId) {
                return "areas/{$areaId}/tasks/{$attachment->task_id}/{$storedName}";
            }
            return "tasks/{$attachment->task_id}/{$storedName}";
        }

        if ($attachment->area_id) {
            return "areas/{$attachment->area_id}/documents/{$storedName}";
        }

        return "users/{$attachment->owner_user_id}/private/{$storedName}";
    }
}
