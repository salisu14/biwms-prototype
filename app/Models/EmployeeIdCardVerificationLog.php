<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeIdCardVerificationLog extends Model
{
    protected $fillable = [
        'card_id',
        'verified_at',
        'result',
        'ip_address',
        'user_agent',
        'device',
        'location_code',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(EmployeeIdCard::class, 'card_id');
    }
}
