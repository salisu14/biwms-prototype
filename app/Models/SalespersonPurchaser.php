<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalespersonPurchaser extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'commission_pct',
        'phone_no',
        'email',
        'employee_id',
        'is_active',
    ];

    protected $casts = [
        'commission_pct' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship to the Employee record (Human Resources)
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Helper to get the linked User if it exists through the Employee
     */
    public function user()
    {
        return $this->employee?->user;
    }
}
