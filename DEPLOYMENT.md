# Production Deployment

## Prerequisites

- ISPConfig server with Docker installed
- Domain pointing to server
- GitHub token with package read access (for ghcr.io)

## Steps

### 1. Clone Repository

On your ISPConfig server:

```bash
git clone https://github.com/mostafiz-8bits/umrah-app.git /var/www/clients/client0/web1/umrah-app
cd /var/www/clients/client0/web1/umrah-app
```

### 2. Configure Environment

```bash
bash docker/scripts/setup-env.sh
```

This script will:
1. Create `.env.production` from `.env.production.sample` if it doesn't exist
2. Validate required variables (`DB_PASSWORD`, `APP_KEY`, `APP_URL` is HTTPS)
3. Regenerate `.env.production.sample` from your configured `.env.production` (values blanked to `***`)

### 3. Generate Laravel APP_KEY

```bash
# If PHP is available locally:
php artisan key:generate

# Or run temporarily in container:
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate
```

Then update `APP_KEY` in `.env.production`.

### 4. Deploy Application

```bash
chmod +x deploy-prod.sh
./deploy-prod.sh
```

### 5. Configure ISPConfig Reverse Proxy

In ISPConfig Panel:
1. Go to your site → Options tab
2. Find "Web Server Directives" or "Apache Directives"
3. Add:

For Apache:
```apache
ProxyPreserveHost On
ProxyPass / http://127.0.0.1:8000/
ProxyPassReverse / http://127.0.0.1:8000/
```

For Nginx:
```nginx
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto https;
    client_max_body_size 100M;
}
```

### Trust Proxies & HTTPS

The application runs behind the ISPConfig reverse proxy above, which
terminates TLS (HTTPS) and forwards to the app container over HTTP.
The proxy sets `X-Forwarded-Proto` (hardcoded to `https`), `X-Forwarded-For`,
`X-Real-IP`, and `Host` headers.

Laravel trusts all proxy IPs and the full set of `X-Forwarded-*`
headers (configured in `bootstrap/app.php`). In production,
`AppServiceProvider` forces `https://` URL generation via
`URL::forceScheme('https')`, so that redirects, asset URLs, and API
callbacks use the correct public HTTPS scheme.

**Required environment variables in `.env.production`:**
- `APP_URL` — must be set to your HTTPS URL (validated by `setup-env.sh`)
- `SESSION_SECURE_COOKIE=true` — marks the session cookie as `Secure`
  so the browser only sends it over HTTPS. Without this, the session
  is lost across redirects behind the TLS-terminating proxy, causing
  "the page isn't redirecting properly" loops.

The setup script (`docker/scripts/setup-env.sh`) validates both and
will refuse to proceed if either is missing or incorrect.

### 6. Set File Permissions

```bash
chown -R web1:client1 /var/www/clients/client0/web1/web
chmod -R 755 /var/www/clients/client0/web1/web
```

## Updates

Push to `main` branch - CI builds/pushes new image automatically. Watchtower on the server will auto-update within 5 minutes.

To manually update:
```bash
./deploy-prod.sh
```

To pin to a specific image tag (e.g., a known git SHA):
```bash
IMAGE_TAG=sha-abc123def ./deploy-prod.sh
```

This is useful for rolling back to a known-good version. List available tags:
```bash
docker compose -f docker-compose.prod.yml pull app
```

## Rollback

```bash
# Stop current stack
docker compose -f docker-compose.prod.yml down

# List available images
docker images ghcr.io/mostafiz-8bits/umrah-app

# Force recreate with specific tag (if available)
docker compose -f docker-compose.prod.yml up -d --no-deps --force-recreate
```

## Multiple Sites on One Server

Each site runs from its own clone directory with its own `.env.production` file.
`COMPOSE_PROJECT_NAME` defaults to the directory name, so differently-named
deploy directories never collide (the project name controls the Compose project
prefix: auto-generated container names, network names, and volume names).

Per-site checklist:

1. Clone the repo into a site-specific directory:
   ```bash
   git clone https://github.com/mostafiz-8bits/umrah-app.git /var/www/clients/client0/web1/<site-name>
   ```
2. Run `bash docker/scripts/setup-env.sh` and configure `.env.production` for this site.
3. Set site-specific naming/port variables before deploying — export them (or edit
   the block at the top of `deploy-prod.sh`): `APP_PORT`, `DB_EXPOSE_PORT`,
   `DB_CONTAINER_NAME`, `REDIS_CONTAINER_NAME`, `DB_USERNAME`, `DB_DATABASE`, and
   `COMPOSE_PROJECT_NAME` (which defaults to the directory name).
4. Deploy: `./deploy-prod.sh`.

> **WARNING:** never change `COMPOSE_PROJECT_NAME` for an existing site after its
> first deploy — the MySQL data volume name is derived from it, and changing it
> makes the app start against a fresh, empty database (the old data stays in the
> old volume, now orphaned).

**Existing-site migration note:** before this change the project name came from the
directory basename; after this change `deploy-prod.sh` derives `COMPOSE_PROJECT_NAME`
from the script's own directory, so an existing site whose deploy directory is *not*
named `umrah-app` keeps its project name automatically. However, if an operator
overrides `COMPOSE_PROJECT_NAME` or renames the directory of an existing site, the
resolved data volume name changes and the site starts against an empty DB.
Existing sites: do not rename the directory and do not set `COMPOSE_PROJECT_NAME`.
New sites: set it (or rely on the directory name) before the first deploy.

## Troubleshooting

### Check service status

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs app
docker compose -f docker-compose.prod.yml logs db
```

### FATAL: password authentication failed for user

**Cause:** the app container authenticates with a credential that differs from the one the
MySQL role actually has. Two compounding mechanics: (1) Compose's `environment:` block
takes precedence over `env_file:` values, and `${VAR:-default}` interpolation reads only
the shell / project `.env` — never the `env_file` contents — so the app's `DB_PASSWORD`
was the interpolation result, not the `.env.production` value; (2) the MySQL image applies
`MYSQL_ROOT_PASSWORD` / `MYSQL_PASSWORD` only when the data volume is first initialized,
so the role's real password is fixed in the named data volume and ignores later compose changes.

**Fix (data-preserving — the volume is never touched):**
1. Pull the fixed compose (it no longer has the shadowing `environment:` blocks):
   ```bash
   git pull
   ```
2. Make sure `.env.production` holds the `DB_PASSWORD` you want (and that the three
   `MYSQL_*` keys mirror the `DB_*` values).
3. Align the DB role to it (runs via container-local; no password prompt needed):
   ```bash
   docker compose -f docker-compose.prod.yml exec db mysql -u root -p"${DB_ROOT_PASSWORD}" -e "ALTER USER '${DB_USERNAME}'@'%' IDENTIFIED BY '${DB_PASSWORD}';"
   ```
4. Recreate the app container so it picks up the credential from `env_file`:
   ```bash
   docker compose -f docker-compose.prod.yml up -d app
   ```
5. Verify:
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan migrate:status
   docker compose -f docker-compose.prod.yml ps
   ```

**Prevention:** never keep a stray `.env` file in the deploy directory — it feeds Compose
interpolation. Credentials come exclusively from `.env.production` via `env_file`.
Naming/port variables (`COMPOSE_PROJECT_NAME`, `APP_PORT`, `DB_EXPOSE_PORT`, the
`*_CONTAINER_NAME` values) come from shell exports in `deploy-prod.sh`, not from
`.env.production`; the "no stray `.env`" rule is unchanged.

### Manual database migration

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

### Reset Laravel cache

```bash
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
```

### 413 Request Entity Too Large (file uploads)

**Cause:** Uploading passenger documents (passport scans, visa copies) exceeds the body size limits at any of three layers: ISPConfig reverse proxy, container nginx, or PHP.

**Fix:**
1. ISPConfig Nginx proxy — add `client_max_body_size 100M;` to the Nginx directives in ISPConfig Panel (see Section 5 above).
2. Container nginx — `client_max_body_size 100M;` in `docker/nginx/conf.d/default.conf`.
3. PHP — `upload_max_filesize = 50M` and `post_max_size = 50M` in `docker/php/conf.d/zz-app.ini`.

After these changes, rebuild the Docker image (push to `main` triggers CI automatically).

## Backup Strategy

Database backup:
```bash
docker compose -f docker-compose.prod.yml exec db mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" ${DB_DATABASE} > backup_$(date +%F).sql
```

File backup:
```bash
tar czf backup_files_$(date +%F).tar.gz -C /var/www/clients/client0/web1/web .
```

Schedule via cron:
```bash
# Daily backup at 2AM
0 2 * * * /path/to/backup-script.sh
```
