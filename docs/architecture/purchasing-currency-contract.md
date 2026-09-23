# Purchasing Currency Contract

**Status: Authoritative purchasing currency contract through Phase 1, Phase 1A,
Phase 2, Phase 2A and Phase 3C-C2.** The purchase invoice liability now posts
through the certified currency-aware boundary and uses accounting LCY as the
inventory valuation source (see §8). Vendor Ledger dual-currency storage
integration, FX settlement, revaluation, purchase credit memo multicurrency and
historical repair remain **deferred** (Phase 3+). Sections marked *target
contract* describe approved future semantics that are **not yet implemented**.

## 1. Currencies and rate convention

- **LCY (local currency):** NGN. Inventory valuation currency is NGN.
- **Document currency (FCY):** the currency of the purchase document
  (PO / receipt / invoice / credit memo), e.g. USD.
- **Canonical rate convention:** the factor is **LCY per 1 document-currency
  unit**.

  ```
  LCY = FCY × factor
  FCY = LCY ÷ factor
  ```

### 1.1 Rate default semantics (no universal default)

- `purchase_orders.currency_factor` and `purchase_receipts.exchange_rate` are
  **nullable database fields**. There is **no universal database default** and
  no generic NULL→1 normalization.
- **NGN/LCY documents resolve factor 1 through currency-aware domain logic**
  (`PurchaseOrder::resolvedCurrencyFactor()` / `PurchasingCurrency::factorFor()`),
  not through a stored default.
- **FCY documents require an explicit finite positive factor.** A missing
  foreign-currency factor is **unresolved** and must **fail closed** — it is
  never inferred from the document, the vendor, or a historical value.

### 1.2 Canonical helper semantics (`App\Support\PurchasingCurrency`)

- `factorFor('NGN', null)` **may resolve to `1`** (LCY context is well defined).
- `factorFor('USD', null)` **fails** (`InvalidArgumentException`): a foreign
  document without a rate is unresolved.
- `normalizeFactor(null)` **fails**. It is context-free and must **never**
  silently produce `1`.
- A factor must be **finite and strictly greater than zero**. Non-finite input
  cannot be represented (`Brick\Math\BigDecimal`) and is rejected; zero and
  negative factors are rejected.
- Helpers convert amounts only. They never decide which column a value belongs
  in: document/FCY prices remain distinct from item reference costs.

## 2. Three distinct concepts (must not be conflated)

1. **Item reference / standard cost** — maintained in **LCY/NGN** on the item
   card (`items.unit_cost`, `items.standard_cost`). Used for costing,
   comparison, expected cost, and variance analysis. It is a reference.
2. **Document / vendor price** — the negotiated commercial price in the
   **document currency** (e.g. USD 0.50/g). It is authoritative for the
   purchase document.
3. **LCY accounting / inventory value** — the document amount converted at the
   **document recognition rate** (the authorized factor). Authoritative for
   inventory valuation, GRNI, and LCY accounting.

**Item reference cost ≠ vendor negotiated price.** An item's reference cost
must not automatically become the vendor FCY purchase price. A vendor price is
derived from `Item.standard_cost` only by an explicit, intentional pricing
workflow — never by silent field copying.

## 3. Field semantics

### 3.1 PurchaseOrder

- `currency_code` — **document currency**.
- `currency_factor` — **nullable stored rate snapshot**, LCY per 1 document
  currency unit. Resolved to 1 only for NGN/LCY documents; foreign documents
  require an explicit value (else fail closed). **New.**
- Document amounts (authoritative commercial values): `total_amount`,
  `total_vat`, `grand_total`.
- LCY equivalents (derived): `total_amount_lcy`, `total_vat_lcy`,
  `grand_total_lcy`. **New** (nullable).

### 3.2 PurchaseOrderLine

- `unit_cost` — **negotiated/vendor price in document currency** (authoritative).
- `line_total` — **document-currency amount**.
- `unit_cost_lcy` — **derived LCY equivalent** (`unit_cost × factor`). **New**
  (nullable).
- `line_total_lcy` — **derived LCY equivalent** (`line_total × factor`). **New**
  (nullable).

**Invariant:** changing the exchange rate **recalculates the LCY equivalents
only**. It **never** changes the negotiated FCY price (`unit_cost`, `line_total`)
or the vendor commercial price.

### 3.3 PurchaseReceipt / PurchaseReceiptLine

- `currency_code` — document currency label.
- `exchange_rate` — LCY per 1 document-currency unit, **nullable**. It stays
  nullable and must **not** universally default to 1. A **foreign receipt rate
  must be explicit/authorized** (inherited from the source document chain); a
  missing foreign-currency rate fails closed.
- `direct_unit_cost` — **document-currency** unit cost.
- `line_amount` — **document-currency** line amount.
- `unit_cost_lcy` — **LCY equivalent**.
- `line_amount_lcy` — **LCY equivalent**. **New** (nullable).
- A missing FCY rate does **not** mean 1.

### 3.4 PurchaseInvoice / PostedPurchaseInvoice (+ lines)

- `currency_code`, `currency_factor` — document currency and rate.
- The document **and its posted snapshot** preserve **both** the
  **FCY/document values** and the **LCY equivalents**.
- `currency_factor` is **inherited/authorized from the document chain** and must
  **not be hard-coded to 1 for foreign documents**.
- Document values: `total_amount`, `total_vat`, `grand_total`,
  `remaining_amount`.
- LCY equivalents: `total_amount_lcy`, `total_vat_lcy`, `grand_total_lcy`,
  `remaining_amount_lcy`. **New** (nullable).
- Line values: `unit_cost` / `unit_cost_lcy`, `line_total` / `line_total_lcy`,
  `vat_amount` / `vat_amount_lcy`, `amount_including_vat` /
  `amount_including_vat_lcy`. `line_total_lcy` is **new** (nullable).
- **`remaining_amount_lcy` is a NON-AUTHORITATIVE synchronized document
  snapshot.** It exists for reporting convenience and must not be treated as the
  source of truth for open payable exposure. **`VendorLedgerEntry` remains
  authoritative for open payable exposure.**

### 3.5 Vendor Ledger — *approved target contract; storage/posting integration belongs to Phase 3+*

This section describes **approved target semantics**. It is **not yet
implemented** in storage or posting.

- Base `debit_amount` / `credit_amount` / `amount` / `remaining_amount` —
  **LCY carrying values**.
- `original_debit_amount` / `original_credit_amount` — **FCY (document)**
  amounts, where `FCY = LCY ÷ factor`.
- `currency_code` + `currency_factor` — document currency context.
- **`VendorLedgerEntry` remains authoritative for payable exposure.** Any
  dual-currency storage transformation is a Phase 3+ change and has **not** been
  performed.

### 3.6 G/L boundary (current, precisely)

- Current **Posting Kernel line economics are treated as LCY inputs**.
- The kernel must **not globally multiply by an exchange rate**, and it does
  **not itself transform raw FCY into LCY**. It records `currency_code` /
  `exchange_rate` for reference only; amounts are consumed as posted.
- Existing **LCY-only callers must remain safe** and unchanged in behaviour.
- Document-currency vs LCY separation for **postings** is deferred: a later,
  explicit currency-aware posting contract may carry **both** FCY and LCY
  representations. No blanket kernel conversion is planned or implemented.

## 4. Representation and derivation rules

- **FCY/document commercial values are authoritative** purchase-document values.
- **LCY equivalents may be deterministically derived** from `FCY × authorized
  factor` through controlled domain/service/model calculation. Derivation is
  permitted when it is explicit, rate-authorized, and semantically faithful.
- An **exchange-rate change may recalculate LCY values**; it must not alter the
  underlying FCY commercial values.
- **LCY item/reference cost must NEVER silently overwrite or relabel the
  negotiated FCY/vendor price.**
- Historical rows with **unknown FCY/LCY relationships remain unresolved** and
  are not reinterpreted.

**The key prohibition is silent semantic relabelling — not derivation itself.**

## 5. Historical data

- New LCY representation fields remain **nullable** where historical meaning is
  unknown. No historical foreign-currency document is **backfilled or
  reinterpreted**.
- Historical FCY documents with an **unknown or malformed rate relationship
  remain unresolved** until an explicit, controlled correction phase.
- `currency_factor` does **not** default to 1 for historical foreign documents.
- Known malformed foreign **factor-1 mirrored** records
  (`unit_cost == unit_cost_lcy`) are **not trusted** as authoritative historical
  FCY prices.

## 6. Purchase price provenance (Phase 2 / 2A)

- `purchase_prices.currency_code` — the **authoritative currency of that
  particular negotiated price**.
- **NULL** `currency_code` — **ambiguous historical provenance**. It is never
  inferred from the requesting document; such a price is **skipped** and never
  treated as the document currency.
- `Vendor.currency` is a **defaulting input for a new PO only**. It is **not
  proof of `PurchasePrice` currency**.

**Price-source priority (deliberate, not numeric):**

1. a valid negotiated `PurchasePrice` in the **matching document currency**;
2. a **trustworthy historical commercial price**;
3. a **converted LCY reference-cost suggestion**.

Raw numeric **"lowest price wins" across currencies is prohibited**, because it
would rank values that are not the same unit.

**Different known `PurchasePrice` currency:** skip, unless an **authoritative
supported conversion path** exists. No blind FX triangulation is performed.

## 7. Historical last-price rate rule

A posted historical document may be reused as a last commercial price only when
its dual-currency relationship is internally valid:

- the rate is **finite and strictly greater than zero** (a foreign rate is
  **NOT** required to be greater than 1 — rates below 1 are valid and must not
  be rejected merely for being below 1);
- the rate is not treated as the LCY rate for a foreign document; and
- `unit_cost_lcy == unit_cost × rate` within tolerance.

**Exception:** a **foreign currency + factor exactly 1 + mirrored FCY/LCY
values** is treated as **untrusted historical data** under the current BIWMS
historical trust policy, unless it is explicitly validated or corrected. The
known malformed signature (foreign currency stamped rate 1 with
`unit_cost == unit_cost_lcy`) therefore remains unresolved until an explicit
correction phase.

## 8. Posting boundary and inventory valuation source (Phase 3C-C2)

The purchase invoice caller now posts its liability through the certified
**currency-aware** posting boundary (`PostingIntentMode::CURRENCY_AWARE` via
`PostingIntent::fromArray`). This section records the load-bearing semantics.

### 8.1 Commercial FCY vs accounting LCY

- The **document/FCY** commercial amounts (`unit_cost`, `line_total`,
  `grand_total`, `vat_amount`) are unchanged and remain authoritative for the
  purchase document.
- The **G/L base `debit_amount` / `credit_amount` / `amount` columns are LCY**.
  The document-currency amount of a commercial line is carried alongside in the
  explicit trace columns `document_currency_code`, `document_debit_amount`,
  `document_credit_amount`, `document_amount`, `currency_factor`,
  `posting_line_type` (and `lcy_only_reason` for LCY-only lines).
- **Inventory valuation is LCY.** The invoice-driven valuation source is the
  line's accounting LCY value — never the commercial FCY amount. The receipt's
  expected inventory cost is likewise LCY.

### 8.2 One accounting rule

The single deterministic LCY rule at the accounting boundary is:

```
LCY = round(FCY × factor, 2, HALF_UP)
```

implemented as `PurchasingCurrency::accountingLcy()`. It is deliberately
**not** the wider-scale pipeline of `PurchasingCurrency::lcyFromFcy()`
(round at amount scale, then reduce to currency scale), which can differ by one
minor unit through double rounding. Every value that reaches the G/L or is
validated by the posting kernel uses `accountingLcy()`; prospective purchase
invoice LCY snapshots use the same rule so the document snapshot and its posting
cannot diverge.

### 8.3 Explicit rounding only

If the sum of the per-line LCY debits differs from the LCY A/P control credit by
a minor unit, the difference is posted as an **explicit `LCY_ONLY` line with
`lcy_only_reason = ROUNDING`** against the vendor posting group's **Invoice
Rounding Account**. A residual is never absorbed silently, the A/P control amount
is never adjusted, and if no rounding account is configured the posting **fails
closed**.

### 8.4 Supported factor invariant

When expected-cost inventory G/L posting is enabled, receipt recognition and
invoice recognition must use the **same rate**. A foreign receipt recognised at
one rate and an invoice recognised at another has no exchange-rate or
purchase-price variance mechanism, so it **fails closed before posting**.

### 8.5 Not yet supported

Foreign-currency **purchase credit memo** posting remains **fenced** (it fails
closed). Its legacy accounting path hard-codes factor 1 and writes its
payable/VAT legs outside the certified currency-aware boundary while the vendor
ledger is version-2 LCY. Local-currency credit memos are unchanged.
