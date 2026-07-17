# BIWMS Performance Audit

`php artisan biwms:performance-audit` is a report-only diagnostic command for Filament and Livewire performance risks. It does not mutate business data, permissions, caches, or schema.

## Command Options

```bash
php artisan biwms:performance-audit
php artisan biwms:performance-audit --panel=hr
php artisan biwms:performance-audit --panel=hr --details
php artisan biwms:performance-audit --panel=hr --measure-routes --runs=5
php artisan biwms:performance-audit --export=storage/app/reports/performance-audit.json
```

Use isolated PostgreSQL test schemas for test-suite verification. Do not run multiple test suites against the same schema concurrently.

## Severity Levels

- `critical`: confirmed severe risk that should block release once such checks are introduced.
- `high`: likely high-impact issue such as large operational preloads, navigation database work, or expensive global search.
- `medium`: optimization candidate that should be handled in ranked passes.
- `low`: informational finding or accepted fallback.

## Route Measurement

When `--measure-routes` is used, the command measures representative HR routes through Laravel's HTTP kernel using an HR-accessible seeded user. `--runs=5` records repeated warm measurements and reports min, median, and max values.

Tracked metrics include response time, query count, duplicate query count, database duration, peak memory, and HTML response size. The median warm response is the primary comparison metric.

## Employees Index Benchmarks

The Employees index uses `10` rows by default in production, with pagination options `10`, `25`, and `50`. Do not add an `all` option for high-volume HR tables.

Two benchmark modes are documented for this page:

- Diagnostic benchmark: a temporary `3` row configuration may be used locally to isolate Filament shell cost from table payload during investigation.
- Production benchmark: the committed configuration is `10` rows and is the value used for release verification.

When reviewing payload size, separate the shared Filament shell from the Employees table/page delta. The shell payload is common across HR pages and should not be charged to the Employees table optimization budget.

## Performance Budgets

Budgets are warnings only:

- normal index warm median: `500ms`
- complex index warm median: `800ms`
- normal HTML payload: `200 KB`
- complex HTML payload: `300 KB`
- duplicate queries: `0`

Warnings do not fail CI yet. Use them to rank the next optimization pass.

## JSON Structure

Exports include:

- `summary`: count of findings by severity.
- `findings`: category, severity, panel, resource/page, file, line, message, and remediation.
- `global_search`: globally searchable resources and likely expensive resources.
- `route_measurements`: cold run, warm runs, warm summary, and budget warnings.

Keep exported before/after reports for comparison, for example:

```text
storage/app/reports/performance-audit-before-phase3.json
storage/app/reports/performance-audit-after-phase3.json
```

## False Positives

The scanner is conservative. Small reference-table preloads may remain acceptable. Findings should be ranked against measured route impact before code changes are made.

## Cache Lifecycle

Static metadata caches store only resource class metadata and table-column metadata. They must not contain user, tenant, business, authorization, or model-instance state.

Deployment procedures that restart PHP-FPM workers naturally refresh these caches. If Octane or another long-running worker is used later, restart workers after resource or migration changes.

## Production Safety

The command is safe to run in production-like environments because it is read-only. Route measurement still renders pages, so run it during a low-traffic diagnostic window when possible.
