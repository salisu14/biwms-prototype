# Sales Multi-Currency Monetary Contract

**Status: introduced in Phase 3B2-B (document economics only).** This document
defines how Sales documents preserve a commercial (document-currency) amount and
its local-currency (LCY) recognition equivalent. It does **not** change posting,
ledger, settlement, or FX-recognition semantics; those remain governed by the
Common Multi-Currency Contract.

## 1. Local currency and rate convention

- **LCY is NGN.**
- **Factor** (`currency_factor`) is **LCY units per 1 document-currency unit**:

  ```
  amount_lcy = amount_fcy x currency_factor
  ```

- For **NGN**: `currency_factor = 1` and FCY = LCY numerically.
- For a **foreign currency**: the factor must be **explicit, finite and > 0**.
  It is never inferred, never assumed to be USD, and a missing factor is never
  silently treated as 1. A missing/invalid foreign factor **fails closed**.

## 2. Commercial truth vs accounting equivalent

- **Commercial truth is the document/FCY amount.** A negotiated price (e.g.
  `USD 220`) is the commercial agreement and is authoritative for the Sales
  document.
- **The LCY value is a derived recognition equivalent only** (e.g.
  `USD 220 x 1500 = NGN 330,000`). It is a derivation, not a new commercial
  agreement.
- **Reference/list pricing remains separate.** An item-card reference price of
  `NGN 300,000` must never force a foreign document to `USD 200`, and must never
  be relabelled as a foreign amount. Price selection follows the Phase 3B2-A
  provenance rules (`SalesPricingResolver`).

## 3. Monetary representation

Each persisted monetary component may carry two representations:

```
<amount>       -> document / FCY amount (authoritative)
<amount>_lcy   -> derived LCY equivalent (nullable)
```

- All `*_lcy` columns are **nullable** and are never backfilled.
- Historical rows with no authorised currency context keep **NULL** LCY values;
  they are never reinterpreted or fabricated.
- Columns are **not** independent inputs: `*_lcy` is derived from the
  corresponding FCY value by the document's factor.

## 4. Rounding

- FCY amounts use the document's existing monetary precision.
- LCY equivalents are rounded **half-up** to the **same scale as their source
  column** (Sales Order / posted documents: 4 dp; direct Sales Invoice and
  Credit Memo: 2 dp; credit memo unit price: 5 dp).
- **Header LCY totals are the sum of the persisted, rounded line LCY values** —
  never an independent re-derivation from raw floats. A document whose lines have
  no authorised LCY value keeps NULL LCY totals rather than a fabricated zero.
- Money arithmetic uses the shared decimal helpers (`DecimalMath` /
  `DocumentCurrency`), not binary floating point.

## 5. Sales Order

- Each line stores the commercial FCY price (`unit_price`) and derives
  `unit_price_lcy`, `line_total_lcy`, `line_amount_lcy`,
  `line_discount_amount_lcy`, `vat_amount_lcy`, `amount_including_vat_lcy`.
- Header FCY totals (`subtotal`, `line_discount_total`,
  `invoice_discount_amount`, `total_amount`, `total_vat`, `grand_total`) and
  their LCY counterparts are derived from the lines.
- **Changing the factor never reprices the commercial FCY price.** It recomputes
  the LCY equivalents of persisted lines and the header LCY totals only. A
  factor change must not re-run commercial price selection.

## 6. Sales Invoice

- **Order-linked invoices** preserve the originating order's currency, factor,
  FCY price and line economics; the current `SalesPrice` is **not** re-resolved.
- **Direct invoices** use the Phase 3B2-A provenance rules, then derive LCY at
  the document factor.
- A foreign invoice with an absent/invalid factor **fails closed**; it never
  falls back to factor 1.
- Posting side effects (inventory, value entries, G/L, customer ledger) are
  unchanged by this phase.

## 7. Posted Sales Invoice snapshot

- The posted snapshot preserves document currency, the factor used, the FCY
  amounts and their LCY equivalents.
- A posted invoice is immutable with respect to those economics: a later change
  to a price, item reference or exchange rate does not alter it.
- Historical posted invoices are never backfilled; their new LCY columns remain
  NULL where unknown.

## 8. Sales Credit Memo

- **Linked memo** (from a posted invoice): the line inherits the original posted
  invoice unit economics (price, discount, VAT) and is never repriced from
  current prices/references.
- **Source posted invoice currency economics are authoritative.** For a memo
  linked to a posted Sales Invoice:

  ```
  memo currency_code   = source posted invoice currency_code
  memo currency_factor = source posted invoice currency_factor
  ```

  - When the caller states no currency/factor, the memo **inherits** the source
    posted invoice's currency and factor rather than defaulting to NGN.
  - A caller currency that **differs** from the source is **rejected**
    (`sales_credit_memo_currency_conflict`); it is never substituted.
  - A caller factor that **differs** from the source (for the same currency) is
    **rejected** (`sales_credit_memo_currency_factor_conflict`); the source
    historical factor is never silently replaced.
  - A caller value that **matches** the source is allowed, and an omitted part is
    inherited from the source.
  - **Cross-currency Credit Memo is not supported** in this phase. Historical
    commercial unit economics are never relabelled under another currency
    (e.g. a `USD 220` posted line is never persisted as `NGN 220`).
  - The same rule is re-checked as a final fail-closed guard before posting side
    effects, so a draft whose persisted context conflicts with its source cannot
    post.
- **Direct/unlinked memo**: uses the Phase 3B2-A provenance rules; a foreign memo
  with an absent/invalid factor **fails closed**.
- Sign convention is preserved: posted credit memo amounts are negative, and
  their LCY equivalents mirror the same signed document values.

## 9. Posted Sales Credit Memo snapshot

- Preserves currency, factor, FCY amounts and LCY equivalents (subtotal, total
  amount, VAT, grand total, remaining amount).
- Historical rows are not backfilled.

## 10. Tax / VAT and discounts

- Existing Sales tax/VAT business rules are preserved exactly. A monetary tax
  amount stored in document currency gets a deterministic LCY equivalent
  (`tax_lcy = tax_fcy x factor`) at the same scale.
- Percentage discounts remain percentages. Monetary discounts carry both FCY and
  LCY representations. When a discount amount is derived from a percentage, that
  effective discount is persisted to the FCY `discount_amount` column and the LCY
  equivalent is derived from that same persisted value, so the FCY and LCY
  representations always describe one discount. A factor change never changes a
  negotiated discount percentage or its FCY economics; only the LCY amount moves.

## 11. Historical / legacy handling

- No historical rewrite or backfill.
- Unrelated edits do not fabricate LCY values for a document whose currency
  context is unknown or whose foreign factor is unresolved.
- Foreign documents with insufficient currency context continue to fail closed
  where commercial/accounting progression requires valid monetary context.
- Posted historical documents are never mutated by this layer.

## 12. Explicit exclusions

This contract and Phase 3B2-B deliberately do **not** implement or change:

- `PostingIntent` / Posting Kernel / `PostingService` semantics;
- `CustomerLedgerEntry` semantics, Customer Ledger migration or backfill;
- settlement / application logic, cross-currency bank settlement;
- realized FX, unrealized FX, or revaluation;
- `PaymentService` / `BankAccountLedgerService`;
- Purchasing / Vendor Ledger;
- `ValueEntry`, inventory valuation, or COGS semantics;
- historical data rewrite/backfill, production data, or deployment.

## 13. Helper

`App\Services\Sales\SalesDocumentMonetaryCalculator` is the single deterministic
derivation point:

- `deriveLcy(currency, factor, documentAmount, scale)` → LCY string or NULL;
- `deriveComponents(currency, factor, map, scale)` → map of LCY strings/NULLs;
- `total(iterable, scale)` → sum of LCY values, or NULL when all are unresolved.

It performs no database access, no rate lookup, no pricing and no posting.
`App\Support\DocumentCurrency` remains the authoritative currency/factor
validator.
