<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

class NumberSeriesException extends BusinessException
{
    /**
     * @param  array<int, string>  $seriesCodes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        string $message,
        public readonly array $seriesCodes = [],
        string $title = 'Number series setup is incomplete',
        string $field = 'number_series',
        int $httpStatus = 422,
        string $severity = 'warning',
        ?string $codeIdentifier = null,
        array $metadata = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            title: $title,
            field: $field,
            httpStatus: $httpStatus,
            severity: $severity,
            codeIdentifier: $codeIdentifier,
            metadata: ['series_codes' => $seriesCodes] + $metadata,
            previous: $previous,
        );
    }
}
