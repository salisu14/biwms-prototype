<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionSchedulingExceptionType: string
{
    case NoRouting = 'no_routing';
    case NoValidWorkCenter = 'no_valid_work_center';
    case NoAvailableMachineCenter = 'no_available_machine_center';
    case MissingCalendar = 'missing_calendar';
    case CapacityOverload = 'capacity_overload';
    case DueDateImpossible = 'due_date_impossible';
    case UpstreamDependencyUnresolved = 'upstream_dependency_unresolved';
    case MaterialUnavailable = 'material_unavailable';
    case QualityHold = 'quality_hold';
    case MaintenanceConflict = 'maintenance_conflict';
    case AlternateResourceRequired = 'alternate_resource_required';
    case OperationDurationInvalid = 'operation_duration_invalid';
    case FrozenOperationMoved = 'frozen_operation_moved';
}
