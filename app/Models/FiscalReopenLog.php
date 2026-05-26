<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalReopenLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'previous_allow_posting_from',
        'previous_allow_posting_to',
        'new_allow_posting_from',
        'new_allow_posting_to',
        'reason',
        'requested_by',
    ];

    protected $casts = [
        'previous_allow_posting_from' => 'date',
        'previous_allow_posting_to' => 'date',
        'new_allow_posting_from' => 'date',
        'new_allow_posting_to' => 'date',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
