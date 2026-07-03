# BIWMS Pilot Go-Live Checklist

This checklist is based on the latest deployment verification pass.

## Technical Verification Status

The technical deployment checks are passing:

- `php artisan migrate:fresh --seed` passes.
- `php artisan test --compact` passes: 236 tests, 3440 assertions.
- `php artisan biwms:security-audit` passes.
- `php artisan biwms:health-check` passes.
- `php artisan biwms:finance-reconcile --details` is clean.
- Generated-column grep is clean.
- Derived fields are maintained by model/service logic:
  - `fixed_assets.net_book_value`
  - `employees.full_name`
  - `actual_overhead_costs.remaining_amount`

The remaining items are pilot setup/data warnings, not deployment blockers. Resolve them before first client live transactions.

## Remaining Pilot Setup Warnings

1. Company/business profile is not configured.
2. Number series need active unblocked lines.
3. No active bank/cash account is configured.
4. Super Admin MFA is not confirmed.
5. Inventory reconciliation reports seeded item stock cache vs ledger-sum mismatches.

## Exact Setup Order

### 1. Configure Company And Business Profile

Complete this first because reports, posting context, audit context, and client-facing documents need a correct company identity.

Checklist:

- Create or update the company information record.
- Confirm company name, trading name, address, country, tax registration, email, phone, base currency, and fiscal year settings.
- Create at least one active business record if the pilot uses business/factory dimensions.
- Confirm the profile appears correctly on reports and printed documents.

Validation:

```bash
php artisan biwms:pilot-check
```

Expected result: company/business profile warning is cleared.

### 2. Configure Number Series

Configure document numbering before creating pilot transactions. Posting and audit traceability depend on reliable document numbers.

Checklist:

- Create active number series for pilot document flows:
  - sales orders/invoices/credit memos
  - purchase orders/invoices/credit memos
  - payments/receipts
  - bank ledger entries
  - inventory journals/adjustments
  - warehouse receipts/shipments/transfers, if in pilot scope
  - production orders/output/consumption, if in pilot scope
  - payroll documents, if in pilot scope
- Add at least one unblocked number series line for each active series.
- Confirm prefixes and starting numbers match the client pilot convention.
- Avoid changing number series after live pilot transactions begin unless approved and documented.

Validation:

```bash
php artisan biwms:pilot-check
```

Expected result: number series warning is cleared.

### 3. Configure Bank/Cash Account

Configure bank/cash before posting receipts, payments, payroll payments, petty cash, or bank reconciliation entries.

Checklist:

- Create at least one active bank or cash account.
- Link it to the correct G/L cash or bank account.
- Enable receipts and/or payments as appropriate.
- Confirm opening bank balance is entered through an approved opening entry process.
- Confirm bank ledger and G/L cash/bank account reconcile after opening entry.

Validation:

```bash
php artisan biwms:pilot-check
php artisan biwms:finance-reconcile --details
```

Expected result:

- bank/cash account warning is cleared.
- finance reconciliation remains clean.

### 4. Confirm Super Admin MFA

Complete Super Admin MFA before inviting client users or enabling live pilot access.

Checklist:

- Confirm at least one user has the canonical `super_admin` role.
- Log in as that Super Admin.
- Complete MFA setup.
- Confirm recovery codes are stored securely by the client-designated administrator.
- Avoid shared Super Admin accounts for daily work.
- Create least-privilege users for normal pilot operations.

Validation:

```bash
php artisan biwms:pilot-check
php artisan biwms:security-audit
```

Expected result:

- Super Admin MFA warning is cleared.
- security audit remains clean.

### 5. Resolve Seeded Inventory Stock Cache Mismatches

The current inventory warning is a setup/data issue: seeded item stock cache values do not match item ledger sums. It is not a deployment blocker, but it must be resolved before the client relies on stock availability.

Recommended pilot-safe approach:

- Do not directly edit item stock fields as the source of truth.
- Decide whether pilot starts with zero stock or with opening stock balances.
- If starting with zero stock:
  - clear or adjust seeded/demo item stock through an approved setup process before client use.
  - rerun inventory reconciliation.
- If starting with real opening stock:
  - post opening stock through approved inventory journal/opening balance documents.
  - confirm every opening stock movement creates Item Ledger Entries and Value Entries.
  - confirm cached item stock matches ledger sums after posting.
- Do not add an automatic repair command for pilot data without review and approval.

Validation:

```bash
php artisan biwms:inventory-reconcile --details
```

Expected result:

- item stock field vs item ledger sum mismatches are understood and resolved before live inventory use.
- no negative stock violations.
- no missing item ledger entries.
- no missing or mismatched value entries.

## Final Pre-Login Commands

Run after all warnings are resolved:

```bash
php artisan optimize:clear
php artisan permission:cache-reset
php artisan biwms:security-audit
php artisan biwms:health-check
php artisan biwms:pilot-check
php artisan biwms:finance-reconcile --details
php artisan biwms:inventory-reconcile --details
```

Expected result:

- security audit has no hard-check findings.
- health check has no critical failures.
- pilot check has no errors and no unresolved pilot warnings.
- finance reconciliation is clean.
- inventory reconciliation has no unexplained stock, ledger, or value mismatches.

## First Client Login

Before live transactions:

- Confirm the pilot URL loads over HTTPS.
- Confirm Super Admin login and MFA challenge work.
- Confirm client users can access only their assigned panels.
- Confirm menus and backend authorization match assigned roles.
- Confirm company profile appears correctly on reports and prints.
- Confirm number series generates expected document numbers.
- Confirm bank/cash account is available for receipts and payments.
- Confirm opening inventory, receivables, payables, and bank balances are accepted by the client.
- Share `docs/client-feedback.md` with pilot users.

## Go-Live Sign-Off

Record:

- Date and time:
- Environment URL:
- Release/commit:
- Technical verifier:
- Client approver:
- Remaining known issues:
- Commands run:
- Decision: Go / No-Go
