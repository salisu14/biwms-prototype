# Manufacturing Phase 2A Architecture: Semi-Finished Items and Multi-Level Production

## 1. Executive Summary

Manufacturing Phase 2A should extend the accepted Phase 1 architecture without replacing it. Semi-finished goods should remain ordinary inventory `Item` records. Parent and child production orders should be linked explicitly through hierarchy, supply-link, and reservation records, but actual quantity and value must continue to flow through the existing ledger architecture:

```text
Inventory quantity -> ItemLedgerEntry
Item application -> ItemApplicationEntry
Capacity -> CapacityLedgerEntry
Inventory value -> ValueEntry
Inventory G/L -> ValueEntryAccountingOrchestrator
Accounting -> GeneralLedgerPostingKernel through GeneralLedgerService
```

Phase 2A should not directly roll child WIP into parent WIP. A child order produces a semi-finished inventory item. The parent order consumes that item later through normal item ledger, item application, value entry, and G/L posting.

Recommended implementation style: a hybrid hierarchy model. BIWMS should snapshot the multi-level BOM hierarchy before release, but create child production orders only through an explicit controlled action. Existing single-level production order behavior remains the default and must remain backward compatible.

No Phase 2A runtime behavior is implemented by this document.

## 2. Current-State Architecture

### 2.1 Production Order Relationships

Current `ProductionOrder` owns:

- output item through `item_id`;
- order line snapshot through `lines()`;
- component snapshot through `components()`;
- routing snapshot through `routingLines()`;
- selected BOM and routing through `production_bom_id`, `production_bom_version_id`, `routing_id`, and `routing_version_id`;
- production ledger trace through `itemLedgerEntries()`, `capacityLedgerEntries()`, `outputCostAllocations()`, `expectedCostSnapshots()`, `varianceCalculations()`, and `glEntries()`.

The current production order is one output item per order. Output quantity is compared against `quantity_base` and `remaining_quantity`.

### 2.2 BOM Explosion Behavior

`ProductionOrderService::refreshComponents()` rebuilds `production_order_components` from the selected BOM or BOM version. `ProductionBomLine` and `ProductionBomVersionLine` already support:

- `type = ITEM`;
- `type = PRODUCTION_BOM`;
- `production_bom_id_related`;
- `quantity_per`;
- `scrap_percent`;
- `routing_link_code`;
- `location_code`;
- `bin_code`;
- effective active versions.

`ProductionOrderService::explodeBomLines()` already performs recursive explosion with:

- maximum depth of 25;
- visited BOM guard;
- circular BOM exception;
- UOM conversion to item base quantity;
- scrap multiplication;
- `bom_level`, `bom_path`, and `source_bom_code` on component snapshots.

Important limitation: current recursion flattens child BOM components into the parent order. It does not preserve semi-finished intermediate inventory or child order ownership.

### 2.3 Output Assumptions

Current output posting assumes the production order output item is the order `item_id`. `postOutput()` creates an `ItemLedgerEntry` with `entry_type = OUTPUT`, then allocates accumulated production cost through `ProductionOutputCostService`, creates/updates a `ValueEntry`, and posts inventory G/L via `ValueEntryAccountingOrchestrator`.

### 2.4 Component Reservation Behavior

There is no authoritative reservation table for production components. Existing fields include:

- `production_orders.reserved_from_stock`;
- `production_order_components.reserved_quantity`;
- warehouse request/pick interactions.

These fields are useful as cached or workflow state but are not sufficient as reservation evidence for multi-level manufacturing.

### 2.5 Costing and Settlement Flow

Current manufacturing costing is order-specific:

- expected material/capacity/overhead snapshots are created by `ExpectedManufacturingCostService`;
- consumption creates negative `ItemLedgerEntry` and outbound `ItemApplicationEntry`;
- capacity creates `CapacityLedgerEntry` and capacity/overhead `ValueEntry` records;
- output creates positive `ItemLedgerEntry`, output `ValueEntry`, and output cost allocations;
- value entries own inventory G/L through `ValueEntryAccountingOrchestrator`;
- `ProductionOrderCostSettlementService` settles one order independently;
- variance is recorded through `ProductionVarianceCalculationService` and variance value entries.

This order-level WIP ownership must remain authoritative.

### 2.6 Completion Rules

Operational finish currently requires released status, output posting, remaining capacity posting, remaining manual consumption checks, and cost settlement readiness. `ProductionCompletionReadinessService` currently checks shop-floor operation/journal/quality readiness for one order.

### 2.7 Item-Type and Replenishment Fields

Existing reusable item fields:

- `item_type`: `RAW_MATERIAL`, `FINISHED_GOOD`, `PACKAGING`, `SPARE_PART`, `SERVICE`;
- `production_bom_id`;
- `routing_id`;
- `inventory_method`;
- `costing_method`;
- `unit_cost`, `standard_cost`, `last_direct_cost`;
- `inventory_posting_group_id`;
- `location_id`;
- `base_uom_id`;
- item UOM assignments and conversion factors;
- item tracking fields.

There is no explicit semi-finished classification yet.

### 2.8 Existing Fields That Can Support Phase 2A

Reusable foundations:

- `ProductionBomLine::TYPE_PRODUCTION_BOM`;
- `ProductionBomVersionLine::production_bom_id_related`;
- `ProductionOrderComponent.bom_level`;
- `ProductionOrderComponent.bom_path`;
- `ProductionOrderComponent.source_bom_code`;
- `ProductionOrder.production_bom_version_id`;
- `ProductionOrder.routing_version_id`;
- `Item.production_bom_id`;
- `Item.routing_id`;
- `ItemLedgerEntry.remaining_quantity`;
- `ItemApplicationEntry`;
- `ValueEntry.cost_component`;
- `ProductionOutputCostAllocation`;
- `ProductionExpectedCostSnapshot`;
- `ProductionVarianceCalculation`.

### 2.9 One-Level Assumptions

Current constraints and assumptions:

- one production order output item;
- parent BOM refresh flattens all sub-BOM materials into one component list;
- no child production order generation;
- no explicit supply link between parent component and child output;
- no manufacturing reservation ledger;
- parent completion cannot reason about child orders;
- costing settlement is per order only, with no hierarchy summary;
- reports assume `production_order_no` is the atomic manufacturing document.

### 2.10 Existing Tests That Must Stay Green

Phase 2A must preserve:

- `tests/Feature/Manufacturing/*`;
- `tests/Feature/ManufacturingReportsTest.php`;
- `tests/Feature/Inventory/*`;
- `tests/Feature/Accounting/*`;
- `tests/Feature/Authorization/PurchaseOrderCustomerProductionFixTest.php`;
- reconciliation commands: `biwms:inventory-reconcile`, `biwms:manufacturing-cost-reconcile`, `biwms:shop-floor-reconcile`.

## 3. Phase 2A Scope

Phase 2A introduces architecture for:

- semi-finished items;
- multi-level BOM semantics;
- BOM hierarchy snapshots;
- parent and child production order hierarchy;
- explicit parent component to child output supply links;
- production material reservations;
- intermediate inventory flow;
- level-by-level costing and WIP;
- late cost propagation across item application;
- hierarchy readiness and reconciliation;
- backward-compatible single-level manufacturing.

## 4. Explicit Exclusions

Phase 2A must not implement:

- routing-to-routing dependencies;
- operation dependency graphs;
- inter-routing wait conditions;
- batch genealogy;
- lot genealogy reports;
- quality hold/release between child and parent orders;
- advanced planning and scheduling;
- finite capacity scheduling;
- campaign manufacturing;
- alternate BOMs or routings;
- family production;
- co-products or by-products;
- process formulas;
- automated MRP;
- Manufacturing Phase 2B or 2C.

## 5. Semi-Finished Item Definition

### ADR-2A-001: Semi-Finished Items Remain Normal Inventory Items

Context: Semi-finished goods must be produced, stored, valued, transferred, counted, reserved, consumed, and cost-adjusted like all inventory.

Decision: Do not create a `SemiFinishedItem` master table. Add or reuse item-level classification to identify manufacturing role.

Alternatives considered:

- new `SemiFinishedItem` table: rejected because it duplicates item inventory, UOM, valuation, tracking, and posting setup;
- label-only classification: insufficient for validation and planning;
- infer from BOM/routing only: useful but not explicit enough for UX and permissions.

Consequences:

- semi-finished items use normal `items`, `item_ledger_entries`, `value_entries`, and `item_application_entries`;
- accounting is driven by posting groups and value entries, not by labels;
- item reports can include semi-finished stock without special joins.

Risks:

- existing `ItemType` lacks `SEMI_FINISHED`;
- users may misclassify items.

Mitigation:

- Phase 2A.1 should add either `SEMI_FINISHED` to `ItemType` or add a distinct `manufacturing_policy` field. Preferred: add `ItemType::SEMI_FINISHED` plus optional `manufacturing_policy` only if future planning needs it.

Recommended classifications:

- Purchased Raw Material: purchased and consumed;
- Manufactured Semi-Finished Item: produced, stored, consumed by another order;
- Manufactured Finished Good: produced and sold;
- Packaging Material: purchased and consumed;
- Consumable: tracked or non-tracked production support material;
- Service: non-inventory.

## 6. Multi-Level BOM Semantics

A BOM line may reference:

- a purchased/raw inventory item;
- a semi-finished item with its own certified BOM/routing;
- a related production BOM through existing `production_bom_id_related`.

Recommended rules:

- maximum default depth: 10 for UI and operations, hard technical guard: 25 to match existing service;
- cycle detection at certification and at explosion time;
- duplicate nodes allowed only when they represent distinct component requirements; aggregate display is allowed, but source lines remain separate in snapshots;
- UOM conversion must resolve component quantity into component base UOM using `Item::getConversionFactorForUomDecimal()`;
- scrap applies at each BOM level before child requirement generation;
- fixed quantities and reference quantities require explicit line-basis fields before implementation;
- active certified BOM version is selected by effective date and snapshotted;
- inactive/closed child BOM blocks hierarchy release;
- missing BOM/routing for a manufactured child item becomes a critical readiness finding;
- recursion must use bounded depth and visited path guard.

The existing BOM version `quantity_per` already hints at reference-basis behavior, but Phase 2A should make basis explicit:

- `reference_quantity`;
- `reference_uom`;
- `reference_quantity_base`;
- line basis enum: `PER_UNIT`, `PER_REFERENCE_QUANTITY`, `FIXED_PER_BATCH`, `MANUAL_ACTUAL`.

## 7. BOM Explosion Algorithm

Recommended approach: hybrid snapshot.

### ADR-2A-005: BOM Hierarchy Is Snapshotted Before Release

Context: Production orders must not depend on mutable current BOM lines after release.

Decision: Add a hierarchy planning/snapshot layer. Explosion creates immutable-ish planned records that preserve BOM, version, quantities, UOMs, path, and source lines. Child production orders are generated explicitly from those snapshots.

Alternatives:

- snapshot all levels and child orders at parent creation: too aggressive and noisy;
- progressive explosion only: weaker traceability and high risk of mutable BOM drift;
- existing flattened explosion only: cannot preserve semi-finished inventory.

Consequences:

- parent orders can remain single-level by default;
- hierarchy mode gains traceability before release;
- unreleased hierarchy can be refreshed with controlled supersession;
- released children make the hierarchy non-refreshable except through replan/cancel actions.

Algorithm:

1. Lock root production order.
2. Resolve root BOM/routing versions by effective date.
3. Walk BOM lines depth-first with max depth and visited item/BOM path.
4. For each line, calculate gross, scrap-adjusted, and base quantities.
5. Classify line as purchased/raw, semi-finished child demand, packaging, service, or exception.
6. Snapshot the hierarchy node, BOM line, version IDs, UOM, location, quantity basis, and costing assumptions.
7. For semi-finished manufactured item, create a planned supply requirement, not component flattening.
8. Preserve flattened Phase 1 behavior unless hierarchy mode is requested.

## 8. Production Hierarchy Model

### ADR-2A-006: Single-Level Manufacturing Remains Backward Compatible

Decision: Existing orders are standalone root orders. Do not require hierarchy records for every single-level order.

Recommended fields on `production_orders`:

- `parent_production_order_id` nullable;
- `root_production_order_id` nullable initially, backfilled to own ID;
- `production_level` default 0;
- `hierarchy_path` nullable;
- `order_origin` enum: standalone, generated_child, manual_linked_child;
- `source_production_order_component_id` nullable;
- `planning_group_id` nullable.

Recommended separate tables:

- `production_hierarchies`;
- `production_hierarchy_nodes`;
- `production_order_supply_links`;
- `production_material_reservations`.

Use both direct order fields and link tables: direct fields support fast filtering/tree display; link tables remain authoritative for supply and planning.

Phase 2A boundary: one generated child order supplies one specific parent component requirement. Existing inventory may satisfy part of the requirement; child production supplies only the shortage.

Trade-off: this avoids consolidation complexity and duplicate allocation risk. Manual consolidation can be a later phase.

## 9. Supply-Link Model

### ADR-2A-004: Parent-Child Supply Is Represented Explicitly

Decision: Add `ProductionOrderSupplyLink` as the authoritative record linking parent component demand to child output or planned supply.

Purpose: represent planned and actual supply state independently from production order statuses.

Fields:

- `id`;
- `business_id`;
- `root_production_order_id`;
- `parent_production_order_id`;
- `parent_component_id`;
- `child_production_order_id` nullable;
- `child_output_item_id`;
- `required_quantity_base`;
- `reserved_from_inventory_quantity_base`;
- `planned_child_quantity_base`;
- `produced_quantity_base`;
- `supplied_quantity_base`;
- `remaining_quantity_base`;
- `unit_of_measure_code`;
- `status`;
- `source_bom_level`;
- `source_hierarchy_node_id`;
- `idempotency_key`;
- `created_by`, `updated_by`;
- timestamps.

Statuses:

- `planned`;
- `child_order_created`;
- `partially_produced`;
- `available`;
- `partially_supplied`;
- `supplied`;
- `cancelled`;
- `exception`.

Mutability: planned quantities may change before release through controlled service actions. Produced/supplied quantities are derived from child output and parent consumption ledgers, or updated only by services that reference ledger entries.

## 10. Reservation Model

Add `ProductionMaterialReservation` as a bounded manufacturing reservation ledger.

Fields:

- `id`;
- `business_id`;
- `production_order_id`;
- `production_order_component_id`;
- `supply_link_id` nullable;
- `item_id`;
- `location_id` nullable;
- `item_ledger_entry_id` nullable;
- `child_production_order_id` nullable;
- `reservation_type`: existing_inventory, child_output, manual_supply;
- `quantity_base`;
- `remaining_quantity_base`;
- `status`: active, partially_consumed, consumed, released, cancelled, expired;
- `reserved_at`;
- `released_at`;
- `idempotency_key`;
- `metadata`;
- timestamps.

Reservations do not change inventory quantity. Actual consumption remains an `ItemLedgerEntry` and `ItemApplicationEntry`.

Reservation lifecycle:

1. Created by stock reservation or child output reservation service.
2. Reduced when parent consumption posts against it.
3. Released when parent demand is cancelled or replanned.
4. Cancelled only if no posted consumption depends on it.
5. Reconciled against item ledger remaining quantity and supply links.

## 11. Child-Order Generation Rules

Actions:

- Explode Hierarchy;
- Generate Child Orders;
- Regenerate Unreleased Children;
- Release Hierarchy;
- Cancel Child Requirement;
- Link Existing Child Order.

Generation is allowed only when:

- parent/root order is not finished/cancelled;
- hierarchy snapshot is current;
- child BOM and routing are certified and effective;
- no active duplicate supply link exists;
- user has backend permission;
- business scope matches.

Idempotency identity:

```text
root_order_id|parent_component_id|hierarchy_node_id|planning_version|child_item_id|shortage_quantity_base
```

Repeated generation returns the existing child order/link.

Quantity updates:

- before child release: regenerate or supersede unreleased child;
- after child release: require controlled replan, cancellation, or additional child requirement;
- after child output: no destructive rewrite; use adjustment/replacement supply.

## 12. Existing-Inventory Supply Rules

Availability calculation should be bounded, deterministic, and not a full MRP engine.

Available quantity:

```text
open inbound item ledger remaining quantity
- active reservations
filtered by item, location, lot/serial when applicable, business where available
```

Phase 2A should ignore future MRP supply, safety stock, and open transfers unless explicitly selected. Existing inventory reservation should be a separate action or controlled option during child generation, not an invisible side effect during simple order creation.

Example:

```text
Required Extract: 100 L
Available unreserved Extract: 40 L
Reserve existing inventory: 40 L
Generate child order: 60 L
```

## 13. Intermediate Inventory Flow

### ADR-2A-003: Child Output Enters Inventory Before Parent Consumption

Decision: Child production output creates normal item ledger and value entries for the semi-finished item. Parent consumption consumes that inventory later.

Flow:

```text
Child Production Order
-> Output ItemLedgerEntry
-> Output ValueEntry
-> Intermediate inventory
-> Reservation/Application to parent component
-> Parent Consumption ItemLedgerEntry
-> ItemApplicationEntry from child output to parent consumption
-> Parent consumption ValueEntry
```

Do not bypass inventory by transferring child WIP directly to parent WIP.

Location behavior:

- if child output and parent consumption locations match, reserve/apply directly;
- if locations differ, require an inventory transfer or explicit cross-location override;
- Phase 2A should not silently consume from another location.

## 14. Posting Flow

Posting ownership remains:

- source production services create `ItemLedgerEntry` and `CapacityLedgerEntry`;
- `ValueEntryService` creates/updates value entries;
- `ItemApplicationService` applies outbound consumption to inbound layers;
- `ValueEntryAccountingOrchestrator` posts inventory/WIP/capacity G/L;
- `GeneralLedgerService` and posting kernel post balanced G/L transactions;
- source services may post non-inventory business G/L only when not owned by value entries.

G/L amounts that must originate from Value Entries:

- inventory increases/decreases;
- WIP material;
- WIP capacity;
- WIP overhead;
- output inventory;
- manufacturing variances;
- inventory cost adjustments.

Source-module postings that remain outside value entry ownership:

- payroll;
- fixed asset depreciation;
- maintenance cost if not capitalized/inventory-valued;
- actual overhead source accruals before manufacturing absorption;
- general journal entries;
- bank/vendor/customer ledgers.

## 15. Costing Flow

### ADR-2A-002: Each Production Order Owns Its Own WIP and Settlement

Decision: Every parent and child order settles independently. A root summary may aggregate but does not replace order-level ownership.

Child order:

```text
raw material + capacity + overhead + variance -> semi-finished output value
```

Parent order:

```text
semi-finished consumption value + parent material + parent capacity + parent overhead + variance -> finished output value
```

Costing behavior:

- FIFO/LIFO/Specific: parent consumption cost comes from applied child output or selected inbound layer;
- Average: parent consumption uses average layer cost from available inbound layers;
- Standard: output is valued at standard; variances are recorded by level;
- partial child output can be reserved and consumed partially;
- parent consumption before final child cost may carry provisional actual/expected cost and later append adjustments;
- late costs propagate through item application and value entry adjustments, not through direct mutation.

## 16. Late Cost Propagation

### ADR-2A-007: Late Cost Propagates Through Item Application and Value Entry Adjustment

Required direction:

```text
Child Value Entry adjustment
-> ItemApplicationEntry
-> Parent consumption Value Entry adjustment
-> Parent output cost adjustment
-> downstream adjustment
```

Ownership:

- inventory cost adjustment service owns inbound layer corrections;
- item application identifies affected outbound consumption;
- production hierarchy cost propagation service schedules parent order adjustment;
- production cost settlement service marks affected settled parents as adjustment-required;
- value entries are append-only for adjustments.

Guardrails:

- maximum traversal depth;
- idempotency key per source adjustment and target value entry;
- no mutation of historical value entries;
- closed costing periods create current-period adjustment entries if permitted by accounting policy;
- reconciliation reports pending propagation.

## 17. WIP by Level

Each production order tracks its own:

- expected cost;
- actual material cost;
- capacity cost;
- overhead cost;
- output value;
- variance;
- settlement status;
- open WIP balance.

Root hierarchy WIP summary:

```text
sum(open WIP by linked production order)
```

The hierarchy summary is a report/read model, not the accounting source of truth.

## 18. Completion Readiness

Readiness categories:

- Operational completion readiness: child output available, reservations supplied, parent consumption posted, shop-floor complete.
- Cost settlement readiness: expected/actual cost entries complete, output allocated, variance handled.
- Hierarchy completion readiness: all linked child requirements supplied or cancelled with replacement.

Parent finish should be blocked when:

- required child order is incomplete;
- required child output not posted;
- required quantity unavailable;
- required reservation missing;
- child order cancelled without replacement supply;
- parent consumption missing;
- hierarchy reconciliation has critical findings.

Final cost adjustment should not unnecessarily block operational finishing unless existing costing-period or settlement rules require it.

## 19. Status Model

Recommended enums:

- `ProductionOrderOrigin`: standalone, generated_child, manual_linked_child;
- `ProductionHierarchyStatus`: draft, planned, exploded, children_generated, partially_released, released, in_progress, partially_completed, completed, exception, cancelled;
- `ProductionSupplyLinkStatus`: planned, child_order_created, partially_produced, available, partially_supplied, supplied, cancelled, exception;
- `ProductionSupplyType`: existing_inventory, generated_child_order, manual_child_order, manual_supply;
- `ProductionReservationStatus`: active, partially_consumed, consumed, released, cancelled, expired;
- `ProductionReservationType`: existing_inventory, child_output, manual_supply;
- `ProductionHierarchyReadinessClassification`: ready, warning, blocked, critical;
- `ProductionBomExplosionStatus`: draft, current, superseded, released, cancelled.

Store workflow states that drive actions. Derive summary readiness whenever possible.

## 20. Quantity Model

Supply relationships must distinguish:

- `gross_required_quantity_base`;
- `scrap_adjusted_quantity_base`;
- `inventory_available_quantity_base`;
- `reserved_inventory_quantity_base`;
- `child_planned_quantity_base`;
- `child_output_quantity_base`;
- `child_available_quantity_base`;
- `supplied_quantity_base`;
- `consumed_quantity_base`;
- `remaining_quantity_base`.

Avoid ambiguous `quantity` fields in new tables.

Rounding:

- use `DecimalMath`, `DecimalPrecision`, and `DecimalRounding`;
- convert BOM UOM to item base UOM before reservation or child generation;
- apply scrap at each level;
- fixed quantities should not be multiplied by parent quantity;
- reference quantities should divide by `reference_quantity_base`;
- child output rounding should follow item UOM precision and production batch policy.

## 21. Location Behavior

Recommended precedence for generated child order location:

1. explicit user-selected child output location;
2. parent BOM line `location_code`;
3. item default manufacturing location;
4. work center location;
5. parent order `location_code`.

Parent consumption location:

1. parent component `location_code`;
2. parent order `location_code`;
3. item default location.

If child output location differs from parent consumption location, require transfer or explicit controlled cross-location supply action.

## 22. Lot and Serial Boundary

Phase 2A minimum behavior:

- child output lot/serial remains on `ItemLedgerEntry`;
- parent consumption applies to actual child output entries;
- item tracking validation remains existing inventory responsibility;
- supply links may reference child order and child output item ledger entry;
- genealogy report remains Phase 2B.

Do not create a separate lot ledger.

## 23. Cancellation and Replanning

Rules:

- parent cancelled before child release: cancel/supersede planned child requirements and release reservations;
- parent cancelled after child release: child remains real; cancel only if unposted and allowed;
- child partially produced then cancelled: produced inventory remains; open shortage becomes exception/replacement demand;
- parent quantity reduced: release excess reservations; cancel unreleased child shortage where possible;
- parent quantity increased: create additional requirement/link;
- child overproduced: extra output remains inventory unless reserved manually;
- linked inventory reservation released only through reservation service;
- posted production corrected through reversal or adjustment, never deletion.

## 24. Concurrency and Locking

Recommended lock order:

```text
Root Production Order
-> Parent Production Order
-> Parent Component
-> Supply Link
-> Child Production Order
-> Reservation
-> Item Ledger candidates
```

Use deterministic row locks and unique constraints:

- hierarchy root and planning version unique;
- active supply link uniqueness by parent component and child/order/source;
- reservation idempotency unique;
- child order idempotency unique;
- no cross-business links.

Avoid acquiring item ledger candidate locks before parent component locks.

## 25. Authorization

Follow standard permission names:

- `manufacturing.production_hierarchy.view`;
- `manufacturing.production_hierarchy.explode`;
- `manufacturing.production_hierarchy.generate_children`;
- `manufacturing.production_hierarchy.release`;
- `manufacturing.production_hierarchy.replan`;
- `manufacturing.production_hierarchy.cancel`;
- `manufacturing.production_supply_link.view`;
- `manufacturing.production_supply_link.create`;
- `manufacturing.production_supply_link.update_planned`;
- `manufacturing.production_supply_link.cancel`;
- `manufacturing.production_material_reservation.view`;
- `manufacturing.production_material_reservation.create`;
- `manufacturing.production_material_reservation.release`.

Roles:

- planner: explode, generate planned children, replan unreleased;
- production manager: release hierarchy, approve replans/cancellations;
- store officer: reserve/release inventory supply;
- costing officer: view cost propagation/readiness;
- shop-floor operator: no hierarchy mutation, view assigned child order operations;
- auditor: read-only hierarchy, supply, reservations, ledger trace.

Backend policies and services must enforce authorization; UI visibility is not enough.

## 26. Filament UX

Production Order page tabs:

- Order Summary;
- Hierarchy;
- Components;
- Child Orders;
- Supply Status;
- Reservations;
- Routing;
- Shop Floor;
- Costing;
- Readiness;
- Ledger Trace.

Hierarchy tree example:

```text
Mai Sasanci Carton
├── Shrink Pack
│   ├── Filled Bottle
│   │   └── Mixed Syrup
│   │       └── Extract
│   ├── Shrink Sleeve
│   └── Paper Tray
└── Carton Packaging
```

Actions by state:

- draft/planned: Explode BOM, Refresh Unreleased Plan;
- exploded: Generate Child Orders, Reserve Existing Stock;
- children generated: Release Hierarchy, Link Existing Child Order;
- released/in progress: View Readiness, Reserve Child Output, Cancel Planned Supply where safe;
- exception: Replan, Link Replacement Supply;
- completed/cancelled: read-only trace.

All actions delegate to services.

## 27. Proposed Services

### `ProductionBomExplosionService`

Responsibilities: resolve and snapshot BOM hierarchy. Owns cycle detection, depth guard, UOM conversion, line basis, effective versions.

Transaction: locks root order and relevant BOM/version rows for snapshot consistency.

Must not: create child production orders or post ledgers.

### `ProductionHierarchyPlanningService`

Responsibilities: create/update `production_hierarchies`, planning version, nodes, and planned supply requirements.

Transaction: root order lock, hierarchy lock, node upsert.

Must not: consume inventory or post output.

### `ProductionChildOrderGenerationService`

Responsibilities: generate idempotent child orders from supply links/shortages.

Transaction: root, parent, component, supply link, child order.

Must not: release orders or reserve inventory implicitly unless explicitly requested.

### `ProductionSupplyLinkService`

Responsibilities: create, update planned, cancel, and recalculate supply links.

Must not: infer supply solely from order status.

### `ProductionMaterialReservationService`

Responsibilities: reserve existing inventory and child output, release reservations, reduce reservations on consumption.

Must not: alter inventory quantity.

### `ProductionHierarchyReleaseService`

Responsibilities: validate hierarchy readiness and release eligible parent/child orders in deterministic order.

### `ProductionHierarchyReadinessService`

Responsibilities: produce operational, costing, and hierarchy readiness findings.

### `ProductionHierarchyCancellationService`

Responsibilities: controlled cancellation/supersession of planned hierarchy supply.

### `ProductionHierarchyCostPropagationService`

Responsibilities: trace late cost adjustments through item application to affected parent consumption/output and settlement status.

Must not: mutate historical value entries.

## 28. Proposed Schema

### Fields to Add to `production_orders`

Purpose: fast hierarchy filtering and child order identity.

Fields:

- `parent_production_order_id` nullable FK;
- `root_production_order_id` nullable FK;
- `production_level` unsigned small integer default 0;
- `hierarchy_path` nullable text;
- `order_origin` string/enum default `standalone`;
- `source_production_order_component_id` nullable FK;
- `planning_group_id` nullable UUID/string;
- `hierarchy_planning_version` nullable integer.

Indexes:

- `(root_production_order_id, production_level)`;
- `parent_production_order_id`;
- `source_production_order_component_id`;
- `(planning_group_id)`.

Deletion: restrict if posted/ledgered; null only where existing production order deletion rules allow.

### `production_hierarchies`

Purpose: root planning header.

Fields:

- `id`, `business_id`, `root_production_order_id`, `status`, `planning_version`, `exploded_at`, `released_at`, `cancelled_at`, `created_by`, `metadata`, timestamps.

Unique:

- `(root_production_order_id, planning_version)`.

Mutability: mutable only before release; later changes create new version/supersession.

### `production_hierarchy_nodes`

Purpose: BOM explosion snapshot tree.

Fields:

- `id`, `business_id`, `production_hierarchy_id`, `parent_node_id`, `root_production_order_id`, `source_production_order_id`, `source_component_id`, `item_id`, `production_bom_id`, `production_bom_version_id`, `routing_id`, `routing_version_id`, `source_bom_line_id`, `source_bom_line_type`, `level`, `path`, `node_type`, `line_basis`, `quantity_per_base`, `gross_required_quantity_base`, `scrap_adjusted_quantity_base`, `reference_quantity_base`, `location_code`, `status`, `snapshot`, timestamps.

Indexes:

- `(production_hierarchy_id, level)`;
- `(root_production_order_id, item_id)`;
- `parent_node_id`;
- `(source_component_id)`.

Deletion: no cascade after release; supersede instead.

### `production_order_supply_links`

Defined in section 9.

Unique:

- active unique by `(parent_component_id, child_output_item_id, status not in cancelled)`;
- unique `idempotency_key`.

### `production_material_reservations`

Defined in section 10.

Unique:

- `idempotency_key`;
- optional active unique for exact `(production_order_component_id, item_ledger_entry_id, reservation_type)` where active.

### Audit Fields

Use existing audit trail service for hierarchy actions. Do not write passwords/tokens/secrets. Store business-safe metadata only.

## 29. Proposed Enums

Add only when implementation starts:

- `ProductionOrderOrigin`;
- `ProductionHierarchyStatus`;
- `ProductionSupplyLinkStatus`;
- `ProductionSupplyType`;
- `ProductionReservationStatus`;
- `ProductionReservationType`;
- `ProductionHierarchyReadinessClassification`;
- `ProductionBomExplosionStatus`;
- `ProductionBomLineBasis`.

Reuse existing `ProductionOrderStatus`, `ManufacturingCostComponent`, `CostingMethod`, and `ItemLedgerEntryType`.

## 30. Reconciliation Design

Recommended new report-only command:

```bash
php artisan biwms:manufacturing-hierarchy-reconcile --details --export=...
```

Findings:

- `bom_cycle_detected`;
- `bom_depth_exceeded`;
- `manufactured_component_without_bom`;
- `manufactured_component_without_routing`;
- `hierarchy_link_cross_business`;
- `child_order_item_mismatch`;
- `child_order_quantity_mismatch`;
- `duplicate_active_supply_link`;
- `parent_component_over_supplied`;
- `parent_component_under_supplied`;
- `reservation_exceeds_requirement`;
- `reservation_exceeds_inventory`;
- `child_output_unreserved`;
- `child_output_overallocated`;
- `child_output_consumed_by_wrong_parent`;
- `parent_consumption_without_supply`;
- `parent_finished_with_incomplete_child`;
- `child_cancelled_with_open_parent_requirement`;
- `hierarchy_status_mismatch`;
- `root_order_mismatch`;
- `production_level_mismatch`;
- `orphan_child_order`;
- `orphan_supply_link`;
- `late_child_cost_not_propagated`;
- `parent_settled_before_required_cost_adjustment`;
- `hierarchy_wip_summary_mismatch`;
- `cross_location_supply_without_transfer`.

Extend existing commands:

- `inventory-reconcile`: child output overallocated, parent consumption without supply, location mismatch;
- `manufacturing-cost-reconcile`: late child cost propagation and hierarchy WIP summary;
- `shop-floor-reconcile`: child/parent operational readiness display only, no new authoritative state.

All reconciliation remains report-only.

## 31. Backward Compatibility

Rules:

- existing production orders have no parent;
- existing orders are root orders;
- existing refresh keeps flattened single-level behavior unless hierarchy mode is requested;
- existing production journal posting remains unchanged;
- existing shop-floor execution remains order-specific;
- existing settlement services remain authoritative per order;
- existing reports remain valid;
- hierarchy records are optional for simple orders;
- no historical ledger rewrite.

## 32. Data Migration Strategy

Use additive, bounded PostgreSQL-safe migrations.

Recommended sequence:

1. Add nullable hierarchy fields to `production_orders`.
2. Backfill `root_production_order_id` in chunks or leave nullable until touched; avoid a single large locking update.
3. Create hierarchy/supply/reservation tables with indexes.
4. Add partial unique indexes for active links/reservations.
5. Add enum columns as strings/checks only if the project convention supports easy evolution.

Do not require `migrate:fresh` for production repair. Do not rewrite item/value/capacity ledgers.

## 33. Test Plan

### BOM Explosion

- two-level BOM;
- three-level BOM;
- cycle detection;
- maximum depth;
- version snapshot;
- UOM conversion;
- scrap;
- fixed quantity;
- reference quantity;
- inactive child BOM;
- missing routing;
- cross-business reference.

### Child-Order Generation

- automatic generation;
- repeated generation idempotency;
- manual linking;
- existing inventory supply;
- mixed stock and child production;
- duplicate link prevention;
- quantity increase/decrease;
- cancellation;
- child replacement.

### Reservations

- reserve existing stock;
- reserve child output;
- partial reservation;
- release reservation;
- concurrent reservation;
- over-reservation prevention;
- wrong location;
- wrong lot;
- wrong business.

### Posting and Inventory

- child output creates inventory;
- parent consumes child output;
- item application links correct entries;
- partial child output;
- partial parent consumption;
- over/under production;
- transfer-required location mismatch.

### Costing

- child material/capacity/overhead;
- parent consumes child cost;
- cost roll-up;
- late child adjustment;
- downstream adjustment;
- standard-cost variance by level;
- average-cost child;
- settlement by order;
- root WIP aggregation.

### Completion

- parent blocked by incomplete child;
- parent ready after child supply;
- cancelled child;
- partially supplied child;
- inventory supply replacement;
- operational readiness;
- costing readiness remains separate.

### Authorization

- planner;
- manager;
- store officer;
- costing officer;
- cross-business denial;
- backend action enforcement.

### Reconciliation

- every proposed finding;
- clean hierarchy returns zero;
- report-only behavior;
- JSON export;
- filters.

### Backward Compatibility

- all existing single-level manufacturing tests remain green;
- production journal behavior unchanged;
- shop-floor behavior unchanged;
- costing tests unchanged.

## 34. Performance Considerations

Risks:

- recursive explosion;
- N+1 loading;
- tree rendering payload;
- per-line availability queries;
- reservation candidate locks;
- cost propagation traversal;
- large reconciliation scans.

Recommendations:

- use application recursion for planning with hard max depth; consider recursive CTE only for reporting/read models;
- snapshot nodes with depth/path indexes;
- batch insert hierarchy nodes;
- prefetch BOM lines, items, UOM assignments, and routing links;
- chunk availability checks by item/location;
- disable global search for append-only hierarchy/reservation records;
- use cached/read-model summaries for dashboard tree;
- avoid unobservable background jobs for core order release.

## 35. Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation | Test Coverage | Reconcile Finding |
|---|---:|---:|---|---|---|
| BOM cycles | Medium | High | Certification and explosion guards | cycle detection | `bom_cycle_detected` |
| Exponential explosion | Medium | High | Max depth, line count caps | depth/large BOM | `bom_depth_exceeded` |
| Duplicate child orders | Medium | High | Idempotency and unique links | repeated generation | `duplicate_active_supply_link` |
| Over-reservation | Medium | High | Reservation ledger and locks | concurrent reservation | `reservation_exceeds_inventory` |
| Child output over-allocation | Medium | High | supply link + reservation checks | partial output | `child_output_overallocated` |
| Parent finish before child supply | Medium | High | readiness service | blocked finish | `parent_finished_with_incomplete_child` |
| Cost double-counting | Medium | High | value entry ownership boundary | child+parent costing | `hierarchy_wip_summary_mismatch` |
| Cross-level WIP duplication | Low | High | order-level WIP only | root summary | `hierarchy_wip_summary_mismatch` |
| Late adjustment recursion | Medium | High | bounded traversal and idempotency | late cost propagation | `late_child_cost_not_propagated` |
| Cross-business links | Low | High | constraints + policies | cross-business denial | `hierarchy_link_cross_business` |
| Quantity rounding drift | Medium | Medium | DecimalMath and explicit quantity fields | UOM/scrap/reference tests | `child_order_quantity_mismatch` |
| Location mismatch | Medium | Medium | transfer-required rule | wrong location | `cross_location_supply_without_transfer` |
| Concurrency deadlocks | Medium | High | deterministic lock order | concurrent generation/reserve | `duplicate_active_supply_link` |
| Backward compatibility regression | Medium | High | hierarchy optional | existing tests | N/A |
| UI complexity | High | Medium | tabs/tree/read models | render tests | N/A |
| Migration lock volume | Medium | High | additive/chunked migrations | migration tests | schema findings |

## 36. Open Design Decisions

- Should `SEMI_FINISHED` be added directly to `ItemType`, or should a separate `manufacturing_policy` field carry this?
- What is the operational default maximum BOM depth: 5, 10, or 25?
- Should stock reservation be automatic during child generation or a separate explicit action?
- Which location should be the seeded default intermediate location for semi-finished goods?
- Should child orders inherit parent due dates by lead-time offset or be manually scheduled initially?
- Should standard cost child variance block parent settlement or only mark adjustment-required?

## 37. Recommended Implementation Sequence

### Phase 2A.1: Schema and Domain Enums

Likely files:

- migrations for order hierarchy fields and new tables;
- enum classes;
- model relationships;
- policies/permissions skeleton.

Tests:

- schema tests;
- policy tests;
- backward compatibility creation tests.

Acceptance:

- no runtime hierarchy behavior yet;
- existing manufacturing tests green.

### Phase 2A.2: BOM Explosion and Hierarchy Snapshots

Likely files:

- `ProductionBomExplosionService`;
- `ProductionHierarchyPlanningService`;
- hierarchy models;
- tests for BOM depth, cycle, UOM, scrap, version snapshot.

Acceptance:

- snapshots generated report-only/planning-only;
- no child orders yet.

### Phase 2A.3: Child-Order Generation and Supply Links

Likely files:

- `ProductionChildOrderGenerationService`;
- `ProductionSupplyLinkService`;
- order relationships and idempotency.

Acceptance:

- repeated generation is idempotent;
- one child supplies one parent component.

### Phase 2A.4: Reservations and Intermediate Inventory Supply

Likely files:

- `ProductionMaterialReservationService`;
- reservation model/policy;
- availability query objects.

Acceptance:

- reservations do not alter inventory;
- consumption remains ledger-driven.

### Phase 2A.5: Cost Roll-Up and Late-Cost Propagation

Likely files:

- `ProductionHierarchyCostPropagationService`;
- cost summary services;
- reconciliation extensions.

Acceptance:

- child adjustments mark/apply parent adjustments append-only.

### Phase 2A.6: Completion Readiness and Reconciliation

Likely files:

- `ProductionHierarchyReadinessService`;
- `biwms:manufacturing-hierarchy-reconcile`.

Acceptance:

- parent completion blocked by critical hierarchy findings.

### Phase 2A.7: Filament UX, Permissions, Docs, Verification

Likely files:

- Production Order hierarchy tabs;
- supply link/reservation read-only resources;
- backend action tests;
- docs.

Acceptance:

- UI delegates to services;
- strict authorization passes.

## 38. Numerical Example: Herbal Manufacturing Flow

Finished product: Mai Sasanci Carton.

Assumptions:

- 1 carton = 24 shrink packs;
- 1 shrink pack = 12 filled bottles;
- 1 filled bottle = 1 bottle + 1 cap + 1 label + 0.5 L mixed syrup;
- 1 L mixed syrup = 0.2 L extract + water + sweetener;
- 1 L extract requires 0.15 kg herbal raw material.

Order: 518 cartons.

Level 0: Mai Sasanci Carton

- parent output: 518 cartons;
- requires 12,432 shrink packs.

Level 1: Shrink Pack

- required: 12,432 packs;
- available unreserved stock: 432 packs;
- reserve existing stock: 432 packs;
- shortage: 12,000 packs;
- generated child order: 12,000 shrink packs.

Level 2: Filled Bottle

- 12,000 shrink packs require 144,000 filled bottles;
- available unreserved stock: 2,000 filled bottles;
- generated child order: 142,000 filled bottles.

Level 3: Mixed Syrup

- 142,000 bottles require 71,000 L syrup;
- available unreserved syrup: 1,000 L;
- generated child order: 70,000 L syrup.

Level 4: Extract

- 70,000 L syrup requires 14,000 L extract;
- available unreserved extract: 4,000 L;
- generated child order: 10,000 L extract.

Illustrative cost:

- Extract child order consumes herbs and capacity: NGN 3,000,000 total, 10,000 L output, NGN 300/L.
- Syrup parent consumes 10,000 L child extract plus 4,000 L stock extract. Applied extract cost = NGN 4,200,000. Other materials/capacity = NGN 1,800,000. Syrup output cost = NGN 6,000,000.
- Filled bottle consumes syrup at applied cost plus bottle/cap/label/capacity. Output cost = NGN 14,200,000.
- Shrink pack consumes filled bottles, sleeve, tray, packaging capacity. Output cost = NGN 17,500,000.
- Carton parent consumes 12,000 produced shrink packs and 432 stock shrink packs. Final carton output cost = NGN 18,200,000.

WIP by level before completion:

- Extract order WIP: open until extract output is posted and settled;
- Syrup order WIP: open until syrup output is posted and settled;
- Filled bottle order WIP: open until output posted and settled;
- Shrink pack order WIP: open until output posted and settled;
- Carton order WIP: open until parent consumption/output/finish.

Late adjustment:

```text
Herb supplier invoice increases Extract cost by NGN 500,000
-> child extract Value Entry adjustment
-> item application to syrup consumption
-> syrup output adjustment
-> application to filled bottle consumption
-> filled bottle output adjustment
-> application to shrink pack consumption
-> shrink pack output adjustment
-> application to carton consumption
-> carton output adjustment or settlement adjustment-required
```

All adjustments are append-only.

## 39. Architecture Decision Records

### ADR-2A-001: Semi-Finished Items Remain Normal Inventory Items

Covered in section 5.

### ADR-2A-002: Each Production Order Owns Its Own WIP and Settlement

Covered in section 15.

### ADR-2A-003: Child Output Enters Inventory Before Parent Consumption

Covered in section 13.

### ADR-2A-004: Parent-Child Supply Is Represented Explicitly

Covered in section 9.

### ADR-2A-005: BOM Hierarchy Is Snapshotted Before Release

Covered in section 7.

### ADR-2A-006: Single-Level Manufacturing Remains Backward Compatible

Covered in section 8 and 31.

### ADR-2A-007: Late Cost Propagates Through Item Application and Value Entry Adjustment

Covered in section 16.

### ADR-2A-008: Phase 2A Does Not Implement Routing Dependencies or Full Genealogy

Context: Routing dependencies and genealogy are valuable but larger than semi-finished item planning.

Decision: Phase 2A links production orders and inventory supply, not operation-to-operation dependencies or genealogy reports.

Alternatives: implement routing dependency graphs or batch genealogy now. Rejected to protect scope.

Consequences: parent readiness is based on child supply/output and existing shop-floor readiness, not operation-level dependency graphs.

Risks: users may expect genealogy from hierarchy links. Mitigation: label genealogy as Phase 2B.

## 40. Phase 2A.1 Implementation Checkpoint

Phase 2A.1 implements only the backward-compatible domain foundation:

- `ItemType::SEMI_FINISHED` is available as a normal inventory item type.
- Production orders now have parent/root/order-origin hierarchy fields.
- Production order components now have optional hierarchy node and manufactured-requirement traceability fields.
- `production_hierarchies` and `production_hierarchy_nodes` snapshot hierarchy planning structure.
- `production_order_supply_links` records planned parent-component supply sources.
- `production_material_reservations` records future reservation ownership without creating reservation behavior.
- Policies and generated permissions cover the new foundation records.

Still deferred by design:

- BOM hierarchy explosion runtime.
- Child production order generation.
- Reservation creation/release runtime.
- Parent consumption from child output.
- WIP, cost propagation, settlement, and G/L posting changes.
- Filament workflow/resources.
- Reconciliation commands for hierarchy runtime.
