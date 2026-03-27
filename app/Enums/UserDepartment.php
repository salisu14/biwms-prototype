<?php
// app/Enums/UserDepartment.php

namespace App\Enums;

enum UserDepartment: string
{
    case WAREHOUSE = 'WAREHOUSE';
    case PRODUCTION = 'PRODUCTION';
    case QA = 'QA';
    case QC = 'QC';
    case PLANNING = 'PLANNING';
    case PROCUREMENT = 'PROCUREMENT';
    case REGULATORY = 'REGULATORY';
    case IT = 'IT';
    case FINANCE = 'FINANCE';
    case MANAGEMENT = 'MANAGEMENT';

    public function label(): string
    {
        return match($this) {
            self::WAREHOUSE => 'Warehouse Operations',
            self::PRODUCTION => 'Manufacturing/Production',
            self::QA => 'Quality Assurance',
            self::QC => 'Quality Control (Lab)',
            self::PLANNING => 'Production Planning',
            self::PROCUREMENT => 'Procurement/Purchasing',
            self::REGULATORY => 'Regulatory Affairs',
            self::IT => 'Information Technology',
            self::FINANCE => 'Finance/Accounting',
            self::MANAGEMENT => 'Executive Management',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::WAREHOUSE => 'bg-orange-100 text-orange-800',
            self::PRODUCTION => 'bg-blue-100 text-blue-800',
            self::QA => 'bg-green-100 text-green-800',
            self::QC => 'bg-teal-100 text-teal-800',
            self::PLANNING => 'bg-purple-100 text-purple-800',
            self::PROCUREMENT => 'bg-pink-100 text-pink-800',
            self::REGULATORY => 'bg-indigo-100 text-indigo-800',
            self::IT => 'bg-gray-100 text-gray-800',
            self::FINANCE => 'bg-yellow-100 text-yellow-800',
            self::MANAGEMENT => 'bg-red-100 text-red-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::WAREHOUSE => 'warehouse',
            self::PRODUCTION => 'industry',
            self::QA => 'clipboard-check',
            self::QC => 'flask',
            self::PLANNING => 'calendar-alt',
            self::PROCUREMENT => 'shopping-cart',
            self::REGULATORY => 'balance-scale',
            self::IT => 'laptop-code',
            self::FINANCE => 'calculator',
            self::MANAGEMENT => 'user-tie',
        };
    }

    /**
     * Default system permissions for this department
     */
    public function defaultPermissions(): array
    {
        return match($this) {
            self::WAREHOUSE => ['inventory.view', 'inventory.move', 'picking.execute', 'receiving.execute'],
            self::PRODUCTION => ['production.view', 'production.execute', 'inventory.issue', 'inventory.receive'],
            self::QA => ['qa.release', 'qa.reject', 'deviation.manage', 'audit.view'],
            self::QC => ['testing.execute', 'coa.manage', 'results.enter'],
            self::PLANNING => ['planning.create', 'forecasts.view', 'capacity.manage'],
            self::PROCUREMENT => ['po.create', 'supplier.manage', 'contracts.view'],
            self::REGULATORY => ['submissions.manage', 'audits.manage', 'regulatory.view'],
            self::IT => ['system.admin', 'users.manage', 'integration.manage'],
            self::FINANCE => ['costs.view', 'posting.execute', 'reports.financial'],
            self::MANAGEMENT => ['all.view', 'reports.executive', 'approvals.all'],
        };
    }

    /**
     * Whether this department performs physical handling
     */
    public function isOperational(): bool
    {
        return in_array($this, [self::WAREHOUSE, self::PRODUCTION, self::QC]);
    }

    /**
     * Whether this department has GMP training requirements
     */
    public function requiresGmpTraining(): bool
    {
        return in_array($this, [self::WAREHOUSE, self::PRODUCTION, self::QA, self::QC]);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
                'operational' => $case->isOperational(),
            ])
            ->toArray();
    }
}
