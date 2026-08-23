<?php

declare(strict_types=1);

namespace App\Exceptions;

class InsufficientInventoryApplicationException extends BusinessException
{
    public static function forOutboundEntry(int|string $entryNumber): self
    {
        return new self(
            message: "Unable to apply outbound item ledger entry {$entryNumber}; insufficient open inbound quantity.",
            title: 'Insufficient inventory costing layers',
            field: 'inventory',
            severity: 'warning',
            codeIdentifier: 'insufficient_open_inbound_quantity',
            metadata: [
                'outbound_entry_number' => $entryNumber,
            ],
        );
    }
}
