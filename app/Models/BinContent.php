<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinContent extends Model
{
    use HasFactory;

    protected $table = 'bin_contents';

    protected $fillable = [
        'bin_id',
        'item_id',
        'zone_id',
        'lot_no',
        'serial_no',
        'expiration_date',
        'quantity',
        'quantity_base',
        'unit_of_measure_code',
        'picked_quantity',
        'negative_adj_qty',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'picked_quantity' => 'decimal:4',
        'negative_adj_qty' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'expiration_date' => 'date',
    ];

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function availableQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->picked_quantity - (float) $this->negative_adj_qty);
    }

    public function isEmpty(): bool
    {
        return $this->availableQuantity() <= 0;
    }

    public function adjustQuantity(float $delta): void
    {
        $this->quantity += $delta;
        $this->save();
    }

    public function reserve(float $quantity): void
    {
        $this->picked_quantity += $quantity;
        $this->save();
    }

    public function releaseReservation(float $quantity): void
    {
        $this->picked_quantity = max(0, $this->picked_quantity - $quantity);
        $this->save();
    }
}
