<?php

// app/Models/ItemLot.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'item_id',
    'lot_number',
    'supplier_lot',
    'receipt_date',
    'expiry_date',
    'retest_date',
    'quantity_received',
    'quantity_remaining',
    'status', // QUARANTINE, APPROVED, REJECTED, EXPIRED
    'coa_reference',
])]
class ItemLot extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $table = 'item_lots';

    protected $casts = [
        'receipt_date' => 'date',
        'expiry_date' => 'date',
        'retest_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedger::class, 'lot_number', 'number');
    }
}
