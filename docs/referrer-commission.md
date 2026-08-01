# Referrer Commission Architecture

BIWMS referrer commission is ledger-driven and append-only.

## Lifecycle

Posted Sales -> Calculation -> Ledger Accrual -> Review -> Settlement Snapshot -> Liability Posting -> Payment Batch -> Payment Applications -> Bank/Cash and G/L Posting

## Source of Truth

- Commission calculations explain how commission was earned.
- Commission ledger entries record accrual, reversal, liability, payment, and payment reversal history.
- Settlement batches are locked snapshots of approved payable amounts.
- Payment applications link actual payments to settlement allocations.
- Referrer balances are derived from settlement lines and payment applications.

No mutable balance field on the referrer is authoritative.

## Accounting

Liability recognition:

- Dr Commission Expense
- Cr Commission Payable

Payment:

- Dr Commission Payable
- Cr Bank or Cash

All accounting entries must go through the central posting kernel via `GeneralLedgerService`.

## Number Series

Phase 6 prefers configured `COMM-LIAB` and `COMM-PAY` number series. If those series are absent or invalid, services use deterministic controlled fallback numbers so retries remain idempotent.

## Security

Commission payment approval, posting, cancellation, and reversal require explicit permissions. Sensitive actions require password confirmation in Filament.

## Reconciliation

Run commission reconciliation after setup changes, before payment runs, and during month-end close:

```bash
php artisan biwms:commission-reconcile --details
```

The command is diagnostic only.
