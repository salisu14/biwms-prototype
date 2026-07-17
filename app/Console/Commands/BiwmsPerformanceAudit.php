<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use App\Support\Filament\CompletedResourceSchema;
use App\Support\FilamentPermissionRegistry;
use Filament\Facades\Filament;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

#[Signature('biwms:performance-audit {--panel= : Panel id to focus on, for example admin or hr} {--details : Show detailed findings} {--measure-routes : Measure representative route performance} {--export= : Write JSON report to the given path}')]
#[Description('Report static BIWMS performance risks without mutating data.')]
class BiwmsPerformanceAudit extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FilamentPermissionRegistry $registry): int
    {
        $panel = $this->option('panel') ? (string) $this->option('panel') : null;
        $details = (bool) $this->option('details');
        $measureRoutes = (bool) $this->option('measure-routes');

        $startedAt = hrtime(true);

        $resources = $registry->resources();
        $panelFindings = $this->panelRegistrationFindings($panel);
        $navigationFindings = $this->navigationQueryFindings();
        $relationshipSelectFindings = $this->relationshipSelectFindings();
        $globalSearchFindings = $this->globalSearchFindings();
        $schemaFindings = $this->completedResourceSchemaFindings();
        $globalSearchRiskFindings = $this->globalSearchRiskFindings($globalSearchFindings);

        $report = [
            'panel' => $panel,
            'resource_count' => count($resources),
            'global_search_resource_count' => count($globalSearchFindings['enabled_resources']),
            'findings' => [
                ...$panelFindings,
                ...$navigationFindings,
                ...$relationshipSelectFindings,
                ...$globalSearchRiskFindings,
                ...$schemaFindings,
            ],
            'global_search' => $globalSearchFindings,
            'route_measurements' => $measureRoutes ? $this->measureRepresentativeRoutes() : [],
            'duration_ms' => round((hrtime(true) - $startedAt) / 1e6, 2),
        ];
        $report['summary'] = $this->severitySummary($report['findings']);

        $this->line('BIWMS Performance Audit');
        $this->line('Mode: report-only. No data was changed.');
        $this->line('Resources scanned: '.$report['resource_count']);
        $this->line('Global-search resources detected: '.$report['global_search_resource_count']);
        $this->line('Findings: '.count($report['findings']));
        $this->line('Critical: '.$report['summary']['critical']);
        $this->line('High: '.$report['summary']['high']);
        $this->line('Medium: '.$report['summary']['medium']);
        $this->line('Low: '.$report['summary']['low']);

        if ($measureRoutes) {
            $this->newLine();
            $this->line('Representative route measurements');

            foreach ($report['route_measurements'] as $measurement) {
                $this->line(sprintf(
                    '%s [%s] cold=%sms/%s queries warm=%sms/%s queries size=%s bytes',
                    $measurement['label'],
                    $measurement['path'],
                    $measurement['cold']['response_time_ms'] ?? 'n/a',
                    $measurement['cold']['query_count'] ?? 'n/a',
                    $measurement['warm']['response_time_ms'] ?? 'n/a',
                    $measurement['warm']['query_count'] ?? 'n/a',
                    $measurement['warm']['response_size_bytes'] ?? 'n/a',
                ));
            }
        }

        if ($details) {
            foreach ($report['findings'] as $finding) {
                $this->newLine();
                $this->line(strtoupper((string) $finding['severity']).' '.$finding['category']);
                $this->line((string) $finding['message']);

                if (! empty($finding['file'])) {
                    $line = isset($finding['line']) ? ':'.$finding['line'] : '';
                    $this->line('File: '.$finding['file'].$line);
                }

                if (! empty($finding['remediation'])) {
                    $this->line('Remediation: '.$finding['remediation']);
                }
            }
        }

        if ($exportPath = $this->option('export')) {
            $path = base_path((string) $exportPath);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->line('Export written: '.$path);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function panelRegistrationFindings(?string $panel): array
    {
        $findings = [];
        $providerFiles = collect(File::files(app_path('Providers/Filament')))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), 'PanelProvider.php'));

        foreach ($providerFiles as $file) {
            $contents = File::get($file->getPathname());
            $panelId = Str::of($file->getFilename())->before('PanelProvider.php')->snake()->toString();

            if ($panel !== null && $panelId !== $panel) {
                continue;
            }

            $usesDiscovery = str_contains($contents, '->discoverResources(');
            $usesManualResources = str_contains($contents, '->resources([');

            if ($usesDiscovery && $usesManualResources) {
                $findings[] = [
                    'type' => 'panel_discovery_and_manual_resources',
                    'category' => 'panel registration risk',
                    'severity' => 'medium',
                    'panel' => $panelId,
                    'resource' => null,
                    'message' => "{$panelId} panel uses both resource discovery and manual resource registration. Verify there is no overlap.",
                    'file' => $file->getPathname(),
                    'line' => $this->lineForPattern($file->getPathname(), '/->discoverResources\(|->resources\(\[/'),
                    'remediation' => 'Keep either discovery or manual registration for overlapping directories, or document intentional non-overlap.',
                ];
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function navigationQueryFindings(): array
    {
        $findings = [];
        $methods = [
            'getNavigationBadge',
            'getNavigationBadgeColor',
            'shouldRegisterNavigation',
            'canAccess',
            'getNavigationLabel',
        ];

        foreach ([app_path('Filament/Resources'), app_path('Filament/Pages')] as $path) {
            if (! File::exists($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = File::get($file->getPathname());

                foreach ($methods as $method) {
                    $methodBody = $this->methodBody($contents, $method);

                    if ($methodBody === null || preg_match('/(count\(|sum\(|exists\(|where\(|whereHas\()/s', $methodBody['body']) !== 1) {
                        continue;
                    }

                    $findings[] = [
                        'type' => 'database_query_in_navigation_metadata',
                        'category' => 'database query in navigation metadata',
                        'severity' => 'high',
                        'panel' => $this->panelFromPath($file->getPathname()),
                        'resource' => $this->resourceFromPath($file->getPathname()),
                        'message' => "{$method}() appears to contain database work.",
                        'file' => $file->getPathname(),
                        'line' => $methodBody['line'],
                        'remediation' => 'Avoid database work in navigation metadata, or cache/scoped-count it briefly when operationally necessary.',
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relationshipSelectFindings(): array
    {
        return $this->scanFilesForPatterns(
            paths: [app_path('Filament/Resources')],
            category: 'eager relationship options',
            severity: 'medium',
            patterns: [
                '/->options\s*\([^;]*(::query\(|::all\(|->pluck\(|->get\()/s',
                '/->preload\s*\((?!\s*false)/',
            ],
            message: 'Form field may eagerly load options or preload relationship choices.',
            remediation: 'Use async searchable relationship selects for large datasets such as employees, candidates, applications, and vacancies.',
        );
    }

    /**
     * @return array{enabled_resources: array<int, string>, likely_expensive_resources: array<int, string>}
     */
    private function globalSearchFindings(): array
    {
        $enabled = [];
        $expensive = [];

        foreach (File::allFiles(app_path('Filament/Resources')) as $file) {
            if (! str_ends_with($file->getFilename(), 'Resource.php')) {
                continue;
            }

            $contents = File::get($file->getPathname());

            if (str_contains($contents, 'protected static bool $isGloballySearchable = false')) {
                continue;
            }

            $resource = str_replace([app_path('Filament/Resources').'/', '.php', '/'], ['', '', '\\'], $file->getPathname());
            $enabled[] = 'App\\Filament\\Resources\\'.$resource;

            if (Str::contains($file->getPathname(), ['Histories', 'Ledger', 'Entries', 'VerificationLogs'])) {
                $expensive[] = $file->getPathname();
            }
        }

        return [
            'enabled_resources' => $enabled,
            'likely_expensive_resources' => $expensive,
        ];
    }

    /**
     * @param  array{enabled_resources: array<int, string>, likely_expensive_resources: array<int, string>}  $globalSearchFindings
     * @return array<int, array<string, mixed>>
     */
    private function globalSearchRiskFindings(array $globalSearchFindings): array
    {
        return collect($globalSearchFindings['likely_expensive_resources'])
            ->map(fn (string $file): array => [
                'type' => 'global_search_risk',
                'category' => 'global search risk',
                'severity' => 'high',
                'panel' => $this->panelFromPath($file),
                'resource' => $this->resourceFromPath($file),
                'message' => 'History, ledger, log, entry, or verification resource appears globally searchable.',
                'file' => $file,
                'line' => 1,
                'remediation' => 'Disable global search on high-volume history/detail resources, or restrict it to concise identifiers only.',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function completedResourceSchemaFindings(): array
    {
        $reflection = new \ReflectionClass(CompletedResourceSchema::class);

        return [[
            'type' => 'completed_resource_schema_metadata',
            'category' => 'generic schema usage',
            'severity' => 'low',
            'panel' => null,
            'resource' => null,
            'message' => 'CompletedResourceSchema uses database column metadata with request/process-level memoization.',
            'file' => $reflection->getFileName(),
            'line' => 24,
            'remediation' => 'Keep as fallback for low-priority resources; replace high-use resources with explicit schemas where UX or payload size matters.',
        ]];
    }

    /**
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $patterns
     * @return array<int, array<string, mixed>>
     */
    private function scanFilesForPatterns(array $paths, string $category, string $severity, array $patterns, string $message, string $remediation): array
    {
        $findings = [];

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = File::get($file->getPathname());

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $contents) !== 1) {
                        continue;
                    }

                    $line = $this->lineForPattern($file->getPathname(), $pattern);
                    $lineContext = $this->lineContext($file->getPathname(), $line);
                    $effectiveSeverity = $this->severityForFinding($severity, $category, $file->getPathname(), $lineContext, $line);
                    $findings[] = [
                        'type' => Str::slug($category, '_'),
                        'category' => $category,
                        'severity' => $effectiveSeverity,
                        'panel' => $this->panelFromPath($file->getPathname()),
                        'resource' => $this->resourceFromPath($file->getPathname()),
                        'message' => $message,
                        'file' => $file->getPathname(),
                        'line' => $line,
                        'remediation' => $remediation,
                    ];

                    break;
                }
            }
        }

        return $findings;
    }

    private function severityForFinding(string $defaultSeverity, string $category, string $path, string $lineContext, ?int $line): string
    {
        if ($category === 'eager relationship options' && $line === null) {
            return $defaultSeverity;
        }

        $largeOperationalResource = Str::contains($path, [
            'Employees',
            'RecruitmentApplications',
            'RecruitmentCandidates',
            'RecruitmentInterviews',
            'RecruitmentOffers',
            'RecruitmentOnboardingPlans',
            'PerformanceAppraisals',
            'EmployeeAttendanceDays',
            'EmployeeAttendanceEvents',
            'WorkforceRosterAssignments',
        ]);

        if ($category === 'eager relationship options' && Str::contains($lineContext, [
            'Role::',
            'Department::',
            'PayCode::',
            'EmployeePostingGroup::',
            'PayrollPostingGroup::',
        ])) {
            return 'medium';
        }

        if ($category === 'eager relationship options' && $largeOperationalResource) {
            return 'high';
        }

        return $defaultSeverity;
    }

    private function lineContext(string $path, ?int $line): string
    {
        if ($line === null) {
            return '';
        }

        $lines = file($path);

        if ($lines === false) {
            return '';
        }

        $start = max(0, $line - 6);
        $slice = array_slice($lines, $start, 12);

        return implode('', $slice);
    }

    private function lineForPattern(string $path, string $pattern): ?int
    {
        $lines = file($path);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $lineNumber => $line) {
            if (preg_match($pattern, $line) === 1) {
                return $lineNumber + 1;
            }
        }

        return null;
    }

    /**
     * @return array{body: string, line: int}|null
     */
    private function methodBody(string $contents, string $method): ?array
    {
        if (preg_match('/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{/m', $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $match[0][1];
        $bodyStart = $start + strlen($match[0][0]);
        $depth = 1;
        $length = strlen($contents);

        for ($offset = $bodyStart; $offset < $length; $offset++) {
            $char = $contents[$offset];

            if ($char === '{') {
                $depth++;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return [
                        'body' => substr($contents, $bodyStart, $offset - $bodyStart),
                        'line' => substr_count(substr($contents, 0, $start), "\n") + 1,
                    ];
                }
            }
        }

        return null;
    }

    private function panelFromPath(string $path): ?string
    {
        if (Str::contains($path, ['/Hr/', '/HR/', '/Recruitment', '/Performance', '/Attendance', '/Workforce', '/Employee'])) {
            return 'hr';
        }

        if (Str::contains($path, ['/Finance/', 'ChartOfAccounts', 'BankAccounts'])) {
            return 'finance';
        }

        return null;
    }

    private function resourceFromPath(string $path): ?string
    {
        if (! Str::contains($path, 'app/Filament/Resources/')) {
            return Str::of($path)->after('app/Filament/Pages/')->before('.php')->replace('/', '\\')->toString();
        }

        return Str::of($path)->after('app/Filament/Resources/')->before('/')->snake()->toString();
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array{critical: int, high: int, medium: int, low: int}
     */
    private function severitySummary(array $findings): array
    {
        $summary = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($findings as $finding) {
            $severity = (string) ($finding['severity'] ?? 'low');
            $summary[$severity] = ($summary[$severity] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function measureRepresentativeRoutes(): array
    {
        $user = $this->benchmarkUser();

        if ($user === null) {
            return [[
                'label' => 'Skipped',
                'path' => 'n/a',
                'error' => 'No user found for authenticated route measurement.',
            ]];
        }

        return collect($this->representativeRoutes())
            ->map(function (array $route) use ($user): array {
                $path = $route['path'];

                return [
                    'label' => $route['label'],
                    'path' => $path,
                    'cold' => $this->measureRoute($path, $user),
                    'warm' => $this->measureRoute($path, $user),
                ];
            })
            ->all();
    }

    private function benchmarkUser(): ?object
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            return null;
        }

        $panel = Filament::getPanel('hr');

        foreach (['hr-officer', 'hr-manager', 'admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role === null) {
                continue;
            }

            $user = $userModel::query()
                ->whereHas('roles', fn ($query) => $query->whereKey($role->getKey()))
                ->get()
                ->first(fn (object $candidate): bool => method_exists($candidate, 'canAccessPanel') && $candidate->canAccessPanel($panel));

            if ($user !== null) {
                return $user;
            }
        }

        $superAdminRole = Role::query()->whereIn('name', ['Super Admin', 'super_admin'])->first();

        if ($superAdminRole !== null) {
            $user = $userModel::query()
                ->whereHas('roles', fn ($query) => $query->whereKey($superAdminRole->getKey()))
                ->first();

            if ($user !== null) {
                return $user;
            }
        }

        return $userModel::query()->first();
    }

    /**
     * @return array<int, array{label: string, path: string}>
     */
    private function representativeRoutes(): array
    {
        return array_values(array_filter([
            ['label' => 'HR dashboard', 'path' => '/hr'],
            ['label' => 'Recruitment Applications index', 'path' => '/hr/recruitment-applications'],
            ['label' => 'Candidates index', 'path' => '/hr/recruitment-candidates'],
            ['label' => 'Employees index', 'path' => '/hr/employees'],
            ['label' => 'Attendance Register', 'path' => '/hr/employee-attendance-days'],
            ['label' => 'Workforce Roster Assignments index', 'path' => '/hr/workforce-roster-assignments'],
            ['label' => 'Performance Appraisals index', 'path' => '/hr/performance-appraisals'],
            ['label' => 'Recruitment Application create', 'path' => '/hr/recruitment-applications/create'],
            $this->firstRecordRoute('Recruitment Application edit', RecruitmentApplication::class, '/hr/recruitment-applications/%s/edit'),
            ['label' => 'Candidate create', 'path' => '/hr/recruitment-candidates/create'],
            $this->firstRecordRoute('Candidate edit', RecruitmentCandidate::class, '/hr/recruitment-candidates/%s/edit'),
        ]));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array{label: string, path: string}|null
     */
    private function firstRecordRoute(string $label, string $modelClass, string $pathFormat): ?array
    {
        $id = $modelClass::query()->value('id');

        if ($id === null) {
            return null;
        }

        return [
            'label' => $label,
            'path' => sprintf($pathFormat, $id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function measureRoute(string $path, object $user): array
    {
        $queries = [];
        $capturing = true;

        DB::listen(function (QueryExecuted $query) use (&$queries, &$capturing): void {
            if (! $capturing) {
                return;
            }

            $queries[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ];
        });

        $startedAt = hrtime(true);
        $startedMemory = memory_get_usage(true);

        $session = app('session.store');
        $session->start();
        $session->put([
            'two_factor_passed_at' => now()->timestamp,
            'super_admin_2fa_passed_at' => now()->timestamp,
            'admin_authenticated_at' => now()->timestamp,
            'admin_last_activity_at' => now()->timestamp,
        ]);

        Auth::forgetGuards();
        Auth::shouldUse('web');
        Auth::guard('web')->login($user);
        Auth::guard('web')->setUser($user);
        $session->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());

        $request = Request::create($path, 'GET');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn (): object => $user);

        try {
            $response = app(Kernel::class)->handle($request);
        } catch (\Throwable $exception) {
            $capturing = false;

            return [
                'error' => $exception::class.': '.$exception->getMessage(),
                'response_time_ms' => round((hrtime(true) - $startedAt) / 1e6, 2),
                'query_count' => count($queries),
                'database_duration_ms' => round(array_sum(array_column($queries, 'time_ms')), 2),
            ];
        }

        $capturing = false;
        $content = method_exists($response, 'getContent') ? (string) $response->getContent() : '';

        $duplicateQueryCount = collect($queries)
            ->map(fn (array $query): string => $query['sql'].'|'.json_encode($query['bindings']))
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->sum(fn (int $count): int => $count - 1);

        return [
            'status' => $response->getStatusCode(),
            'redirect_to' => $response->headers->get('Location'),
            'response_time_ms' => round((hrtime(true) - $startedAt) / 1e6, 2),
            'query_count' => count($queries),
            'duplicate_query_count' => $duplicateQueryCount,
            'database_duration_ms' => round(array_sum(array_column($queries, 'time_ms')), 2),
            'peak_memory_mb' => round((memory_get_peak_usage(true) - $startedMemory) / 1024 / 1024, 2),
            'response_size_bytes' => strlen($content),
        ];
    }
}
