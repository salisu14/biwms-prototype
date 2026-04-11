<?php

namespace App\Services;

use App\Models\VatBusinessPostingGroup;
use App\Models\VatPostingSetup;
use App\Models\VatProductPostingGroup;

class VatService
{
    /**
     * Resolve VAT Posting Setup from business and product groups
     */
    public function resolveSetup(int|VatBusinessPostingGroup|null $bus, int|VatProductPostingGroup|null $prod): ?VatPostingSetup
    {
        $busId = $bus instanceof VatBusinessPostingGroup ? $bus->id : $bus;
        $prodId = $prod instanceof VatProductPostingGroup ? $prod->id : $prod;

        if (! $busId || ! $prodId) {
            return null;
        }

        return VatPostingSetup::where([
            'vat_business_posting_group_id' => $busId,
            'vat_product_posting_group_id' => $prodId,
        ])->first();
    }

    /**
     * Calculate VAT amounts
     *
     * @param  float  $amount  The base amount (either net or gross depending on $isInclusive)
     * @param  VatPostingSetup|float|null  $setupOrPercentage  Either the setup model or a direct percentage
     * @param  bool  $isInclusive  Whether the provided $amount is VAT-inclusive
     * @return array{net_amount: float, vat_amount: float, total_amount: float, percentage: float}
     */
    public function calculate(float $amount, mixed $setupOrPercentage = null, bool $isInclusive = false): array
    {
        $percentage = 0;
        if ($setupOrPercentage instanceof VatPostingSetup) {
            $percentage = (float) $setupOrPercentage->vat_percentage;
        } elseif (is_numeric($setupOrPercentage)) {
            $percentage = (float) $setupOrPercentage;
        }

        if ($isInclusive) {
            // Amount = Net + (Net * P/100) = Net * (1 + P/100)
            // Net = Amount / (1 + P/100)
            $totalAmount = $amount;
            $netAmount = $percentage > 0 ? $amount / (1 + ($percentage / 100)) : $amount;
            $vatAmount = $totalAmount - $netAmount;
        } else {
            $netAmount = $amount;
            $vatAmount = $amount * ($percentage / 100);
            $totalAmount = $netAmount + $vatAmount;
        }

        return [
            'net_amount' => round($netAmount, 4),
            'vat_amount' => round($vatAmount, 4),
            'total_amount' => round($totalAmount, 4),
            'percentage' => $percentage,
        ];
    }

    /**
     * Get VAT percentage for a combination
     */
    public function getVatPercentage(int|VatBusinessPostingGroup|null $bus, int|VatProductPostingGroup|null $prod): float
    {
        $setup = $this->resolveSetup($bus, $prod);

        return $setup ? (float) $setup->vat_percentage : 0;
    }
}
