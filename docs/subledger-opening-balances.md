# Customer and Vendor Opening Balances

BIWMS records controlled customer and vendor opening balances as finance
documents in `subledger_opening_balances`. The document is the source record
for the cutover event; posting creates one customer or vendor ledger entry and
one balanced G/L transaction. The control account is resolved from the party's
posting group and the offset is the configured opening-balance equity account.

## Lifecycle

1. Create a `DRAFT` document for a positive customer or vendor amount.
2. Review the party, business, currency factor, dates, and accounts.
3. Post only in an open accounting period through the opening-balance service.
4. Apply later receipts or payments through the existing settlement workflow.
5. Reverse through the controlled reversal action when an un-settled opening
   balance needs correction. Posted facts are not edited or deleted.

Customer openings debit receivables and credit opening equity. Vendor openings
credit payables and debit opening equity. The amount is captured in the party
currency and converted to LCY using the stored currency factor. Unsupported
credit balances are rejected in this phase; use the approved credit-memo or
settlement workflows for credit situations.

## Controls

- Customer and vendor opening number series are `CUSTOMER-OPENING` and
  `VENDOR-OPENING`.
- Posting is authorized by the finance opening-balance permissions and the
  active business context.
- The posting transaction uses the canonical General Ledger Posting Kernel,
  with an idempotency key and row locks for retry/concurrency safety.
- The source opening document, subledger row, posting transaction, and control
  G/L line retain explicit lineage.
- Posted and reversed opening documents are immutable. Reversal is append-only
  and cannot proceed while the opening ledger entry has active applications.
- Audit events are recorded for creation, posting, and reversal without
  storing credentials or other secrets.

## Reporting and reconciliation

Customer statements, vendor statements, and aging reports already read the
customer/vendor ledger entries. Once posted, opening entries therefore appear
in those reports and can be settled like other open subledger entries. The
report-only `biwms:subledger-reconcile` command checks opening-document links,
business ownership, remaining amounts, reversal state, and currency snapshots.

Run it with `--details` before pilot cutover. It reports findings and never
repairs, backfills, deletes, or reposts data automatically.

## Scope limitation

This phase does not add automatic cash, inventory, payroll, or G/L repair
logic. Opening balances are finance cutover documents only; all later business
transactions must use their existing posting services.
