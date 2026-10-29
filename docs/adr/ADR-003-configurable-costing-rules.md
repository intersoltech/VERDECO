# ADR-003: Costing and process rules are database-driven

- Status: Accepted
- Date: 2026-10-29

## Context
The process flow for recycled PET production includes several configurable stages, allocation bases, and quality rules. Hard-coding these values would create churn and delay adaptation to client requirements.

## Decision
Process stages, cost categories, allocation bases, reason codes, and standard rates are represented as data with effective dates and plant-level configuration rather than hard-coded enums.

## Consequences
- More flexibility for client-driven process changes.
- More careful validation and a stronger schema around effective-dated records.
- Some complexity shifts from app code to configuration and data management.

## Notes
This decision is consistent with the requirement that new products and stages be added without code changes.
