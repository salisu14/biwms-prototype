<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionReadinessClassification: string
{
    case Waiting = 'waiting';
    case UserActionRequired = 'user_action_required';
    case ConfigurationProblem = 'configuration_problem';
    case IntegrityProblem = 'integrity_problem';
    case SecurityProblem = 'security_problem';
}
