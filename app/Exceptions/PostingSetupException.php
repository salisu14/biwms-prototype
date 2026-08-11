<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

class PostingSetupException extends BusinessException
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(string $message, array $metadata = [], ?Throwable $previous = null)
    {
        parent::__construct(
            message: $message,
            title: 'Posting setup is incomplete',
            field: 'posting_setup',
            httpStatus: 422,
            codeIdentifier: 'posting_setup',
            metadata: $metadata,
            previous: $previous,
        );
    }
}
