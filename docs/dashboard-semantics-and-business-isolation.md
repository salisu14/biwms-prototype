# Dashboard Semantics and Business Isolation

BIWMS dashboard services expose a mixture of operational, subledger, G/L,
inventory-ledger/value, and manufacturing execution measures. The source of
each measure is intentional:

| Dashboard | Measure | Authority | Classification |
| --- | --- | --- | --- |
| Finance | Cash / bank | `BankAccountLedgerEntry` | G/L/subledger financial |
| Finance | Receivables / payables | Customer/Vendor ledger remaining amounts | Subledger financial |
| Finance | Revenue / COGS | G/L entries by account category | G/L financial |
| Finance | Trial-balance health | Business-scoped G/L debit-credit difference | G/L financial |
| Sales | Posted sales and invoice count | Posted sales invoices | Operational document |
| Sales | Payments and outstanding receivables | Customer ledger entries | Subledger financial |
| Purchase | Posted purchases and receipts | Posted purchase invoices / receipt lines | Operational document |
| Purchase | Payables and unpaid invoices | Vendor ledger entries | Subledger financial |
| Inventory | Quantity / movement / negative stock | Item Ledger Entries | Inventory ledger |
| Inventory | Stock value | Value Entries | Inventory value |
| Manufacturing | Open orders / output / shortages | Production orders and Item Ledger Entries | Execution |
| Manufacturing | WIP / variance | Value Entries | Manufacturing cost |

Dashboard services accept an explicit `businessId`. When it is omitted, they
use the active business session where the source has persisted business
ownership. A missing context preserves the existing all-context service API
for non-panel callers and tests; panel requests are expected to establish the
active business context.

Financial sources with a `business_id` are filtered explicitly. Item Ledger
Entries, items, locations, Production Orders, and related component tables do
not currently have reliable business ownership in the schema. Their
business-specific dashboard output is therefore marked with an
`ownership_limitations` value rather than being falsely filtered by an
unrelated dimension. Adding manufacturing or inventory ownership is a future
schema decision, outside this phase.

All monetary dashboard values are explicitly LCY aggregates. G/L reports use
the stored `*_amount_lcy` fields with a legacy fallback to the transaction
amount when the LCY snapshot is null. Customer and Vendor ledgers do not have
separate LCY columns in the current schema, so their remaining balances use
`remaining_amount * currency_factor`. Posted document operational totals use
their persisted document `currency_factor` to produce LCY dashboard values.
Original currency values remain available on document and subledger detail
surfaces; dashboards do not silently sum mixed-currency display values.

`ValueEntry.cost_amount_actual` and `cost_amount_expected` retain the
existing economic sign convention. Inventory reports derive movement
direction from the signed Value Entry quantity, while zero-quantity rows keep
their stored adjustment sign. This FR-D pass does not normalize or rewrite
those values.

## Reconciliation warnings

Finance and inventory warning cards invoke the existing report-only
reconciliation commands. Those commands currently produce global diagnostics,
so the card exposes `reconciliation_scope = global` and labels the count as a
global report-only warning. It must not be interpreted as a business-specific
reconciliation result until the commands themselves support business-scoped
reports.

Warnings never repair data. The known seeded item stock-cache differences
remain diagnostic findings; ledger quantity and value remain authoritative.
