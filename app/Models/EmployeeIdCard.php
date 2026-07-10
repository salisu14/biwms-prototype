<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeIdCard extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LOST = 'lost';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_REPLACED = 'replaced';

    protected $fillable = [
        'employee_id',
        'business_id',
        'template_id',
        'card_number',
        'token',
        'status',
        'issue_date',
        'expiry_date',
        'issued_by',
        'issued_at',
        'printed_by',
        'printed_at',
        'print_count',
        'revoked_by',
        'revoked_at',
        'revocation_reason',
        'replaced_card_id',
        'last_verified_at',
        'id_card_status',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'issued_at' => 'datetime',
        'printed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'print_count' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmployeeIdCardTemplate::class, 'template_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function replacedCard(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_card_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeIdCardHistory::class, 'card_id');
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(EmployeeIdCardVerificationLog::class, 'card_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->expiry_date === null || $this->expiry_date->gte(today()));
    }

    public function getIdCardNumberAttribute(): ?string
    {
        return $this->card_number;
    }

    public function getIdCardTokenAttribute(): ?string
    {
        return $this->token;
    }

    public function getIdCardStatusAttribute(): ?string
    {
        return $this->status;
    }

    public function setIdCardStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = $value;
    }

    public function getIdCardIssueDateAttribute(): mixed
    {
        return $this->issue_date;
    }

    public function getIdCardExpiryDateAttribute(): mixed
    {
        return $this->expiry_date;
    }
}
