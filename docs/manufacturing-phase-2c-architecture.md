# Manufacturing Phase 2C Architecture

## Existing Assumptions Audited

Work Center Groups already represent production sections such as extraction/mixing, filling/capping, and packaging. Phase 2C does not introduce a separate Production Section model.

Work Centers own pooled capacity, queue time, costing references, location, and calendar entries.

Machine Centers belong to Work Centers and represent specific equipment. Phase 2C treats Machine Center assignments as exclusive unless future configuration expands this.

Production Order Routing Lines already carry setup time, run time, wait time, move time, Work Center, Machine Center, sequence, expected output quantity, and planned timestamps.

Work Center Calendars provide dated working windows and available capacity. HR workforce scheduling remains separate; it is not abused as the manufacturing capacity calendar.

Production Downtime is currently tied to shop-floor operation execution. Phase 2C can report and respect mapped execution downtime where available, but does not invent maintenance/equipment downtime mappings when no reliable link exists.

## New Planning Tables

`production_schedules`

- one planning run/version;
- horizon, mode, status, freeze horizon, generated/approved audit fields;
- planning summary metadata.

`production_schedule_lines`

- schedule-to-production-order membership;
- due date, priority, quantity, lateness summary.

`production_operation_schedules`

- operation-level assignment;
- Work Center / Machine Center;
- scheduled start/finish;
- setup/run/wait/queue duration;
- capacity required;
- alternate-resource reasoning;
- idempotency key.

`production_scheduling_exceptions`

- reviewable planner exceptions;
- severity, type, message, suggested action.

`production_alternate_resources`

- explicit primary-to-alternate resource configuration;
- priority, effective dates, efficiency factor.

`production_campaigns` and `production_campaign_orders`

- planner-controlled grouping and sequence records.

## Ownership Boundary

Phase 2C owns planning state only.

It never creates or modifies:

- `ItemLedgerEntry`;
- `ItemApplicationEntry`;
- `ValueEntry`;
- `CapacityLedgerEntry`;
- G/L entries;
- production journal postings.

Existing unscheduled Phase 1, Phase 2A, and Phase 2B production execution remains valid.

## Services

`ProductionSchedulingService`

- loads candidate orders;
- orders them deterministically;
- schedules routing operations forward or backward;
- persists schedule versions;
- approves and reschedules controlled schedule versions.

`ProductionCapacityCalendarService`

- finds feasible forward/backward slots within Work Center Calendar windows;
- prevents exclusive Machine Center overlap;
- enforces Work Center pooled concurrency.

`ProductionAlternateResourceSelectionService`

- evaluates primary and configured alternate resources;
- selects the earliest feasible resource with deterministic tie-breaks;
- records human-readable selection reasoning.

`ProductionScheduleBottleneckService`

- calculates utilization from scheduled load and calendar capacity;
- reports high-utilization or overloaded resources.

`ProductionCampaignPlanningService`

- suggests same-routing campaigns;
- creates planner-selected campaigns without posting side effects.

## Security

New permission prefixes:

- `manufacturing.production_schedule.*`
- `manufacturing.production_operation_schedule.*`
- `manufacturing.production_scheduling_exception.*`
- `manufacturing.production_alternate_resource.*`
- `manufacturing.production_campaign.*`
- `manufacturing.production_campaign_order.*`

Planner actions require explicit schedule permissions. Shop-floor operator permissions do not grant APS planner controls.

## Performance

Indexes are added for:

- schedule status/horizon;
- schedule line order membership;
- operation resource/time overlap checks;
- operation order/status;
- exception schedule/severity/status;
- alternate resource lookup;
- campaign Work Center/time.

Planning horizons should remain bounded. APS Lite is not intended for unbounded historical schedule generation.
