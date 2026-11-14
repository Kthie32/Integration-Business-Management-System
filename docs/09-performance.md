# 09 — Performance Check (Phase 14)

Checked: 2026-11-13 on the development machine (Intel Celeron N3150 1.6 GHz, ~4 GB RAM, Windows, MySQL 8.0, PHP 8.5).
This machine is slow: plain PHP runs about 4–6 times slower here than on a typical VPS core, so the times below
are an upper bound. What matters is how they grow with data and how many queries a page runs.

## 1. Sample volume

`php artisan ibms:demo:volume` (Phase 14, never runs in production) fills a migrated and seeded database with a year of
activity through the normal services, so numbers, stock movements, receipts, approvals and audit entries are real.
Defaults: 2,000 invoices, 60 employees, 12 months. Run on the test database it produced:

| Records | Count | Records | Count |
|---|---:|---|---:|
| Customers | 300 | Stock movements | 5,111 |
| Items | 150 | Expenses | 600 |
| Quotations | 500 | Requisitions | 300 |
| Invoices / lines | 2,000 / 4,961 | Employees | 60 |
| Payments / receipts | 1,389 / 1,389 | Payroll runs / payslips | 11 / 660 |
| Audit log entries | 17,668 | Status history entries | 10,537 |

Generating it took about 15 minutes, nearly all of it issuing and paying the invoices (about 0.35 s per invoice with its
stock issue, number, receipt and audit entries).

## 2. Results

Each page was opened as a Super Administrator (who sees everything) with query logging and Eloquent's lazy-loading
detection switched on.

**No N+1 queries.** No page triggered a lazy load, and no query shape repeated five or more times. List pages run
4–11 queries and the dashboard 32–40, whatever the data volume.

| Page | Time | Queries | Database time |
|------|-----:|--------:|--------------:|
| Dashboard (Super Admin / Finance Officer) | 1.1 s / 0.5 s | 40 / 32 | 0.2 s |
| Invoices, quotations, payments, receipts, expenses, requisitions, employees, stock lists | 0.2–0.75 s | 4–11 | < 0.1 s |
| Customer page, statement (one year) | 0.3 s | 7–10 | < 0.05 s |
| Payroll run page (60 payslips) | 1.0 s | 15 | 0.03 s |
| Reports over one year, most | 0.2–0.8 s | 3–13 | < 0.05 s |
| Revenue / receipt register / invoice register (1,389–2,000 rows) | 2.3–3.0 s | 4–6 | ~0.1 s |
| Stock issued (4,961 rows) | 6.2 s | 9 | 0.24 s |
| Invoice register Excel export (2,000 rows) | 11.7 s | 3 | 0.12 s |
| Payroll register Excel export (60 rows) | 0.5 s | 3 | — |

Database time is a small part of every page. The large reports spend their time in PHP building every row, because
totals and charts need all of them; the screen shows at most 1,000 rows, and exports contain all of them. On this CPU
that is about 1 ms per row. On a normal server, expect roughly a fifth of these times: around 1 s for a year of stock
issues and 2–3 s for a 2,000-row Excel export.

## 3. Changes made

| Change | Why |
|--------|-----|
| Index on `invoices.invoice_date` | The invoice list sorts by it; the invoice register, sales reports and dashboard filter by it. Before: whole table read and sorted. After: the list reads 25 index entries; a one-month register reads only that month |
| Index on `invoices.issued_at` | The dashboard's "latest invoices" sorted the whole table on every visit |
| Index on `quotations.quote_date` | The quotation list sorts by it; the quotations report filters by it |

Migration `2026_11_18_090000_add_date_indexes_to_invoices_and_quotations_tables`; the test schema dump was regenerated.
Tables that grow fastest (stock movements, payments, receipts, expenses, audit log, approvals, status history) already
had indexes for their list order and filters, and those queries use them.

## 4. Advice for production

- Enable OPcache (`opcache.enable=1`) on the web server, and run `php artisan config:cache`, `route:cache` and `view:cache`
  on each deployment.
- Long reports: filter by a shorter period or warehouse when on-screen speed matters. Excel exports of a full year are
  fine for occasional use; move them to a queued job if they become a daily routine with tens of thousands of rows.
- To repeat the check on a copy of the database: migrate and seed it, then run
  `php artisan ibms:demo:volume --invoices=2000 --employees=60 --months=12`. The command refuses to run in production.
