<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionHoldType: string
{
    case Compliance = 'compliance';
    case Documentation = 'documentation';
    case ReturnRisk = 'return_risk';
    case FraudReview = 'fraud_review';
    case CustomerDispute = 'customer_dispute';
    case ReferrerDispute = 'referrer_dispute';
    case ManagementReview = 'management_review';
    case ReconciliationIssue = 'reconciliation_issue';
    case Manual = 'manual';
}
