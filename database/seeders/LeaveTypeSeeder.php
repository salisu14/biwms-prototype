<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'ANNUAL', 'name' => 'Annual Leave', 'paid' => true, 'color' => '#2563eb'],
            ['code' => 'SICK', 'name' => 'Sick Leave', 'paid' => true, 'requires_attachment' => true, 'attachment_required_after_days' => 2, 'color' => '#dc2626'],
            ['code' => 'MATERNITY', 'name' => 'Maternity Leave', 'paid' => true, 'color' => '#db2777'],
            ['code' => 'PATERNITY', 'name' => 'Paternity Leave', 'paid' => true, 'color' => '#7c3aed'],
            ['code' => 'COMPASSIONATE', 'name' => 'Compassionate Leave', 'paid' => true, 'color' => '#475569'],
            ['code' => 'STUDY', 'name' => 'Study Leave', 'paid' => true, 'color' => '#059669'],
            ['code' => 'UNPAID', 'name' => 'Unpaid Leave', 'paid' => false, 'requires_hr_approval' => true, 'color' => '#92400e'],
        ];

        foreach ($types as $type) {
            LeaveType::query()->updateOrCreate(
                ['business_id' => null, 'code' => $type['code']],
                [
                    'name' => $type['name'],
                    'unit' => 'days',
                    'paid' => $type['paid'],
                    'requires_attachment' => $type['requires_attachment'] ?? false,
                    'attachment_required_after_days' => $type['attachment_required_after_days'] ?? null,
                    'allow_half_day' => true,
                    'allow_negative_balance' => false,
                    'requires_manager_approval' => true,
                    'requires_hr_approval' => $type['requires_hr_approval'] ?? true,
                    'color' => $type['color'],
                    'is_active' => true,
                ]
            );
        }
    }
}
