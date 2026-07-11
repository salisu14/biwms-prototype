<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterPeriod;
use App\Models\WorkforceStaffingRequirement;

class WorkforceCoverageService
{
    /**
     * @return array{assigned: int, minimum_required: int, target_required: int, missing_critical_roles: array<int, string>, understaffed: bool, overstaffed: bool}
     */
    public function summaryForPeriod(WorkforceRosterPeriod $period): array
    {
        $assigned = WorkforceRosterAssignment::query()
            ->where('workforce_roster_period_id', $period->id)
            ->whereNotIn('status', [WorkforceRosterAssignment::STATUS_CANCELLED, WorkforceRosterAssignment::STATUS_REPLACED, WorkforceRosterAssignment::STATUS_DECLINED])
            ->distinct('employee_id')
            ->count('employee_id');

        $requirements = WorkforceStaffingRequirement::query()
            ->with('rosterRole')
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $period->date_to)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->date_from))
            ->get();

        $minimum = (int) $requirements->sum('minimum_required');
        $target = (int) $requirements->sum(fn (WorkforceStaffingRequirement $requirement): int => (int) ($requirement->target_required ?? $requirement->minimum_required));
        $missingCritical = $requirements
            ->filter(fn (WorkforceStaffingRequirement $requirement): bool => (bool) $requirement->rosterRole?->is_critical)
            ->filter(fn (WorkforceStaffingRequirement $requirement): bool => ! WorkforceRosterAssignment::query()
                ->where('workforce_roster_period_id', $period->id)
                ->where('roster_role_id', $requirement->roster_role_id)
                ->whereNotIn('status', [WorkforceRosterAssignment::STATUS_CANCELLED, WorkforceRosterAssignment::STATUS_REPLACED, WorkforceRosterAssignment::STATUS_DECLINED])
                ->exists())
            ->map(fn (WorkforceStaffingRequirement $requirement): string => $requirement->rosterRole?->code ?? 'unknown')
            ->values()
            ->all();

        return [
            'assigned' => $assigned,
            'minimum_required' => $minimum,
            'target_required' => $target,
            'missing_critical_roles' => $missingCritical,
            'understaffed' => $assigned < $minimum || $missingCritical !== [],
            'overstaffed' => $target > 0 && $assigned > $target,
        ];
    }
}
