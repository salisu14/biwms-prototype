# BIWMS Pilot Setup Guide

Use this guide to prepare BIWMS for the 3-month client pilot. The goal is to configure master data and opening balances in a controlled order before users start daily transactions.

## Recommended Setup Order

1. Company and business profile
   - Confirm company name, address, base currency, tax details, and reporting identity.
   - Create active business/factory/warehouse dimensions where they are used.

2. Users, roles, and MFA
   - Create pilot users.
   - Assign least-privilege roles.
   - Confirm every Super Admin has MFA enabled before go-live.

3. Chart of accounts
   - Load or create posting accounts first.
   - Confirm bank, receivables, payables, revenue, COGS, inventory, WIP, payroll, and expense accounts.

4. Posting groups and posting setup
   - Configure customer and vendor posting groups.
   - Configure general business/product posting groups.
   - Configure general posting setup for expected sales, purchase, inventory, and manufacturing combinations.
   - Configure inventory posting groups and inventory posting setup by location where needed.

5. Number series
   - Configure document numbering for sales, purchase, inventory, finance, payment, warehouse, production, payroll, and credit memo documents.
   - Verify every active series has at least one unblocked line.

6. Master data
   - Create customers, vendors, items, units of measure, payment terms, warehouses, locations, and bins where applicable.
   - Confirm every inventory item has posting groups and base UOM configured.

7. Opening balances
   - Opening stock: post through approved opening inventory journals.
   - Opening receivables and payables: enter posted opening invoices or approved opening ledger entries.
   - Opening bank balances: enter bank/cash balances through controlled opening journals or bank ledger setup.

8. Readiness validation
   - Run the pilot check.
   - Run security and reconciliation diagnostics.
   - Resolve setup warnings before client users begin live pilot transactions.

## Pilot Checklist

- Company/business profile is complete.
- Pilot users and roles are configured.
- Super Admin MFA is confirmed.
- Chart of accounts exists and includes all control accounts.
- Customer, vendor, general, and inventory posting groups are configured.
- Number series are active and have unblocked lines.
- Customers, vendors, and items are created.
- Warehouses and inventory locations are active.
- Opening stock is posted and reconciled.
- Opening receivables and payables are posted and reconciled.
- Opening bank balances are posted and reconciled.
- `php artisan biwms:pilot-check` has been reviewed.
- `php artisan biwms:security-audit` has no hard-check failures.
- Finance and inventory reconciliation reports have been reviewed.

## Common Mistakes To Avoid

- Starting transactions before number series are configured.
- Creating customers, vendors, or items without posting groups.
- Using generic catch-all accounts for receivables, payables, inventory, COGS, or revenue.
- Entering opening stock directly into item cards instead of posting ledger-backed opening entries.
- Entering opening bank balances without matching bank ledger and G/L control-account impact.
- Posting purchase invoices after receipts in a way that double-adds inventory.
- Treating cached item stock or master-table balances as the source of truth instead of ledgers.
- Giving pilot users broad roles to work around missing permissions.
- Allowing Super Admin access without MFA.

## Readiness Commands

```bash
php artisan biwms:pilot-check
php artisan biwms:security-audit
php artisan biwms:health-check
php artisan biwms:finance-reconcile --details
php artisan biwms:inventory-reconcile --details
```

The pilot check is report-only. It does not create setup records, fix reconciliation findings, or mutate pilot data.
