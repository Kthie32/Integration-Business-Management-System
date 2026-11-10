# 02 — Software Requirements Specification (SRS)

Version 0.1 (draft for review) — 2026-11-10

Requirement IDs (`FR-QUO-03`, `NFR-SEC-02`, …) are referenced by tests and the progress report.
Items marked **[CONFIRM]** depend on answers in `07-assumptions-and-open-questions.md`.

---

## 1. Introduction

### 1.1 Purpose
Defines the functional and non-functional requirements of IBMS, the acceptance basis for each module.

### 1.2 Users
Super Administrator, System Administrator, Manager, Finance Officer, Accountant, HR Officer,
Procurement Officer, Inventory Officer, Department Head, General Employee. Roles are bundles of granular
permissions (see `05-roles-and-permissions.md`); access decisions are always made on permissions.

### 1.3 Definitions
- **Document** — a numbered business record (quotation, invoice, receipt, requisition, payslip, payroll run).
- **Issued / posted** — a document that has left draft and become part of the business record; it is
  immutable except through controlled actions (void, reverse, cancel).
- **Stock movement** — an immutable ledger entry that changes stock quantity. The ledger is the source of truth.

---

## 2. Cross-cutting functional requirements

| ID | Requirement |
|----|-------------|
| FR-GEN-01 | Every list screen supports search, filters, column sorting and pagination. |
| FR-GEN-02 | Every create/update validates on the server (Form Requests) and shows field-level errors. |
| FR-GEN-03 | Every action is authorized on the server (policies/gates); UI hides what a user cannot do, but is never relied on. |
| FR-GEN-04 | Every create/update/delete/state change writes an audit log entry (who, what, which record, when, before/after values). |
| FR-GEN-05 | Draft records may be deleted (soft delete) by authorized users. Issued/approved/paid records cannot be deleted; they are voided, cancelled or reversed with a mandatory reason. |
| FR-GEN-06 | Document numbers are unique, sequential, gap-controlled and generated at the moment a document is issued/created under a database lock. Format configurable per document type (prefix, padding, yearly reset). |
| FR-GEN-07 | All monetary values are stored as `DECIMAL(15,2)` and calculated with exact decimal arithmetic (no floating point). Rounding rule: half-up to 2 dp at line level. |
| FR-GEN-08 | Multi-record operations (issue invoice, record payment, post stock, run payroll, fulfil requisition) run in a single database transaction. |
| FR-GEN-09 | Users receive in-app notifications for items awaiting their action (approvals, low stock). Email notifications configurable **[CONFIRM]**. |
| FR-GEN-10 | Success/error flash messages and confirmation dialogs for destructive or irreversible actions. |

---

## 3. Module requirements

### 3.1 Dashboard (DSH)
| ID | Requirement |
|----|-------------|
| FR-DSH-01 | Summary cards: quotations (count, by status), invoices (count, value), revenue collected (period), outstanding receivables, overdue invoices, total expenses (period), stock value/item count, low-stock count, employees (active), last payroll total, pending requisitions, pending approvals for the current user. |
| FR-DSH-02 | Charts: monthly revenue vs expenses (12 months), invoice status breakdown, top customers, payroll trend. |
| FR-DSH-03 | Recent transactions list (payments, invoices issued, stock movements). |
| FR-DSH-04 | Each widget is shown only if the user holds the corresponding view permission. |
| FR-DSH-05 | Period selector (this month / quarter / year / custom). |

### 3.2 Users, roles & permissions (USR)
| ID | Requirement |
|----|-------------|
| FR-USR-01 | No public self-registration. Users are created by holders of `users.create`. A new user receives a set-password link. |
| FR-USR-02 | Login (email + password), logout, "remember me", password reset by email. |
| FR-USR-03 | Login throttling: 5 failed attempts per minute per email+IP, then lockout. |
| FR-USR-04 | Users can edit their own profile (name, phone, photo) and change their password (current password required). |
| FR-USR-05 | Activate/deactivate users. Deactivated users cannot log in and active sessions are terminated. |
| FR-USR-06 | Create/edit roles and assign permissions per role; users may hold multiple roles; direct per-user permissions supported. |
| FR-USR-07 | Super Administrator role cannot be deleted, edited away or removed from the last super admin. |
| FR-USR-08 | Optionally link a user account to an employee record (needed for department-head approval routing and "my payslips"). |
| FR-USR-09 | Sessions stored in the database; admins can view and revoke a user's active sessions. |
| FR-USR-10 | Optional two-factor authentication (TOTP) **[CONFIRM]**. |

### 3.3 Customers & suppliers (CUS / SUP)
| ID | Requirement |
|----|-------------|
| FR-CUS-01 | CRUD customers: code (auto), name, type (individual/company), TIN, contact person, phone, email, address, payment terms (days), credit limit (optional), notes, active flag. |
| FR-CUS-02 | Customer profile shows quotation, invoice and payment history and current outstanding balance. |
| FR-CUS-03 | A customer with transactions cannot be deleted, only deactivated. |
| FR-SUP-01 | CRUD suppliers: code (auto), name, TIN, contacts, address, bank/mobile money details, payment terms, active flag. |
| FR-SUP-02 | Supplier profile shows goods received, expenses and requisition fulfilments linked to the supplier. |
| FR-SUP-03 | A supplier with transactions cannot be deleted, only deactivated. |

### 3.4 Inventory (INV)
| ID | Requirement |
|----|-------------|
| FR-INV-01 | Item categories (hierarchical, one level of nesting). |
| FR-INV-02 | Items: code (auto), name, description, category, type (`stock` or `service`), unit of measure, selling price, cost price, tax rate, minimum stock level, reorder quantity, active flag. Service items carry no stock. |
| FR-INV-03 | Warehouses/stores: code, name, location, active flag. A default warehouse is seeded. |
| FR-INV-04 | Movement types: opening balance, goods received, issue, transfer out/in, adjustment (+/−), sale issue, return. Each movement records item, warehouse, quantity, unit cost, reference document, user, date, notes. |
| FR-INV-05 | Movements are immutable. Corrections are made with a reversing movement. |
| FR-INV-06 | Current balance per item per warehouse is maintained in a balance table updated in the same transaction as the movement, with row locks. A reconciliation command verifies balances against the ledger. |
| FR-INV-07 | Any movement that would take a balance below zero is rejected. |
| FR-INV-08 | Stock adjustments require `stock.adjust` permission and a reason; adjustments above a configurable value require approval **[CONFIRM]**. |
| FR-INV-09 | Low-stock alert when balance ≤ minimum level: dashboard widget, list filter and in-app notification to inventory/procurement roles (daily scheduled check plus on-movement check). |
| FR-INV-10 | Item stock card: chronological movements with running balance. |
| FR-INV-11 | Goods received note (GRN): receive one or more items from a supplier into a warehouse with quantities and unit costs; creates `goods_received` movements **[CONFIRM scope]**. |
| FR-INV-12 | Stock valuation using weighted average cost **[CONFIRM]**. |

### 3.5 Quotations (QUO)
| ID | Requirement |
|----|-------------|
| FR-QUO-01 | Create quotation: customer (select or quick-add), date, validity date (default from settings, e.g. 30 days), currency, line items (catalogue item or free-text service), quantity, unit price, line discount, tax rate; document-level discount; notes and terms. |
| FR-QUO-02 | Totals calculated server-side: line subtotal = qty × price − line discount; tax per line; document discount allocated pro-rata before tax; grand total. Client-side preview uses the same rules but is not trusted. |
| FR-QUO-03 | Statuses: Draft → Pending Approval → Approved → Sent → Accepted / Rejected / Expired; Cancelled from any pre-accepted state. Approval step can be configured as always / above amount / never **[CONFIRM]**. |
| FR-QUO-04 | Only drafts are editable. Editing an approved/sent quotation creates a new revision (`QT-2026-00012-R1`) and supersedes the old one; history preserved. |
| FR-QUO-05 | Quotation number assigned on first save (draft) **[CONFIRM: or on approval]**. |
| FR-QUO-06 | Scheduled job marks quotations past validity as Expired. |
| FR-QUO-07 | PDF download and print view with organization branding. |
| FR-QUO-08 | Convert an Accepted quotation into a draft invoice (copies lines; links both ways). A quotation converts once. |
| FR-QUO-09 | Status history (who/when/comment) shown on the quotation. |

### 3.6 Invoices (IVC)
| ID | Requirement |
|----|-------------|
| FR-IVC-01 | Create invoice from accepted quotation or independently; same line/discount/tax rules as quotations. |
| FR-IVC-02 | Statuses: Draft → Issued → Partially Paid → Paid; Void. "Overdue" is derived (issued, balance > 0, due date passed) and displayed as a badge/filter. |
| FR-IVC-03 | Invoice number assigned when issued. Drafts can be edited/deleted; issued invoices are locked. |
| FR-IVC-04 | Due date = issue date + customer payment terms (editable before issue). |
| FR-IVC-05 | Issuing an invoice that contains stock items issues stock from a chosen warehouse (`sale_issue` movement) **[CONFIRM — or stock leaves via a separate delivery step]**. |
| FR-IVC-06 | Correction: an issued invoice with no payments can be voided (reason required, `invoices.void`), which reverses stock movements; a corrected copy can be created from it. Invoices with payments must have payments reversed first. Credit notes are a later enhancement **[CONFIRM]**. |
| FR-IVC-07 | Amount paid and balance are derived from non-reversed payments, maintained on the invoice in the same transaction as each payment. |
| FR-IVC-08 | PDF download, print, payment history on the invoice view. |

### 3.7 Payments & receipts (PAY / RCT)
| ID | Requirement |
|----|-------------|
| FR-PAY-01 | Record a payment against an issued invoice: date, amount, method (cash, bank transfer, mobile money, cheque, card), transaction reference, payer, notes. |
| FR-PAY-02 | Amount must be > 0 and ≤ invoice balance (overpayment / customer credit handling **[CONFIRM]**). |
| FR-PAY-03 | Transaction reference is required for non-cash methods and unique per method. |
| FR-PAY-04 | On successful payment, a receipt with a unique number is generated in the same transaction. A receipt cannot be created any other way. |
| FR-PAY-05 | Reversal: `payments.reverse` permission, mandatory reason; payment and receipt marked reversed (never deleted), invoice balance restored, audit logged. |
| FR-PAY-06 | Receipt PDF/print; payment history per invoice and per customer; customer statement of account. |

### 3.8 Expenses (EXP) — proposed supporting module **[CONFIRM]**
| ID | Requirement |
|----|-------------|
| FR-EXP-01 | Record expenses: date, category, supplier (optional), department, amount, tax, payment method, reference, description, attachment. |
| FR-EXP-02 | Expenses can originate from requisition fulfilment or be entered directly (permission `expenses.create`). |
| FR-EXP-03 | Recorded expenses are voided, not deleted. Payroll totals are reported as an expense category on reports. |

### 3.9 Employees (EMP)
| ID | Requirement |
|----|-------------|
| FR-EMP-01 | Departments: code, name, parent (optional), head (employee), active flag. |
| FR-EMP-02 | Employee: number (auto), names, gender, date of birth, national ID (NIN), TIN, NSSF number, phone, email, address, next of kin, department, job title, employment type (permanent/contract/casual), hire date, contract end date, status (active, on leave, suspended, terminated), termination date/reason, bank/mobile money details, optional linked user. |
| FR-EMP-03 | Salary information (basic salary, allowances, bank details) visible only with `employees.view_salary`. |
| FR-EMP-04 | Employee documents: upload (PDF/JPG/PNG/DOCX, max 5 MB), stored on a private disk, downloadable only through an authorized route. |
| FR-EMP-05 | Employment history: changes to department, job title, status and salary are recorded with effective date. |
| FR-EMP-06 | Employees with payroll history cannot be deleted; they are terminated/archived. |

### 3.10 Payroll (PRL)
| ID | Requirement |
|----|-------------|
| FR-PRL-01 | Pay components: allowances and deductions, each with calculation (fixed amount / % of basic / % of gross), taxable flag, NSSF-able flag. |
| FR-PRL-02 | Employee salary structure: basic salary + assigned components with amounts, all effective-dated. |
| FR-PRL-03 | Statutory rules (PAYE bands, NSSF employee/employer rates, LST schedule) stored as effective-dated configuration, editable by authorized users, never hard-coded. Seeded values must be verified against URA/NSSF publications before go-live. |
| FR-PRL-04 | Payroll run per month: Draft → Calculated → Pending Approval → Approved → Paid. Recalculation allowed until approval. |
| FR-PRL-05 | Per employee: gross = basic + allowances; taxable pay; PAYE; NSSF employee (deduction) and employer (cost); LST where applicable; other deductions; net pay. Line-level breakdown stored, so a payslip can always be reproduced. |
| FR-PRL-06 | Approved and paid runs are locked. Corrections via an adjustment in the next run **[CONFIRM]**. No deletion after approval. |
| FR-PRL-07 | Payslips (PDF) per employee; employees with a linked user can view their own payslips. |
| FR-PRL-08 | Payroll summary, deductions schedule (PAYE, NSSF) and bank/mobile money payment list exports (PDF/Excel). |
| FR-PRL-09 | Access restricted to `payroll.*` permissions; the person who prepared a run cannot approve it. |

### 3.11 Requisitions & approvals (REQ / APR)
| ID | Requirement |
|----|-------------|
| FR-REQ-01 | Create requisition: department, date needed, purpose/justification, priority, lines (catalogue item or free text, quantity, estimated unit cost), attachments. |
| FR-REQ-02 | Statuses: Draft → Submitted → In Approval → Approved / Rejected → Partially Fulfilled → Fulfilled; Cancelled by requester before final approval. |
| FR-REQ-03 | Approval workflows are configurable: ordered steps, each resolved to a role, the department head of the requesting department, or a named user; optional minimum amount per step. Default: Department Head → Finance Officer → Manager. |
| FR-REQ-04 | At each step, an approver approves, rejects (comment mandatory) or returns for revision. All decisions are recorded with timestamp and comment. |
| FR-REQ-05 | No user can approve a requisition they created, nor act on more than one step of the same request. |
| FR-REQ-06 | Fulfilment (by procurement/inventory roles) of approved lines: stock items → `issue` stock movement from a warehouse; purchased goods/services → expense record (and GRN where goods are stocked). Quantities cannot exceed approved quantities. |
| FR-REQ-07 | Requisition PDF showing approval trail and signatures block. |
| FR-APR-01 | The approval engine is generic (polymorphic) and reused by quotations, stock adjustments and payroll runs. |
| FR-APR-02 | "My approvals" inbox lists items awaiting the current user. |

### 3.12 Reports (RPT)
| ID | Requirement |
|----|-------------|
| FR-RPT-01 | Financial: revenue (payments received), expenses, payments by method, receivables aging (current, 1–30, 31–60, 61–90, 90+), invoice register, receipt register, customer statement. |
| FR-RPT-02 | Quotations: generated, accepted, rejected, pending, conversion rate. |
| FR-RPT-03 | Inventory: stock balances (with valuation), stock received, issued, adjustments, low stock, item stock card. |
| FR-RPT-04 | Payroll: monthly payroll register, salary summary by employee/department, deductions (PAYE/NSSF/LST), payment status. |
| FR-RPT-05 | Requisitions: pending, approved, rejected, fulfilled, by department. |
| FR-RPT-06 | Every report: date range, department filter where applicable, sorting, summary cards/chart, PDF export, Excel export, print view. Each report gated by its own permission. |

### 3.13 Settings & audit (SET / AUD)
| ID | Requirement |
|----|-------------|
| FR-SET-01 | Organization profile: name, logo (PNG/JPG/SVG ≤ 1 MB), address, phone, email, website, TIN, bank details, document footer text, signatory names. |
| FR-SET-02 | Base currency (code, symbol, decimal places for display). |
| FR-SET-03 | Tax rates (name, rate, active, default). |
| FR-SET-04 | Document numbering per document type: prefix, padding, reset period (never/yearly), next number. |
| FR-SET-05 | Defaults: quotation validity days, invoice payment terms, low-stock check, quotation approval threshold. |
| FR-SET-06 | Notification preferences (in-app/email per event). |
| FR-AUD-01 | Audit log viewer with filters (user, module, action, date range); entries are append-only and cannot be edited or deleted from the application. |
| FR-AUD-02 | Logins, failed logins, logouts, permission changes and exports of sensitive data are audited. |

---

## 4. Non-functional requirements

| ID | Requirement |
|----|-------------|
| NFR-SEC-01 | Passwords hashed with bcrypt/argon2 (Laravel default); minimum 8 characters with mixed case & number. |
| NFR-SEC-02 | CSRF protection on all state-changing requests; Blade auto-escaping for output; Eloquent/query builder parameter binding only (no raw string interpolation). |
| NFR-SEC-03 | Authorization via policies on every controller action; route middleware as a second layer. |
| NFR-SEC-04 | Rate limiting on login, password reset and exports. |
| NFR-SEC-05 | File uploads: MIME + extension whitelist, size limits, random filenames, private storage, served via authorized controller. |
| NFR-SEC-06 | Secrets only in `.env` (never committed). Production: `APP_DEBUG=false`, HTTPS enforced, secure/HTTP-only/SameSite cookies, security headers. |
| NFR-SEC-07 | Session idle timeout (configurable, default 120 min); session ID regenerated on login. |
| NFR-PERF-01 | List pages respond < 1 s with 100k records per table (indexes, eager loading, pagination). |
| NFR-PERF-02 | Heavy exports run as queued jobs with a download notification when larger than a threshold. |
| NFR-USE-01 | Responsive from 360 px phones to desktop; WCAG 2.1 AA colour contrast, labelled form controls, keyboard navigable. |
| NFR-REL-01 | Database transactions and row locking for all financial and stock operations; daily backups (deployment phase). |
| NFR-MNT-01 | PSR-12 code style (Laravel Pint), Laravel conventions, service layer for business rules, automated tests for critical paths. |
| NFR-AUD-01 | All significant actions are traceable to a user and timestamp. |
| NFR-LOC-01 | Timezone Africa/Kampala; dates displayed `d M Y`; amounts with thousands separators. |
