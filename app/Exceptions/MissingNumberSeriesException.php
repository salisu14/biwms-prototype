<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class MissingNumberSeriesException extends RuntimeException
{
    /**
     * @param  array<int, string>  $seriesCodes
     */
    public function __construct(
        string $message,
        public readonly array $seriesCodes = [],
    ) {
        parent::__construct($message);
    }
}
