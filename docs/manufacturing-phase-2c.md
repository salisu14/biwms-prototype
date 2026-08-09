# Manufacturing Phase 2C: APS Lite

## Purpose

Phase 2C adds practical production scheduling and capacity planning. It answers planner questions:

- what should run;
- when it should run;
- where it should run;
- what is overloaded, blocked, late, or movable.

It remains a planning layer. It does not post inventory, capacity, value, or G/L entries.

## Scheduling Algorithm

Default priority order:

1. explicit Production Order priority;
2. earliest due date;
3. earliest planned start/release timestamp;
4. document number.

Forward scheduling starts at the later of the horizon start or order start time, then schedules routing lines by ascending line number.

Backward scheduling starts from the order due date or horizon end, then schedules routing lines by descending line number.

Operation duration formula:

```text
duration = setup_time once + scaled run_time + wait_time + move_time
scaled run_time = run_time * max(1, order quantity base / routing expected output quantity)
```

If routing expected output quantity is missing, the service treats the routing line run time as already representing the planned order quantity.

## Finite Capacity

Machine Centers are treated as exclusive resources in Phase 2C. Two active operations cannot overlap on the same Machine Center.

Work Centers may allow pooled concurrency through their `capacity` value. Work Center Calendar entries define daily working windows and available minutes.

The scheduler does not assume 24/7 capacity. Missing calendars produce scheduling exceptions.

## Alternate Resources

Configured alternates are stored in `ProductionAlternateResource`.

Selection order:

1. primary resource if it has the earliest feasible slot;
2. configured alternates by priority;
3. deterministic tie-break by resource order.

Selection metadata records why a resource was chosen, for example when a configured alternate provides an earlier feasible slot than an occupied primary machine.

## Dependency Awareness

Phase 2B operation dependencies influence scheduling:

- if the upstream operation is also in the schedule, downstream work starts after its scheduled finish;
- if the upstream operation is not in the schedule and is not actually ready, a scheduling exception is recorded;
- scheduled readiness remains distinct from execution readiness.

Shop-floor execution still performs the final readiness check before setup/run starts.

## Campaign Planning

Campaigns group production orders to reduce setup/changeover friction. Phase 2C supports:

- planner-selected campaigns;
- same-routing campaign suggestions;
- setup reduction metadata;
- sequence and changeover notes.

Campaigns never allow capacity overbooking. They are a grouping preference, not an override.

## Rescheduling And Freeze Horizon

Rescheduling creates a new schedule version and supersedes the old schedule.

Operations inside the freeze horizon are protected unless a planner explicitly overrides the freeze with a reason. Completed and historical execution records are not moved by APS Lite.

## Reconciliation

Run:

```bash
php artisan biwms:production-schedule-reconcile --details
```

The command is report-only and checks:

- missing production orders or routing lines;
- overlapping exclusive machines;
- Work Center concurrency overloads;
- predecessor timing violations;
- calendar gaps;
- duplicate active assignments;
- approved schedules without operations;
- lateness;
- invalid durations;
- orphan exceptions.

## Backend Complexity Vs Frontend Simplicity

The backend persists enough detail for deterministic planning, auditing, diagnostics, and rescheduling.

The everyday planner UI should remain simple:

- Production Schedules;
- Campaigns;
- concise exceptions;
- bottleneck indicators;
- suggested actions.

Technical operation schedules and exception rows are diagnostic records, not the normal operator experience.

## Non-Goals

Phase 2C does not implement:

- mathematical optimization;
- AI scheduling;
- demand forecasting;
- purchase-order automation;
- transfer-order automation;
- full alternate-routing selection;
- drag-and-drop Gantt;
- digital twin simulation;
- Phase 3 functionality.
