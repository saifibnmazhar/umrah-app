# BM Umrah Laravel UI Integration

## Overview
This project integrates HTML/JS UI references from `./ui-references/` into a Laravel Blade application with Tailwind CSS v4 and Alpine.js.

## UI Reference Usage
- Original HTML/JS files in `./ui-references/` are design references
- DO NOT modify files in this folder
- Use as reference only for new implementations

## Laravel Structure
```
resources/
├── views/
│   ├── layouts/app.blade.php      # Main layout
│   ├── partials/nav.blade.php     # Navigation
│   ├── components/               # 10 reusable components
│   ├── dashboard/index.blade.php
│   ├── bookings/index.blade.php
│   ├── fares/admin.blade.php
│   ├── fares/passenger-details.blade.php
│   ├── visas/admin.blade.php
│   ├── visas/passenger-details.blade.php
│   ├── fingerprints/admin.blade.php
│   ├── fingerprints/staff.blade.php
│   ├── settings/index.blade.php
│   ├── reports/                   # 11 report views
│   ├── invoices/                   # 2 views
│   ├── passengers/details.blade.php
│   ├── packages/details.blade.php
│   ├── refunds/confirmation.blade.php
│   ├── re-issues/confirmation.blade.php
│   └── tickets/add-confirmation.blade.php
```

## Blade Components (10)
1. `stat-card.blade.php` - Dashboard stats
2. `status-badge.blade.php` - Status indicators
3. `page-header.blade.php` - Page titles
4. `data-table.blade.php` - Tables
5. `search-input.blade.php` - Search fields
6. `action-button.blade.php` - Buttons
7. `empty-state.blade.php` - Empty states
8. `tab-button.blade.php` - Tabs
9. `modal.blade.php` - Modals
10. `toast.blade.php` - Notifications
11. `loading-state.blade.php` - Loading states
12. `error-state.blade.php` - Error states
13. `skeleton.blade.php` - Skeleton loaders

## Alpine.js Patterns
- `x-data="{ key: value }"` - Component state
- `x-show` / `x-bind` - Conditional rendering
- `x-model` - Two-way binding
- `@click` - Event handlers
- `:class` - Dynamic classes

## Route Structure (28 routes)
| Path | Name | View |
|------|------|------|
| `/` | home | redirect to dashboard |
| `/dashboard` | dashboard | dashboard.index |
| `/bookings` | booking.index | bookings.index |
| `/fares/admin` | fare.admin | fares.admin |
| `/visas/admin` | visa.admin | visas.admin |
| `/fingerprints/admin` | fingerprint.admin | fingerprints.admin |
| `/fingerprints/staff` | fingerprint.staff | fingerprints.staff |
| `/settings` | settings | settings.index |
| `/reports/statement` | report.statement | reports.statement |
| `/reports/profit-loss` | report.profit-loss | reports.profit-loss |
| `/reports/visa` | report.visa | reports.visa |
| `/reports/visa-agent` | report.visa-agent | reports.visa-agent |
| `/reports/ticket-agent` | report.ticket-agent | reports.ticket-agent |
| `/reports/due` | report.due | reports.due |
| `/reports/reissue-refund` | report.reissue-refund | reports.reissue-refund |
| `/reports/user-wise-sales` | report.user-sales | reports.user-wise-sales |
| `/reports/pending-outbound` | report.pending-ticket | reports.pending-outbound |
| `/reports/payment-receiving` | report.payment-receiving | reports.payment-receiving |
| `/reports/fingerprint` | report.fingerprint | reports.fingerprint |
| `/reports/branch-due-details` | report.branch-due-details | reports.branch-due-details |
| `/invoices/{id}` | invoices.details | invoices.details |
| `/invoices/{id}/print` | invoices.print | invoices.print |
| `/passengers/{id}` | passengers.details | passengers.details |
| `/packages/{id}` | packages.details | packages.details |
| `/re-issues/{id}/confirm` | re-issues.confirmation | re-issues.confirmation |
| `/refunds/{id}/confirm` | refunds.confirmation | refunds.confirmation |
| `/tickets/{id}/add-confirm` | tickets.add-confirmation | tickets.add-confirmation |

## Running the Project
```bash
# Install dependencies
npm install

# Start Vite dev server
npm run dev

# Start Laravel server
php artisan serve
```

## Key Patterns
- Use `route()` helper for all links
- Use `@include('partials.nav')` for navigation
- Dummy data via `@php` blocks in each view
- Form `name` attributes for Laravel binding
- Use `x-data` for component state instead of inline JavaScript

## Tailwind Configuration
Custom slate colors (50-950) are defined in `resources/css/app.css` via `@theme`:
```css
@theme {
  --color-slate-50: #f8fafc;
  --color-slate-100: #f1f5f9;
  /* ... etc */
}
```

## Form Field Names
| Page | Fields |
|------|--------|
| Booking | customer_name, customer_mobile, passenger_name[], passenger_passport[], route, package_type, pax_qty, remarks |
| Fare Admin | airline, route, selling_fare, tax, pax_type, effective_from, effective_to |
| Visa Admin | passport, name, mobile, agent_name, submission_date, status |
| Settings | min_days, max_days, flight_gap_notice, default_airport, charge_*, package_* |

## Documentation

| Document | Purpose |
|----------|---------|
| [docs/README.md](docs/README.md) | **Development Handbook** — onboarding, architecture, conventions, testing, CI/CD |
| [AGENTS.md](AGENTS.md) | Agent conventions and TDD workflow |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Production deployment guide (ISPConfig, Watchtower, rollback, backups) |

## Docker Development

### Development Environment

```bash
# Start containers (app + MySQL)
docker compose up -d

# App runs on http://localhost:8080
# MySQL runs on 127.0.0.1:3306
```

### Production Environment

```bash
# 1. Configure environment
bash docker/scripts/setup-env.sh

# 2. Generate APP_KEY (paste into .env.production)
php artisan key:generate

# 3. Deploy
./deploy-prod.sh
```

The app runs on port 8000 on localhost. See [DEPLOYMENT.md](DEPLOYMENT.md) for full production deployment instructions including ISPConfig reverse proxy setup and Watchtower auto-deploy configuration.

### Docker Configuration Files

- `Dockerfile` — Multi-stage build (Node 22 build stage + PHP 8.4-fpm-alpine runtime)
- `docker-compose.yml` — Dev environment (app + MySQL)
- `docker-compose.prod.yml` — Prod environment (app + MySQL + Redis)
- `docker/entrypoint.sh` — Container entrypoint (cache, migrate, permissions)
- `docker/nginx/conf.d/default.conf` — Nginx site config
- `docker/php/conf.d/zz-app.ini` — PHP runtime overrides (512M memory, 300s timeout)
- `docker/supervisord.conf` — Process manager for PHP-FPM + Nginx
- `docker/scripts/setup-env.sh` — Production environment setup/validation
- `.github/workflows/build-push.yml` — CI/CD pipeline (PHP tests, JS tests, Docker build/push)
