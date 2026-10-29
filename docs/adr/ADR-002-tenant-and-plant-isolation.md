# ADR-002: Tenant and plant scoping are first-class

- Status: Accepted
- Date: 2026-10-29

## Context
The project is designed for 20+ plants and for eventual multi-tenant hosting. Cost data, product standards, and incident workflow must not leak across plants or organizations.

## Decision
All operational tables include tenant_id and plant_id, with the standard Laravel global scope pattern used to enforce visibility. The schema also uses composite indexes where the query patterns demand them.

## Consequences
- Data isolation is explicit and testable.
- Future multi-company hosting is easier.
- Larger queries must consider composite-index performance from the start.

## Notes
This is not yet fully production-hardened, but the schema and architecture are aligned with the required scale and safety model.
