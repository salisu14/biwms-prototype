<?php

declare(strict_types=1);

namespace App\Services\Posting;

class PostingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $errors,
        public readonly array $postedEntries,
        public readonly ?string $documentNo = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function getPostedEntryIds(): array
    {
        return collect($this->postedEntries)->pluck('id')->toArray();
    }
}
