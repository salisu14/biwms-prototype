<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PayrollStatutorySetup;
use App\Models\PayCode;
use App\Enums\PayCodeType;
use App\Enums\CalculationMethod;
use App\Models\ChartOfAccount;
use App\Enums\AccountCategory;

class PayrollSetupSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Statutory Setup (Kenyan 2024 Rates)
        PayrollStatutorySetup::updateOrCreate(
            ['code' => 'KRA2024'],
            [
                'personal_relief' => 2400.00,
                'insurance_relief_percentage' => 15.00,
                'income_tax_bands' => [
                    ['limit' => 24000, 'rate' => 10],   // 10% on first 24,000
                    ['limit' => 8333, 'rate' => 25],    // 25% on next 8,333
                    ['limit' => 467667, 'rate' => 30],  // 30% on next 467,667
                    ['limit' => 300000, 'rate' => 32.5], // 32.5% on next 300,000
                    ['limit' => 99999999, 'rate' => 35], // 35% on everything above
                ],
                'nssf_tier1_limit' => 7000,
                'nssf_tier1_rate' => 6,
                'nssf_tier2_limit' => 36000,
                'nssf_tier2_rate' => 6,
                'nhif_rate' => 2.75, // SHIF rate
                'is_active' => true,
            ]
        );

        // 2. Ensure basic Accounts exist (or use existing ones for proto)
        $expenseAccount = ChartOfAccount::where('account_number', '60100')->first(); // Warehouse Labor
        $liabilityAccount = ChartOfAccount::where('name', 'Tax Payable Account')->first() 
            ?? ChartOfAccount::where('account_category', AccountCategory::LIABILITY)->first();

        // 3. Create Standard PayCodes
        PayCode::updateOrCreate(['code' => 'BASE'], [
            'name' => 'Basic Salary',
            'type' => PayCodeType::EARNING,
            'calculation_method' => CalculationMethod::FIXED_AMOUNT,
            'taxable' => true,
            'gl_account_id' => $expenseAccount?->id,
        ]);

        PayCode::updateOrCreate(['code' => 'PAYE'], [
            'name' => 'Income Tax (PAYE)',
            'type' => PayCodeType::DEDUCTION,
            'calculation_method' => CalculationMethod::FORMULA,
            'is_statutory' => true,
            'gl_account_id' => $liabilityAccount?->id,
        ]);

        PayCode::updateOrCreate(['code' => 'NSSF'], [
            'name' => 'NSSF Deduction',
            'type' => PayCodeType::DEDUCTION,
            'calculation_method' => CalculationMethod::FORMULA,
            'is_statutory' => true,
            'gl_account_id' => $liabilityAccount?->id,
        ]);

        PayCode::updateOrCreate(['code' => 'NHIF'], [
            'name' => 'NHIF/SHIF Deduction',
            'type' => PayCodeType::DEDUCTION,
            'calculation_method' => CalculationMethod::FORMULA,
            'is_statutory' => true,
            'gl_account_id' => $liabilityAccount?->id,
        ]);
    }
}
