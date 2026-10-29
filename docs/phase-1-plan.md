# Phase 1: Foundation, ERD, and seed data

## Goal
Build the agreed Phase 1 foundation for Verdeco Cost Control: modular domain structure, multi-plant / multi-tenant-ready schema, configurable costing assumptions, and realistic seed data for three demo plants.

## Text ERD

Organization
- Organization (tenant) 1..N Plants
- Plant 1..N ProductionLines
- Plant N..N Users via plant_user

Master data
- Supplier 1..N Materials
- Material 1..N MaterialPrices (deferred to later phases; seeded as standard snapshot for phase 1)
- ProductGrade 1..N StandardCosts
- Currency 1..N FxRate
- CostCategory 1..N Costing entries and allocations

Operations
- Batch/ProductionRun (planned in later phases; schema seam added for phase 1 via standard costs and incident tables)
- CostIncident 1..N CostIncidentLine
- DailyProductionSummary / DailyExpense (planned for phase 2+)

Core relationship rules
- Every operational row carries tenant_id and plant_id as the base isolation keys.
- Only closed snapshots are immutable; future phases will use adjustment entries instead of editing history.
- Plant-level costing_mode is configurable: batch, daily, or both.

## Model list for phase 1
- Organization / Tenant context (ready for future multi-tenant support)
- Plant
- ProductionLine
- Supplier
- Material
- ProductGrade
- Currency
- FxRate
- CostCategory
- StandardCost
- CostIncident
- CostIncidentLine
- PlantUser (pivot)

## Assumptions
- Verdeco is a single-tenant deployment today, but every operational table is shaped for tenant_id so another company can be hosted later without schema churn.
- Plants operate in different time zones and base currencies; USD is the default, but the schema allows multiple currencies.
- Feedstock is PET flake purchased hot-washed and measured in kilograms internally.
- Costing is configured per plant using a costing_mode of batch, daily, or both.
- Unplanned costs are treated as first-class records, and they can be allocated to batches or days by a configurable rule in later phases.
- Costs are never stored as floats; the key monetary and quantity fields are decimal-based.
- Standard cost records include effective periods so costing can resolve the correct values as-of batch date.

## Open Questions
1. Which exact plants and line types should be used in production, and how many shifts do they run?
2. Should we allow a plant to report both batch and daily costs simultaneously, or is a single mode still preferred for initial rollout?
3. How should incident allocation rules be configured at the plant level: by kg, by hours, by direct tag, or equal split?
4. Do we require a strict line-level standard cost model, or should product-by-plant standards be used before line-specific overrides exist?
5. What is the approved standard for quality hold and downgrade recovery credits?
6. Should the system support grouped vendor pricing (supplier contracts) immediately, or only material-level pricing history later?
7. Is there an approved list of root-cause categories for incidents, or should it remain fully metadata-driven?
8. Which currency/date semantics are required for group consolidation: plant base currency only or multi-currency consolidation from the start?
9. Who approves standard changes and incident closings in the client workflow?
10. Are there any government reporting or audit requirements for period close and adjustments that must be reflected in the schema sooner than later?

## Scope of this phase
Completed here:
- domain skeleton and modular organization
- core data model for plants, lines, materials, grades, rates, standards, and incidents
- realistic demo seed data for three plants
- migration-ready schema with indexes and tenant/plant isolation

Deferred to later phases:
- production batch entry, daily summaries, and rollups
- cost calculation services and variance logic
- dashboards, alert engine, and exports
- role scoping, audit trails, and approvals

## Risk notes
- The architecture is intentionally configurable rather than hard-coded; this creates flexibility but requires disciplined use of database-driven metadata and effective-dated standards.
- The initial schema intentionally leaves many operational tables empty but ready for future implementation; this reduces early churn and keeps the system scannable for future modules.
- Because the app is single-tenant today, the tenant keys are present but not highly stressed; later phases must validate cross-tenant and cross-plant isolation in tests.
