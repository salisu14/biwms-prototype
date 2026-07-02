<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\CompanyInformation;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\InventoryPostingSetup;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorPostingGroup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

#[Signature('biwms:pilot-check {--json : Output machine-readable JSON}')]
#[Description('Report BIWMS client pilot readiness checks.')]
class BiwmsPilotCheck extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = [
            $this->companyProfileCheck(),
            $this->chartOfAccountsCheck(),
            $this->postingGroupsCheck(),
            $this->numberSeriesCheck(),
            $this->bankAccountCheck(),
            $this->inventoryLocationsCheck(),
            $this->superAdminMfaCheck(),
            $this->securityAuditCheck(),
            $this->reconciliationCommandsCheck(),
        ];

        $summary = [
            'pass' => count(array_filter($checks, fn (array $check): bool => $check['status'] === 'pass')),
            'warning' => count(array_filter($checks, fn (array $check): bool => $check['status'] === 'warning')),
            'error' => count(array_filter($checks, fn (array $check): bool => $check['status'] === 'error')),
        ];

        $report = [
            'mode' => 'report-only',
            'summary' => $summary,
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Pilot Readiness Check');
        $this->line('Mode: report-only. No setup, posting, or ledger data was changed.');
        $this->newLine();

        foreach ($checks as $check) {
            $this->line(sprintf(
                '[%s] %s: %s',
                strtoupper((string) $check['status']),
                $check['label'],
                $check['message'],
            ));
        }

        $this->newLine();
        $this->line(sprintf(
            'Summary: %s pass, %s warning, %s error.',
            $summary['pass'],
            $summary['warning'],
            $summary['error'],
        ));

        if ($summary['error'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function companyProfileCheck(): array
    {
        $companyProfiles = CompanyInformation::query()
            ->whereNotNull('company_name')
            ->where('company_name', '!=', '')
            ->count();

        $businesses = Business::query()
            ->where('is_active', true)
            ->count();

        return $this->check(
            key: 'company_profile',
            label: 'Company/business profile',
            passed: $companyProfiles > 0 || $businesses > 0,
            passMessage: "{$companyProfiles} company profile(s), {$businesses} active business record(s).",
            warningMessage: 'Create the company information record or at least one active business before pilot transactions.',
            counts: [
                'company_profiles' => $companyProfiles,
                'active_businesses' => $businesses,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function chartOfAccountsCheck(): array
    {
        $postingAccounts = ChartOfAccount::query()
            ->where('blocked', false)
            ->where('structural_type', 'posting')
            ->count();

        return $this->check(
            key: 'chart_of_accounts',
            label: 'Chart of accounts',
            passed: $postingAccounts > 0,
            passMessage: "{$postingAccounts} active posting account(s) available.",
            warningMessage: 'Seed or create the chart of accounts before configuring posting groups.',
            counts: ['active_posting_accounts' => $postingAccounts],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function postingGroupsCheck(): array
    {
        $counts = [
            'customer_posting_groups' => CustomerPostingGroup::query()->where('blocked', false)->count(),
            'vendor_posting_groups' => VendorPostingGroup::query()->where('blocked', false)->count(),
            'general_posting_setups' => GeneralPostingSetup::query()->where('blocked', false)->count(),
            'inventory_posting_setups' => InventoryPostingSetup::query()->count(),
        ];

        $missing = array_keys(array_filter($counts, fn (int $count): bool => $count === 0));

        return $this->check(
            key: 'posting_groups',
            label: 'Posting groups and setups',
            passed: $missing === [],
            passMessage: 'Customer, vendor, general, and inventory posting setups are present.',
            warningMessage: 'Missing pilot posting setup: '.implode(', ', $missing).'.',
            counts: $counts,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function numberSeriesCheck(): array
    {
        $activeSeries = NumberSeries::query()
            ->where('is_active', true)
            ->count();

        $activeLines = NumberSeriesLine::query()
            ->where('blocked', false)
            ->count();

        return $this->check(
            key: 'number_series',
            label: 'Number series',
            passed: $activeSeries > 0 && $activeLines > 0,
            passMessage: "{$activeSeries} active series and {$activeLines} available line(s).",
            warningMessage: 'Configure active number series and unblocked lines before pilot posting.',
            counts: [
                'active_series' => $activeSeries,
                'active_lines' => $activeLines,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bankAccountCheck(): array
    {
        $activeBankAccounts = BankAccount::query()
            ->where('active', true)
            ->count();

        return $this->check(
            key: 'bank_cash_account',
            label: 'Bank/cash account',
            passed: $activeBankAccounts > 0,
            passMessage: "{$activeBankAccounts} active bank/cash account(s) configured.",
            warningMessage: 'Create at least one active bank or cash account linked to a G/L account.',
            counts: ['active_bank_accounts' => $activeBankAccounts],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryLocationsCheck(): array
    {
        $activeLocations = Location::query()
            ->where('is_active', true)
            ->where('blocked', false)
            ->count();

        return $this->check(
            key: 'inventory_locations',
            label: 'Warehouses/locations',
            passed: $activeLocations > 0,
            passMessage: "{$activeLocations} active inventory location(s) configured.",
            warningMessage: 'Create at least one active, unblocked inventory location.',
            counts: ['active_locations' => $activeLocations],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function superAdminMfaCheck(): array
    {
        $superAdminRoleIds = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['super_admin', 'Super Admin', 'super-admin', 'super admin'])
            ->pluck('id');

        $superAdminUsers = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $superAdminRoleIds))
            ->count();

        $superAdminUsersWithMfa = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $superAdminRoleIds))
            ->whereNotNull('two_factor_secret')
            ->whereNotNull('two_factor_confirmed_at')
            ->count();

        return $this->check(
            key: 'super_admin_mfa',
            label: 'Super Admin MFA',
            passed: $superAdminUsers > 0 && $superAdminUsersWithMfa > 0,
            passMessage: "{$superAdminUsersWithMfa} of {$superAdminUsers} Super Admin user(s) have confirmed MFA.",
            warningMessage: $superAdminUsers === 0
                ? 'Create or assign a Super Admin user before pilot.'
                : 'Require confirmed MFA for at least one Super Admin before pilot.',
            counts: [
                'super_admin_users' => $superAdminUsers,
                'super_admin_users_with_mfa' => $superAdminUsersWithMfa,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function securityAuditCheck(): array
    {
        try {
            $output = new BufferedOutput;
            $exitCode = Artisan::call('biwms:security-audit', ['--json' => true], $output);
            $report = json_decode($output->fetch(), true) ?: [];

            $hardFindings = [
                'resources_without_permission_module' => count($report['resources_without_permission_module'] ?? []),
                'resources_without_permission_resource' => count($report['resources_without_permission_resource'] ?? []),
                'resources_without_policies' => count($report['resources_without_policies'] ?? []),
                'missing_generated_permissions' => count($report['missing_generated_permissions'] ?? []),
            ];

            $findingCount = array_sum($hardFindings);

            return [
                'key' => 'security_audit',
                'label' => 'Security audit',
                'status' => $exitCode === self::SUCCESS && $findingCount === 0 ? 'pass' : 'warning',
                'message' => $exitCode === self::SUCCESS && $findingCount === 0
                    ? 'Security audit hard checks pass.'
                    : 'Security audit is callable but reports hard-check findings; run php artisan biwms:security-audit for details.',
                'counts' => $hardFindings,
            ];
        } catch (\Throwable $exception) {
            return $this->errorCheck('security_audit', 'Security audit', $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function reconciliationCommandsCheck(): array
    {
        $commands = [
            'biwms:finance-reconcile' => 'finance_reconcile',
            'biwms:inventory-reconcile' => 'inventory_reconcile',
        ];

        $results = [];

        foreach ($commands as $command => $key) {
            try {
                $results[$key] = Artisan::call($command, ['--json' => true], new BufferedOutput) === self::SUCCESS ? 'pass' : 'warning';
            } catch (\Throwable) {
                $results[$key] = 'error';
            }
        }

        if (in_array('error', $results, true)) {
            return [
                'key' => 'reconciliation_commands',
                'label' => 'Reconciliation commands',
                'status' => 'error',
                'message' => 'One or more reconciliation commands could not run.',
                'counts' => $results,
            ];
        }

        $passed = ! in_array('warning', $results, true);

        return [
            'key' => 'reconciliation_commands',
            'label' => 'Reconciliation commands',
            'status' => $passed ? 'pass' : 'warning',
            'message' => $passed
                ? 'Finance and inventory reconciliation diagnostics are callable.'
                : 'One or more reconciliation diagnostics returned a warning exit code.',
            'counts' => $results,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function check(string $key, string $label, bool $passed, string $passMessage, string $warningMessage, array $counts = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $passed ? 'pass' : 'warning',
            'message' => $passed ? $passMessage : $warningMessage,
            'counts' => $counts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorCheck(string $key, string $label, \Throwable $exception): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => 'error',
            'message' => $exception->getMessage(),
            'counts' => [],
        ];
    }
}
