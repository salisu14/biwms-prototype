<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashVoucherLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'petty_cash_voucher_id',
        'line_number',
        'expense_account_id',
        'description',
        'amount',
        'dimension_department_id',
        'dimension_project_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'line_number' => 'integer',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(PettyCashVoucher::class, 'petty_cash_voucher_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_department_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_project_id');
    }

    // Inside PettyCashVoucherLine.php
    protected static function booted(): void
    {
        static::creating(function (PettyCashVoucherLine $line) {
            if (empty($line->line_number)) {
                $maxLine = static::where('petty_cash_voucher_id', $line->petty_cash_voucher_id)
                    ->max('line_number');

                $line->line_number = $maxLine ? $maxLine + 10000 : 10000;
            }
        });
    }
}
