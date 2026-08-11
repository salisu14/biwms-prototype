<?php

declare(strict_types=1);

namespace App\Exceptions;

class DocumentStateException extends BusinessException
{
    public function __construct(string $message, string $field = 'status')
    {
        parent::__construct(
            message: $message,
            title: 'Document state prevents this action',
            field: $field,
            httpStatus: 409,
            codeIdentifier: 'document_state',
        );
    }
}
