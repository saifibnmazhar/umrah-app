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

**Image:** `ghcr.io/${{ github.repository }}`

**When this fails:**
- Check Dockerfile syntax
- Check `composer install` fails (missing extensions, version conflicts)
- Check `npm run build` fails (frontend errors)
- Check layer caching issues (clear cache manually if needed)

## Continuous Deployment (Production)

Deployment is **not** automated in CI/CD, and there is **no Watchtower on the
production server** — nothing polls `ghcr.io` and nothing restarts the app by
itself. The process is:

1. CI builds and pushes the Docker image to `ghcr.io`
2. You SSH to the production server and run the deploy script

**To deploy** (run on the production server, in the project directory):

```bash
./deploy-prod.sh
```

This script:
1. Validates `.env.production` exists and the compose file parses
2. Pulls the latest image from ghcr.io
3. Stops only the `app` container (`compose stop`, not `compose down`)
4. Starts MySQL 8.0 and waits for it to report healthy, then starts Redis
5. Starts the app container
6. Waits for the app container's healthcheck (fails fast if the entrypoint
   crash-loops, usually a failing migration)
7. Fixes storage permissions
8. Runs migrations when `MIGRATE=true`
9. Clears the config, route and view caches
10. Runs `sessions:flush --force` to log everyone out (skipped with a warning
    on images that predate the command)

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

None. The staging workflow only tests, builds and pushes the image — it has no
SSH or deployment step, so it needs no credentials beyond the built-in
`GITHUB_TOKEN`.

### Staging Deployment

Pushing to `staging` runs CI:

1. CI runs tests (unit + feature) and the npm build
2. The Docker image is built and tagged `staging` + `staging-<sha>`
3. The image is pushed to `ghcr.io`

**That is where CI stops.** Nothing is deployed automatically: Watchtower does
not run on the staging server and `staging.yml` has no deploy job, so an image
only reaches staging when someone runs the deploy script on that server:

```bash
# On the staging server, after CI has pushed
chmod +x deploy-staging.sh
IMAGE_TAG=staging-<sha> ./deploy-staging.sh
```

`deploy-staging.sh` then:

1. Validates the compose file parses and `.env.staging` exists, then sources
   `.env.staging` so bash can read `$MIGRATE`
2. Pulls the staging image from ghcr.io
3. Stops only the `app` container (`compose stop`, not `compose down` — the
   database is not recreated on every deploy; volumes are never removed)
4. Starts MySQL 8.0, waits for it to report healthy, then starts Redis
5. Starts the app container and waits for its healthcheck (fails fast on an
   entrypoint crash-loop)
6. Clears the config, route and view caches
7. Fixes storage permissions
8. Runs migrations when `MIGRATE=true` (seeders are never run)
9. Runs `sessions:flush --force` to log everyone out (skipped with a warning
   on images that predate the command)

The script does **not** update `IMAGE_TAG`, prune images, or curl a URL —
health comes from `docker compose` healthchecks only. To deploy a pinned
image, pass the tag in: `IMAGE_TAG=staging-<sha> ./deploy-staging.sh`.

Staging and production intentionally share this behaviour: same caches
cleared, same forced logout, same Redis persistence.

### Staging Configuration Files

| File | Purpose |
|------|---------|
| `.env.staging.sample` | Template for staging environment variables |
| `docker-compose.staging.yml` | Staging Docker Compose config (ports on 8001, staging DB) |
| `deploy-staging.sh` | Staging server deployment script (mirrors the copy on that server) |

### Staging vs Production Differences

| Aspect | Production | Staging |
|--------|-----------|---------|
| Image tag | `latest` + `sha-<short-sha>` | `staging` + `staging-<sha>` |
| Deploy method | Manual: `./deploy-prod.sh` on the server | Manual: `./deploy-staging.sh` on the server |
| Watchtower | Not installed | Not installed |
| DB name | `binmishal_umrah_live` | `umrah_staging` |
| Port | 8000 (`APP_PORT`) | 8001 |
| APP_ENV | `production` | `staging` |
| APP_DEBUG | `false` | `true` |
| Auto-deploy | None — CI pushes, you deploy | None — CI pushes, you deploy |
| Seeders | Never run by the deploy script | Never run by the deploy script |
| `MIGRATE` | `true` (run in the entrypoint **and** the script) | `false` unless enabled |

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
