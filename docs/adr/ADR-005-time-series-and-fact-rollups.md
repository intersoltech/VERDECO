# ADR-005: Event-Driven Fact Rollups and Units of Measure Strategy

- Status: Accepted
- Date: 2026-10-30

## Context
Verdeco operates across multiple manufacturing plants generating thousands of production batches, downtime ticks, utility meter readings, and unplanned incidents. 
Querying raw transactional and time-series tables directly on management dashboards causes severe database contention and degrades p95 latency under multi-user concurrency.
Additionally, operations teams in US plants view output in pounds (lb), while internal recipes and standard costing require SI kilograms (kg).

## Decision
1. **Pre-aggregated Read Models (Fact Tables)**:
   - High-volume dashboards read exclusively from pre-aggregated fact tables (`fact_daily_line_cost`, `fact_daily_plant_kpi`, `fact_monthly_variance`, `fact_incident_summary`).
   - Transactional mutations emit asynchronous domain events (`BatchClosed`, `IncidentApproved`, `DowntimeLogged`).
   - Listeners update fact tables incrementally.
   - An idempotent CLI command (`php artisan rollups:rebuild`) provides disaster recovery and historical backfills.

2. **First-Class Units & Multi-Unit Display**:
   - Weights and costs are stored internally strictly in **kilograms (kg)** and plant base currency (USD default) with decimal precision (BCMath).
   - Display conversions (e.g. `kg` <-> `lb`, `1 lb = 0.45359237 kg`) are performed through a database-driven `units` conversion service, allowing user-level toggling on all dashboards without mutating database rows.

## Consequences
- Dashboard response times remain sub-second regardless of transactional row volume.
- Raw operational tables can be archived safely without breaking reporting.
- Clean separation between operational data entry and executive analytics.
