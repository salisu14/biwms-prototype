<?php

declare(strict_types=1);

namespace App\Services\Posting;

use Illuminate\Support\Collection;

interface PostingRoutineInterface
{
    /**
     * Validate all lines before posting
     */
    public function validate(object $batch): array;

    /**
     * Execute posting
     */
    public function post(object $batch): PostingResult;

    /**
     * Reverse posted entries
     */
    public function reverse(object $batch, string $reason): void;

    /**
     * Preview entries without posting
     */
    public function preview(object $batch): Collection;
}
