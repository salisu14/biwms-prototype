<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'status' => \App\Enums\PayrollStatus::class,
    ];

    public function lines()
    {
        return $this->hasMany(PayrollLine::class);
    }
}
