<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionOperationDependencyReadiness: string
{
    case Ready = 'ready';
    case Blocked = 'blocked';
    case PartiallyReady = 'partially_ready';
    case WaitingForUpstreamOutput = 'waiting_for_upstream_output';
    case WaitingForUpstreamOperation = 'waiting_for_upstream_operation';
    case WaitingForQualityRelease = 'waiting_for_quality_release';
    case WaitingForReservedSupply = 'waiting_for_reserved_supply';
    case UpstreamCancelled = 'upstream_cancelled';
    case InvalidDependency = 'invalid_dependency';
}
