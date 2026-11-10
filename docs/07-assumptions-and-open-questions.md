# 07 — Assumptions & Open Questions

Last updated: 2026-11-10

These are decisions about organizational policy that should not be invented by the developer.
Each has a **proposed default**: if you agree, no action is needed; otherwise state the change.

## A. Blocking before Phase 3 (project initialization)

| # | Question | Decision / status |
|---|----------|-------------------|
| A1 | Organization name and logo | **Decided:** default name "Magezi IBM"; name, logo and branding are editable in Settings so the system can be customized for any business. |
| A2 | GitHub repository URL | **Decided:** https://github.com/Kthie32/Integration-Business-Management-System (remote `origin`). |
| A3 | Create `ibms`, `ibms_testing` and a dedicated `ibms` MySQL user | **Done.** |
| A4 | Delete `../example/`? | **Open** — owner said proceed, but inspection found custom code there (Money, numbering, line-item calculator, middleware, sales migrations). Kept until the owner confirms whether it should be deleted or reviewed for reuse. |

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

## C. Inventory

| # | Question | Proposed default |
|---|----------|------------------|
| C1 | Multiple warehouses/stores? | Supported; one default seeded |
| C2 | Stock valuation method | Weighted average cost |
| C3 | Do stock adjustments need approval? | Yes, by `stock.adjust_approve` holder other than the creator |
| C4 | Batch/expiry or serial-number tracking? | Not required |

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
