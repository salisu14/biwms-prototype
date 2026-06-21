<?php

declare(strict_types=1);

namespace App\Enums;

enum FAPostingType: string
{
    case ACQUISITION = 'acquisition';           // Initial purchase/capitalization
    case DEPRECIATION = 'depreciation';         // Periodic depreciation expense
    case APPRECIATION = 'appreciation';        // Value increase (revaluation)
    case WRITE_DOWN = 'write_down';            // Impairment/Value decrease
    case DISPOSAL = 'disposal';                // Sale/scrap/retirement
    case DISPOSAL_GAIN = 'disposal_gain';      // Profit on sale
    case DISPOSAL_LOSS = 'disposal_loss';      // Loss on sale
    case MAINTENANCE = 'maintenance';          // Revenue expense (not capitalized)
    case UPGRADE = 'upgrade';                  // Capital improvement
    case TRANSFER = 'transfer';                // Inter-company or location move
    case SPLIT = 'split';                      // Divide into multiple assets
    case COMBINE = 'combine';                  // Merge multiple assets
    case REVALUATION = 'revaluation';          // Periodic revaluation (IAS 16)
    case DEPRECIATION_ACCELERATED = 'depreciation_accelerated'; // Tax vs. book difference
}
