# ADR-004: Production readiness starts with operational guardrails

## Status
Accepted

## Context
The Verdeco Cost Control application has moved from a proof-of-concept into a production-oriented operational system. As the system grows to multiple plants, more users, and more real-time monitoring, the app needs stronger guardrails around access, health, and operational reliability.

## Decision
The application must include explicit authorization policies, plant/tenant scoping middleware, and a health endpoint before new business modules are considered production-grade.

## Consequences
- Users only see data in plants they are assigned to, unless explicitly granted broader access.
- Monitoring endpoints and admin screens are easier to secure and reason about.
- A simple health route gives production operators a basic signal that the app and database are responsive.
- The design is intentionally minimal but provides a strong base for more advanced RBAC and monitoring.
