<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseQuoteArchive extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_quote_id',
        'version_no',
        'document_no',
        'document_type',
        'vendor_id',
        'contact_id',
        'buyer_id',
        'vendor_quote_no',
        'document_date',
        'posting_date',
        'order_date',
        'due_date',
        'status',
        'currency_code',
        'currency_factor',
        'payment_terms_code',
        'payment_method_code',
        'location_code',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimensions',
        'amount',
        'amount_including_vat',
        'vat_amount',
        'vendor_note',
        'internal_note',
        'archived_at',
        'archived_by',
        'archive_reason',
        'quote_data',
    ];

    protected $casts = [
        'document_date' => 'date',
        'posting_date' => 'date',
        'order_date' => 'date',
        'due_date' => 'date',
        'archived_at' => 'datetime',
        'amount' => 'decimal:4',
        'amount_including_vat' => 'decimal:4',
        'vat_amount' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'dimensions' => 'array',
        'quote_data' => 'array',
    ];

    public function purchaseQuote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class);
    }

    public function lineArchives(): HasMany
    {
        return $this->hasMany(PurchaseQuoteLineArchive::class)->orderBy('line_no');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function getVersionLabelAttribute(): string
    {
        return "Version {$this->version_no} - ".$this->archived_at->format('Y-m-d H:i');
    }
}
