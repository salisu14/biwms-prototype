<?php

// app/Models/WarehouseReceipt.php

namespace App\Models;

use App\Enums\WarehouseReceiptStatus;
use App\Services\Inventory\StockMovementService;
use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseReceipt extends Model
{
    protected static function booted(): void
    {
        static::creating(function (WarehouseReceipt $receipt): void {
            if (empty($receipt->document_number)) {
                $receipt->document_number = self::generateNumber();
            }
        });
    }

    protected $fillable = [
        'document_number',
        'location_id',
        'source_document',
        'source_document_id',
        'source_document_number',
        'vendor_id',
        'status',
        'assigned_user_id',
        'receipt_date',
        'expected_receipt_date',
        'posted_date',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'expected_receipt_date' => 'date',
        'posted_date' => 'datetime',
        'status' => WarehouseReceiptStatus::class,
    ];

    // Relationships
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseReceiptLine::class);
    }

    // Status helpers
    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public function isReceived(): bool
    {
        return $this->status === 'RECEIVED';
    }

    public function canPost(): bool
    {
        return in_array($this->status, ['RELEASED', 'PARTIALLY_RECEIVED']);
    }

    // Post the receipt (creates item ledger entries)
    public function post(): bool
    {
        app(StockMovementService::class)->postWarehouseReceipt($this);

        return true;
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public static function generateNumber(): string
    {
        return app(NumberSeriesService::class)->getNextNoFromSeries(
            ['W-REC', 'WAREHOUSE_RECEIPT', 'WR'],
            null,
            'Warehouse Receipt'
        );
    }

    // Scope
    public function scopeOpen($query)
    {
        return $query->whereIn('status', WarehouseReceiptStatus::cases());
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}
