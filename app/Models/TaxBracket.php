<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxBracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_table_id',
        'from_amount',
        'to_amount',
        'rate',
        'base_tax',
    ];

    protected $casts = [
        'from_amount' => 'decimal:2',
        'to_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'base_tax' => 'decimal:2',
    ];

    public function taxTable(): BelongsTo
    {
        return $this->belongsTo(TaxTable::class);
    }
}
