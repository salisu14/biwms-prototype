<?php

// app/Enums/UserRole.php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case MANAGER = 'MANAGER';
    case SUPERVISOR = 'SUPERVISOR';
    case OPERATOR = 'OPERATOR';
    case VIEWER = 'VIEWER';
    case AUDITOR = 'AUDITOR';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'System Administrator',
            self::MANAGER => 'Department Manager',
            self::SUPERVISOR => 'Team Supervisor',
            self::OPERATOR => 'Operations Operator',
            self::VIEWER => 'Read-Only Viewer',
            self::AUDITOR => 'Quality Auditor',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ADMIN => 'bg-red-100 text-red-800 border-red-300',
            self::MANAGER => 'bg-purple-100 text-purple-800 border-purple-300',
            self::SUPERVISOR => 'bg-blue-100 text-blue-800 border-blue-300',
            self::OPERATOR => 'bg-green-100 text-green-800 border-green-300',
            self::VIEWER => 'bg-gray-100 text-gray-800 border-gray-300',
            self::AUDITOR => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ADMIN => 'user-shield',
            self::MANAGER => 'user-tie',
            self::SUPERVISOR => 'user-cog',
            self::OPERATOR => 'user-hard-hat',
            self::VIEWER => 'eye',
            self::AUDITOR => 'search',
        };
    }

    /**
     * Level in hierarchy (for escalation)
     */
    public function level(): int
    {
        return match ($this) {
            self::VIEWER => 1,
            self::OPERATOR => 2,
            self::AUDITOR => 3,
            self::SUPERVISOR => 4,
            self::MANAGER => 5,
            self::ADMIN => 6,
        };
    }

    /**
     * Whether this role can approve deviations
     */
    public function canApproveDeviations(): bool
    {
        return $this->level() >= 4;
    }

    /**
     * Whether this role can modify posted transactions
     */
    public function canModifyPosted(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Whether this role requires dual authentication
     */
    public function requiresDualAuth(): bool
    {
        return in_array($this, [self::ADMIN, self::MANAGER]);
    }

    /**
     * Get approval limit (if applicable)
     */
    public function approvalLimit(): ?float
    {
        return match ($this) {
            self::OPERATOR => 0,
            self::SUPERVISOR => 1000.00,
            self::MANAGER => 10000.00,
            self::ADMIN => null, // Unlimited
            default => 0,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
                'level' => $case->level(),
                'can_approve' => $case->canApproveDeviations(),
            ])
            ->toArray();
    }
}
