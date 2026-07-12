<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentOffer extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $guarded = [];

    protected $casts = [
        'proposed_start_date' => 'date',
        'contract_end_date' => 'date',
        'base_salary' => 'decimal:4',
        'allowance_summary' => 'array',
        'benefit_summary' => 'array',
        'valid_until' => 'date',
        'approved_at' => 'datetime',
        'issued_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (RecruitmentOffer $offer): void {
            if ($offer->exists && $offer->getOriginal('status') === self::STATUS_ACCEPTED && $offer->isDirty()) {
                throw new \RuntimeException('Accepted offers are immutable.');
            }
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
