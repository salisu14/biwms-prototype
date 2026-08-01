# Referrer Commission User Guide

## Setup

Configure Referral Commission Settings before processing commission payments:

1. Enable referral commissions.
2. Select the commission currency.
3. Configure commission expense and payable accounts.
4. Configure a bank account or petty cash fund for payment.

## Payment Process

1. Confirm the settlement batch is locked.
2. Post commission liability for the locked settlement.
3. Create a commission payment batch from the locked settlement.
4. Prepare and submit the payment batch.
5. A different authorized user approves the batch.
6. Post the approved batch.

Posted payment batches are immutable. Corrections must be made by reversal.

## Notes

- Partial payments are allowed and outstanding amounts are derived from payment applications.
- Bank and cheque payments require a bank account. Cash payments require a petty cash fund.
- Mobile money, wallet, and other methods are not enabled until finance setup provides a safe ledger path.
- Full bank details are not shown in normal payment tables; payment lines keep masked beneficiary references.

## Reconciliation

Use:

```bash
php artisan biwms:commission-reconcile --details
```

Review critical findings before paying commissions. Do not manually edit commission ledger entries, payment applications, or posted batches.
