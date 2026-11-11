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
| 1 | MySQL access verified | ✅ | 8.0.46, utf8mb4, strict mode |
| 1–2 | Planning documents 01–07 | ✅ | Reviewed by owner 2026-11-10; section A of doc 07 answered |
| 3 | Git repository | ✅ | Local repo, `main` baseline + `phase-3-foundation` branch. GitHub remote pending URL |
| 3 | MySQL databases & app user | ✅ | `ibms`, `ibms_testing` (utf8mb4_unicode_ci); user `ibms`@localhost with rights on those two DBs only; password only in `.env` |
| 3 | Environment configuration | ✅ | `APP_NAME="Magezi IBM"` (default only — editable in Settings, Phase 5), `Africa/Kampala`, encrypted DB sessions, database queue/cache |
| 3 | Laravel Boost | ✅ | Guidelines + skills installed (`boost.json`) |
| 3 | Core packages | ✅ | Fortify 1.41, spatie/laravel-permission 8.5, laravel-dompdf 3.1, Laravel Excel 4.0 — all install cleanly on Laravel 13 / PHP 8.5; configured in their phases |
| 3 | Bootstrap 5 frontend | ✅ | Tailwind removed; Bootstrap 5.3.8, Bootstrap Icons, Chart.js, Tom Select, Sass via Vite; `npm run build` succeeds |
| 3 | Test setup | ✅ | PHPUnit against MySQL `ibms_testing`; `FoundationConfigurationTest` verifies DB, strict mode, timezone, asset build — 6/6 passing |
| 4 | Authentication (Fortify) | ✅ | Login, logout, forgot/reset password, password confirmation, optional TOTP 2FA with recovery codes; Bootstrap Blade views; login throttling (5/min); deactivated accounts refused at login and signed out mid-session |
| 4 | Mandatory 2FA (decision F1) | ✅ | Super Administrator and System Administrator must confirm 2FA before using the app (`ibms.two_factor_required_roles`); others optional |
| 4 | Users administration | ✅ | List/filter, create (emailed set-password link, 24 h), edit, activate/deactivate, resend link, view/end sessions. Guards: only Super Admin assigns or manages Super Admins; no self-deactivation; no self-role-change (except Super Admin); at least one active Super Admin always remains |
| 4 | Session management | ✅ | Own and admin session lists identify sessions by HMAC-hashed keys — raw session IDs never reach the browser; "sign out other devices" requires password and rotates remember token |
| 4 | Roles & permissions | ✅ | Permission catalogue in code (`PermissionCatalog`), 10 system roles seeded with the doc 05 matrix; role editor with grouped permissions; Super Admin role read-only; users cannot edit roles they hold; system/assigned roles cannot be deleted |
| 4 | Audit logging foundation | ✅ | `Auditable` trait + `AuditLogger`; sign-in/out, failed logins, lockouts, resets, role/permission changes, sessions revoked; append-only `audit_logs`; audit log viewer with filters and before/after detail (`audit.view`) |
| 4 | Seeders | ✅ | `PermissionSeeder`, `RoleSeeder` (idempotent, preserves organization edits), `SuperAdminSeeder` from `IBMS_SUPER_ADMIN_*` env values |
| 4 | App shell (interim) | ✅ | Sidebar (permission-aware, off-canvas on mobile), top bar with account menu, flash messages, page header and form components — refined in Phase 5 |
| 4 | Tests | ✅ | 62 tests passing (auth, 2FA enforcement, users, account actions, sessions, roles, audit log, seeders, profile/password) |
| 5 | App layout | ✅ | Desktop sidebar collapses to icons (remembered per browser); notifications bell with unread count, recent items, "mark all as read" and a full notifications page; breadcrumbs; skip-to-content link; shared confirmation dialog for destructive actions |
| 5 | Shared Blade components | ✅ | stat-card, status-badge, status-history, money, breadcrumb, data-table, filter-bar, confirm-modal, form input/select/textarea/checkbox |
| 5 | Settings module | ✅ | Organization profile (contact, TIN, bank details, footer, signatory), logo upload (PNG/JPG/WebP ≤ 1 MB on the public disk), brand colour applied at runtime, organization name used in the UI, page titles and email sender; currency (code, symbol, display decimals) and finance defaults; tax rates (exactly one active default; rates deactivated, never deleted); system settings (date format, email notifications, low-stock check). Gated by `settings.organization` / `settings.finance` / `settings.system`; every change audited |
| 5 | NumberingService | ✅ | Per-type prefix, digits and reset period (never/yearly/monthly) for 10 document types; `SELECT … FOR UPDATE` inside the caller's transaction; rolled-back documents release their number; next number can move forward only (audited) |
| 5 | Money | ✅ | `App\Support\Money` on bcmath: exact add/subtract/multiply/divide/percentage, half-away-from-zero rounding to 2 dp, display formatting (UGX 0 decimals) |
| 5 | Status history | ✅ | `HasStatusHistory` trait + `StatusEnum` contract: allowed-transition checks, append-only `status_histories` with user and comment |
| 5 | Seeders | ✅ | `SettingsSeeder` (VAT 18 % default + Exempt 0 %, numbering config, current-period sequences), idempotent |
| 5 | Tests | ✅ | Full suite 142 tests passing (Money unit tests, numbering, settings service, settings pages, tax rates, notifications, status history, settings seeder) |
| 6 | Departments | ✅ | Code, name, parent (cycle-proof), head employee, active flag; cannot deactivate with current staff or delete with any staff/sub-departments (soft delete); `departments.manage` |
| 6 | Employees | ✅ | Auto number (EMP-0001), personal/contact/next-of-kin, department, job title, employment type (contract needs end date), hire date, optional linked user account (one employee per account); status workflow Active ↔ On leave / Suspended → Terminated (final, reason required, removes department headship, warns if the linked account is still active); never deleted |
| 6 | Employee record rules | ✅ | New permission `employees.view_department` (Department Head) limits visibility to departments the user's linked employee heads; `employees.view` sees all. NIN/TIN/NSSF and bank/mobile-money details only with `employees.view_salary`, never overwritten by users who cannot see them, masked as `[hidden]` in the audit log |
| 6 | Employee documents | ✅ | PDF/JPG/PNG/DOCX ≤ 5 MB (extension and content checked), private `local` disk under random names, download/removal only via authorized routes scoped to the employee; `employees.view_documents` / `employees.manage_documents` |
| 6 | Employment history | ✅ | Dated entries for department, job title, employment type and status changes (readable old → new values, effective date, notes, recorded by), plus status history timeline |
| 6 | Customers & suppliers | ✅ | Auto codes (CUS-0001 / SUP-0001, continuous numbering configurable in Settings), contact, TIN, terms, credit limit (customers), bank/mobile money (suppliers), active flag; deletion blocked once transactions exist (hook for later phases), otherwise soft delete; profile pages ready for transaction history |
| 6 | Tests | ✅ | Feature tests for customers, suppliers, departments, employees, status changes, documents and department-head visibility |
| 7 | Items & categories | ✅ | Auto codes (ITM-0001), stock/service type (locked once stock has moved), unit, selling price, standard cost, tax rate, minimum level, reorder quantity, active flag; categories nest one level; items/categories with history are deactivated, not deleted; `items.view` / `items.manage` |
| 7 | Warehouses | ✅ | Code, name, location; exactly one default (seeded "MAIN – Main Store"); cannot deactivate the default or a warehouse holding stock; `warehouses.manage` |
| 7 | StockService ledger | ✅ | Append-only `stock_movements` (model refuses update/delete) + `stock_levels` per item/warehouse updated in the same transaction under `lockForUpdate`; weighted average cost (4 dp, half-up); negative balances rejected in the service and by DB `CHECK`; reversals at the original cost (transfers reversed in both warehouses); public API `receive()`, `issue()`, `transfer()`, `recordOpeningBalance()`, `reverse()`, `reverseFor($document)` for invoices (Phase 9) and requisitions (Phase 11) |
| 7 | Goods received notes | ✅ | Draft → Posted (GRN-YYYY-NNNNN assigned on posting, stock added) → Void (stock reversed; refused if already issued); voiding needs `stock.adjust_approve` and cannot be done by the person who posted; supplier deletion now blocked once goods were received; supplier page lists GRNs |
| 7 | Issues, transfers, opening balances | ✅ | Direct forms with `stock.issue` / `stock.transfer` / `stock.adjust` (opening balance only before any other movement); reversal of these by a `stock.adjust_approve` holder other than the person who recorded them |
| 7 | Stock adjustments | ✅ | Multi-line ± changes with reason; always need approval (docs/07 C3) by a `stock.adjust_approve` holder other than the creator (Super Administrator included); approve applies stock, reject needs a reason, creator may cancel; approvers and creator notified in-app |
| 7 | Stock card, levels, movements | ✅ | Per-item stock card with opening/closing and running balance by warehouse and date range; stock levels with valuation; filterable movement ledger |
| 7 | Low stock & reconciliation | ✅ | In-app alert when a movement takes an item to/below its minimum (once per crossing, after commit) and daily 07:00 summary (`ibms:stock:low-stock-check`, honours the Settings switch) to users with `items.manage` or `stock.receive`; `ibms:stock:reconcile [--fix]` compares balances with the ledger (weekly report scheduled; fixes are audited) |
| 7 | Tests | ✅ | Full suite 270 tests passing (76 new): ledger/average-cost/reversal/negative-stock/rollback tests, GRN and adjustment workflows with segregation of duties, operations, items, categories, warehouses, commands, and a role × ability matrix |
| 8–15 | Remaining implementation | ⬜ | Next: Phase 8 (quotations) |
