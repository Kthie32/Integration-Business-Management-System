# Magezi IBM — Integrated Business Management System

A web-based business management system for quotations, invoicing, payments and receipts, inventory,
customers and suppliers, employees, payroll (Uganda), requisitions with approval workflows, and reporting.

The organization name, logo and branding are configurable, so the system can be set up for any business.

> **Status:** Phase 3 (project foundation) complete. See [docs/06-implementation-roadmap.md](docs/06-implementation-roadmap.md) for the progress report.

## Technology

PHP 8.5 · Laravel 13 (MVC) · MySQL 8 · Blade · Bootstrap 5 · Laravel Fortify · spatie/laravel-permission ·
DomPDF · Laravel Excel · PHPUnit. Node.js is used only to build front-end assets with Vite.

## Documentation

| Document | |
|----------|-|
| [Project overview & environment](docs/01-project-overview.md) | Scope, environment report |
| [Software Requirements Specification](docs/02-software-requirements-specification.md) | Functional and non-functional requirements |
| [System architecture](docs/03-system-architecture.md) | Layers, technology decisions, folder structure |
| [Database design](docs/04-database-design.md) | ERD and data dictionary |
| [Roles & permissions](docs/05-roles-and-permissions.md) | Permission catalogue and role matrix |
| [Roadmap & progress](docs/06-implementation-roadmap.md) | Phases and progress report |
| [Assumptions & open questions](docs/07-assumptions-and-open-questions.md) | Decisions awaiting confirmation |

## Local installation

Requirements: PHP 8.3+ with `bcmath`, `gd`, `intl`, `mbstring`, `pdo_mysql`, `zip`; Composer 2; MySQL 8; Node.js 20+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the databases and a dedicated user (as a MySQL administrator):

```sql
CREATE DATABASE ibms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE ibms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ibms'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON ibms.* TO 'ibms'@'localhost';
GRANT ALL PRIVILEGES ON ibms_testing.* TO 'ibms'@'localhost';
```

Set `DB_PASSWORD` in `.env`, then:

```bash
php artisan migrate
npm run build        # or: composer run dev  (server, queue, logs and Vite together)
php artisan serve
```

## Tests

Tests run against the MySQL database `ibms_testing` (configured in `phpunit.xml`; credentials come from `.env`).

```bash
php artisan test --compact
```

## Security

Never commit `.env`. Secrets (database password, `APP_KEY`, mail credentials) live only in environment configuration.
