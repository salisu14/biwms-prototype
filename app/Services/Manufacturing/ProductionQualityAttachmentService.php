<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionOperationExecutionStatus;
use App\Models\Manufacturing\ProductionQualityCheck;
use App\Models\Manufacturing\ProductionQualityCheckAttachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionQualityAttachmentService
{
    private const DISK = 'local';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'text/plain',
    ];

    public function store(ProductionQualityCheck $qualityCheck, UploadedFile $file, User $user): ProductionQualityCheckAttachment
    {
        $this->authorize($qualityCheck, $user);
        $this->assertQualityCheckCanReceiveAttachments($qualityCheck);
        $this->validateFile($file);

        $extension = strtolower((string) ($file->extension() ?: $file->guessExtension() ?: 'bin'));
        $storedFilename = Str::uuid()->toString().'.'.$extension;
        $path = "manufacturing/quality-checks/{$qualityCheck->id}/{$storedFilename}";

        $stored = Storage::disk(self::DISK)->putFileAs(
            "manufacturing/quality-checks/{$qualityCheck->id}",
            $file,
            $storedFilename
        );

        if ($stored !== $path) {
            throw new RuntimeException('Quality attachment could not be stored.');
        }

        return ProductionQualityCheckAttachment::query()->create([
            'production_quality_check_id' => $qualityCheck->id,
            'disk' => self::DISK,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);
    }

    public function download(ProductionQualityCheckAttachment $attachment, User $user): StreamedResponse
    {
        $attachment->loadMissing('qualityCheck.execution');
        $this->authorize($attachment->qualityCheck, $user);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            throw new RuntimeException('Quality attachment file is missing from private storage.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_filename,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(ProductionQualityCheck $qualityCheck, User $user): void
    {
        $qualityCheck->loadMissing('execution');

        if (! $user->can('placeQualityHold', $qualityCheck->execution)) {
            throw new AuthorizationException('User is not authorized to access production quality attachments.');
        }
    }

    private function assertQualityCheckCanReceiveAttachments(ProductionQualityCheck $qualityCheck): void
    {
        $qualityCheck->loadMissing('execution');

        if (in_array($qualityCheck->execution->status, [
            ProductionOperationExecutionStatus::Posted,
            ProductionOperationExecutionStatus::Cancelled,
            ProductionOperationExecutionStatus::Reversed,
        ], true)) {
            throw new RuntimeException('Finalized production quality checks cannot receive new attachments.');
        }
    }

    private function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Quality attachment upload is invalid.');
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            throw new RuntimeException('Quality attachment must be between 1 byte and 10 MB.');
        }

        $mimeType = (string) $file->getMimeType();
        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('Quality attachment file type is not allowed.');
        }
    }
}
