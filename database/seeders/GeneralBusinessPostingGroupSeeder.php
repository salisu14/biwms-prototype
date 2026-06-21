<?php

namespace Database\Seeders;

use App\Models\GeneralBusinessPostingGroup;
use App\Models\VatBusinessPostingGroup;
use Illuminate\Database\Seeder;

class GeneralBusinessPostingGroupSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve VAT BUSINESS groups (NOT product groups)
        $vat = fn(string $code) => VatBusinessPostingGroup::where('code', $code)->first();

        $domestic = $vat('DOMESTIC');
        $export   = $vat('EXPORT');

        if (! $domestic || ! $export) {
            throw new \RuntimeException('Run VatPostingSeeder first.');
        }

        $groups = [
            ['code' => 'DOMESTIC', 'description' => 'Domestic Customers/Vendors', 'vat' => $domestic],
            ['code' => 'LOCAL', 'description' => 'Local Market', 'vat' => $domestic],

            ['code' => 'EXPORT', 'description' => 'Export Sales/Purchases', 'vat' => $export],
            ['code' => 'FOREIGN', 'description' => 'Foreign Customers/Vendors', 'vat' => $export],
        ];

        foreach ($groups as $group) {
            GeneralBusinessPostingGroup::updateOrCreate(
                ['code' => $group['code']],
                [
                    'description' => $group['description'],
                    'default_vat_business_posting_group_id' => $group['vat']->id,
                    'auto_create_vat_bus_posting_group' => false,
                    'blocked' => false,
                ]
            );
        }
    }
}
