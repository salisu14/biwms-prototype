<?php

declare(strict_types=1);

namespace App\Enums;

enum MaintenanceContractType: string
{
    case PREVENTIVE = 'preventive';      // Scheduled regular maintenance
    case CORRECTIVE = 'corrective';      // Break-fix support
    case PREDICTIVE = 'predictive';      // Condition-based monitoring
    case FULL_SERVICE = 'full_service';    // All-inclusive
    case WARRANTY = 'warranty';            // Manufacturer warranty
    case EXTENDED_WARRANTY = 'extended_warranty'; // Post-warranty extension

    public function includesParts(): bool
    {
        return in_array($this, [self::FULL_SERVICE, self::WARRANTY, self::EXTENDED_WARRANTY]);
    }

    public function includesLabor(): bool
    {
        return true; // All types include labor
    }

    public function isScheduled(): bool
    {
        return in_array($this, [self::PREVENTIVE, self::PREDICTIVE]);
    }
}
