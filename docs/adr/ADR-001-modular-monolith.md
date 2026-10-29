# ADR-001: Use a modular monolith

- Status: Accepted
- Date: 2026-10-29

## Context
The Verdeco Cost Control application is large, multi-plant, and cost-accounting oriented. The system must support different plant operations, evolving standards, variable cost analysis, and future ERP/machine integrations without a disruptive rewrite.

## Decision
We will implement the application as a modular monolith. Domain modules live under `app/Domain`, and each module owns its own models, migrations, services, policies, and Filament resources when they are introduced.

## Consequences
- Simpler deployment and operational model for Phase 1.
- Clear module boundaries reduce default coupling.
- The domain event and service interface patterns allow future extraction into bounded contexts if needed.
- Requires discipline: modules must not reach into other modules’ Eloquent internals beyond IDs and read models.

## Notes
This is the right balance for the stated scale. We will not over-engineer microservices at this stage, but the seam for future extraction is intentionally preserved.
