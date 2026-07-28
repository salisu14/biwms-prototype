<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemApplicationEntry extends Model
{
    protected $fillable = [
        'inbound_item_ledger_entry_id',
        'outbound_item_ledger_entry_id',
        'applied_quantity',
        'remaining_quantity_after_application',
        'application_date',
        'application_source',
        'costing_method',
        'unit_cost',
        'cost_amount',
        'is_reversed',
        'reversal_of_application_id',
        'idempotency_key',
        'audit_metadata',
    ];

    protected $casts = [
        'applied_quantity' => 'decimal:8',
        'remaining_quantity_after_application' => 'decimal:8',
        'application_date' => 'date',
        'unit_cost' => 'decimal:8',
        'cost_amount' => 'decimal:4',
        'is_reversed' => 'boolean',
        'audit_metadata' => 'array',
    ];

    public function inboundItemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class, 'inbound_item_ledger_entry_id');
    }

    public function outboundItemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class, 'outbound_item_ledger_entry_id');
    }

    public function reversalOfApplication(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_application_id');
    }
}
