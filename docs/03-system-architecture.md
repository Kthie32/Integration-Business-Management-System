# 03 — System Architecture

Version 0.1 (draft for review) — 2026-11-10

## 1. Architectural style

A server-rendered **Laravel 13 MVC monolith** with a service layer, deployed as a single application
backed by MySQL 8. A monolith fits a single organization's ERP: one deployable, transactional
consistency across modules, simple hosting.

```
 Browser (Bootstrap 5 + vanilla JS + Chart.js)
        │  HTTPS (session cookie, CSRF token)
        ▼
 ┌──────────────────────── Laravel application ────────────────────────┐
 │ Routes ─► Middleware (auth, active-user, permission, throttle)      │
 │        ─► Controller ─► Form Request (validation)                   │
 │                     ─► Policy (authorization)                       │
 │                     ─► Service (business rules, DB transaction)     │
 │                          ├─► Models / Eloquent ─► MySQL             │
 │                          ├─► NumberingService (locked sequences)    │
 │                          ├─► StockService (ledger + balances)       │
 │                          ├─► ApprovalService (workflow engine)      │
 │                          └─► Events ─► Listeners / Notifications    │
 │                     ─► Blade view  │  PDF (DomPDF)  │  Excel export │
 │ Queue worker: exports, emails, notifications                        │
 │ Scheduler: quotation expiry, low-stock check, overdue reminders     │
 └──────────────────────────────────────────────────────────────────────┘
```

### Layer responsibilities

| Layer | Responsibility | Must not |
|-------|----------------|----------|
| Routes | Resource routes grouped per module, behind `auth` + `active` middleware | Contain logic |
| Middleware | Authentication, deactivated-user lockout, coarse permission checks, throttling | Make business decisions |
| Form Requests | Input validation and normalization, `authorize()` delegating to policies | Write data |
| Policies | Per-record authorization (permission + ownership + state, e.g. "cannot approve own requisition") | Mutate state |
| Controllers | Orchestrate: validate → authorize → call service → return view/redirect/PDF | Hold business rules or calculations |
| Services | Business operations, calculations, state transitions, transactions, events | Return HTTP responses |
| Models | Relationships, casts, scopes, accessors; `$fillable` whitelists | Hold multi-model workflows |
| Views | Presentation via layouts and Blade components | Query the DB or compute totals |

## 2. Technology decisions

| Concern | Choice | Rationale |
|---------|--------|-----------|
| Framework | Laravel 13 (installed), PHP 8.5 | Mandated; latest stable compatible with environment |
| Database | MySQL 8.0, InnoDB, utf8mb4, strict mode | Mandated; row-level locking for financial/stock operations |
| Authentication | **Laravel Fortify** (headless) + custom Bootstrap Blade views | First-party; provides login, reset, throttling, optional 2FA without imposing Tailwind/React starter-kit UI |
| Authorization | **spatie/laravel-permission** + Laravel Policies/Gates | De-facto standard for granular role/permission RBAC on Laravel; integrates with `can()`/Gate; avoids reinventing a security-critical component |
| UI | Bootstrap 5.3 + Bootstrap Icons, bundled with Vite (replacing Tailwind) | Mandated; Vite is build tooling only |
| Charts | Chart.js (bundled) | Lightweight, no framework required |
| JS behaviour | Vanilla JS modules (line-item editors, confirm dialogs, select search via Tom Select) | No SPA framework per brief |
| PDF | **barryvdh/laravel-dompdf** | Mandated option; PHP-only, works on shared hosting |
| Excel | **maatwebsite/excel** (Laravel Excel) — compatibility with Laravel 13 verified at install; fallback `openspout/openspout` | Mandated option |
| Money maths | `App\Support\Money` value object backed by **bcmath** (extension present) | Exact decimal arithmetic; no floats |
| Audit trail | Custom `Auditable` trait + `AuditLogger` service writing to `audit_logs` | Full control over format (before/after diff), no extra dependency |
| Notifications | Laravel database notifications (+ mail channel when SMTP configured) | Built-in |
| Queue / cache / sessions | `database` driver (dev & shared hosting); Redis optional on VPS | Works everywhere; switchable via `.env` |
| Tests | PHPUnit 12 (already installed), MySQL test database `ibms_testing` | Tests exercise real MySQL locking/decimal behaviour |
| Code style | Laravel Pint (PSR-12) | Built-in |
| Dev tooling | Laravel Boost (dev only, per `AGENTS.md`), Laravel Pail | |

New dependencies are limited to: Fortify, spatie/laravel-permission, laravel-dompdf, Laravel Excel,
Bootstrap, Bootstrap Icons, Chart.js, Tom Select. Each is justified above.

## 3. Key design mechanisms

### 3.1 Money & calculations
- Columns: `DECIMAL(15,2)` for amounts, `DECIMAL(15,4)` for unit prices/costs, `DECIMAL(15,3)` for quantities, `DECIMAL(7,4)` for rates.
- One `DocumentCalculator` service computes quotation and invoice totals (line subtotal → line discount →
  pro-rata document discount → tax → total). Quotations and invoices share it; tests cover rounding cases.
- Totals are persisted on the document header when saved, so historical documents never change if tax rates change later. Line items store the tax rate *value* used, not just a reference.

### 3.2 Document numbering
`document_sequences` row per (document type, period). `NumberingService::next('invoice')` runs inside
the caller's transaction with `SELECT … FOR UPDATE`, guaranteeing unique numbers under concurrency.
Unique DB indexes on every number column are the final safeguard.

### 3.3 Immutable records & state machines
- Each document has a PHP backed enum for status with an explicit allowed-transitions map.
  Services call `$doc->transitionTo(Status::Issued)`, which throws on illegal transitions.
- Every transition is written to a `status_histories` (polymorphic) table: from, to, user, comment, time.
- Issued/approved records reject updates at policy *and* service level. Drafts use soft deletes.

### 3.4 Inventory ledger
- `stock_movements` is append-only (no update/delete routes; model guards against updates).
- `stock_levels(item_id, warehouse_id)` holds the current quantity and weighted average cost, updated
  in the same transaction as the movement, with `lockForUpdate()` on the balance row.
- Negative stock is rejected in `StockService`, and a DB `CHECK (quantity >= 0)` gives a final guard.
- `php artisan ibms:stock:reconcile` recomputes balances from the ledger and reports mismatches.

### 3.5 Approval engine
- `approval_workflows` (per document type, with optional amount threshold) → ordered `approval_workflow_steps`
  (approver type: role / department head / specific user).
- When a document is submitted, `ApprovalService` snapshots the steps into `approvals` rows (polymorphic
  `approvable`), so later workflow edits don't affect in-flight requests.
- Approve/reject at the current step is allowed only if the user resolves to that step's approver,
  is not the requester, and has not acted on an earlier step. Final approval fires a `DocumentApproved` event.

### 3.6 Payroll engine
- `PayrollCalculator` takes an employee's effective salary structure and the effective statutory rule set
  for the period, and returns a breakdown (pure function, heavily unit-tested).
- Statutory rules live in `statutory_rates` as effective-dated JSON (e.g. PAYE bands), editable in settings.
- `PayrollService` creates the run, calculates every active employee in one transaction, and stores
  `payroll_items` and `payroll_item_lines` so payslips are reproducible after rules change.

### 3.7 Audit logging
`Auditable` trait on models hooks `created/updated/deleted/restored` and records changed attributes
(excluding secrets like passwords and tokens). Services log domain actions explicitly (issue, void,
approve, reverse, export). `audit_logs` has no update/delete path in the application.

### 3.8 Security controls (summary)
Fortify login throttling · bcrypt hashing · CSRF middleware · Blade escaping · Eloquent bindings ·
policies on every action · `EnsureUserIsActive` middleware · private disk for documents/payroll files ·
signed, authorized download routes · security headers middleware · `.env` only for secrets ·
HTTPS + secure cookies in production · sensitive fields (salary, bank details, NIN) hidden unless permitted.

## 4. Folder structure

```
business-site/
├── app/
│   ├── Enums/                  QuotationStatus, InvoiceStatus, PaymentMethod, StockMovementType, …
│   ├── Events/  Listeners/     DocumentApproved, PaymentRecorded, StockBelowMinimum, …
│   ├── Exceptions/             InvalidStatusTransition, InsufficientStock, SelfApprovalNotAllowed
│   ├── Exports/                Laravel Excel export classes per report
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/  Dashboard/  Sales/ (Quotation, Invoice, Payment, Receipt, Customer)
│   │   │   ├── Inventory/ (Item, Category, Warehouse, StockMovement, GoodsReceipt)
│   │   │   ├── HR/ (Department, Employee, EmployeeDocument)  Payroll/  Procurement/ (Requisition, Supplier)
│   │   │   ├── Finance/ (Expense)  Approvals/  Reports/  Admin/ (User, Role, Setting, AuditLog)
│   │   ├── Middleware/         EnsureUserIsActive, SecurityHeaders
│   │   └── Requests/<Module>/  Store…Request, Update…Request
│   ├── Models/                 Eloquent models (+ Concerns/Auditable, HasStatusHistory, HasDocumentNumber)
│   ├── Notifications/
│   ├── Policies/
│   ├── Providers/
│   ├── Services/
│   │   ├── Sales/              DocumentCalculator, QuotationService, InvoiceService, PaymentService
│   │   ├── Inventory/          StockService, GoodsReceiptService
│   │   ├── Payroll/            PayrollCalculator, PayrollService
│   │   ├── Approval/           ApprovalService
│   │   ├── Requisition/        RequisitionService, FulfilmentService
│   │   ├── Reports/            one query class per report
│   │   └── System/             NumberingService, AuditLogger, SettingsService, PdfService
│   └── Support/                Money
├── config/ibms.php             app-specific defaults (non-secret)
├── database/
│   ├── migrations/             one migration per table, ordered by dependency
│   ├── factories/              for tests only
│   └── seeders/                PermissionSeeder, RoleSeeder, SettingsSeeder, StatutoryRateSeeder,
│                               SuperAdminSeeder (reads credentials from .env), DemoDataSeeder (local only)
├── docs/                       this documentation set
├── resources/
│   ├── js/                     app.js, modules/line-items.js, confirm.js, charts.js
│   ├── scss/                   app.scss (Bootstrap + theme variables)
│   └── views/
│       ├── layouts/            app (sidebar + topbar), guest (auth), print, pdf
│       ├── components/         stat-card, data-table, filter-bar, form/*, status-badge, modal, confirm, breadcrumb, money
│       ├── <module>/           index, create, edit, show (+ _form partials)
│       └── pdf/                quotation, invoice, receipt, payslip, requisition, report
├── routes/
│   ├── web.php                 includes routes/modules/*.php
│   ├── modules/                sales.php, inventory.php, hr.php, payroll.php, procurement.php, reports.php, admin.php
│   └── console.php             scheduled tasks
└── tests/
    ├── Unit/                   Money, DocumentCalculator, PayrollCalculator, Numbering
    └── Feature/<Module>/       HTTP + permission + workflow tests
```

## 5. Environments

| | Local | Testing | Production |
|-|-------|---------|------------|
| DB | `ibms` on local MySQL, user `ibms` | `ibms_testing`, refreshed per test run | Managed/VPS MySQL, dedicated least-privilege user |
| Debug | on | off | off |
| Mail | log driver | array | SMTP **[CONFIRM provider]** |
| Queue | database (sync allowed) | sync | database or Redis + supervisor/cron worker |
| Seed data | roles, permissions, settings, demo data | factories | roles, permissions, settings, super admin only — no demo data |
