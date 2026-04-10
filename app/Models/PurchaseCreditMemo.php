<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseCreditMemo extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'external_document_number',
        'vendor_id',
        'vendor_name',
        'corrects_invoice_id',
        'corrects_invoice_number',
        'subtotal',
        'tax_amount',
        'grand_total',
        'currency_code',
        'posting_date',
        'document_date',
        'location_id',
        'status',
        'rejection_reason',
        'approver_id',
        'approved_at',
        'created_by',
        'reason_code',
        'description',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'posting_date' => 'date',
        'document_date' => 'date',
        'approved_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'grand_total' => 'decimal:4',
    ];

    // Relationships
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseCreditMemoLine::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // Approval Logic
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

    public static function generateNumber(): string
    {
        $prefix = 'D-PCM'; // Draft Purchase Credit Memo
        $year = date('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseCreditMemo $memo) {
            if (empty($memo->document_number)) {
                $memo->document_number = self::generateNumber();
            }
            if (empty($memo->status)) {
                $memo->status = ApprovalStatus::DRAFT;
            }
            if (empty($memo->created_by) && auth()->check()) {
                $memo->created_by = auth()->id();
            }
        });
    }
}
