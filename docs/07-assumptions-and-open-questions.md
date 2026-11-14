# 07 — Assumptions & Open Questions

Last updated: 2026-11-11

These are decisions about organizational policy that should not be invented by the developer.
Each has a **proposed default**: if you agree, no action is needed; otherwise state the change.

## A. Blocking before Phase 3 (project initialization)

| # | Question | Decision / status |
|---|----------|-------------------|
| A1 | Organization name and logo | **Decided:** default name "Magezi IBM"; name, logo and branding are editable in Settings so the system can be customized for any business. |
| A2 | GitHub repository URL | **Decided:** https://github.com/Kthie32/Integration-Business-Management-System (remote `origin`). |
| A3 | Create `ibms`, `ibms_testing` and a dedicated `ibms` MySQL user | **Done.** |
| A4 | Delete `../example/`? | **Done (2026-11-10):** an earlier incomplete prototype with no git history or data; its useful parts were already rebuilt here (Money, numbering, middleware, settings), so it was deleted. |

## B. Finance & sales

| # | Question | Proposed default |
|---|----------|------------------|
| B1 | Currency: UGX only, or multiple currencies? | UGX only, displayed with 0 decimals, stored with 2 |
| B2 | Is the organization VAT-registered? Are prices VAT-exclusive or inclusive? | VAT 18 % configurable per item; prices VAT-exclusive |
| B3 | Quotation approval: always / above an amount / never? | Required above a configurable amount (default: never until set) |
| B4 | Invoice corrections: void-and-reissue only, or also credit notes? | Void-and-reissue (only when no payments); credit notes as a later enhancement |
| B5 | Can one payment cover several invoices? Overpayments / customer deposits? | One payment → one invoice; overpayment rejected |
| B6 | Does stock leave the warehouse when an invoice is issued, or via a separate delivery note? | On invoice issue, from the selected warehouse |
| B7 | **Expenses** module (needed for the dashboard "total expenses" and expense reports) — add it? | Yes, lightweight (doc 02 §3.8) |
| B8 | **Goods receiving** from suppliers (GRN) — sufficient, or are full Purchase Orders required? | GRN only; purchase orders later if needed |
| B9 | Will quotations/invoices/receipts be emailed to customers? Which SMTP provider? | PDF download/print only at first; email when SMTP is provided |
| B10 | When does a quotation get its number (FR-QUO-05)? | **Applied in Phase 8:** when the draft is first submitted, so deleted drafts never leave gaps; a draft returned for changes keeps its number and can then only be cancelled, not deleted |
| B11 | Quotation approval threshold behaviour | **Applied in Phase 8:** blank threshold = never; any amount (including 0) = approval needed when the total is at or above it; approver must hold `quotations.approve` and never be the author (Super Administrator included); approvers may approve or return the draft with a comment |
| B12 | Revisions of finalised quotations (FR-QUO-04) | **Applied in Phase 8:** approved, sent, rejected or expired quotations can be revised; the revision is a draft numbered `<original>-R1`, `-R2` …; the original stays valid until the revision is submitted, then becomes "Superseded"; accepted, cancelled and superseded quotations cannot be revised |
| B13 | Customer acceptance of an expired quotation | **Applied in Phase 8:** refused — prepare a revision with a new validity date instead |
| B14 | When does an invoice get its number, and when does stock leave? | **Applied in Phase 9:** on issue (FR-IVC-03), in one transaction with the stock issue; if any stocked line is short of stock nothing is issued and the number is released. Lines for the same item are combined into one `sale_issue` movement; service and free-text lines move no stock; a warehouse is required only when the invoice has stocked items (default warehouse pre-selected). Invoices cannot be issued with a future date or a zero total |
| B15 | Due date | **Applied in Phase 9:** editable on the draft; left empty it is the invoice date + the customer's payment terms (organization default if none) |
| B16 | Void and corrections (B4) | **Applied in Phase 9:** only an *issued* invoice with no completed payments can be voided (`invoices.void`, reason required); its stock movements are reversed; a "corrected copy" creates a new draft linked to the voided invoice. No segregation of duties on voiding (Finance Officer by default) |
| B17 | Converting a quotation (FR-QUO-08) | **Applied in Phase 9:** creates a draft invoice linked to the quotation, keeping the tax rate the customer accepted; deleting that draft releases the quotation so it can be converted again; requires both `quotations.convert` and `invoices.create` |
| B18 | Payment rules (B5) | **Applied in Phase 9:** one payment → one issued invoice; amount > 0 and ≤ balance (overpayments rejected); date not in the future nor before the invoice date; every method except cash needs a transaction reference, unique per method among completed payments (a reversed payment frees its reference); payments are numbered `PMT-YYYY-NNNNN`; reversal needs `payments.reverse` and a reason and returns the invoice to Partially paid / Issued |
| B19 | Receipts | **Applied in Phase 10:** a receipt (`RCT-YYYY-NNNNN`) is issued automatically for every payment in the same transaction and cannot be created any other way; reversing the payment (from the payment or the receipt) voids the receipt — status `valid` → `void`, never deleted. Receipts are numbered from their payment date. Existing payments were given receipts when the Phase 10 migration ran |
| B20 | Overdue invoices and credit limits | **Applied in Phase 9:** "Overdue" is derived (issued/partially paid, balance > 0, due date passed) and shown as a badge and filter; a daily 07:15 check (`ibms:invoices:overdue-check`) sends one in-app summary of newly overdue invoices to users who record payments or void invoices, each invoice announced once. A customer credit limit only produces a warning on the draft, it does not block issuing |
| B21 | Expenses (B7) | **Applied in Phase 10:** lightweight module per doc 02 §3.8. `amount` is the total paid including tax, `tax_amount` the tax part (0 ≤ tax ≤ amount). Lifecycle Draft → Recorded → Void: drafts are editable and deletable and carry no number; recording assigns `EXP-YYYY-NNNNN` and locks the expense; recorded expenses are voided with a reason (`expenses.void`), never deleted. No approval step yet — the Phase 11 approval engine can add one between Draft and Recorded. Payment method and reference follow the payment rules (reference required except for cash). One optional attachment (PDF/JPG/PNG ≤ 5 MB, private disk). Expense categories are finance configuration managed with `settings.finance` (no new permission); a starter list is seeded once |
| B22 | Customer statement | **Applied in Phase 10:** a running statement of account, not an open-item statement. Invoices count on their invoice date, payments on their payment date, payment reversals and invoice voids on the day they happened, so a past statement never changes later. Drafts are excluded. Default period: 1 January of the current year to today |

## C. Inventory

| # | Question | Proposed default |
|---|----------|------------------|
| C1 | Multiple warehouses/stores? | Supported; one default seeded |
| C2 | Stock valuation method | Weighted average cost |
| C3 | Do stock adjustments need approval? | Yes, by `stock.adjust_approve` holder other than the creator |
| C4 | Batch/expiry or serial-number tracking? | Not required |
| C5 | Who may void a posted goods received note or reverse a direct issue/transfer? | **Applied in Phase 7:** a `stock.adjust_approve` holder (Manager by default) who did not record it — the same segregation as adjustments |
| C6 | Item codes | **Applied in Phase 7:** continuous `ITM-0001` numbering, configurable in Settings → Document numbering |
| C7 | Valuation for stock received with no known cost (adjustment increases) | **Applied in Phase 7:** the warehouse's current average cost, falling back to the item's standard cost |

## D. HR & payroll (Uganda)

| # | Question | Proposed default |
|---|----------|------------------|
| D1 | Pay frequency | **Applied in Phase 12:** monthly regular runs only, one per month (current or next month at the latest) |
| D2 | Statutory rules to seed — **must be verified against current URA and NSSF publications before go-live**. Developer's current understanding (unverified): PAYE resident monthly bands 0–235,000 @ 0 %; 235,001–335,000 @ 10 %; 335,001–410,000 @ 20 % (+10,000); above 410,000 @ 30 % (+25,000), plus an extra 10 % on income above 10,000,000. NSSF 5 % employee + 10 % employer on gross. LST per local-government schedule, annual max UGX 100,000, deducted in instalments. | **Applied in Phase 12:** seeded from 1 Jul 2024 exactly as stated, marked unverified; LST schedule seeded as 0 up to 100,000, then 5,000 / 10,000 / 20,000 / 30,000 / 40,000 / 60,000 / 70,000 / 80,000 / 90,000 per 100,000 band of monthly gross pay and 100,000 above 1,000,000 a year, in four equal instalments July–October. NSSF on basic + NSSF-applicable allowances. NSSF and LST are not deducted before PAYE. Unverified rates are flagged on every open run and on Statutory rates, where `payroll.configure` holders correct them, add new effective-dated versions and mark them verified with a source |
| D3 | Non-resident employees, or other statutory items (e.g. withholding on contractors)? | **Applied in Phase 12:** residents by default; a non-resident PAYE rule set (unverified: 10 % from the first shilling, 20 % above 335,000, 30 % above 410,000, plus 10 % above 10,000,000) is seeded and used for employees not marked resident for tax. No contractor withholding |
| D4 | Common other deductions: salary advances, loans, SACCO, union dues? Should loans have repayment schedules? | **Applied in Phase 12:** generic components (seeded: housing, transport, lunch allowances; salary advance recovery, loan repayment, SACCO deductions); no loan schedules |
| D5 | Who approves payroll? | **Applied in Phase 12:** no payroll workflow is seeded, so a run goes to any `payroll.approve` holder (Manager, Finance Officer); a workflow for "Payroll runs" can be configured. Whoever created, calculated or submitted the run cannot approve it, Super Administrators included |
| D6 | Payroll corrections after approval | **Applied in Phase 12:** approved and paid runs are locked; corrections are one-off adjustments (allowance or deduction, taxable / NSSF flags) in the next open run. Approved months' salaries cannot be changed retroactively. Pay is not pro-rated for part months (use an adjustment) |
| D7 | Bank or mobile money payment files in a specific bank format? | **Applied in Phase 12:** generic payment list (bank / mobile money / cash per employee) as Excel, PDF and print |
| D8 | Leave and attendance management? | Out of scope (not in brief) |

## E. Requisitions & approvals

| # | Question | Proposed default |
|---|----------|------------------|
| E1 | Default workflow and amount thresholds | **Applied in Phase 11:** seeded "Standard requisition approval": Department head → Finance (Finance Officer role) → Management (Manager role). No threshold is set, so the Manager step applies to every amount until a minimum amount is entered on it in Approval workflows. Role steps need the role plus `requisitions.approve`; Super Administrators count as members of every role. Workflow configuration needs `approvals.configure` (Super Administrator by default) |
| E2 | What happens if the requester is the department head? | **Applied in Phase 11:** the step is skipped (reason recorded) and routing continues; the same applies when the department has no head or the only eligible approver is inactive or already acted. If every step is skipped, a final step for any `requisitions.approve` holder other than the requester is added |
| E3 | Does fulfilment generate a purchase from a supplier (GRN/expense) or only stock issues? | **Applied in Phase 11:** catalogue stock items are always issued from stock (buy them in with a goods received note first if the store is short); non-stock and free-text lines are purchased into one draft expense per delivery, linked to the requisition, which Finance records with payment details. Approved quantity = requested quantity (approvers return the request to change quantities). Fulfilment date is today; fulfilments are not reversed in this phase |

## F. Security & operations

| # | Question | Proposed default |
|---|----------|------------------|
| F1 | Two-factor authentication | ~~Optional per user; mandatory for Super/System Admin~~ **Removed 2026-11-14 at the owner's request:** users sign in with email and password only (see docs/08) |
| F2 | Session idle timeout | 120 minutes |
| F3 | Hosting target and domain | Recommendation in Phase 15 (VPS preferred for queues/scheduler) |
| F4 | Existing data to import (customers, items, employees, opening stock balances)? | Import via Excel templates in Phase 13–15 if needed |

## H. Reports & dashboard

| # | Question | Proposed default |
|---|----------|------------------|
| H1 | Default reporting period | **Applied in Phase 13:** reports open on the current month up to today; any start/end date can be chosen. Balances, ageing and low stock are "as at today". The dashboard period selector offers this month, this quarter, this year or custom dates (invalid custom dates fall back to this month) |
| H2 | How payroll appears among expenses (FR-EXP-03) | **Applied in Phase 13:** as the "Payroll" category, one line per approved or paid run, costing gross pay plus employer NSSF, dated on the last day of its pay period. Department filters use the department recorded on each payslip. The dashboard expenses card shows operating expenses only; the revenue vs expenses chart adds payroll only for users who may see payroll figures |
| H3 | Revenue basis | **Applied in Phase 13:** revenue is cash received (completed payments, reversals excluded); invoiced sales are reported separately (invoice register, sales by customer / item). Void invoices are listed in the register but never totalled |
| H4 | Receivables ageing | **Applied in Phase 13:** open balances as at today, by days past the due date: current (not yet due), 1–30, 31–60, 61–90, 90+ |
| H5 | Quotation conversion rate | **Applied in Phase 13:** accepted ÷ quotations generated in the period, cancelled excluded; "generated" = numbered (submitted at least once), and superseded revisions are not counted so a revised quotation counts once |
| H6 | Who may export | **Applied in Phase 13:** running a report needs its own permission (`reports.financial`, `.sales`, `.inventory`, `.payroll`, `.requisitions`); PDF and Excel downloads also need `reports.export` and are audited (`report_exported`, with the filters); the print view needs only the report permission. Requisition reports follow requisition visibility (department heads see their departments) |

## G. Recorded engineering assumptions (no answer needed unless you object)

- PHPUnit (already installed) is used rather than Pest.
- Node.js/npm is used only to compile Bootstrap assets; no Node server.
- spatie/laravel-permission and Laravel Fortify are used for RBAC and authentication (doc 03 §2).
- Document numbers: `QT-2026-00001`, `INV-2026-00001`, `RCT-2026-00001`, `REQ-2026-00001`, `GRN-…`, `EXP-…`, `PAY-2026-11`, `EMP-00001`; yearly reset; configurable.
- Timezone Africa/Kampala; English UI.
