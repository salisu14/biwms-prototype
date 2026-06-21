<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyAdjustmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyAdjustmentLedger extends Model
{
    use HasFactory;

    protected $table = 'currency_adjustment_ledger';

    protected $fillable = [
        'currency_id',
        'adjustment_account_id',
        'document_type',
        'document_no',
        'posting_date',
        'adjustment_type',
        'original_amount',
        'adjusted_amount',
        'adjustment_amount',
        'original_exch_rate',
        'new_exch_rate',
        'vendor_ledger_entry_id',
        'customer_ledger_entry_id',
        'bank_account_ledger_entry_id',
        'gl_entry_id',
        'created_by',
        'description',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'adjustment_type' => CurrencyAdjustmentType::class,
        'original_amount' => 'decimal:4',
        'adjusted_amount' => 'decimal:4',
        'adjustment_amount' => 'decimal:4',
        'original_exch_rate' => 'decimal:6',
        'new_exch_rate' => 'decimal:6',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function adjustmentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'adjustment_account_id');
    }

    public function vendorLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(VendorLedgerEntry::class);
    }

    public function customerLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerLedgerEntry::class);
    }

    public function bankAccountLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(BankAccountLedgerEntry::class);
    }

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isGain(): bool
    {
        return $this->adjustment_type->isGain();
    }
}
