<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'salesperson_code', 'employee_id', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_required', 'two_factor_enabled_by', 'two_factor_disabled_at', 'two_factor_disabled_by', 'two_factor_last_challenged_at', 'two_factor_reset_at', 'two_factor_reset_by'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_required' => 'boolean',
            'two_factor_disabled_at' => 'datetime',
            'two_factor_last_challenged_at' => 'datetime',
            'two_factor_reset_at' => 'datetime',
        ];
    }

    public function isEmployeeActive(): bool
    {
        return $this->employee?->is_active === true;
    }

    public function hasConfirmedTwoFactorAuthentication(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function requiresTwoFactor(): bool
    {
        if ((bool) $this->two_factor_required) {
            return true;
        }

        $requiredRoles = config('security.two_factor_required_roles', []);

        return $this->hasAnyRole($requiredRoles);
    }

    public function twoFactorRecoveryCodesRemaining(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (app()->environment(['testing'])) {
            return true;
        }

        if (! $this->roles()->exists()) {
            return false;
        }

        if ($this->employee && ! $this->isEmployeeActive()) {
            return false;
        }

        $panelRoles = [
            'admin' => ['super_admin', 'admin', 'business-manager'],
            'sales' => ['super_admin', 'admin', 'sales-representative', 'sales-manager'],
            'finance' => ['super_admin', 'admin', 'finance-accountant', 'finance-manager'],
            'procurement' => ['super_admin', 'admin', 'purchasing-agent', 'purchasing-manager'],
            'project' => ['super_admin', 'admin', 'project-manager'],
            'warehouse' => ['super_admin', 'admin', 'warehouse-worker', 'warehouse-manager'],
            'factory' => ['super_admin', 'admin', 'factory-operator', 'factory-manager'],
            'hr' => ['super_admin', 'admin', 'hr-officer', 'hr-manager'],
            'service' => ['super_admin', 'admin', 'service-manager'],
        ];

        $allowedRoles = $panelRoles[$panel->getId()] ?? ['super_admin', 'admin'];

        return $this->hasAnyRole($allowedRoles);
    }

    /**
     * Documents created by this user
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'created_by', 'id');
    }

    /**
     * Ledger entries performed by this user
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedger::class, 'created_by', 'id');
    }

    /**
     * The Employee record linked to this system user.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The default Salesperson/Purchaser code assigned to this user (BC User Setup).
     */
    public function defaultSalesperson(): BelongsTo
    {
        return $this->belongsTo(SalespersonPurchaser::class, 'salesperson_code', 'code');
    }
}
