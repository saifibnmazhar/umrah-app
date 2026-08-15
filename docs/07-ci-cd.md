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
