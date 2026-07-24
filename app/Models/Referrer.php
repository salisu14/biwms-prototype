<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferrerType;
use App\Exceptions\MissingNumberSeriesException;
use App\Services\AuditTrailService;
use App\Services\NumberSeriesService;
use Database\Factories\ReferrerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Referrer extends Model
{
    /** @use HasFactory<ReferrerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'type',
        'contact_id',
        'customer_id',
        'vendor_id',
        'employee_id',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'commission_eligible',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => ReferrerType::class,
        'commission_eligible' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Referrer $referrer): void {
            $referrer->normalizeLinkedRecords();
            $referrer->validateBusinessRules();

            if (blank($referrer->code)) {
                $referrer->code = static::generateCode();
            }

            $referrer->created_by ??= auth()->id();
            $referrer->updated_by ??= auth()->id();
        });

        static::updating(function (Referrer $referrer): void {
            $referrer->normalizeLinkedRecords();
            $referrer->validateBusinessRules();
            $referrer->updated_by = auth()->id();
        });

        static::created(fn (Referrer $referrer): mixed => $referrer->recordAudit('referrer_created', 'created'));
        static::updated(function (Referrer $referrer): void {
            if ($referrer->wasChanged('is_active')) {
                $referrer->recordAudit(
                    $referrer->is_active ? 'referrer_activated' : 'referrer_deactivated',
                    $referrer->is_active ? 'activated' : 'deactivated',
                );
            }

            if ($referrer->wasChanged('commission_eligible')) {
                $referrer->recordAudit(
                    $referrer->commission_eligible ? 'referrer_commission_enabled' : 'referrer_commission_disabled',
                    $referrer->commission_eligible ? 'commission_enabled' : 'commission_disabled',
                );
            }

            $referrer->recordAudit('referrer_updated', 'updated');
        });
        static::deleted(fn (Referrer $referrer): mixed => $referrer->recordAudit('referrer_deleted', 'deleted'));
        static::restored(fn (Referrer $referrer): mixed => $referrer->recordAudit('referrer_restored', 'restored'));
    }

    /**
     * @throws MissingNumberSeriesException
     */
    public static function generateCode(): string
    {
        return app(NumberSeriesService::class)->getNextNo('REFERRER');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function customerReferrals(): HasMany
    {
        return $this->hasMany(CustomerReferral::class);
    }

    public function commissionPlanAssignments(): HasMany
    {
        return $this->hasMany(ReferrerCommissionPlanAssignment::class);
    }

    public function activeCommissionPlanAssignment(): HasOne
    {
        return $this->hasOne(ReferrerCommissionPlanAssignment::class)
            ->where('status', ReferralCommissionAssignmentStatus::ACTIVE)
            ->whereNull('effective_to')
            ->latestOfMany();
    }

    public function referredCustomers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Customer::class,
            CustomerReferral::class,
            'referrer_id',
            'id',
            'id',
            'customer_id',
        );
    }

    public function linkedEntityLabel(): string
    {
        return match ($this->type) {
            ReferrerType::CONTACT => $this->contact?->name ?? 'Missing contact',
            ReferrerType::EXISTING_CUSTOMER => $this->customer?->name ?? 'Missing customer',
            ReferrerType::EMPLOYEE => $this->employee?->full_name ?? 'Missing employee',
            ReferrerType::VENDOR => $this->vendor?->vendor_name ?? 'Missing vendor',
            default => 'Independent',
        };
    }

    private function normalizeLinkedRecords(): void
    {
        $type = $this->type instanceof ReferrerType
            ? $this->type
            : ReferrerType::from((string) $this->type);

        if ($type !== ReferrerType::CONTACT) {
            $this->contact_id = null;
        }

        if ($type !== ReferrerType::EXISTING_CUSTOMER) {
            $this->customer_id = null;
        }

        if ($type !== ReferrerType::EMPLOYEE) {
            $this->employee_id = null;
        }

        if ($type !== ReferrerType::VENDOR) {
            $this->vendor_id = null;
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateBusinessRules(): void
    {
        $type = $this->type instanceof ReferrerType
            ? $this->type
            : ReferrerType::from((string) $this->type);

        $errors = [];

        if (blank($this->name)) {
            $errors['name'] = 'The referrer name is required.';
        }

        match ($type) {
            ReferrerType::CONTACT => blank($this->contact_id) ? $errors['contact_id'] = 'A contact is required for contact referrers.' : null,
            ReferrerType::EXISTING_CUSTOMER => blank($this->customer_id) ? $errors['customer_id'] = 'A customer is required for existing customer referrers.' : null,
            ReferrerType::EMPLOYEE => blank($this->employee_id) ? $errors['employee_id'] = 'An employee is required for employee referrers.' : null,
            ReferrerType::VENDOR => blank($this->vendor_id) ? $errors['vendor_id'] = 'A vendor is required for vendor referrers.' : null,
            default => null,
        };

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function recordAudit(string $eventType, string $action): mixed
    {
        return app(AuditTrailService::class)->recordGeneric(
            eventType: $eventType,
            action: $action,
            auditable: $this,
            documentType: 'REFERRER',
            documentNo: $this->code,
            description: "Referrer {$action}: {$this->code}",
            metadata: [
                'business_id' => $this->business_id,
                'type' => $this->type?->value,
            ],
        );
    }
}
