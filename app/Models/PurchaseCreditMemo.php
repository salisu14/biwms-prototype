<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalStatus;
use App\Services\NumberSeriesService;
use App\Traits\Approvable as ApprovableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class PurchaseCreditMemo extends Model implements Approvable
{
    use ApprovableTrait, HasFactory;

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

    public function correctedInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'corrects_invoice_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public static function generateNumber(): string
    {
        $postingDate = now();
        $service = app(NumberSeriesService::class);

        $seriesCandidates = ['P-CM', 'PURCHASE_CREDIT_MEMO', 'PCM'];

        foreach ($seriesCandidates as $seriesCode) {
            $number = $service->tryGetNextNo($seriesCode, $postingDate);
            if ($number) {
                return $number;
            }
        }

        $legacySeries = NumberSeries::query()
            ->whereIn('code', $seriesCandidates)
            ->where('is_active', true)
            ->orderByRaw(
                "CASE code
                    WHEN 'P-CM' THEN 1
                    WHEN 'PURCHASE_CREDIT_MEMO' THEN 2
                    WHEN 'PCM' THEN 3
                    ELSE 99
                END"
            )
            ->first();

        if ($legacySeries) {
            $legacyNo = $legacySeries->generateNumber();
            if (! empty($legacyNo)) {
                return $legacyNo;
            }
        }

        throw new RuntimeException(
            'No Purchase Credit Memo number series is configured. Please set up one of: P-CM, PURCHASE_CREDIT_MEMO, or PCM.'
        );
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

    /*
    |--------------------------------------------------------------------------
    | Approvable Interface
    |--------------------------------------------------------------------------
    */

    public function getApprovalAmount(): float
    {
        return (float) ($this->grand_total ?? 0);
    }

    public function getApprovalDocumentType(): string
    {
        return 'Purchase Credit Memo';
    }

    public function getApprovalRequestorId(): int
    {
        return (int) ($this->created_by ?? auth()->id());
    }

    public function getApprovalPostingGroupId(): ?int
    {
        return $this->vendor?->vendor_posting_group_id;
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
