<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCreditMemo extends Model
{
    protected $fillable = [
        'customer_id',
        'memo_number',

        // Financials
        'total_amount',
        'currency_code',

        // Status lifecycle
        'status', // draft, pending, rejected, archived, approved, posted, cancelled

        // Posting
        'posted_at',
        'posted_by',

        // Business logic
        'reason',
        'effective_date',

        // BC-style linking
        'sales_invoice_id',

        // Dimensions (very important for ERP)
//        'dimension_1_id',
//        'dimension_2_id',
        'item_id',
        'quantity',
        'price',
        'total',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'effective_date' => 'date',
        'posted_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(SalesCreditMemoLine::class, 'sales_credit_memo_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function refreshTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('total'),
        ]);
    }
}
