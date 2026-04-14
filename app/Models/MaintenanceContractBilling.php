<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceContractBilling extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_contract_id',
        'billing_date',
        'amount',
        'status',
        'purchase_invoice_id',
        'actual_invoice_date',
    ];

    protected $casts = [
        'billing_date' => 'date',
        'amount' => 'decimal:4',
        'actual_invoice_date' => 'date',
    ];

    public function maintenanceContract(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContract::class, 'maintenance_contract_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function markAsInvoiced(int $purchaseInvoiceId, \DateTime $invoiceDate): void
    {
        $this->update([
            'status' => 'invoiced',
            'purchase_invoice_id' => $purchaseInvoiceId,
            'actual_invoice_date' => $invoiceDate,
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid']);
    }
}
