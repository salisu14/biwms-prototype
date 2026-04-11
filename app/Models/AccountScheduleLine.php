<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'totaling_type' => \App\Enums\AccountScheduleTotalingType::class,
        'row_type' => \App\Enums\AccountScheduleRowType::class,
        'amount_type' => \App\Enums\AccountScheduleAmountType::class,
        'show_opposite_sign' => 'boolean',
        'bold' => 'boolean',
        'italic' => 'boolean',
        'underline' => 'boolean',
        'indentation' => 'integer',
        'new_page' => 'boolean',
    ];

    public function schedule(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AccountSchedule::class, 'schedule_id');
    }
}
