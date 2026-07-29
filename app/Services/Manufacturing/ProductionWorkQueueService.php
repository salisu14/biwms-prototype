<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionOperationExecutionStatus;
use App\Enums\ProductionOrderStatus;
use App\Models\Employee;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductionWorkQueueService
{
    /**
     * @param  array{work_center_id?: int, machine_center_id?: int, operator_employee_id?: int, search?: string}  $filters
     * @return Collection<int, ProductionOrderRoutingLine>
     */
    public function availableOperations(array $filters = [], ?Employee $operator = null): Collection
    {
        return ProductionOrderRoutingLine::query()
            ->with(['productionOrder.item', 'workCenter', 'machineCenter', 'operationExecutions.assignments'])
            ->whereHas('productionOrder', function (Builder $query) use ($filters): void {
                $query->where('status', ProductionOrderStatus::RELEASED);

                if (! empty($filters['search'])) {
                    $query->where('document_number', 'like', '%'.$filters['search'].'%');
                }
            })
            ->when($filters['work_center_id'] ?? null, fn (Builder $query, int $workCenterId): Builder => $query->where('work_center_id', $workCenterId))
            ->when($filters['machine_center_id'] ?? null, fn (Builder $query, int $machineCenterId): Builder => $query->where('machine_center_id', $machineCenterId))
            ->whereDoesntHave('operationExecutions', function (Builder $query): void {
                $query->whereIn('status', [
                    ProductionOperationExecutionStatus::Posted,
                    ProductionOperationExecutionStatus::Cancelled,
                    ProductionOperationExecutionStatus::Reversed,
                ]);
            })
            ->orderBy('production_order_id')
            ->orderBy('line_number')
            ->get()
            ->filter(fn (ProductionOrderRoutingLine $line): bool => $operator === null || $this->operatorCanSee($line, $operator))
            ->values();
    }

    private function operatorCanSee(ProductionOrderRoutingLine $line, Employee $operator): bool
    {
        return $line->operationExecutions->isEmpty()
            || $line->operationExecutions->contains(fn ($execution): bool => (int) $execution->operator_employee_id === (int) $operator->id
                || $execution->assignments->contains('employee_id', $operator->id));
    }
}
