<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DepartmentStatus;
use App\Models\Department;
use App\Models\DepartmentEmployee;
use App\Models\DimensionValue;
use App\Models\Employee;
use App\Models\GlEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DepartmentService
{
    public function __construct(
        private readonly DimensionService $dimensionService,
        private readonly NumberSeriesService $numberSeriesService
    ) {}

    /**
     * Create department with automatic dimension value creation
     */
    public function create(array $data): Department
    {
        return DB::transaction(function () use ($data) {
            // Auto-generate code if not provided
            if (empty($data['department_code'])) {
                $data['department_code'] = $this->numberSeriesService->getNextNo('DEPT');
            }

            // Create dimension value if dimension integration enabled
            if (!empty($data['create_dimension_value']) && $data['create_dimension_value'] === true) {
                $dimensionValue = $this->createDimensionValue($data);
                $data['dimension_value_id'] = $dimensionValue->id;
                $data['global_dimension_1_code'] = $dimensionValue->code;
            }

            $department = Department::create($data);

            // Update employee manager reference if manager_id provided
            if (!empty($data['manager_id'])) {
                $this->updateManager($department, $data['manager_id']);
            }

            return $department->fresh(['dimensionValue', 'manager']);
        });
    }

    /**
     * Update department with hierarchy recalculation
     */
    public function update(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data) {
            $oldParentId = $department->parent_department_id;

            $department->update($data);

            // Recalculate paths if parent changed
            if (isset($data['parent_department_id']) && $data['parent_department_id'] != $oldParentId) {
                $this->recalculatePaths($department);
            }

            // Update manager if changed
            if (isset($data['manager_id']) && $data['manager_id'] != $department->getOriginal('manager_id')) {
                $this->updateManager($department, $data['manager_id']);
            }

            return $department->fresh();
        });
    }

    /**
     * Move department in hierarchy
     */
    public function moveDepartment(Department $department, ?int $newParentId): void
    {
        if ($newParentId && $department->isDescendantOf($newParentId)) {
            throw new \InvalidArgumentException('Cannot move department to its own subtree');
        }

        $department->update(['parent_department_id' => $newParentId]);
        $this->recalculatePaths($department);
    }

    /**
     * Merge two departments (BC: Combine Departments)
     */
    public function mergeDepartments(Department $source, Department $target): void
    {
        DB::transaction(function () use ($source, $target) {
            // Move employees
            Employee::where('department_id', $source->id)
                ->update(['department_id' => $target->id]);

            // Move sub-departments
            Department::where('parent_department_id', $source->id)
                ->update(['parent_department_id' => $target->id]);

            // Update G/L entries dimension (historical)
            GlEntry::where('shortcut_dimension_1_code', $source->global_dimension_1_code)
                ->update(['shortcut_dimension_1_code' => $target->global_dimension_1_code]);

            // Block source department
            $source->block("Merged into {$target->department_code}");

            // Recalculate target budget
            $target->recalculateBudget();
        });
    }

    /**
     * Assign employee to department
     */
    public function assignEmployee(
        Department $department,
        int $employeeId,
        array $assignmentData = []
    ): DepartmentEmployee {
        $defaultData = [
            'department_id' => $department->id,
            'employee_id' => $employeeId,
            'assignment_type' => 'primary',
            'assignment_date' => now(),
            'allocation_percentage' => 100,
            'is_default_dimension' => true,
        ];

        $data = array_merge($defaultData, $assignmentData);

        // End previous primary assignment
        if ($data['assignment_type'] === 'primary') {
            DepartmentEmployee::where('employee_id', $employeeId)
                ->where('assignment_type', 'primary')
                ->whereNull('end_date')
                ->update(['end_date' => now()]);
        }

        $assignment = DepartmentEmployee::create($data);

        // Update employee's default department
        if ($data['is_default_dimension']) {
            Employee::where('id', $employeeId)->update([
                'department_id' => $department->id,
                'shortcut_dimension_1_code' => $department->global_dimension_1_code,
            ]);
        }

        return $assignment;
    }

    /**
     * Get department hierarchy tree
     */
    public function getHierarchy(?int $rootId = null): array
    {
        $query = Department::with(['manager', 'subDepartments'])
            ->where('status', DepartmentStatus::ACTIVE);

        if ($rootId) {
            $query->where('id', $rootId);
        } else {
            $query->rootLevel();
        }

        return $query->get()->map(function ($dept) {
            return $this->buildTreeNode($dept);
        })->toArray();
    }

    /**
     * Get budget report by department
     */
    public function getBudgetReport(array $departmentIds = [], ?int $fiscalYear = null): array
    {
        $fiscalYear = $fiscalYear ?? now()->year;

        $query = Department::with(['dimensionValue'])
            ->whereNotNull('annual_budget');

        if (!empty($departmentIds)) {
            $query->whereIn('id', $departmentIds);
        }

        return $query->get()->map(function ($dept) use ($fiscalYear) {
            $actual = GeneralLedgerEntry::where('shortcut_dimension_1_code', $dept->global_dimension_1_code)
                ->whereYear('posting_date', $fiscalYear)
                ->sum('amount');

            return [
                'department_code' => $dept->department_code,
                'department_name' => $dept->name,
                'budget' => $dept->annual_budget,
                'actual' => abs($actual),
                'variance' => $dept->annual_budget - abs($actual),
                'variance_percent' => $dept->annual_budget ?
                    (($dept->annual_budget - abs($actual)) / $dept->annual_budget * 100) : 0,
            ];
        })->toArray();
    }

    // Private helpers
    private function createDimensionValue(array $data): DimensionValue
    {
        return $this->dimensionService->createValue([
            'dimension_code' => 'DEPARTMENT', // Standard BC dimension
            'code' => $data['department_code'],
            'name' => $data['name'],
            'dimension_value_type' => 'standard',
            'blocked' => false,
        ]);
    }

    private function recalculatePaths(Department $department): void
    {
        $parent = $department->parentDepartment;

        $department->level = $parent ? $parent->level + 1 : 0;
        $department->department_path = $parent
            ? $parent->department_path . '|' . $department->department_code
            : $department->department_code;

        $department->save();

        // Recursively update children
        foreach ($department->subDepartments as $child) {
            $this->recalculatePaths($child);
        }
    }

    private function updateManager(Department $department, int $managerId): void
    {
        $manager = Employee::find($managerId);

        if ($manager && $manager->department_id !== $department->id) {
            // Assign manager to department if not already assigned
            $this->assignEmployee($department, $managerId, [
                'position_title' => 'Department Manager',
                'is_default_dimension' => false,
            ]);
        }
    }

    private function buildTreeNode(Department $department): array
    {
        return [
            'id' => $department->id,
            'code' => $department->department_code,
            'name' => $department->name,
            'manager' => $department->manager?->full_name,
            'employee_count' => $department->employees()->count(),
            'children' => $department->subDepartments->map(fn($child) => $this->buildTreeNode($child)),
        ];
    }
}
