<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'customer_id',
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
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function refreshTotal(): void
    {
        $this->update([
            'total_amount' => $this->lines()->sum('line_total'),
        ]);
    }
}
