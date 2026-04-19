<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalStatus;
use App\Traits\Approvable as ApprovableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCreditMemo extends Model implements Approvable
{
    use ApprovableTrait;

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

        // Approval
        'rejection_reason',
        'approver_id',
        'approved_at',

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
        return $this->status === ApprovalStatus::POSTED;
    }

    public function refreshTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('total'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Approvable Interface
    |--------------------------------------------------------------------------
    */

    public function getApprovalAmount(): float
    {
        return (float) ($this->total_amount ?? 0);
    }

    public function getApprovalDocumentType(): string
    {
        return 'Sales Order'; // Credit memos fall under the same approval template
    }

    public function getApprovalRequestorId(): int
    {
        return (int) ($this->posted_by ?? auth()->id());
    }

    public function getApprovalPostingGroupId(): ?int
    {
        return null;
    }

    public function markAsReleased(): void
    {
        $this->update([
            'status' => ApprovalStatus::APPROVED,
            'approved_at' => now(),
            'approver_id' => auth()->id(),
        ]);
    }
}

