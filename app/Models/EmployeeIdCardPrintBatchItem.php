<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeIdCardPrintBatchItem extends Model
{
    protected $fillable = [
        'batch_id',
        'card_id',
        'employee_id',
        'status',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(EmployeeIdCardPrintBatch::class, 'batch_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(EmployeeIdCard::class, 'card_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
