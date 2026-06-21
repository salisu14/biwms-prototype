<?php

namespace Database\Seeders;

use App\Models\VendorPostingGroup;
use Illuminate\Database\Seeder;

class VendorPostingGroupSeeder extends Seeder
{
    public function run(): void
    {
        $postingGroups = [
            [
                'code' => 'DOMESTIC',
                'description' => 'Domestic Vendors',
                'payables_account' => '21100', // Trade Payables - Domestic
                'service_charge_acc' => '60900',
                'payment_disc_debit_acc' => '50900',
                'payment_disc_credit_acc' => '40900',
                'invoice_rounding_account' => '60950',
                'debit_curr_appl_acc' => '21100',
                'credit_curr_appl_acc' => '21100',
                'debit_appl_acc' => '21100',
                'credit_appl_acc' => '21100',
                'prepayment_account' => '21300',
            ],
            [
                'code' => 'FOREIGN',
                'description' => 'Foreign Vendors',
                'payables_account' => '21200', // Trade Payables - Foreign
                'service_charge_acc' => '60900',
                'payment_disc_debit_acc' => '50900',
                'payment_disc_credit_acc' => '40900',
                'invoice_rounding_account' => '60950',
                'debit_curr_appl_acc' => '21200',
                'credit_curr_appl_acc' => '21200',
                'debit_appl_acc' => '21200',
                'credit_appl_acc' => '21200',
                'prepayment_account' => '21400',
            ],
            [
                'code' => 'INTERCOMPANY',
                'description' => 'Intercompany Vendors',
                'payables_account' => '21500', // Intercompany Payables
                'service_charge_acc' => '60900',
                'payment_disc_debit_acc' => '50900',
                'payment_disc_credit_acc' => '40900',
                'invoice_rounding_account' => '60950',
                'debit_curr_appl_acc' => '21500',
                'credit_curr_appl_acc' => '21500',
                'debit_appl_acc' => '21500',
                'credit_appl_acc' => '21500',
                'prepayment_account' => '21600',
            ],
            [
                'code' => 'EMPLOYEE',
                'description' => 'Employee Vendors',
                'payables_account' => '21800', // Employee Payables
                'service_charge_acc' => '60900',
                'payment_disc_debit_acc' => '50900',
                'payment_disc_credit_acc' => '40900',
                'invoice_rounding_account' => '60950',
                'debit_curr_appl_acc' => '21800',
                'credit_curr_appl_acc' => '21800',
                'debit_appl_acc' => '21800',
                'credit_appl_acc' => '21800',
                'prepayment_account' => '21900',
            ],
        ];

        foreach ($postingGroups as $group) {
            VendorPostingGroup::updateOrCreate(
                ['code' => $group['code']],
                $group
            );
        }
    }
}
