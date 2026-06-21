<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\PurchaseLineType;
use App\Enums\PurchaseQuoteStatus;
use App\Services\NumberSeriesService;
use App\Services\Purchase\PurchaseQuoteCalculationService;
use App\Traits\Approvable as ApprovableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class PurchaseQuote extends Model implements Approvable
{
    use ApprovableTrait, HasFactory, SoftDeletes;

    protected $fillable = [
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
        'requested_receipt_date',
        'promised_receipt_date',
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
        'released_at',
        'released_by',
        'quote_no',
        'is_price_inclusive',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'posting_date' => 'date',
            'order_date' => 'date',
            'due_date' => 'date',
            'requested_receipt_date' => 'date',
            'promised_receipt_date' => 'date',
            'released_at' => 'datetime',
            'amount' => 'decimal:4',
            'amount_including_vat' => 'decimal:4',
            'vat_amount' => 'decimal:4',
            'currency_factor' => 'decimal:6',
            'dimensions' => 'array',
            'status' => PurchaseQuoteStatus::class,
        ];
    }

    // Relationships
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

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseQuoteLine::class)->orderBy('line_no');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', PurchaseQuoteStatus::OPEN);
    }

    public function scopeReleased($query)
    {
        return $query->where('status', PurchaseQuoteStatus::RELEASED);
    }

    public function scopeConvertible($query)
    {
        return $query->where('status', PurchaseQuoteStatus::RELEASED);
    }

    /**
     * Add a new line to the purchase quote
     * BC Pattern: Lines numbered in 10000 increments for insertion flexibility
     */
    public function addLine(array $data): PurchaseQuoteLine
    {
        // Check if quote can be edited
        if (! $this->status->canEdit()) {
            throw new \InvalidArgumentException(
                "Cannot add lines to a {$this->status->label()} quote"
            );
        }

        if (! $this->status->canEdit()) {
            throw new \InvalidArgumentException("Cannot add lines to a {$this->status->label()} quote");
        }

        // Auto-calculate price if item provided and no cost specified
        if (! empty($data['type']) && $data['type'] === PurchaseLineType::ITEM
            && ! empty($data['item_id'])
            && empty($data['direct_unit_cost'])) {

            $priceService = $priceService ?? app(PurchaseQuoteCalculationService::class);
            $item = Item::find($data['item_id']);

            if ($item && $this->vendor) {
                $priceInfo = $priceService->getUnitCost(
                    $this->vendor,
                    $item,
                    $data['quantity'] ?? 1,
                    $data['unit_of_measure_code'] ?? null
                );

                $data['direct_unit_cost'] = $priceInfo['direct_unit_cost'];
                $data['line_discount_percent'] = $priceInfo['line_discount_percent']
                    ?? $data['line_discount_percent'] ?? 0;

                if ($priceInfo['vendor_item_no']) {
                    $data['vendor_item_no'] = $priceInfo['vendor_item_no'];
                }
            }
        }

        // Calculate next line number (BC standard: increments of 10000)
        $lastLineNo = $this->lines()->max('line_no') ?? 0;
        $lineNo = $lastLineNo + 10000;

        // Set defaults from quote header if not provided
        $data['line_no'] = $lineNo;
        $data['purchase_quote_id'] = $this->id;
        $data['outstanding_quantity'] = $data['quantity'] ?? 0;

        // Inherit dimensions from header if not specified
        if (empty($data['shortcut_dimension_1_code'])) {
            $data['shortcut_dimension_1_code'] = $this->shortcut_dimension_1_code;
        }
        if (empty($data['shortcut_dimension_2_code'])) {
            $data['shortcut_dimension_2_code'] = $this->shortcut_dimension_2_code;
        }

        // Inherit location from header if not specified
        if (empty($data['location_code'])) {
            $data['location_code'] = $this->location_code;
        }

        // Inherit dates from header if not specified
        if (empty($data['requested_receipt_date'])) {
            $data['requested_receipt_date'] = $this->requested_receipt_date;
        }
        if (empty($data['promised_receipt_date'])) {
            $data['promised_receipt_date'] = $this->promised_receipt_date;
        }

        // Create the line
        $line = $this->lines()->create($data);

        // Recalculate quote totals
        $this->calculateTotals();

        return $line->fresh();
    }

    /**
     * Insert a line between existing lines (BC pattern)
     */
    public function insertLine(int $afterLineNo, array $data): PurchaseQuoteLine
    {
        if (! $this->status->canEdit()) {
            throw new \InvalidArgumentException(
                "Cannot insert lines into a {$this->status->label()} quote"
            );
        }

        // Find the line number to insert after
        $prevLine = $this->lines()->where('line_no', $afterLineNo)->first();

        if (! $prevLine) {
            throw new \InvalidArgumentException("Line {$afterLineNo} not found");
        }

        // Find the next line to calculate the gap
        $nextLine = $this->lines()
            ->where('line_no', '>', $afterLineNo)
            ->orderBy('line_no')
            ->first();

        if ($nextLine) {
            $gap = $nextLine->line_no - $prevLine->line_no;

            // If gap is too small, renumber all lines
            if ($gap < 2) {
                $this->renumberLines();

                return $this->insertLine($afterLineNo, $data);
            }

            $lineNo = $prevLine->line_no + (int) ($gap / 2);
        } else {
            $lineNo = $prevLine->line_no + 10000;
        }

        $data['line_no'] = $lineNo;

        return $this->addLine($data);
    }

    /**
     * Renumber all lines to restore 10000 increments
     */
    public function renumberLines(): void
    {
        $lines = $this->lines()->orderBy('line_no')->get();
        $newLineNo = 10000;

        foreach ($lines as $line) {
            $line->update(['line_no' => $newLineNo]);
            $newLineNo += 10000;
        }
    }

    /**
     * Delete a line and recalculate totals
     */
    public function deleteLine(int $lineNo): bool
    {
        if (! $this->status->canEdit()) {
            throw new \InvalidArgumentException(
                "Cannot delete lines from a {$this->status->label()} quote"
            );
        }

        $line = $this->lines()->where('line_no', $lineNo)->first();

        if (! $line) {
            return false;
        }

        $deleted = $line->delete();

        if ($deleted) {
            $this->calculateTotals();
        }

        return $deleted;
    }

    /**
     * Update line and recalculate totals
     */
    public function updateLine(int $lineNo, array $data): PurchaseQuoteLine
    {
        if (! $this->status->canEdit()) {
            throw new \InvalidArgumentException(
                "Cannot update lines in a {$this->status->label()} quote"
            );
        }

        $line = $this->lines()->where('line_no', $lineNo)->firstOrFail();

        $line->update($data);
        $this->calculateTotals();

        return $line->fresh();
    }

    /**
     * Calculate and update quote totals from lines
     */
    /**
     * Calculate and update quote totals from lines
     */
    public function calculateTotals(): void
    {
        // We use reorder() to remove the default 'orderBy(line_no)'
        // defined in the relationship, which breaks Postgres aggregate queries.
        $totals = $this->lines()
            ->reorder()
            ->selectRaw('
            SUM(line_amount) as amount,
            SUM(vat_amount) as vat_amount,
            SUM(amount_including_vat) as amount_including_vat
        ')->first();

        $this->updateQuietly([
            'amount' => $totals->amount ?? 0,
            'vat_amount' => $totals->vat_amount ?? 0,
            'amount_including_vat' => $totals->amount_including_vat ?? 0,
        ]);
    }
    //    public function calculateTotals(): void
    //    {
    //        $totals = $this->lines()->selectRaw('
    //            SUM(line_amount) as amount,
    //            SUM(vat_amount) as vat_amount,
    //            SUM(amount_including_vat) as amount_including_vat
    //        ')->first();
    //
    //        $this->update([
    //            'amount' => $totals->amount ?? 0,
    //            'vat_amount' => $totals->vat_amount ?? 0,
    //            'amount_including_vat' => $totals->amount_including_vat ?? 0,
    //        ]);
    //    }

    /**
     * Check if quote can be converted to order
     */
    public function canConvertToOrder(): bool
    {
        return $this->status->canConvertToOrder() && $this->lines()->exists();
    }

    /**
     * Check if quote is released
     */
    public function isReleased(): bool
    {
        return $this->status === PurchaseQuoteStatus::RELEASED;
    }

    /**
     * Get next line number without creating a line (for UI prep)
     */
    public function getNextLineNo(): int
    {
        $lastLineNo = $this->lines()->max('line_no') ?? 0;

        return $lastLineNo + 10000;
    }

    // Approvable Interface Implementation

    public function getApprovalAmount(): float
    {
        return (float) $this->amount_including_vat;
    }

    public function getApprovalDocumentType(): string
    {
        return 'Purchase Quote';
    }

    public function getApprovalLocationCode(): ?string
    {
        return $this->location_code;
    }

    public function getApprovalDimensions(): array
    {
        return $this->dimensions ?? [];
    }

    public function getApprovalRequestorId(): int
    {
        return (int) ($this->buyer_id ?? Auth::id());
    }

    public function getApprovalPostingGroupId(): ?int
    {
        return $this->vendor?->vendor_posting_group_id;
    }

    public function markAsReleased(): void
    {
        $this->update([
            'status' => PurchaseQuoteStatus::RELEASED,
            'released_at' => now(),
            'released_by' => auth()->id(),
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseQuote $quote): void {
            if (empty($quote->document_no)) {
                $quote->document_no = self::generateDocumentNumber();
            }
        });

        static::saved(function (PurchaseQuote $quote): void {
            $quote->calculateTotals();
        });
    }

    public static function generateDocumentNumber(): string
    {
        return app(NumberSeriesService::class)->getNextNoFromSeries(
            ['P-QUOTE', 'PURCHASE_QUOTE', 'PQ'],
            null,
            'Purchase Quote'
        );
    }
}
