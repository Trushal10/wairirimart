# Vue-Ecommerce — BMAD Documentation

**Project:** Vue-Ecommerce (Laravel + Inertia/Vue + Blade)
**Documentation set:** Business • Model • Architecture • Development (BMAD)
**Last updated:** 2026-07-17
**Audience:** Product owners, developers, QA engineers, DevOps, and any team taking over the project.

This documentation set is the single source of truth for the Vue-Ecommerce platform. It describes **only what is implemented in the current codebase** as of the date above; planned or missing functionality is called out explicitly.

---

## How to read this documentation

Start with **[01 — Executive Summary](01-executive-summary.md)** for the 10-minute overview, then follow the numbered order or jump to the section that matches your role:

| I am a…                | Start with                                                                                                                                          |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Product manager / PO   | [01 Executive Summary](01-executive-summary.md), [02 BRD](02-business-requirements.md), [03 PRD](03-product-requirements.md), [25 Feature Matrix](25-feature-matrix.md) |
| Backend developer      | [04 Architecture](04-system-architecture.md), [05 Database](05-database-schema.md), [06 API](06-api-documentation.md), [22 Folder Structure](22-folder-structure.md) |
| Frontend developer     | [04 Architecture](04-system-architecture.md), [09 Customer Workflow](09-customer-workflow.md), [22 Folder Structure](22-folder-structure.md)         |
| Payments / integrations| [10 Payment Architecture](10-payment-architecture.md), [11 Courier Architecture](11-courier-architecture.md), [24 Third-Party Integrations](24-third-party-integrations.md) |
| Security / infosec     | [14 Auth & RBAC](14-authentication-rbac.md), [15 Security](15-security-architecture.md), [21 QA Report](21-qa-report.md)                            |
| DevOps / SRE           | [17 Deployment](17-deployment-guide.md), [18 Environment](18-environment-config.md), [19 Queue & Cron](19-queue-cron-jobs.md), [28 Maintenance](28-maintenance-guide.md) |
| QA engineer            | [20 Testing Strategy](20-testing-strategy.md), [21 QA Report](21-qa-report.md), [07 User Flows](07-user-flows.md)                                   |

---

## Table of Contents

### Business layer
1. [Executive Summary](01-executive-summary.md)
2. [Business Requirements (BRD)](02-business-requirements.md)
3. [Product Requirements (PRD)](03-product-requirements.md)

### Model & Architecture layer
4. [System Architecture](04-system-architecture.md)
5. [Database Schema (ERD)](05-database-schema.md)
6. [API Documentation](06-api-documentation.md)

### Workflow layer
7. [User Flow Diagrams](07-user-flows.md)
8. [Admin Workflow](08-admin-workflow.md)
9. [Customer Workflow](09-customer-workflow.md)

### Commerce sub-systems
10. [Payment Gateway Architecture](10-payment-architecture.md)
11. [Courier Partner Architecture](11-courier-architecture.md)
12. [Order Management Flow](12-order-management.md)
13. [Inventory Management](13-inventory-management.md)

### Platform layer
14. [Authentication & Authorization (RBAC)](14-authentication-rbac.md)
15. [Security Architecture](15-security-architecture.md)
16. [Notification System](16-notification-system.md)

### Operations
17. [Deployment Guide](17-deployment-guide.md)
18. [Environment Configuration](18-environment-config.md)
19. [Queue & Cron Jobs](19-queue-cron-jobs.md)

### Quality
20. [Testing Strategy](20-testing-strategy.md)
21. [QA Report](21-qa-report.md)

### Development layer
22. [Folder Structure](22-folder-structure.md)
23. [Coding Standards](23-coding-standards.md)
24. [Third-Party Integrations](24-third-party-integrations.md)

### Product overview
25. [Feature Matrix](25-feature-matrix.md)
26. [Known Limitations](26-known-limitations.md)
27. [Roadmap](27-roadmap.md)
28. [Maintenance Guide](28-maintenance-guide.md)

### Modules
29. [Reports Module](29-reports-module.md) — Sales · Orders · Payments · Shipping · P&L + Operating Expenses

---

## Documentation conventions

- **Mermaid diagrams** are embedded inline; any Markdown viewer that supports Mermaid (GitHub, GitLab, VS Code with the Mermaid extension) will render them.
- **File references** use the pattern `path/to/file.php:line` — click to navigate in an IDE-integrated Markdown viewer.
- **NOT IMPLEMENTED** is used to explicitly flag missing or planned functionality. Do not assume it exists.
- **STUB** indicates a class/module skeleton is present but the integration is not wired to a live provider.
- **BC (Backwards-compatible)** flags places kept for legacy reasons.

## Source-of-truth precedence

If this documentation conflicts with the code, the **code wins**. Please open a PR to update the affected document.
