# Development Environment

> Part of the [Development Handbook](README.md) · **Mode:** How-to

This guide covers daily development tasks: starting/stopping containers,
running the dev server, debugging, and managing caches.

## Docker Development

### Starting the stack

```bash
# Build and start (first time or after Dockerfile changes)
docker compose up -d --build

# Subsequent starts (no rebuild needed)
docker compose up -d
```

**Ports:**
- App: `http://localhost:8080`
- PostgreSQL: `127.0.0.1:5433`

### Stopping and cleaning

```bash
# Stop containers (keeps volumes/data)
docker compose down

# Stop and remove volumes (destroys all data)
docker compose down -v

# Full reset (rebuild from scratch)
docker compose down -v && docker compose up -d --build
```

### Common Docker commands

```bash
# View logs
docker compose logs -f app
docker compose logs -f db

# Run a command in the container
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose exec app bash

# Check container status
docker compose ps

# Rebuild after dependency changes
docker compose up -d --build --force-recreate app
```

### Rebuilding after code changes

```bash
# Rebuild PHP image (after composer.json changes)
docker compose up -d --build app

# Rebuild Node assets (after package.json changes)
docker compose up -d --build app
```

> **Tip:** The Dockerfile builds assets in a Node stage, then copies the built
> files into the PHP image. Any `npm` dependency or Vite config change requires a
> full rebuild (`--build`).

## Local Development (Without Docker)

If you prefer to run services locally without Docker:

```bash
# 1. Install PHP dependencies
composer install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Run migrations (SQLite by default)
php artisan migrate

# 4. Install frontend dependencies and build
npm install
npm run build    # or: npm run dev (for hot-reload)

# 5. Start the dev server
php artisan serve
```

For local development, the default `DB_CONNECTION=sqlite` works out of the box
(no PostgreSQL needed). The SQLite database file is created at
`database/database.sqlite`.

## IDE Recommendations

### VS Code

Extensions:
- **PHP Intelephense** — PHP language server, autocomplete, refactoring
- **Laravel Blade Snippets** — Blade directive snippets
- **Prettier** — Frontend formatting
- **EditorConfig for VS Code** — enforces `.editorconfig`

### PHPStorm

- Built-in Laravel support (plugins: Laravel, Blade)
- Database tool window for SQL browsing
- Xdebug support for breakpoints

## Debugging

### Laravel Logs

```bash
# Tail the log
tail -f storage/logs/laravel.log

# Or from Docker
docker compose logs -f app
```

### Enable Debug Mode

In `.env`:
```
APP_DEBUG=true
```

Then clear config cache:
```bash
php artisan config:clear
```

> **Warning:** Never set `APP_DEBUG=true` in production.

### SQL Query Log

```bash
# Enable query logging
php artisan tinker
>>> \DB::enableQueryLog();
>>> // ... run your code
>>> \DB::getQueryLog();

# Or use Laravel Debugbar (if installed)
```

### File Upload Diagnostics

The project has a `DiagnosticLogger` (`app/Support/DiagnosticLogger.php`) that
logs upload metadata (file count, total size) to `storage/logs/laravel.log`.
Check logs when upload issues occur.

## Cache Management

### Clear all caches (during development)

```bash
php artisan optimize:clear
```

### Clear specific caches

```bash
php artisan config:clear       # Clear config cache
php artisan route:clear        # Clear route cache
php artisan view:clear         # Clear compiled views
php artisan cache:clear        # Clear application cache
```

### Cache for production (optimization)

```bash
php artisan config:cache       # Cache config (2x faster)
php artisan route:cache        # Cache routes
php artisan view:cache         # Cache compiled views
```

> **Note:** The Docker entrypoint (`docker/entrypoint.sh`) automatically caches
> config, routes, and views on container startup. You do not need to run these
> manually in Docker.

### Clearing after changes

Always clear caches after:
- Changing `config/*.php` files
- Changing route definitions
- Changing Blade views (in production)

```bash
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Navigation

Previous: [Architecture](02-architecture.md) ·
Next: [Coding Conventions](04-coding-conventions.md) ·
Full index: [README](README.md)
