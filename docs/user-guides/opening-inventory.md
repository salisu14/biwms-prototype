# Opening Inventory

Opening Inventory is used to enter approved go-live stock balances before normal warehouse, purchase, sales, and production postings begin.

## Prerequisites

- Company/business profile is configured.
- Chart of accounts, inventory posting groups, general posting setup, inventory posting setup, locations, items, and item UOM assignments are configured.
- Accounting periods are open for the posting date.
- Costing periods are not closed for the posting date.
- Users have the required permissions:
  - `inventory.opening_inventory.view_any`
  - `inventory.opening_inventory.create`
  - `inventory.opening_inventory.update`
  - `inventory.opening_inventory.post`
  - `inventory.opening_inventory.cancel`

## Create A Document

Go to **Inventory Operations > Opening Inventories** and create a new document.

Header fields identify the business, document number, posting date, source, and description. Document numbers are generated from an opening-inventory number series when available. Manual document numbers are allowed but must be unique within the selected business.

## Enter Lines

Each line requires:

- item;
- location;
- unit of measure;
- quantity;
- unit cost;
- lot or serial number where the item tracking setup requires it.

The form previews base quantity and amount, but posting recalculates these values server-side from the item UOM assignment. The service calculation is authoritative.

## Posting Workflow

Only `DRAFT` documents can be edited, posted, cancelled, or deleted.

Posting creates:

1. Item Ledger Entry;
2. Value Entry;
3. G/L posting through the Value Entry accounting orchestrator and posting kernel;
4. stock-cache recalculation;
5. audit trail.

Filament actions never create ledger records directly. They call `OpeningInventoryService`.

## Traceability

The view page shows document lines, totals, posting metadata, linked Item Ledger Entries, Value Entries, and posting transaction references.

## Corrections After Posting

Posted opening inventory documents are immutable. Do not edit or delete posted lines. Corrections must be made through a reviewed inventory adjustment or controlled repair process.

## Reconciliation

Run:

```bash
php artisan biwms:inventory-reconcile --details
```

The command is diagnostic-only. It reports opening inventory issues such as missing Item Ledger Entries, Value Entry accounting gaps, line/ledger mismatches, cross-business references, and draft documents with ledger records. It does not repair data automatically.

## Go-Live Checklist

- Confirm physical quantities and UOMs with operations.
- Confirm unit costs with finance.
- Confirm posting setup accounts.
- Post opening inventory before normal operational postings.
- Run inventory reconciliation after posting.
- Export or archive the approved opening stock worksheet outside the system if required by the implementation team.
