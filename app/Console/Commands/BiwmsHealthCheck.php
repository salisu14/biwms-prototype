<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:health-check')]
#[Description('Run BIWMS production readiness and security health checks.')]
class BiwmsHealthCheck extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $criticalFailures = [];
        $this->info('BIWMS Health Check');
        $this->newLine();

        $appEnv = (string) config('app.env');
        $appDebug = (bool) config('app.debug');
        $sessionSecure = (bool) config('session.secure');
        $isProduction = $appEnv === 'production';

        $this->line("APP_ENV: {$appEnv}");
        $this->line('APP_DEBUG: '.($appDebug ? 'true' : 'false'));
        $this->line('SESSION_SECURE_COOKIE: '.($sessionSecure ? 'true' : 'false'));
        $this->line('Admin idle timeout: '.config('security.admin_idle_timeout_minutes').' minute(s)');
        $this->line('Admin absolute session lifetime: '.config('security.admin_absolute_session_lifetime_minutes').' minute(s)');
        $this->line('Queue connection: '.config('queue.default'));
        $this->line('Mail mailer: '.(config('mail.default') ?: 'not configured'));
        $this->newLine();

        if ($isProduction && $appDebug) {
            $criticalFailures[] = 'APP_DEBUG=true in production';
        }

        if ($isProduction && ! $sessionSecure) {
            $criticalFailures[] = 'SESSION_SECURE_COOKIE=false in production';
        }

        $this->checkDatabase($criticalFailures);
        $this->checkWritablePath('Storage writable', storage_path(), $criticalFailures);
        $this->checkWritablePath('Logs writable', storage_path('logs'), $criticalFailures);
        $this->checkFailedJobs();
        $this->checkSecurityAudit($criticalFailures);
        $this->checkOldPermissions();
        $this->checkOptimizationFiles();
        $this->checkDiskSpace();

        $this->newLine();

        if ($criticalFailures !== []) {
            $this->error('Critical failures:');

            foreach ($criticalFailures as $failure) {
                $this->line(" - {$failure}");
            }

            return self::FAILURE;
        }

        $this->info('Health check passed with no critical failures.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $criticalFailures
     */
    private function checkDatabase(array &$criticalFailures): void
    {
        try {
            DB::select('select 1');
            $this->line('Database connectivity: ok');
        } catch (\Throwable $exception) {
            $this->error('Database connectivity: failed - '.$exception->getMessage());
            $criticalFailures[] = 'database unavailable';
        }
    }

    /**
     * @param  array<int, string>  $criticalFailures
     */
    private function checkWritablePath(string $label, string $path, array &$criticalFailures): void
    {
        $writable = File::isDirectory($path) && File::isWritable($path);
        $this->line($label.': '.($writable ? 'ok' : 'failed'));

        if (! $writable) {
            $criticalFailures[] = "{$path} is not writable";
        }
    }

    private function checkFailedJobs(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->line('Failed jobs count: table not present');

            return;
        }

        $this->line('Failed jobs count: '.DB::table('failed_jobs')->count());
    }

    /**
     * @param  array<int, string>  $criticalFailures
     */
    private function checkSecurityAudit(array &$criticalFailures): void
    {
        $exitCode = Artisan::call('biwms:security-audit', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $criticalCounts = [
            'resources_without_permission_module' => count($report['resources_without_permission_module'] ?? []),
            'resources_without_permission_resource' => count($report['resources_without_permission_resource'] ?? []),
            'resources_without_policies' => count($report['resources_without_policies'] ?? []),
            'missing_generated_permissions' => count($report['missing_generated_permissions'] ?? []),
        ];

        $this->line('Security audit hard checks: '.(($exitCode === self::SUCCESS && array_sum($criticalCounts) === 0) ? 'ok' : 'failed'));

        foreach ($criticalCounts as $label => $count) {
            $this->line(" - {$label}: {$count}");
        }

        if ($exitCode !== self::SUCCESS || array_sum($criticalCounts) > 0) {
            $criticalFailures[] = 'security audit critical failure';
        }
    }

    private function checkOldPermissions(): void
    {
        $count = Schema::hasTable('permissions')
            ? Permission::query()
                ->where('guard_name', 'web')
                ->pluck('name')
                ->filter(fn (string $permission): bool => (bool) preg_match('/^(view_any|view|create|update|delete|delete_any|restore|restore_any|force_delete|force_delete_any)_[a-z0-9_]+$/', $permission))
                ->count()
            : 0;

        $this->line("Old generated permission rows: {$count}");
    }

    private function checkOptimizationFiles(): void
    {
        $this->line('Config cached: '.(File::exists(base_path('bootstrap/cache/config.php')) ? 'yes' : 'no'));
        $this->line('Routes cached: '.(File::exists(base_path('bootstrap/cache/routes-v7.php')) ? 'yes' : 'no'));
        $this->line('Events cached: '.(File::exists(base_path('bootstrap/cache/events.php')) ? 'yes' : 'no'));
    }

    private function checkDiskSpace(): void
    {
        $freeBytes = disk_free_space(base_path());

        if ($freeBytes === false) {
            $this->line('Disk space: unavailable');

            return;
        }

        $freeMegabytes = round($freeBytes / 1024 / 1024);
        $this->line("Disk space free: {$freeMegabytes} MB".($freeMegabytes < 1024 ? ' (warning)' : ''));
    }
}
