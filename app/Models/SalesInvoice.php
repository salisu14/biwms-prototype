<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Services\Business\BusinessContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    protected $fillable = [
        'business_id',
        'invoice_number',
        'customer_id',
        'sales_order_id',
        'total_amount',
        'currency_code',
        'status',
        'posted_at',
        'posted_by',
        'invoice_date',
        'due_date',
        //        'dimension_1_id',
        //        'dimension_2_id',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'invoice_date' => 'date',
        'due_date' => 'date',
        'posted_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'business_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesInvoice $invoice): void {
            $invoice->business_id ??= $invoice->sales_order_id
                ? SalesOrder::query()->whereKey($invoice->sales_order_id)->value('business_id')
                : app(BusinessContextService::class)->resolveId();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function isPosted(): bool
    {
        return $this->status === ApprovalStatus::POSTED || $this->posted_at !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function refreshTotal(): void
    {
        $this->update([
            'total_amount' => $this->lines()->sum('line_total'),
        ]);
    }
}
