<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\EmployeeConfirmationDecision;
use Illuminate\Support\Facades\DB;

class EmployeeConfirmationDecisionService
{
    public function submit(EmployeeConfirmationDecision $decision, int $userId): EmployeeConfirmationDecision
    {
        if ($decision->status !== 'draft') {
            throw new \RuntimeException('Only draft confirmation decisions can be submitted.');
        }

        $decision->update([
            'status' => 'submitted',
            'submitted_by' => $userId,
        ]);

        return $decision->fresh();
    }

    public function approve(EmployeeConfirmationDecision $decision, int $userId): EmployeeConfirmationDecision
    {
        if (! in_array($decision->status, ['submitted', 'under_review'], true)) {
            throw new \RuntimeException('Only submitted confirmation decisions can be approved.');
        }

        $decision->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $decision->fresh();
    }

    public function implement(EmployeeConfirmationDecision $decision, int $userId): EmployeeConfirmationDecision
    {
        return DB::transaction(function () use ($decision, $userId): EmployeeConfirmationDecision {
            $decision = EmployeeConfirmationDecision::query()->with('employee')->lockForUpdate()->findOrFail($decision->id);

            if ($decision->status !== 'approved') {
                throw new \RuntimeException('Only approved confirmation decisions can be implemented.');
            }

            if ($decision->implemented_at !== null) {
                throw new \RuntimeException('This confirmation decision has already been implemented.');
            }

            if ($decision->decision_type === 'confirm') {
                $decision->employee->update(['is_active' => true]);
            }

            $decision->update([
                'status' => 'implemented',
                'implemented_by' => $userId,
                'implemented_at' => now(),
            ]);

            return $decision->fresh();
        });
    }
}
