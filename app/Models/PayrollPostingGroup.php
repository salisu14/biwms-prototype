<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPostingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'salaries_account_id',
        'wages_account_id',
        'social_security_account_id',
        'tax_payable_account_id',
        'net_pay_account_id',
    ];

    public function salariesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'salaries_account_id');
    }

    public function wagesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'wages_account_id');
    }

    public function socialSecurityAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'social_security_account_id');
    }

    public function taxPayableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'tax_payable_account_id');
    }

    public function netPayAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'net_pay_account_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
