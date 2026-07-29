# Referrer Commission Phase 4

Phase 4 introduces commission calculation and accrual foundations for posted sales documents.

## Scope

- Posted Sales Invoices are the primary accrual source.
- Draft invoices, sales orders, and quotes do not create commission accruals.
- Commission entries are append-only ledger records.
- Commission accruals do not post to the G/L and do not create payments.
- Existing customer referral history remains the relationship source of truth.

## Calculation Flow

1. A sales invoice is posted.
2. `CommissionCalculationService` evaluates the posted invoice after the invoice transaction commits.
3. The service resolves the active primary `CustomerReferral` for the customer and posting date.
4. The service resolves the active referrer commission plan assignment.
5. Eligible lines are snapshotted into `commission_calculation_lines`.
6. Accrual entries are written to `commission_ledger_entries`.

If no eligible referral or plan exists, the system records an ineligible calculation result without creating a payable accrual.

## Supported Bases

- `gross_sales`
- `net_sales`
- `line_net_amount`
- `gross_profit`
- `quantity`
- `fixed_amount`

Gross profit uses posted invoice line cost/profit snapshots. If those snapshots are missing, the calculation is marked failed instead of guessing.

## Ledger Rules

`commission_ledger_entries` is append-only:

- corrections create reversal or adjustment entries;
- existing entries are not edited;
- deletion is blocked by the model;
- balances are derived from ledger sums, not stored totals.

Entry types include accrual, reversal, adjustment, settlement, and cancellation. Phase 4 creates accruals, reversals, and controlled adjustments only.

## Reversals

Posted Sales Credit Memos reverse commission using the original accrual snapshots where the credit memo line links back to an original posted invoice line.

Partial reversals are proportional and capped at the unreversed original accrual amount.

## Reconciliation

Run:

```bash
php artisan biwms:commission-reconcile --details
```

Optional JSON export:

```bash
php artisan biwms:commission-reconcile --details --export=storage/app/reports/commission-reconcile.json
```

The command is report-only. It does not repair, delete, reverse, post, pay, or mutate commission data.

## Security

Commission calculations and ledger entries have explicit policies and BIWMS permissions:

- `sales.commission_calculation.view_any`
- `sales.commission_calculation.view`
- `sales.commission_calculation.calculate`
- `sales.commission_calculation.recalculate`
- `sales.commission_calculation.reverse`
- `sales.commission_ledger.view_any`
- `sales.commission_ledger.view`
- `sales.commission_ledger.export`
- `sales.commission_adjustment.create`
- `sales.commission_adjustment.approve`
- `sales.commission_adjustment.reverse`
- `sales.referrer_commission_balance.view`
- `sales.commission_reconcile.run`

Do not bypass referral history by adding a direct `customers.referrer_id` shortcut.

## Not Included

Phase 4 does not implement commission payment workflows, payment approvals, payout documents, or G/L posting.
