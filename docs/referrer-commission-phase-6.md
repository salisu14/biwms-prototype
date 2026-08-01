# Referrer Commission Phase 6: Payment and Liability Settlement

Phase 6 completes the referrer commission flow after settlement locking.

## Posting Flow

1. Posted sales documents create commission calculations and accrual ledger entries.
2. Review batches approve eligible accruals.
3. Settlement batches create locked snapshots of approved payable amounts.
4. Liability posting recognizes the locked settlement in finance:
   - Dr Commission Expense
   - Cr Commission Payable
5. Payment batches apply cash or bank payments against locked settlement lines.
6. Payment posting clears the payable and creates the payment trail:
   - Dr Commission Payable
   - Cr Bank or Cash
   - Commission payment applications against settlement allocations
   - Commission ledger payment entries
   - Bank ledger entry for bank or cheque payments

## Ownership Boundary

Commission calculation, review, and settlement services own commission eligibility and payable snapshots.
`CommissionLiabilityPostingService` and `CommissionPaymentService` own liability/payment posting.
All G/L entries are created through the central `GeneralLedgerService` posting kernel.

Filament resources, models, observers, controllers, and reports must not write G/L entries directly.

## Supported Payment Methods

Phase 6 supports:

- bank transfer
- cheque
- cash through a configured petty cash fund

Mobile money and custom methods remain blocked until explicit posting setup exists.

## Example

Approved commission settlement: NGN 500,000

Liability recognition:

- Dr Commission Expense: NGN 500,000
- Cr Commission Payable: NGN 500,000

First payment:

- Dr Commission Payable: NGN 300,000
- Cr Bank: NGN 300,000
- Outstanding: NGN 200,000

Second payment:

- Dr Commission Payable: NGN 200,000
- Cr Bank: NGN 200,000
- Outstanding: NGN 0

The settlement is fully paid when payment applications equal the locked net settlement amount.

## Known Limitations

- Withholding tax is deferred until a configured withholding setup exists.
- Mobile money, wallet, and other payment methods are blocked until a real ledger path exists.
- Full payment reversal is implemented; partial reversal remains a future controlled workflow.
- Recovery required after a late paid-commission reversal is reported by balances/reconciliation and must be settled through a future approved offset or recovery process.
- Manufacturing Phase 2 is outside this phase.

## Diagnostics

`php artisan biwms:commission-reconcile --details` reports Phase 6 findings such as:

- locked settlements without liability posting
- duplicate liability postings
- payment totals that do not match lines
- payment applications exceeding settlement allocations
- payments without bank/cash or G/L entries
- self-approved payment batches

The command is report-only and does not repair data.
