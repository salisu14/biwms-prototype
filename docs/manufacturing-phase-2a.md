# Manufacturing Phase 2A Implementation Log

## Phase 2A.1: Schema, Enums, Models, and Backward-Compatible Foundation

Implemented:

- Added `SEMI_FINISHED` to the item type enum.
- Added hierarchy provenance fields to production orders.
- Added hierarchy traceability fields to production order components.
- Added production hierarchy header and node tables.
- Added production order supply link table.
- Added production material reservation table.
- Added enums, models, relationships, factories, policies, and permissions for the new foundation records.
- Added tests for schema, relationships, immutability guards, authorization, and architecture boundaries.

Scope intentionally not implemented in this checkpoint:

- BOM explosion behavior.
- Child production order generation.
- Stock availability or material reservation runtime.
- Parent consumption from child output.
- Cost propagation, WIP, settlement, Value Entry, Item Ledger Entry, Capacity Ledger Entry, or G/L posting behavior.
- Filament resources and user workflow.

The ownership boundary remains:

- Inventory quantity is owned by Item Ledger Entries.
- Item application is owned by Item Application Entries.
- Capacity is owned by Capacity Ledger Entries.
- Inventory value is owned by Value Entries.
- Inventory-related G/L posting is orchestrated from Value Entries.
- Accounting posting remains owned by the posting kernel.
