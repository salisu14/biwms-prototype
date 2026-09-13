# BIWMS Common Multi-Currency Contract

**Status: authoritative common contract introduced in Phase 3A (shared currency
contract and schema foundation).** This document defines the invariants that
both Purchasing (AP) and Sales (AR) must obey. Phase 3A introduces the contract,
the shared deterministic helper (`App\Support\DocumentCurrency`) and additive
schema only. It does **not** change existing Purchasing behaviour, Sales
calculations, ledger semantics, G/L posting, payment behaviour, or production
data.

Scope note: `App\Support\PurchasingCurrency` remains the **certified Purchasing
wrapper for now**. Phase 3A does **not** require existing Purchasing callers to
migrate to the new helper. Both helpers must agree on the shared mathematics
(see §10).

## Status matrix — CURRENT / FOUNDATION / FUTURE TARGET

Nothing in the FUTURE TARGET column is implemented by Phase 3A. Where a target
is described later in this document, this matrix is the authority on its status.

| Area | CURRENT runtime | FOUNDATION added by Phase 3A | FUTURE TARGET (not implemented) |
|---|---|---|---|
| Local currency | NGN used throughout valuation/G/L | `DocumentCurrency::LCY_CODE` + contract | — |
| Vendor Ledger | base `debit/credit/amount/remaining` already hold **LCY**; `original_* = amount ÷ factor` | contract states the target semantics | base LCY = authoritative, `original_*` = FCY, explicitly validated on write |
| Customer Ledger | same LCY-base convention as Vendor Ledger | contract states the target semantics | base LCY = authoritative, `original_*` = FCY, explicitly validated on write |
| Posting Kernel | LCY-first; receives LCY line economics; no FX inference | contract restates this as binding | a currency-aware posting contract that receives document amount + LCY amount + currency + factor and validates (kernel `post()` unchanged) |
| Realized FX | **none** | none | computed at payment application when settlement rate ≠ recognition rate |
| Unrealized FX | **none** | none | period-end revaluation of open foreign balances, plus reversal |
| Cross-currency settlement | **blocked** by the same-currency bank guard | contract restates the guard as authoritative | a dedicated cross-currency settlement workflow |
| Sales document calculation | FCY and LCY are **not** distinguished; several paths copy/relabel values | nullable LCY columns + factor columns + `sales_prices` table (schema only) | explicit FCY/LCY derivation on sales documents, wired to the shared helper |
| Historical classification | not performed | contract defines trusted / reconstructable / ambiguous / malformed | classification + bounded, audited remediation |

## 1. Local currency

1. **LCY is NGN.** The local currency code is `NGN`, and the inventory
   valuation currency is NGN.

## 2. Document currency

2. A document currency may be **NGN (LCY)** or **FCY (foreign currency)**
   (e.g. USD, EUR, GBP).

## 3. Rate convention

3. The canonical factor is **LCY per 1 document-currency unit**:

   ```
   LCY = FCY × currency_factor
   FCY = LCY ÷ currency_factor
   ```

4. **NGN documents resolve factor = 1.** LCY context is well defined, so a
   missing factor on an NGN document may resolve to 1 through domain logic.

5. **FCY documents require an explicit finite positive factor.** A missing
   foreign-currency factor is **unresolved** and must **fail closed**.

6. **Never silently default an FCY factor to 1.** A factor of 1 is only ever
   correct for an LCY document; it must never be manufactured for a foreign
   document (in the database, model defaults, or service code).

## 4. Commercial price vs accounting conversion

7. **Commercial price and accounting conversion are separate concepts.** A
   price is a commercial agreement; a converted LCY value is an accounting
   derivation. They must never be conflated.

8. **The negotiated customer/vendor price is authoritative** for the commercial
   transaction.

9. **An item/reference/list price must never silently overwrite a negotiated
   document price.** Reference cost/price is LCY context; it must not be copied
   into an FCY document as if it were the negotiated price.

## 5. Document monetary representation

10. **Document monetary fields may carry two representations:**
    - the **document / FCY amount** (authoritative commercial value), and
    - the **LCY accounting equivalent** (derived at the authorized factor).

    Where both are stored, the FCY/value columns and the `*_lcy` columns must
    remain distinct and independently addressable. New LCY fields are nullable
    until a document explicitly derives them; a foreign document must never be
    populated with copied FCY values under an LCY label.

## 6. Valuation and reporting

11. **Inventory valuation and COGS remain LCY.** Item cost layers drive
    valuation; sales/purchase document currency must not influence inventory
    value.

12. **G/L financial reporting remains LCY.** Ledger reporting and the trial
    balance read LCY amounts.

13. **The existing Posting Kernel remains LCY-first.** The kernel treats its
    line economics as LCY inputs. It must not globally multiply by an exchange
    rate and must not silently infer conversion. Existing LCY-only callers must
    remain behaviour-unchanged.

14. **Currency-aware posting, when later introduced, must receive explicit
    document amount + LCY amount + currency + factor and validate, rather than
    silently infer.** A future currency-aware contract must fail closed on
    mismatch; it must not derive one representation from the other without an
    authorized factor.

## 7. Ledger targets (prospective)

15. **Future Vendor Ledger target:** base `debit/credit/amount/remaining` =
    **LCY**; `original/document` amounts = **FCY**.

16. **Future Customer Ledger target:** base `debit/credit/amount/remaining` =
    **LCY**; `original/document` amounts = **FCY**.

17. **These ledger semantics are PROSPECTIVE TARGETS ONLY in Phase 3A.** No
    historical row is reinterpreted, converted, or rewritten. Current storage
    behaviour (including the existing `original_* = amount ÷ factor`
    convention) is unchanged by this phase.

## 8. Historical classification

18. **Historical rows must later be classified as one of:**
    - **trusted** — the FCY/LCY relationship is internally valid;
    - **reconstructable** — the relationship can be deterministically recovered
      from an authorized source;
    - **ambiguous** — the relationship is unknown and cannot be safely inferred;
    - **malformed** — the relationship is known to be invalid (e.g. a foreign
      document stamped factor 1 with mirrored FCY/LCY values).

    Until classified, historical rows remain unresolved and are not
    reinterpreted.

## 9. Bank settlement and FX

19. **Cross-currency bank settlement remains unsupported until a dedicated
    workflow exists.** You may not settle an FCY balance through an LCY bank
    account (or vice versa) as a shortcut.

20. **The current same-currency bank guard remains authoritative.** Payment
    currency must equal the selected bank account currency; the guard fails
    closed before any ledger, G/L, or audit write.

21. **Recognition rate and settlement rate are distinct concepts.** The
    recognition rate is captured when the document is recognized (e.g. invoice
    posting); the settlement rate is the rate at which a payment settles the
    document. Both are needed to compute FX differences.

22. **Realized FX belongs to settlement/application.** It arises when a payment
    is applied to a document and the settlement rate differs from the
    recognition rate.

23. **Unrealized FX belongs to period-end revaluation of open foreign
    balances.** It is not a settlement event and requires a dedicated
    revaluation workflow (and its reversal).

## 10. Shared helper and Purchasing compatibility

Phase 3A introduces `App\Support\DocumentCurrency` as the shared, deterministic,
database-free currency mathematics and validation helper. It is the future
common foundation for both AP and AR.

- `App\Support\PurchasingCurrency` **remains the certified Purchasing wrapper**
  and is not modified or replaced in this phase.
- `DocumentCurrency` and `PurchasingCurrency` must **agree** on: NGN factor
  resolution, valid FCY factor resolution, missing FCY factor rejection, zero
  factor rejection, negative factor rejection, FCY → LCY conversion, and
  LCY → FCY conversion. This agreement is proven by characterization tests.
- `DocumentCurrency` performs **no exchange-rate lookup and no database access**.
  Rate selection/authorization remains a domain concern.
- One **intentional difference**: for a *missing currency code*,
  `PurchasingCurrency` legacy-infers LCY, whereas `DocumentCurrency` **fails
  closed** (`factorFor`/`isLcyFactor` reject a missing code rather than guessing
  local currency). The two agree on every valid input; this divergence is a
  hardening, not a regression, and Purchasing is not changed.
- `isLocalCurrency()` is a **format-level predicate, not a currency-existence
  oracle**: it returns `false` for a missing code, but it also returns `false`
  for a well-formed yet unregistered code. Callers must resolve existence
  through the currency domain and must never use `! isLocalCurrency(...)` as
  proof that a currency is a valid foreign currency.

## 11. Phase 3A boundaries

This contract is established in Phase 3A, which:

- adds the shared helper and the common contract (this document);
- adds **additive, nullable** Sales LCY/factor schema foundation;
- adds a Sales price provenance structure (so a negotiated FCY price can be
  represented without being overwritten by an LCY reference price);
- removes unsafe database factor defaults only where every creation path can
  safely supply or resolve the value without behaviour change.

Phase 3A does **not**: implement Sales price conversion, change
VendorLedgerEntry/CustomerLedgerEntry semantics, change the Posting Kernel or
PostingService, change PaymentService, implement realized/unrealized FX, repair
historical data, or deploy.
