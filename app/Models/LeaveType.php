<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'business_id',
        'code',
        'name',
        'description',
        'unit',
        'paid',
        'requires_attachment',
        'attachment_required_after_days',
        'allow_half_day',
        'allow_negative_balance',
        'requires_manager_approval',
        'requires_hr_approval',
        'color',
        'is_active',
    ];

    protected $casts = [
        'paid' => 'boolean',
        'requires_attachment' => 'boolean',
        'attachment_required_after_days' => 'decimal:2',
        'allow_half_day' => 'boolean',
        'allow_negative_balance' => 'boolean',
        'requires_manager_approval' => 'boolean',
        'requires_hr_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function policyRules(): HasMany
    {
        return $this->hasMany(LeavePolicyRule::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EmployeeLeaveLedgerEntry::class);
    }
}
