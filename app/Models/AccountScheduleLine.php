<?php

namespace App\Models;

use App\Enums\AccountScheduleAmountType;
use App\Enums\AccountScheduleRowType;
use App\Enums\AccountScheduleTotalingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountScheduleLine extends Model
{
    protected $fillable = [
        'schedule_id',
        'line_no',
        'row_no',
        'description',
        'totaling_type',
        'totaling',
        'row_type',
        'amount_type',
        'show_opposite_sign',
        'bold',
        'italic',
        'underline',
        'indentation',
        'new_page',
    ];

    protected $casts = [
        'totaling_type' => AccountScheduleTotalingType::class,
        'row_type' => AccountScheduleRowType::class,
        'amount_type' => AccountScheduleAmountType::class,
        'show_opposite_sign' => 'boolean',
        'bold' => 'boolean',
        'italic' => 'boolean',
        'underline' => 'boolean',
        'indentation' => 'integer',
        'new_page' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AccountSchedule::class, 'schedule_id');
    }
}
