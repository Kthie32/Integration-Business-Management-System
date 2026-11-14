# 08 — Security Review (Phase 14)

Reviewed: 2026-11-13 · Scope: the whole application at the end of Phase 13 (250 routes, 24 policies, Fortify authentication).

The review combined automated checks that now run with every test run and a manual read of the code
against the checklist below. Each finding is either fixed (with a test) or recorded as an accepted
behaviour or a deployment requirement for whoever deploys the system.

## 1. Summary

| Area | Result |
|------|--------|
| Authorization on routes | **Pass.** Every one of the ~210 signed-in routes refuses a user without the permission (automated sweep) |
| Role matrix (docs/05 §2) | **Pass.** All 10 seeded roles open exactly the pages their permissions allow (automated) |
| Record-level rules (docs/05 §3) | **Pass.** Own payslips, requisition visibility, department-head scope, sensitive employee fields, locked documents |
| IDOR / nested records | **Pass.** Nested routes are scoped to their parent (documents, adjustments) or checked in the controller (salary structures) |
| Mass assignment | **Pass.** Every model has an explicit `#[Fillable]` list or `$guarded = ['*']`; only validated data reaches them |
| SQL injection | **Pass.** All raw expressions use bound parameters or fixed column names; sort columns are whitelisted |
| XSS | **Pass.** Unescaped output is limited to sort links and addresses that are escaped with `e()` first, and the 2FA QR code SVG |
| CSRF | **Pass.** Laravel's request-forgery middleware covers every web route, with no exceptions |
| File uploads | **Pass.** Extension and MIME checked, size-limited, stored under random names on the private disk, downloaded only through authorised controllers |
| Login / 2FA brute force | **Pass.** 5 attempts a minute on login and the 2FA challenge |
| Other secret checks | **Fixed** (F-2): password confirmation, password change, 2FA setup confirmation, reset-link requests |
| Session and 2FA enforcement | **Pass.** Deactivated users are signed out on their next request; mandatory 2FA blocks every module page |
| Security headers | **Improved** (F-3): Content-Security-Policy added to the existing headers |
| Private file exposure | **Fixed** (F-4): unused `/storage/{path}` file-serving route removed |
| Sensitive data in logs | **Pass.** Passwords, tokens, 2FA secrets, salaries and employee identity/bank numbers are excluded or masked in the audit log |

## 2. Findings and fixes

### F-1 Pay component pages crashed (fixed)

The **Payroll → Pay components** list and edit pages returned a server error for everyone allowed to
manage components. The form's `$component` variable clashed with a variable Blade reserves for components,
so the page broke as soon as it was shown inside the layout. Found by the role matrix test.
**Fix:** the variable is now `$payComponent`. **Tests:** `PayrollConfigurationTest` (pages open and show the form),
plus the role matrix and the "every page opens" sweep.

### F-2 No rate limit on password and code checks after sign-in (fixed)

Login and the 2FA challenge were limited, but four other Fortify actions that check a secret were not:
confirming the password, changing the password (checks the current one), confirming a new 2FA code and
requesting a password-reset link. Someone holding a stolen, signed-in session could guess the password without
limit and then switch two-factor off. The reset form could be used to flood mailboxes.
**Fix:** a new `ThrottleAccountSecurityChecks` middleware applies the `account-security` limiter (5 a minute, per
user, or per IP address when signed out) to those four actions. **Tests:** `SecurityHardeningTest` (each limit, and
that the sixth attempt is refused even with the right password).

### F-3 No Content-Security-Policy (fixed)

The application already sent X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy and
HSTS (over HTTPS). It now also sends a Content-Security-Policy with `frame-ancestors 'self'`, `object-src 'none'`,
`base-uri 'self'` and `form-action 'self'`. Script and style sources are not restricted yet, because the layout uses
inline scripts and styles. Restricting them (nonces) is a possible later hardening step.
**Test:** `SecurityHardeningTest`.

### F-4 Private disk exposed through a signed-URL route (fixed)

The private `local` disk (employee documents, expense and requisition attachments) had Laravel's `serve`
option on, which registers `GET/PUT /storage/{path}`. Those routes only accept signed URLs, and the application
never creates any, so they were unused. **Fix:** `serve` is off and the routes no longer exist; files are only
streamed by controllers that check the policy. **Test:** `SecurityHardeningTest`.

## 3. Accepted behaviour (no change)

- **Dashboard, notifications and approval inbox for every signed-in user.** These show only the user's own items, and
  each dashboard widget checks its own permission.
- **Requisition approvers can open the requisitions they may act on.** When no active requisition workflow exists,
  the approval engine falls back to "any user with `requisitions.approve`" (docs/07 E1), so every approver can then
  open pending requisitions. The seeded standard workflow sends step 1 to the requisition's department head only.
  Keep at least one workflow active.
- **Read-only payroll configuration.** Users with `payroll.view` or `payroll.prepare` may view pay components and
  statutory rates; only `payroll.configure` changes them.
- **Departments list.** Visible to users who can view employees (all or their own department), as well as to `departments.manage`.

## 4. Deployment requirements

These are configuration settings for the live server, not code changes:

1. `APP_ENV=production` and `APP_DEBUG=false`. With debug on, error pages expose code and settings, and the
   development tool Laravel Boost turns itself on (it adds a browser-log route).
2. Install with `composer install --no-dev`, so the development packages (Boost, Pail, Faker) are not deployed.
3. Serve only over HTTPS and set `SESSION_SECURE_COOKIE=true`. Keep `SESSION_ENCRYPT=true` and the default `http_only` and `same_site=lax`.
4. Set `IBMS_SUPER_ADMIN_EMAIL` only for the first seed, then remove it.
5. Point the web root at `public/` and keep `storage/app/private` outside it. Run `php artisan storage:link` for the logo only.
6. Use `LOG_LEVEL=warning` or higher in production; the application does not write request data or secrets to the log.

## 5. Automated checks added

| Test | What it proves |
|------|----------------|
| `Security/RouteAuthorizationSweepTest` | Calls every signed-in route (all methods) with real records: a user without permissions is refused everywhere except their own dashboard, notifications and approval inbox; guests are sent to the login page; the Super Administrator opens every page without a server error. A new route without an authorization check fails this test |
| `Security/RolePageAccessMatrixTest` | Each of the 10 roles against 50 entry pages; the expected answer comes from the role's seeded permissions |
| `Security/RecordLevelAccessTest` | docs/05 §3 rules over HTTP, requesting other people's records by address |
| `Security/SecurityHardeningTest` | Rate limits, security headers, private files, CSRF coverage |
| `Integration/*` | End-to-end business flows across modules (see docs/06, Phase 14) |

The performance part of Phase 14 is reported in docs/09.
