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
| B19 | Receipts | **Deferred to Phase 10** as planned in the roadmap: receipts will be generated from payments (FR-PAY-04); Phase 9 payments carry their own number in the meantime |
| B20 | Overdue invoices and credit limits | **Applied in Phase 9:** "Overdue" is derived (issued/partially paid, balance > 0, due date passed) and shown as a badge and filter; a daily 07:15 check (`ibms:invoices:overdue-check`) sends one in-app summary of newly overdue invoices to users who record payments or void invoices, each invoice announced once. A customer credit limit only produces a warning on the draft, it does not block issuing |

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
| D1 | Pay frequency | Monthly only |
| D2 | Statutory rules to seed — **must be verified against current URA and NSSF publications before go-live**. Developer's current understanding (unverified): PAYE resident monthly bands 0–235,000 @ 0 %; 235,001–335,000 @ 10 %; 335,001–410,000 @ 20 % (+10,000); above 410,000 @ 30 % (+25,000), plus an extra 10 % on income above 10,000,000. NSSF 5 % employee + 10 % employer on gross. LST per local-government schedule, annual max UGX 100,000, deducted in instalments. | Seed these marked "unverified"; HR/Finance confirms before first live run |
| D3 | Non-resident employees, or other statutory items (e.g. withholding on contractors)? | Residents only at first |
| D4 | Common other deductions: salary advances, loans, SACCO, union dues? Should loans have repayment schedules? | Generic deductions only; loan schedules later |
| D5 | Who approves payroll? | Manager or Finance Officer; preparer cannot approve |
| D6 | Payroll corrections after approval | Adjustment run / next month adjustment; no editing |
| D7 | Bank or mobile money payment files in a specific bank format? | Generic Excel payment list |
| D8 | Leave and attendance management? | Out of scope (not in brief) |

## E. Requisitions & approvals

| # | Question | Proposed default |
|---|----------|------------------|
| E1 | Default workflow and amount thresholds | Department Head → Finance Officer → Manager (Manager step only above a threshold you set) |
| E2 | What happens if the requester is the department head? | Their step is skipped and routed to the next step |
| E3 | Does fulfilment generate a purchase from a supplier (GRN/expense) or only stock issues? | Both: stock issue for stocked items; expense (and optional GRN) for purchases |

## F. Security & operations

| # | Question | Proposed default |
|---|----------|------------------|
| F1 | Two-factor authentication | Optional per user; mandatory for Super/System Admin |
| F2 | Session idle timeout | 120 minutes |
| F3 | Hosting target and domain | Recommendation in Phase 15 (VPS preferred for queues/scheduler) |
| F4 | Existing data to import (customers, items, employees, opening stock balances)? | Import via Excel templates in Phase 13–15 if needed |

## G. Recorded engineering assumptions (no answer needed unless you object)

- PHPUnit (already installed) is used rather than Pest.
- Node.js/npm is used only to compile Bootstrap assets; no Node server.
- spatie/laravel-permission and Laravel Fortify are used for RBAC and authentication (doc 03 §2).
- Document numbers: `QT-2026-00001`, `INV-2026-00001`, `RCT-2026-00001`, `REQ-2026-00001`, `GRN-…`, `EXP-…`, `PAY-2026-11`, `EMP-00001`; yearly reset; configurable.
- Timezone Africa/Kampala; English UI.
