# 06 — Implementation Roadmap & Progress Report

Last updated: 2026-11-10

## 1. Phases

Each module follows the same definition of done: migrations → models/relationships → services →
Form Requests → policies → routes/controllers → Blade views → permissions seeded → automated tests
(unit + feature + permission) → manual workflow walkthrough → docs updated → progress report updated.
A module is not marked complete because its screens render.

| Phase | Scope | Key deliverables | Depends on |
|-------|-------|------------------|------------|
| 1 | Requirements & planning | Docs 01–07 | — |
| 2 | Architecture & DB design | ERD, data dictionary, permission matrix | 1 |
| 3 | Project initialization | Git repo + GitHub remote, MySQL `ibms`/`ibms_testing` + app user, `.env`, Boost, Bootstrap 5 + Vite replacing Tailwind, Pint, base config (`Africa/Kampala`), CI test run | Review sign-off |
| 4 | Auth, users, roles, permissions | Fortify, login/reset/profile, user admin, role editor, active-user middleware, audit logging foundation, Super Admin seeder | 3 |
| 5 | Shell & shared components | App layout (collapsible sidebar, topbar, notifications, breadcrumbs), Blade components, settings module (organization, currency, tax, numbering), NumberingService, Money, status history | 4 |
| 6 | Departments, employees, customers, suppliers | CRUD + documents + histories | 5 |
| 7 | Inventory | Categories, items, warehouses, StockService ledger, GRN, issues, transfers, adjustments, low-stock alerts, stock card | 6 |
| 8 | Quotations | DocumentCalculator, quotation CRUD, statuses, revisions, PDF/print, expiry job | 7 |
| 9 | Invoices & payments | Invoice lifecycle, stock issue on invoice, void, payments, overdue tracking | 8 |
| 10 | Receipts & expenses | Receipts (auto from payments), reversal, customer statements, expenses | 9 |
| 11 | Requisitions & approval engine | Configurable workflows, approvals inbox, fulfilment into stock/expenses | 7, 10 |
| 12 | Payroll | Pay components, salary structures, statutory rates, calculator, runs, approval, payslips | 6, 11 (approval engine) |
| 13 | Dashboard, reports & documents | Permission-aware dashboard + charts, all reports with PDF/Excel/print | 7–12 |
| 14 | Integration & security testing | End-to-end scenarios, permission matrix tests, security review, performance check with seeded volume | 13 |
| 15 | Deployment & documentation | Hosting recommendation, deployment guide, backup/restore, user manual, maintenance guide, README | 14 |

> The dashboard shell is built in Phase 5, but its widgets are filled as each module lands, with the final pass in Phase 13.

## 2. Critical automated test areas

- Money/DocumentCalculator rounding, discounts, tax (unit, table-driven).
- Numbering uniqueness under concurrent transactions.
- Stock: receive/issue/transfer/adjust, negative-stock rejection, reversal, reconcile command.
- Invoice lifecycle: issue locks edits; void blocked when payments exist; balance after partial/full/reversed payments.
- Receipt only created via payment; reversal voids receipt.
- Payroll calculator against worked examples per PAYE band and NSSF; locking after approval.
- Approval engine: routing, self-approval blocked, rejection, multi-step order, return for revision.
- Permission matrix: each role × key route → allowed/forbidden.
- PDF endpoints return valid PDFs; exports return valid XLSX.

## 3. Progress report

Status key: ✅ done & verified · 🟡 in progress · ⚠️ built, not yet verified · ⬜ pending

| Phase | Item | Status | Notes |
|-------|------|--------|-------|
| 1 | Environment inspection | ✅ | See 01-project-overview.md §3 |
| 1 | Repair interrupted `composer install` | ✅ | Laravel 13.40.0 boots; artisan works |
| 1 | MySQL access verified | ✅ | 8.0.46, utf8mb4, strict mode; no IBMS database created yet |
| 1–2 | Planning documents 01–07 | ⚠️ | Drafted, awaiting owner review |
| 3–15 | All implementation | ⬜ | Starts after review and answers to doc 07 |
