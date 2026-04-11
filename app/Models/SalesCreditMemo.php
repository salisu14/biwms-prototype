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

    public function submitForApproval(): void
    {
        if ($this->status !== ApprovalStatus::DRAFT) {
            throw new \Exception('Only draft credit memos can be submitted for approval.');
        }

        $this->update(['status' => ApprovalStatus::PENDING]);
    }

    public function approve(int $userId): void
    {
        if ($this->status !== ApprovalStatus::PENDING) {
            throw new \Exception('Only pending credit memos can be approved.');
        }

        $this->update([
            'status' => ApprovalStatus::APPROVED,
            'approver_id' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function reject(int $userId, string $reason): void
    {
        if ($this->status !== ApprovalStatus::PENDING) {
            throw new \Exception('Only pending credit memos can be rejected.');
        }

        $this->update([
            'status' => ApprovalStatus::REJECTED,
            'approver_id' => $userId,
            'rejection_reason' => $reason,
        ]);
    }

    public function refreshTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('total'),
        ]);
    }
}
