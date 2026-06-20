<?php

namespace App\Models;

use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PurchaseReceipt extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (PurchaseReceipt $receipt): void {
            if (empty($receipt->document_number)) {
                $receipt->document_number = self::generateDocumentNumber();
            }
        });
    }

    protected $fillable = [
        'document_number',
        'external_document_no',
        'vendor_id',
        'vendor_shipment_no',
        'vendor_invoice_no',
        'order_address_code',
        'posting_date',
        'document_date',
        'receiving_location_id',
        'buyer_id',
        'project_code',
        'department_code',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'purchase_order_id',
        'purchase_order_no',
        'status',
        'posted',
        'posted_at',
        'posted_by',
        'expected_receipt_date',
        'actual_receipt_date',
        'yours_reference',
        'our_reference',
        'transaction_specification',
        'transport_method',
        'entry_point',
        'area',
        'transaction_type',
        'language_code',
        'format_region',
        'buy_from_vendor_name',
        'buy_from_address',
        'buy_from_address_2',
        'buy_from_city',
        'buy_from_post_code',
        'buy_from_county',
        'buy_from_country_region_code',
        'buy_from_contact',
        'pay_to_vendor_no',
        'pay_to_name',
        'pay_to_address',
        'pay_to_address_2',
        'pay_to_city',
        'pay_to_post_code',
        'pay_to_county',
        'pay_to_country_region_code',
        'pay_to_contact',
        'ship_to_code',
        'ship_to_name',
        'ship_to_address',
        'ship_to_address_2',
        'ship_to_city',
        'ship_to_post_code',
        'ship_to_county',
        'ship_to_country_region_code',
        'ship_to_contact',
        'location_code',
        'shipment_method_code',
        'shipping_agent_code',
        'shipping_agent_service_code',
        'package_tracking_no',
        'currency_code',
        'exchange_rate',
        'prices_including_vat',
        'invoice_disc_code',
        'language_code',
        'comment',
        'requested_receipt_date',
        'promised_receipt_date',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'posted_at' => 'datetime',
        'expected_receipt_date' => 'date',
        'actual_receipt_date' => 'date',
        'requested_receipt_date' => 'date',
        'promised_receipt_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'prices_including_vat' => 'boolean',
        'posted' => 'boolean',
    ];

    // Relationships
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'receiving_location_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function vendorInvoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class, 'source_document_id')
            ->where('source_document_type', 'PURCHASE_RECEIPT');
    }

    // Scopes
    public function scopePosted($query)
    {
        return $query->where('posted', true);
    }

    public function scopeUnposted($query)
    {
        return $query->where('posted', false);
    }

    public function scopeByVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeByPurchaseOrder($query, int $purchaseOrderId)
    {
        return $query->where('purchase_order_id', $purchaseOrderId);
    }

    // Business Logic
    public function post(int $userId): void
    {
        if ($this->posted) {
            throw new \Exception('Purchase Receipt already posted');
        }

        DB::transaction(function () use ($userId) {
            // Update inventory
            // $this->updateInventory();

            // Update purchase order received quantities
            // $this->updatePurchaseOrder();

            $this->update([
                'posted' => true,
                'posted_at' => now(),
                'posted_by' => $userId,
                'status' => 'POSTED',
                'actual_receipt_date' => now(),
            ]);
        });
    }

    public static function generateDocumentNumber(): string
    {
        return app(NumberSeriesService::class)->getNextNoFromSeries(
            ['P-REC', 'PURCHASE_RECEIPT', 'PR'],
            null,
            'Purchase Receipt'
        );
    }

    public function isFullyInvoiced(): bool
    {
        return $this->lines->every(function ($line) {
            return $line->quantity_invoiced >= $line->quantity;
        });
    }

    public function getTotalQuantity(): float
    {
        return $this->lines->sum('quantity');
    }

    public function getTotalQuantityReceived(): float
    {
        return $this->lines->sum('quantity_received');
    }
}
