<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeePostingGroup extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeePostingGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'payables_account_id',
        'blocked',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    /**
     * The GL account used for employee salaries and expenses.
     */
    public function payablesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payables_account_id');
    }

    /**
     * The employees assigned to this posting group.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
