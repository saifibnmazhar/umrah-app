# Getting Started

> Part of the [Development Handbook](README.md) · **Mode:** Tutorial

This guide gets you from a fresh clone to a running development environment in
under 10 minutes.

## Prerequisites

| Tool | Version |
|------|---------|
| PHP | 8.3+ |
| Composer | 2.x |
| Node.js | 22.x |
| npm | 10.x+ |
| Docker | 24.x+ |
| MySQL | 8.0 (prod only — Docker provides this) |
| Redis | 7 (prod only — Docker provides this) |

> **No local MySQL or Redis needed for development.** The Docker setup
> includes them.

## Option A: One-Command Setup (Recommended)

If you have PHP and Composer locally, the fastest path is:

```bash
git clone https://github.com/mostafiz-8bits/umrah-app.git
cd umrah-app

# The "setup" script handles everything:
#   composer install
#   cp .env.example .env
#   php artisan key:generate
#   php artisan migrate
#   npm install
#   npm run build
composer setup
```

Then start the dev server:

```bash
php artisan serve
```

Visit: `http://localhost:8000`

## Option B: Docker Development

If you prefer containers (recommended for consistency with production):

```bash
git clone https://github.com/mostafiz-8bits/umrah-app.git
cd umrah-app

# One command starts app + MySQL
docker compose up -d --build
```

Visit:
- App: `http://localhost:8080`
- MySQL: `127.0.0.1:3306`

> **Note:** The Docker build compiles frontend assets during image build.
> Local asset changes require `docker compose up --build` to rebuild.

## Option C: Full Local Dev (Serving All Parts)

For active frontend development, use the full dev server with hot-reloading:

```bash
# Terminal 1: Laravel backend
php artisan serve

# Terminal 2: Vite dev server (hot-reload for CSS/JS)
npm run dev

# Terminal 3: Queue worker (if needed)
php artisan queue:work
```

Visit: `http://localhost:8000` (serves from Laravel; Vite hot-reload overlay loads on top)

## Verify Your Setup

```bash
# Run the test suite
php artisan test

# Check the app boots
php artisan tinker
>>> \App\Models\User::count()
```

## Seeding Sample Data

The project includes seeders that populate the database with sample data
for bookings, passengers, documents, invoices, payments, visa submissions,
fingerprints, and flight date gaps.

```bash
# Run all seeders
php artisan db:seed

# Run a specific seeder
php artisan db:seed --class=BookingSeeder

# Truncate + re-seed (when encountering duplicate entry errors)
php artisan tinker
>>> DB::statement('SET FOREIGN_KEY_CHECKS=0');
>>> DB::table('bookings')->truncate();  // + other tables
>>> DB::statement('SET FOREIGN_KEY_CHECKS=1');
```

### Seed Data Overview

| Table | Records | Description |
|-------|---------|-------------|
| `flight_date_gaps` | 4 | 7, 10, 14, 30-day stay options for passenger forms |
| `documents` | 9 | Booking docs (3), customer docs (2), passenger docs (4) |
| `bookings` | 2 | Full pipeline: 2 adult + 1 child passengers |
| `invoices` | 2 | Linked to bookings |
| `payments` | 3 | Partial + full payment scenarios |
| `visa_submissions` | 3 | One per passenger |

### Storage Disks

- **Public disk** (`storage/app/public/`) — booking customer documents
- **Local disk** (`storage/app/`) — passenger documents

Document records store file paths as strings (e.g. `booking-docs/inv-0001-cust-doc-1.pdf`)
without actual file content. The morphMany relationship links documents to
their owners (Booking, Customer, Passenger).

## Next Steps

1. Read [Architecture](02-architecture.md) to understand the codebase
2. Read [Coding Conventions](04-coding-conventions.md) for style guidance
3. Read [Testing](05-testing.md) and [AGENTS.md](../AGENTS.md) for TDD workflow
4. Pick an issue and start contributing!

## Navigation

Next: [Architecture](02-architecture.md) ·
Full index: [README](README.md)
