# Referrer Commission User Guide

## What Creates Commission

Commission is calculated from Posted Sales Invoices only. Draft documents and sales orders do not accrue commission.

## Before Posting

Confirm these setup records exist:

- active referrer;
- active primary customer referral;
- active commission plan;
- active plan assignment for the referrer;
- valid plan effective dates.

## Reviewing Calculations

Use Sales > Commission Calculations to review evaluated posted invoices.

Statuses:

- `pending` means created but not completed;
- `ineligible` means no valid referral or plan was found;
- `accrued` means ledger entries were created;
- `failed` means a calculation requirement was missing or invalid;
- `reversed` means the accrual has been reversed by later entries.

## Reviewing Ledger Entries

Use Sales > Commission Ledger Entries to review append-only accrual, reversal, and adjustment entries.

Balances should be read from the commission ledger, not from manually maintained fields.

## Reconcile

Finance or Sales administrators can run:

```bash
php artisan biwms:commission-reconcile --details
```

For an exportable audit file:

```bash
php artisan biwms:commission-reconcile --details --export=storage/app/reports/commission-reconcile.json
```

The reconcile command is diagnostic only.

## Review and Settlement Preparation

Operator workflow:

```text
Open review period
→ Generate batch
→ Review exceptions
→ Place or release holds
→ Resolve disputes
→ Submit
→ Approve
→ Lock
→ Prepare settlement
→ Submit settlement
→ Approve settlement
→ Lock for future Phase 6 payment
```

Review batches and settlement batches are separated by currency. Do not combine different currencies in one settlement total.

Settlement preparation locks a snapshot for future payment but does not pay the Referrer and does not post to the General Ledger.

## Corrections

Use credit memos, reversals, or controlled adjustment entries. Do not edit historical commission ledger entries.
