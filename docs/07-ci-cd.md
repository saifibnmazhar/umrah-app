# CI/CD

> Part of the [Development Handbook](README.md) · **Mode:** Explanation

Umrah App uses **GitHub Actions** for continuous integration and deployment.

## Workflow

**File:** `.github/workflows/build-push.yml`

**Trigger:** Push to `main` or open a PR targeting `main`.

## Jobs

### 1. `test-php-unit` and `test-php-feature`

**Purpose:** Run the PHPUnit test suite against MySQL 8.0.

```yaml
runs-on: ubuntu-latest
services:
  mysql:
    image: mysql:8.0
    env:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: umrah_test
      MYSQL_USER: test
      MYSQL_PASSWORD: test
    ports: ['3306:3306']
```

Steps:
1. Checkout code
2. Setup PHP 8.4 with extensions (pdo_mysql, mysqli, intl, mbstring, zip, gd)
3. Setup Node.js 22
4. `composer install` (no interaction, prefer-dist)
5. `npm ci` + `npm run build` (Vite production build)
6. Setup `.env` (SQLite → MySQL, pointing at the MySQL service container)
7. `php artisan key:generate`
8. Run migrations (`php artisan migrate`)
9. `vendor/bin/phpunit`

**When this fails:**
- Check for SQL errors in migrations — the test DB mirrors production schema
- Check for model attribute mismatches — casts, fillable, etc.
- Ensure new migrations don't conflict with the test MySQL instance

### 2. `test-js`

**Purpose:** Verify frontend builds correctly.

Steps:
1. Checkout code
2. Setup Node.js 22
3. `npm ci` (uses `package-lock.json`)
4. `npm run build` (Vite production build)

**When this fails:**
- Check Vite/Tailwind config in `vite.config.js`
- Ensure all imports in `resources/js/` resolve
- Check for CSS errors in `resources/css/app.css`

### 3. `build`

**Purpose:** Build and push Docker image to ghcr.io.

**Depends on:** `test-php-unit`, `test-php-feature`, and `test-js` (runs only after all pass)

Steps:
1. Checkout code
2. Setup Docker Buildx (for multi-platform caching)
3. Login to GitHub Container Registry:
   ```
   registry: ghcr.io
   username: ${{ github.actor }}
   password: ${{ secrets.GITHUB_TOKEN }}
   ```
4. Extract metadata (tags):
   - `latest`
   - `sha-<short-sha>` (e.g., `sha-a1b2c3d`)
5. Build and push Docker image
6. **Build cache** stored at `ghcr.io/${{ github.repository }}/buildx-cache:latest`

**Image:** `ghcr.io/mostafiz-8bits/umrah-app`

**When this fails:**
- Check Dockerfile syntax
- Check `composer install` fails (missing extensions, version conflicts)
- Check `npm run build` fails (frontend errors)
- Check layer caching issues (clear cache manually if needed)

## Continuous Deployment (Production)

Deployment is **not** automated in CI/CD. The process is:

1. CI builds and pushes the Docker image to `ghcr.io`
2. **Watchtower** (running on the ISPConfig server) detects the new image
3. Watchtower auto-updates the production container within ~5 minutes

**To deploy manually** (instead of relying on Watchtower):

```bash
./deploy-prod.sh
```

This script:
1. Pulls the latest image from ghcr.io
2. Starts MySQL 8.0 and Redis 7 first, waits for health
3. Starts the app container
4. Fixes storage permissions

**To pin a specific image version** (e.g., for rollback):

```bash
IMAGE_TAG=sha-abc123def ./deploy-prod.sh
```

## Staging Environment

**Workflow file:** `.github/workflows/staging.yml`

**Trigger:** Push to `staging` branch, PR to `staging`, or manual dispatch.

### Staging Workflow Jobs

| Job | Purpose |
|-----|---------|
| `test-php-unit` | PHPUnit unit tests against MySQL 8.0 |
| `test-js` | npm build verification |
| `build-and-push` | Build Docker image tagged `staging` + `staging-<sha>`, push to ghcr.io |

### Required GitHub Secrets for Staging

None — staging deploys the same way as production:

1. CI builds and pushes the Docker image to `ghcr.io` (tagged `staging` + `staging-<sha>`)
2. **Watchtower** (running on the staging server) detects the new image and auto-updates

No SSH secrets are required.

### Staging Deployment

The staging workflow automatically deploys when a commit is pushed to `staging`:

1. CI runs tests (unit + feature) and npm build
2. Docker image is built and tagged `staging` + `staging-<sha>`
3. Image is pushed to `ghcr.io`
4. **Watchtower** (running on the staging server) detects the new `staging` tag and auto-updates within ~5 minutes
5. The staging entrypoint runs migrations if `MIGRATE=true` in `.env.staging`

No SSH secrets are required — staging deploys the same way as production via Watchtower.

**To deploy staging manually:**

```bash
chmod +x deploy-staging.sh
IMAGE_TAG=staging-<sha> ./deploy-staging.sh
```

The staging deploy script mirrors `deploy-prod.sh` and:
1. Validates `.env.staging` and `docker-compose.staging.yml` exist
2. Validates Docker Compose configuration
3. Pulls the staging image from ghcr.io
4. Stops the app container (preserving volumes)
5. Starts DB, Redis, and app containers in order
6. Waits for DB health (120s timeout)
7. Fixes Laravel storage permissions
8. Runs migrations if `MIGRATE=true` in `.env.staging`
9. Waits for app health (120s timeout)
10. Prints final container status

Uses `source .env.staging` for env var access, and `docker compose`
health checks (not public HTTP) for health verification.

### Staging Configuration Files

| File | Purpose |
|------|---------|
| `.env.staging.sample` | Template for staging environment variables |
| `docker-compose.staging.yml` | Staging Docker Compose config (ports on 8001, staging DB) |
| `deploy-staging.sh` | Local staging deployment script |

### Staging vs Production Differences

| Aspect | Production | Staging |
|--------|-----------|---------|
| Image tag | `latest` + `sha-<short-sha>` | `staging` + `staging-<sha>` |
| Deploy method | Watchtower (auto) | Watchtower (auto) |
| DB name | `binmishal_umrah_live` | `umrah_staging` |
| Port | 8000 | 8001 |
| APP_ENV | `production` | `staging` |
| APP_DEBUG | `false` | `true` |
| Auto-deploy | Watchtower (5 min delay) | Watchtower (5 min delay) |
| Seeders | Not run | Not run (MIGRATE=false) |
| `MIGRATE` | `false` (entrypoint) | `false` (entrypoint) |

---

## Debugging CI Failures

```bash
# Re-run the same commands locally:
composer install --no-interaction --prefer-dist
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
vendor/bin/phpunit

# Check Docker build:
docker build -t test .
```

---

## Navigation

Previous: [Git Workflow](06-git-workflow.md) ·
Next: [Domain Reference](08-domain-reference.md) ·
Full index: [README](README.md)
