# Verdeco production-grade gap analysis

## Scope
This document highlights what is already implemented versus what is still required for a true production-grade multi-plant manufacturing cost control system.

## Implemented
- Modular domain structure under `app/Domain`
- Plant and tenant model foundation
- Three-plant demo seed data
- Standard costing and incident model foundation
- Persisted production batch inputs, material snapshots, and cost results
- Daily production summaries, expense entry, and batch-to-day cost rollups
- Filament entry and review screens for batch and daily production costing
- Guarded cost-period approval, close, reopen, and closed-value immutability
- Append-only cost-period lifecycle history with actor snapshots and separation of duties
- Costing service classes and unit tests
- Alert evaluation, digests, and admin UI
- Multi-plant alert rules
- Health endpoint and policy scaffold

## Still required for production-grade readiness
- Full RBAC and permission model with real policy enforcement
- Broader separation-of-duties rules across incident approvals and administrative changes
- Versioned API and ingestion pipeline
- Queue-driven rollups and dashboard data pipeline
- Docker/CI/arch testing/health automation
- Load testing and observability configuration

## Risk notes
The app has a valuable operational foundation, but shipping it as a production system without the remaining controls would expose the team to data leakage, unreliable costing, and weak operational governance.
