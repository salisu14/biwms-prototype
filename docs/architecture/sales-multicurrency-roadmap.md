# Sales Multi-Currency Roadmap

This document is the durable, repository-tracked record of the remaining
multi-currency work for the Sales domain. It exists because several Phase 3A
risks were only captured in excluded agent reports. It is **architectural
intent only** — none of the items below are implemented yet unless marked done.

See also:
`docs/architecture/common-multicurrency-contract.md`,
`docs/architecture/purchasing-currency-contract.md`.

## Shared conventions

- LCY is **NGN**. Factor is **LCY per 1 FCY** (`LCY = FCY x factor`).
- `App\Support\DocumentCurrency` is the authoritative currency helper.
- A missing currency defaults to LCY **only for new documents**; an existing
  document with a missing/ambiguous currency fails closed and is never
  silently reinterpreted.
- A foreign currency requires an explicit, finite, strictly positive factor.
  An explicitly supplied factor of exactly `1` is valid (parity); a
  **fabricated** factor `1` (missing factor silently becoming 1) is forbidden.
- Never default an unresolved currency to `USD`.

## Status

### Phase 3A — schema + shared contract (done)

- LCY (`*_lcy`) columns added across sales documents/lines (nullable, unpopulated).
- Unsafe `currency_factor DEFAULT 1` relaxed to nullable where every creation
  path can supply it.
- `DocumentCurrency` helper + `SalesPrice` currency-tagged pricing schema.

### Phase 3B1 — currency-context safety (done)

- `SalesDocumentCurrencyService` centralizes Sales currency/factor validation.
- `SalesOrder` no longer defaults `currency_factor` to 1; currency is resolved
  on create.
- `SalesQuoteService::convertToOrder` no longer defaults to `USD`.
- `BlanketOrder::createSalesOrder` resolves NGN/foreign factor explicitly.
- `SalesInvoiceService` / `SalesOrder::postInvoice` /
  `SalesCreditMemoService` preserve validated draft currency/factor and no
  longer hard-code `currency_factor => 1` on posting.
- Admin Filament Sales Order / Invoice / Credit Memo forms expose an explicit
  exchange-rate field (`1 FCY = X NGN`).

### Phase 3B1-R — architecture review corrections (done)

- **New vs existing semantics split.** `SalesDocumentCurrencyService` now
  exposes `resolveForNewDocument()` (blank -> NGN) and
  `resolveForExistingDocument()` (blank -> fail closed). The generic `resolve()`
  was removed so callers must choose deliberately.
- **Model-event boundary corrected.** `SalesOrder` resolves currency on
  `creating` only. On `updating` it resolves **only when currency_code or
  currency_factor is dirty**; unrelated edits no longer rewrite or validate a
  legacy currency context. Changing an existing order to a foreign currency
  without supplying a factor in the same change fails closed (no carried LCY
  parity).
- **DB default removed.** New migration
  `2026_09_13_180000_remove_unsafe_sales_order_currency_code_default.php`
  drops the unsafe `DEFAULT 'USD'` on `sales_orders.currency_code` (still NOT
  NULL). A raw insert that omits currency now fails instead of becoming USD.
- **Pricing label defect corrected.** `SalesPricingResolver` read the
  non-existent `app.default_currency` config key and fell back to a hard-coded
  `'USD'`; item-card prices are now labelled with `app.currency` (NGN).

### Phase 3B2 — monetary calculation (pending)

- Populate and calculate the Sales LCY columns (`subtotal_lcy`,
  `line_discount_total_lcy`, `invoice_discount_amount_lcy`, `total_amount_lcy`,
  `total_vat_lcy`, `grand_total_lcy`, `remaining_amount_lcy`, `unit_price_lcy`,
  `line_total_lcy`, `line_amount_lcy`, `vat_amount_lcy`, etc.) from document
  FCY amounts and the validated factor. **No LCY Sales amount calculation
  exists yet.**
- Define the canonical direction (line -> header, or header -> line) and the
  rounding/scale policy, reusing `DocumentCurrency`.

## Remaining work (Phase 3B+)

### Read/report LCY semantics

Replace these coercions with LCY-authoritative reads once 3B2 populates the
LCY columns. All are currently **report/display only** — none feeds posting,
ledger, approval amount, credit limit, balance, commission, or a persisted
profitability result — but each silently treats a NULL factor as 1 and must be
revisited first in 3B2:

- `SalesDashboardService` — `COALESCE(currency_factor, 1)` across posted-sales
  and receivables aggregations (see `remainingAmountLcyExpression`).
- `Finance/ProfitabilityReportService` — `$invoice?->currency_factor ?: 1`.
- Sales invoice infolist/table `currency_code ?? 'USD'` display fallbacks
  (unreachable for persisted rows because `currency_code` is NOT NULL, but they
  imply false foreign certainty).
- `Console/Commands/BackfillPostedSalesInvoices` and
  `BackfillCustomerInvoiceLedgerEntries` — historical tools that hard-code/fall
  back to factor 1. Historical classification/remediation is a separate,
  explicitly-approved activity.

### Pricing and provenance

- Wire the `SalesPrice` resolver/runtime pricing to resolve currency-tagged
  prices and carry price provenance (currency + source) onto sales lines.
- Decide behaviour when a line price currency differs from the document
  currency (reject vs explicit conversion), without silent FX inference.

### Quote currency ambiguity

- Sales quotes have **no currency column**. Quote line prices are populated
  from item-card/last-price values, which are LCY (NGN) by convention
  (`SalesPricingResolver`, `app.currency = NGN`); the `$` prefixes in the quote
  forms are cosmetic bugs. Phase 3B1 converts quotes to NGN orders on that
  basis.
- A future phase should add an explicit quote currency (or a documented
  quote-currency policy) so the assumption is authoritative rather than
  inferred. Do not implement quote dual-currency calculation before then.

### Shipment currency/LCY completion

- Complete shipment header/line currency and LCY propagation
  (`sales_shipment_headers`, `sales_shipment_lines`).

### Posting kernel currency awareness

- Make `PostingIntent` / `GeneralLedgerPostingKernel` currency-aware so posted
  G/L amounts carry document currency + factor consistently, without a global
  rate multiplication. Cross-module change, explicitly out of scope for the
  Sales-only phases.

### Customer ledger prospective semantics

- Define prospective (new-document) customer-ledger currency/factor semantics,
  including how LCY balances are derived. Historical ledger repair is a
  separate, explicitly-approved activity and is not part of this roadmap.

### Customer ledger applications

- Define currency rules for credit-memo-to-invoice applications
  (cross-currency applications must be rejected or explicitly converted).

### Settlement, realized and unrealized FX

- Payment/settlement FX conversion.
- Realized FX on settlement.
- Revaluation / unrealized FX.

### Cross-currency bank settlement

- Explicitly support or reject cross-currency bank settlement with a defined
  conversion path.

### Dead Sales path cleanup

- `App\Actions\Sales\ConvertQuoteToOrderAction` — unreferenced; broken (no
  currency, invalid `pending` status, non-existent `items()` relation). Remove.
- `App\Actions\Sales\CreateSalesQuoteAction` — unreferenced; writes quote items
  with unlabelled item-card prices. Remove or rebuild on the quote service.
- `PostedSalesCreditMemo::correct()` and `createFromReturn()` — unreferenced and
  unsafe (they fabricate/default currency context). Remove or rebuild through
  the credit-memo service.
- Orphaned `App\Filament\Sales\Resources\*\Schemas\*Form.php` files — never
  imported (the live Sales-panel resources import the admin
  `App\Filament\Resources\...` forms). The orphaned forms reference
  non-existent fields (`document_no`) and expose no currency inputs. Delete.
- `posted_sales_credit_memos.currency_factor` retains a `DEFAULT 1` solely
  because the dead `PostedSalesCreditMemo::correct()` relies on it; remove the
  default together with that cleanup.

### Sales blanket -> order relation gap

- `BlanketOrder::createSalesOrder()` passes `blanket_order_id`, but
  `SalesOrder` has no such fillable attribute or column, so the value is
  silently dropped and converted orders are not linked to their blanket.
  Pre-existing (not introduced by 3B1): decide whether to add the column/relation
  or remove the dead assignment.

### Duplicate draft/posting invoice authority

- `SalesInvoiceService` and `SalesOrder::postInvoice` both produce
  `PostedSalesInvoice` rows. Consolidate to a single authority for invoice
  posting to avoid divergent currency/amount semantics.

## Non-goals for phases 3B1 / 3B1-R

These phases implemented **none** of the items above beyond currency-context
safety. They performed no FCY/LCY amount calculation, no FX lookup, no
posting-kernel change, no customer-ledger change, and no historical data repair.
