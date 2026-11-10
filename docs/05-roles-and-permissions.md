# 05 — Roles & Permissions

Version 0.1 (draft for review) — 2026-11-10

Authorization is always checked against **permissions**. Roles are editable bundles of permissions.
Policies add record-level rules on top (ownership, document state, self-approval prevention).

## 1. Permission catalogue

Format `module.action`. Seeded by `PermissionSeeder`; not editable in the UI (code defines them).

| Module | Permissions |
|--------|-------------|
| Dashboard | `dashboard.view`, `dashboard.view_financials`, `dashboard.view_payroll` |
| Users | `users.view`, `users.create`, `users.update`, `users.deactivate`, `roles.manage`, `sessions.revoke` |
| Customers | `customers.view`, `customers.create`, `customers.update`, `customers.delete` |
| Suppliers | `suppliers.view`, `suppliers.create`, `suppliers.update`, `suppliers.delete` |
| Quotations | `quotations.view`, `quotations.create`, `quotations.update`, `quotations.delete`, `quotations.approve`, `quotations.send`, `quotations.convert`, `quotations.cancel` |
| Invoices | `invoices.view`, `invoices.create`, `invoices.update`, `invoices.delete`, `invoices.issue`, `invoices.void` |
| Payments | `payments.view`, `payments.record`, `payments.reverse` |
| Expenses | `expenses.view`, `expenses.create`, `expenses.void` |
| Inventory | `items.view`, `items.manage`, `warehouses.manage`, `stock.view`, `stock.receive`, `stock.issue`, `stock.transfer`, `stock.adjust`, `stock.adjust_approve` |
| Employees | `employees.view`, `employees.create`, `employees.update`, `employees.view_salary`, `employees.view_documents`, `employees.manage_documents`, `departments.manage` |
| Payroll | `payroll.view`, `payroll.prepare`, `payroll.approve`, `payroll.mark_paid`, `payroll.configure`, `payslips.view_own` |
| Requisitions | `requisitions.create`, `requisitions.view_own`, `requisitions.view_department`, `requisitions.view_all`, `requisitions.approve`, `requisitions.fulfil` |
| Approvals | `approvals.configure` |
| Reports | `reports.financial`, `reports.sales`, `reports.inventory`, `reports.payroll`, `reports.requisitions`, `reports.export` |
| Settings | `settings.organization`, `settings.finance` (currency, tax, numbering), `settings.system` |
| Audit | `audit.view` |

## 2. Default role matrix

● full · ◐ partial (see note) · — none

| Area | Super Admin | Sys Admin | Manager | Finance Off. | Accountant | HR Off. | Procurement | Inventory Off. | Dept Head | Employee |
|------|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Users & roles | ● | ● | — | — | — | — | — | — | — | — |
| Settings | ● | ◐¹ | — | ◐² | — | — | — | — | — | — |
| Audit log | ● | ● | ● | — | — | — | — | — | — | — |
| Customers | ● | — | view | ● | ● | — | — | — | — | — |
| Suppliers | ● | — | view | view | view | — | ● | view | — | — |
| Quotations | ● | — | ● incl. approve | ● | view | — | — | — | — | — |
| Invoices | ● | — | view | ● incl. void | create/issue | — | — | — | — | — |
| Payments/receipts | ● | — | view | ● incl. reverse | record | — | — | — | — | — |
| Expenses | ● | — | view | ● | ● | — | create | — | — | — |
| Inventory | ● | — | view | view | view | — | receive | ● (adjust needs approval) | view | — |
| Employees | ● | — | view (no salary) | — | — | ● | — | — | own dept view | — |
| Payroll | ● | — | approve | approve, mark paid | prepare | prepare | — | — | — | own payslips |
| Requisitions | ● | — | approve, view all | approve, view all | view all | create | fulfil, view all | fulfil | approve, view dept | create, view own |
| Reports | ● | — | all | financial, sales, payroll | financial, sales | payroll | inventory, requisitions | inventory | requisitions | — |

¹ System settings only (not finance). ² Currency, tax and numbering.

All roles include `dashboard.view`, `requisitions.create`, `requisitions.view_own`, `payslips.view_own`.
The matrix is a starting proposal for the organization to adjust **[CONFIRM]**.

## 3. Record-level rules (policies)

- A user never approves a document they created or requested, and acts on at most one step per document.
- The payroll preparer cannot approve the same run.
- A stock adjustment creator cannot approve it.
- Department Heads see requisitions and employees of departments they head.
- Issued invoices, receipts, approved payroll and stock movements are read-only to everyone, including Super Admin; changes happen only through void/reverse actions.
- Super Admin bypasses permission checks (`Gate::before`) but **not** the state and segregation-of-duty rules above.
