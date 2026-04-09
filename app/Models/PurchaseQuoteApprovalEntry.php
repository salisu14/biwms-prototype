<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseQuoteApprovalEntry extends Model
{
    protected $fillable = [
        'purchase_quote_id',
        'sequence_no',
        'approver_id',
        'status', // created, approved, rejected, delegated
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'delegated_to',
        'delegated_at',
        'comment',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'delegated_at' => 'datetime',
    ];

    public function purchaseQuote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
