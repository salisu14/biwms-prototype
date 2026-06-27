```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Security & Authorization

### Authorization Standard

Filament `strictAuthorization()` is enabled. Do not disable it to bypass errors. Missing policy errors should be fixed, not hidden.

Every Filament Resource model must have a registered policy, and every resource must define:

- `permissionModule()`
- `permissionResource()`

The standard permission format is:

```text
module.resource.action
```

Examples:

```text
admin.user.view_any
finance.bank_account.update
pricing.price_list.delete
factory.production_order.finish
```

### Permission Generation

`Database\Seeders\PermissionsTableSeeder` is the source of generated permissions.

Do not manually create CRUD permissions for normal resources. CRUD permissions are generated from the Filament resource registry.

Special business permissions such as `approve`, `post`, `finish`, `void`, and `reconcile` are manually listed in the seeder.

Do not use old generated names such as:

```text
view_any_user
create_price_list
```

### Policy Standard

Use `GenericFilamentPolicy` or `BaseFilamentPolicy` unless special business logic is needed.

Custom policies must still implement the strict authorization method surface:

```text
viewAny
view
create
update
delete
deleteAny
restore
restoreAny
forceDelete
forceDeleteAny
```

### Adding a New Filament Resource

Checklist:

- Add `permissionModule()`.
- Add `permissionResource()`.
- Ensure a policy exists and is registered.
- Run:

```bash
php artisan db:seed --class=Database\\Seeders\\PermissionsTableSeeder
php artisan biwms:security-audit
php artisan test --compact tests/Feature/Authorization/FilamentAuthorizationStandardTest.php
```

### Permission Cleanup

`biwms:permissions-cleanup` defaults to dry-run:

```bash
php artisan biwms:permissions-cleanup --dry-run
```

Never run `--force` without reviewing the output first.

Old generated development permissions are preserved unless confirmed unused. Before a production or staging reset, prefer rebuilding the baseline with `migrate:fresh --seed` when that is safe for the environment.

### Deployment Checklist

Before production:

```bash
php artisan migrate:fresh --seed # if safe for new deployment only
php artisan optimize:clear
php artisan permission:cache-reset
php artisan biwms:security-audit
php artisan test --compact
```

### TODO: Session and Destructive Action Hardening

- Password confirmation for destructive and sensitive actions.
- Session timeout and hardening review.
- Audit logging for role, permission, and security changes.
- IDOR tests for integer primary key resources.
- Prevent self-privilege escalation.
- Audit financial posting and reversal actions.

Next security phase: implement password confirmation for destructive and sensitive Filament actions, including `delete`, `deleteAny`, `forceDelete`, role changes, permission changes, financial posting, payment voiding, journal reversal, payroll posting, production posting, MFA reset, and recovery-code regeneration.
