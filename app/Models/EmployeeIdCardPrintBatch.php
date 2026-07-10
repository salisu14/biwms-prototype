<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeIdCardPrintBatch extends Model
{
    protected $fillable = [
        'business_id',
        'template_id',
        'batch_number',
        'layout',
        'status',
        'created_by',
        'printed_by',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmployeeIdCardTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeeIdCardPrintBatchItem::class, 'batch_id');
    }
}
