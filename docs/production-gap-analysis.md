# Verdeco production-grade gap analysis

## Scope
This document highlights what is already implemented versus what is still required for a true production-grade multi-plant manufacturing cost control system.

## Implemented
- Modular domain structure under `app/Domain`
- Plant and tenant model foundation
- Three-plant demo seed data
- Standard costing and incident model foundation
- Costing service classes and unit tests
- Alert evaluation, digests, and admin UI
- Multi-plant alert rules
- Health endpoint and policy scaffold

## Still required for production-grade readiness
- Full RBAC and permission model with real policy enforcement
- Complete production run / batch / daily accounting models
- Authoritative approval and period-close workflows
- Versioned API and ingestion pipeline
- Queue-driven rollups and dashboard data pipeline
- Docker/CI/arch testing/health automation
- Load testing and observability configuration

## Risk notes
The app has a valuable operational foundation, but shipping it as a production system without the remaining controls would expose the team to data leakage, unreliable costing, and weak operational governance.
