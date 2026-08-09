# Manufacturing Phase 2B Architecture

## New Records

`ProductionOperationDependency`

- connects an upstream production order operation to a downstream production order operation;
- stores required, minimum-start, and fulfilled base quantities;
- references the Phase 2A hierarchy and supply link when generated from child output demand;
- is idempotent by source link, upstream operation, downstream operation, and dependency type;
- is never an inventory, costing, or accounting source of truth.

`ProductionIntermediateHandoff`

- summarizes intermediate output availability between source and destination operations;
- references the supply link, material reservation, and optional child output ledger entry;
- tracks available and consumed quantities for visibility and reconciliation;
- never transfers stock directly.

## Services

`ProductionOperationDependencyGenerationService`

- generates dependencies from Phase 2A child-order supply links;
- maps child final operation to the parent component operation through `routing_link_code`;
- permits unmapped inference only when the parent order has one routing line;
- reports ambiguous mapping as an unresolved planning issue instead of falling back to the first parent operation;
- checks graph cycles before completing generation;
- creates matching handoff records idempotently.

`ProductionOperationDependencyReadinessService`

- evaluates operation start readiness;
- treats fulfilled dependencies as normally ready but still checks cancellation and quality holds;
- returns machine-readable classifications and operator remediation text.

`ProductionOperationDependencyProgressService`

- synchronizes dependency fulfilled quantity and status from supply-link availability;
- synchronizes handoff available and consumed quantities;
- caps dependency and handoff availability/consumption at the required quantity so overproduction does not overfulfil a dependency;
- is called after child supply fulfilment and parent reservation consumption.

`ProductionGenealogyService`

- traces production input/output relationships through `ItemLedgerEntry` and `ItemApplicationEntry`;
- avoids introducing a parallel genealogy source of truth.

## Shop Floor Integration

`ProductionOperationExecutionService` checks dependency readiness before setup or run starts. It does not require dependencies for operations with no downstream dependency records.

Completion, posting, and reversal trigger dependency progress synchronization for related production orders, but posting behavior remains owned by the existing Phase 1/Phase 2A posting services.

## Permissions

Generated permissions:

- `manufacturing.production_operation_dependency.view_any`
- `manufacturing.production_operation_dependency.view`
- `manufacturing.production_operation_dependency.generate`
- `manufacturing.production_operation_dependency.reconcile`
- `manufacturing.production_intermediate_handoff.view_any`
- `manufacturing.production_intermediate_handoff.view`

Policies are explicit and read/generate oriented. Direct create/update/delete is not exposed for normal users.

## Operational Checks

Recommended post-deploy checks:

```bash
php artisan biwms:manufacturing-hierarchy-reconcile --details
php artisan biwms:manufacturing-cost-reconcile --details
php artisan biwms:inventory-reconcile --details
```

These commands should report issues only. They must not repair production data automatically.
