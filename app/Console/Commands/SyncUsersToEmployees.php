<?php

namespace App\Console\Commands;

use App\Enums\EmployeeAssignmentType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hr:sync-users-employees {--dry-run : Show what would change without writing records}')]
#[Description('Ensure each user is linked to an employee record; creates employees for users without one.')]
class SyncUsersToEmployees extends Command
{
    private int $nextSequence = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $usersWithoutEmployee = User::query()
            ->whereNull('employee_id')
            ->orderBy('id')
            ->get();

        if ($usersWithoutEmployee->isEmpty()) {
            $this->info('All users are already linked to employees.');

            return self::SUCCESS;
        }

        $created = 0;
        $linked = 0;

        $this->bootstrapSequence();

        foreach ($usersWithoutEmployee as $user) {
            $nameParts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
            $firstName = $nameParts[0] ?? 'User';
            $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'Account';

            $employeeNumber = $this->nextEmployeeNumber();

            if ($dryRun) {
                $this->line("DRY-RUN user #{$user->id} {$user->email} -> employee {$employeeNumber}");

                continue;
            }

            $employee = Employee::create([
                'employee_number' => $employeeNumber,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $user->email,
                'assignment_type' => EmployeeAssignmentType::Corporate,
                'is_active' => true,
            ]);

            $created++;

            $user->forceFill(['employee_id' => $employee->id])->save();
            $linked++;
        }

        if ($dryRun) {
            $this->warn("Dry run complete. Users needing linkage: {$usersWithoutEmployee->count()}");

            return self::SUCCESS;
        }

        $this->info("Completed. Employees created: {$created}, users linked: {$linked}.");

        return self::SUCCESS;
    }

    private function bootstrapSequence(): void
    {
        $lastEmployeeNumber = Employee::query()
            ->where('employee_number', 'like', 'EMP-%')
            ->orderByDesc('employee_number')
            ->value('employee_number');

        $lastSequence = 0;
        if (is_string($lastEmployeeNumber) && preg_match('/EMP-(\d+)$/', $lastEmployeeNumber, $matches)) {
            $lastSequence = (int) $matches[1];
        }

        $this->nextSequence = $lastSequence + 1;
    }

    private function nextEmployeeNumber(): string
    {
        while (Employee::query()->where('employee_number', sprintf('EMP-%04d', $this->nextSequence))->exists()) {
            $this->nextSequence++;
        }

        $employeeNumber = sprintf('EMP-%04d', $this->nextSequence);
        $this->nextSequence++;

        return $employeeNumber;
    }
}
