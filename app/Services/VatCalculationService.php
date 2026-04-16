<?php

namespace App\Services;

use App\Models\VatPostingSetup;

class VatCalculationService
{
    /**
     * Calculate VAT based on Business Central logic
     * @throws \Exception
     */
    public function calculateVat($amount, $businessGroupCode, $productGroupCode, $isSale = true): array
    {
        $setup = VatPostingSetup::getSetup($businessGroupCode, $productGroupCode);

        if (!$setup) {
            throw new \Exception("VAT Posting Setup not found for {$businessGroupCode} + {$productGroupCode}");
        }

        $vatAmount = 0;

        switch ($setup->vat_calculation_type) {
            case 'normal':
                $vatAmount = $amount * ($setup->vat_percent / 100);
                break;
            case 'reverse_charge':
                // VAT is calculated but not charged (buyer accounts for VAT)
                $vatAmount = $amount * ($setup->vat_percent / 100);
                break;
            case 'full_vat':
                // Entire amount is VAT (import VAT scenario)
                $vatAmount = $amount;
                break;
            case 'sales_tax':
                // US-style sales tax handling
                $vatAmount = $amount * ($setup->vat_percent / 100);
                break;
        }

        return [
            'vat_amount' => round($vatAmount, 2),
            'vat_percent' => $setup->vat_percent,
            'calculation_type' => $setup->vat_calculation_type,
            'gl_account' => $isSale ? $setup->sales_vat_account_id : $setup->purchase_vat_account_id,
            'setup' => $setup
        ];
    }
}
