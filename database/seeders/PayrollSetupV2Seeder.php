<?php

namespace Database\Seeders;

use App\Models\TaxTable;
use App\Models\TaxBracket;
use App\Models\SocialSecurityTier;
use Illuminate\Database\Seeder;

class PayrollSetupV2Seeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Tax Tables (Kenya 2024 bands - Header/Bracket structure)
        $taxTable = TaxTable::create([
            'name' => 'Kenya PAYE 2024',
            'jurisdiction' => 'Kenya',
            'country_code' => 'KE',
            'effective_date' => '2024-01-01',
        ]);

        $taxTable->brackets()->createMany([
            ['from_amount' => 0, 'to_amount' => 24000, 'rate' => 10, 'base_tax' => 0],
            ['from_amount' => 24000, 'to_amount' => 32333, 'rate' => 25, 'base_tax' => 2400],
            ['from_amount' => 32333, 'to_amount' => 500000, 'rate' => 30, 'base_tax' => 2400 + 2083.25],
            ['from_amount' => 500000, 'to_amount' => 800000, 'rate' => 32.5, 'base_tax' => 144783.35],
            ['from_amount' => 800000, 'to_amount' => null, 'rate' => 35, 'base_tax' => 242283.35],
        ]);

        // 2. Seed Social Security Tiers (NSSF Kenya 2024)
        // Using tiered structure: Tier I (7000) and Tier II (36000)
        SocialSecurityTier::create([
            'tier_code' => 'NSSF',
            'code' => 'TIER-1',
            'from_salary' => 0,
            'to_salary' => 7000,
            'employee_rate' => 6,
            'employer_rate' => 6,
            'max_base' => 7000,
            'employee_max_amount' => 420,
            'employer_max_amount' => 420,
        ]);

        SocialSecurityTier::create([
            'tier_code' => 'NSSF',
            'code' => 'TIER-2',
            'from_salary' => 7000,
            'to_salary' => 36000,
            'employee_rate' => 6,
            'employer_rate' => 6,
            'max_base' => 36000-7000,
            'employee_max_amount' => 1740,
            'employer_max_amount' => 1740,
        ]);

        // 3. Seed NHIF (SHIF 2.75%)
        SocialSecurityTier::create([
            'tier_code' => 'NHIF',
            'code' => 'SHIF-2024',
            'from_salary' => 0,
            'to_salary' => null,
            'employee_rate' => 2.75,
            'employer_rate' => 2.75,
            'max_base' => null,
        ]);
    }
}
