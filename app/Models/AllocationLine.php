<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationLine extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'allocation_lines';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'allocation_id',
        'target_account_id',
        'description',
        'percentage',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'percentage' => 'decimal:2',
    ];

    /**
     * The parent Allocation header defined in the Canvas.
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * The G/L Account assigned for this distribution line.
     */
    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'target_account_id');
    }
}
