<?php

namespace Database\Seeders;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $start = Carbon::now()->startOfMonth();

        for ($i = 0; $i < 3; $i++) {
            $periodStart = $start->copy()->addMonths($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            PayrollPeriod::updateOrCreate(
                ['start_date' => $periodStart->toDateString(), 'end_date' => $periodEnd->toDateString()],
                [
                    'payment_date' => $periodEnd->copy()->addDays(3)->toDateString(),
                    'status' => PayrollPeriodStatus::OPEN,
                    'is_current' => $i === 0,
                ]
            );
        }
    }
}
