<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusinessException extends Exception
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        string $message,
        private readonly string $title = 'Action cannot be completed',
        private readonly string $field = 'business',
        private readonly int $httpStatus = 422,
        private readonly string $severity = 'warning',
        private readonly ?string $codeIdentifier = null,
        private readonly array $metadata = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function field(): string
    {
        return $this->field;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function codeIdentifier(): ?string
    {
        return $this->codeIdentifier;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function report(): bool
    {
        Log::log($this->severity === 'info' ? 'info' : 'warning', $this->getMessage(), [
            'exception' => static::class,
            'title' => $this->title,
            'code' => $this->codeIdentifier,
            'metadata' => $this->safeMetadata(),
        ]);

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeMetadata(): array
    {
        return collect($this->metadata)
            ->reject(fn (mixed $value, string|int $key): bool => str($key)->lower()->contains([
                'password',
                'token',
                'secret',
                'recovery',
                'session',
            ]))
            ->all();
    }
}
