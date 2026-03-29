<?php
// app/Models/WarehouseReceipt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseReceipt extends Model
{
    use HasFactory;

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
        if (!$this->canPost()) {
            return false;
        }

        // Implementation would create item ledger entries
        // and potentially G/L entries if using expected costing

        $this->update([
            'status' => 'RECEIVED',
            'posted_date' => now(),
        ]);

        return true;
    }

    // Scope
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['OPEN', 'RELEASED', 'PARTIALLY_RECEIVED']);
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}
