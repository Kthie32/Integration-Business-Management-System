# 01 — Project Overview & Environment Report

**Project:** Integrated Business Management System (IBMS)
**Type:** Web-based ERP / business management application
**Status:** Phase 1–2 (planning & design) — awaiting review. No application code written yet.
**Document date:** 2026-11-10

## 1. Purpose

A single, centralized web application through which the organization manages quotations, invoices,
payments and receipts, inventory, customers and suppliers, employees, payroll, requisitions and
approvals, and produces management reports — replacing manual processes and giving management
accurate, timely, auditable information.

## 2. Planning document set

| # | Document | File |
|---|----------|------|
| 1 | Project overview & environment report | `01-project-overview.md` (this file) |
| 2 | Software Requirements Specification | `02-software-requirements-specification.md` |
| 3 | System architecture & folder structure | `03-system-architecture.md` |
| 4 | Database design, ERD & data dictionary | `04-database-design.md` |
| 5 | Roles & permissions | `05-roles-and-permissions.md` |
| 6 | Implementation roadmap & progress report | `06-implementation-roadmap.md` |
| 7 | Assumptions & open questions | `07-assumptions-and-open-questions.md` |

Installation, testing, deployment, user manual and maintenance guides are produced in later phases.

## 3. Environment inspection (2026-11-10)

| Component | Found | Notes |
|-----------|-------|-------|
| OS | Windows 10 Pro 10.0.19045 | Development machine |
| PHP | 8.5.12 (ZTS, x64) | Extensions present: bcmath, gd, intl, mbstring, pdo_mysql, mysqli, zip, fileinfo, openssl, xml, dom, curl — all that DomPDF and Laravel Excel need |
| Composer | 2.11.1 | |
| Laravel | 13.40.0 (`laravel/framework ^13.17`) | Project skeleton in `business-site/` |
| MySQL | 8.0.46 Community, service `MySQL80` running | Root access verified; utf8mb4, strict mode. Dedicated app user to be created (open question A3) |
| Node.js | 24.22.1 | Used **only** as build tooling (Vite) to bundle Bootstrap/CSS/JS. No Node backend. |
| Git | 2.56.0 | Project is **not yet a git repository** |
| GitHub CLI | not installed | Need the GitHub repository URL from the owner |

### Findings requiring action

1. **Interrupted dependency install** — `vendor/` contained only partial packages and temp zips; `artisan`
   could not run. **Resolved:** `composer install` re-run successfully (no code changes).
2. **Database** — `.env` currently uses SQLite (Laravel default). Will switch to MySQL with a dedicated
   least-privilege user once credentials are agreed.
3. **Frontend scaffold uses Tailwind CSS** — will be replaced with Bootstrap 5 per the mandated stack.
4. **`APP_NAME=Laravel`** — will be set to the organization name.
5. **`AGENTS.md`** asks for Laravel Boost (dev-only AI tooling) to be installed — will be added as a dev
   dependency in Phase 3.
6. **`../example/`** — a second, unrelated Laravel skeleton. Not used by IBMS; can be removed with the
   owner's agreement.

## 4. Scope summary

In scope: the 12 modules in the SRS, plus two supporting modules identified during analysis as
necessary for the specified dashboard and reports:

- **Expenses** — the dashboard and reports require "total expenses" but no module produces expense records.
- **Goods receiving from suppliers** — inventory "stock received" and supplier "purchase history" need a
  source transaction.

Both are proposed in lightweight form and listed for confirmation in `07-assumptions-and-open-questions.md`.

Out of scope unless requested: general ledger/double-entry accounting, multi-company, multi-currency
conversion, customer self-service portal, mobile apps, online payment gateway integration.
