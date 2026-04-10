<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\GlEntry;
use App\Models\PurchaseQuote;
use Illuminate\Support\Facades\DB;

class DepartmentBudgetService
{
    /**
     * Check if department has budget for purchase
     */
    public function hasBudget(Department $department, float $amount): bool
    {
        if ($department->annual_budget === null) {
            return true; // No budget limit
        }

        $projectedUtilization = $department->budget_utilized + $amount;
        return $projectedUtilization <= $department->annual_budget;
    }

    /**
     * Reserve budget for pending purchase quote
     */
    public function reserveBudget(Department $department, PurchaseQuote $quote): void
    {
        if ($department->annual_budget === null) {
            return;
        }

        // Create budget reservation entry
        DB::table('department_budget_reservations')->insert([
            'department_id' => $department->id,
            'purchase_quote_id' => $quote->id,
            'amount' => $quote->amount_including_vat,
            'reserved_at' => now(),
            'expires_at' => now()->addDays(30), // Auto-release after 30 days
        ]);

        // Update utilized budget
        $department->increment('budget_utilized', $quote->amount_including_vat);
    }

    /**
     * Release budget reservation (when quote cancelled/rejected)
     */
    public function releaseReservation(Department $department, PurchaseQuote $quote): void
    {
        $reservation = DB::table('department_budget_reservations')
            ->where('department_id', $department->id)
            ->where('purchase_quote_id', $quote->id)
            ->first();

        if ($reservation) {
            $department->decrement('budget_utilized', $reservation->amount);

            DB::table('department_budget_reservations')
                ->where('id', $reservation->id)
                ->delete();
        }
    }

    /**
     * Commit budget (when quote converted to order)
     */
    public function commitBudget(Department $department, PurchaseQuote $quote): void
    {
        // Remove reservation flag, becomes actual
        DB::table('department_budget_reservations')
            ->where('department_id', $department->id)
            ->where('purchase_quote_id', $quote->id)
            ->update([
                'committed' => true,
                'committed_at' => now(),
            ]);
    }

    /**
     * Recalculate from actual G/L postings
     */
    public function recalculateActuals(Department $department, ?int $fiscalYear = null): void
    {
        $fiscalYear = $fiscalYear ?? now()->year;

        $actual = GlEntry::where('shortcut_dimension_1_code', $department->global_dimension_1_code)
            ->whereYear('posting_date', $fiscalYear)
            ->whereHas('account', function ($q) {
                $q->whereIn('account_type', ['expense', 'cost_of_goods_sold']);
            })
            ->sum('amount');

        $department->update(['budget_utilized' => abs($actual)]);
    }
}
