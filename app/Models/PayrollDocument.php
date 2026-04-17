<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollDocument extends Model
{
    protected $fillable = [
        'document_number',
        'period_start',
        'period_end',
        'status',
        'remarks',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'status' => PayrollStatus::class,
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
