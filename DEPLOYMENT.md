# Production Deployment

## Prerequisites

- ISPConfig server with Docker installed
- Domain pointing to server
- GitHub token with package read access (for ghcr.io)

## Steps

### 1. Project directory

Production is **not a git clone**. It is a hand-maintained directory holding
only what the deployment needs:

```bash
/var/www/umrah.binmishaltravels.com/web/
├── deploy-prod.sh
├── docker-compose.prod.yml
└── .env.production
```

Because there is no `.git` there, changes committed to this repository do
**not** reach the server on their own. After changing `deploy-prod.sh` or
`docker-compose.prod.yml` in the repo, copy the file over, back the old one
up first, and re-validate:

```bash
cd /var/www/umrah.binmishaltravels.com/web
cp deploy-prod.sh deploy-prod.sh.bak-$(date +%F)
bash -n deploy-prod.sh
docker compose -f docker-compose.prod.yml --env-file .env.production config --quiet
```

> **Never copy the dev `docker-compose.yml`.** Production is MySQL-only and
> has no Compose profiles; the project name must stay
> `umrah-binmishaltravels-com` or the existing MySQL volume will be orphaned.

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
    proxy_pass http://127.0.0.1:8002;
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

Push to `main` branch - CI builds/pushes new image automatically. Nothing
further happens on its own: **there is no Watchtower on the production
server**, so you deploy it yourself:

```bash
./deploy-prod.sh
```

To pin to a specific image tag (e.g., a known git SHA):
```bash
IMAGE_TAG=sha-abc123def ./deploy-prod.sh
```

This is useful for rolling back to a known-good version. List available tags:
```bash
docker compose -f docker-compose.prod.yml --env-file .env.production pull app
```

## Rollback

```bash
# List available images
docker images ghcr.io/saifibnmazhar/umrah-app

# Roll back: run the same script with a known-good tag pinned
IMAGE_TAG=sha-abc123def ./deploy-prod.sh
```

You do **not** need `compose down` for a rollback — the script stops only the
`app` container and recreates it from the pinned image, leaving the database,
Redis and all volumes untouched.

## Multiple Sites on One Server

Each site runs from its own **hand-maintained directory** (not a git clone)
with its own `.env.production` file. The Compose project name comes from
`COMPOSE_PROJECT_NAME` in that file, falling back to the compose file default
`umrah-binmishaltravels-com`, so differently-named sites never collide (the
project name controls the Compose project prefix: auto-generated container
names, network names, and volume names).

> **Note on the examples in this document:** the `docker compose` commands below
> omit `--env-file .env.production`, so they rely on the compose file's default
> project name. On a site whose `COMPOSE_PROJECT_NAME` differs from that
> default, add `--env-file .env.production` to every one of them — `deploy-prod.sh`
> always passes it.

Per-site checklist:

1. Create a site-specific directory and copy the three deployment files into
   it: `deploy-prod.sh`, `docker-compose.prod.yml` and `.env.production`
   (for a new site, start from `.env.production.sample`).
2. From a repo checkout, run `bash docker/scripts/setup-env.sh`, then tune the
   copied `.env.production` for this site.
3. Set the site-specific values **in `.env.production`** — `APP_PORT`,
   `DB_USERNAME`, `DB_DATABASE`, `COMPOSE_PROJECT_NAME`. They are read through
   Compose interpolation and `env_file`, not from a block at the top of the
   script, so the script itself never needs editing.
4. Validate, then deploy:
   ```bash
   bash -n deploy-prod.sh
   docker compose -f docker-compose.prod.yml --env-file .env.production config --quiet
   ./deploy-prod.sh
   ```
5. Give every site its own `COMPOSE_PROJECT_NAME` and `APP_PORT`.

> **WARNING:** never change `COMPOSE_PROJECT_NAME` for an existing site after its
> first deploy — the MySQL data volume name is derived from it, and changing it
> makes the app start against a fresh, empty database (the old data stays in the
> old volume, now orphaned).

**Existing sites:** `COMPOSE_PROJECT_NAME` is set explicitly in
`.env.production` (the first site uses `umrah-binmishaltravels-com`). Leave it
alone — the compose file default is a safety net for new sites only, and a
directory rename must never be allowed to rename the project.

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

`deploy-prod.sh` already does this automatically after every deploy: it clears the
config, route and view caches. Nothing needs to be run by hand.

`docker/entrypoint.sh` clears the same three caches on **every container start**,
which is what covers restarts that never run a deploy script (a server reboot,
`docker restart`, `docker compose restart`).

Manually (if the deploy script was interrupted, or you need to recover mid-session):

```bash
docker compose -f docker-compose.prod.yml exec app php artisan config:clear
docker compose -f docker-compose.prod.yml exec app php artisan route:clear
docker compose -f docker-compose.prod.yml exec app php artisan view:clear
```

> **Do not run `config:cache`, `route:cache` or `optimize` in production.**
> Serving cached config/routes/views after a deploy is what caused `419 CSRF token
> mismatch` on ticket issuance and the visa workflow. Caches are deliberately not
> built at container boot either — see `docker/entrypoint.sh`.

### Log all users out

`deploy-prod.sh` ends every successful deploy with:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan sessions:flush --force
```

Everyone who was logged in is redirected to the login page afterwards. Run it by hand
if you need to force a logout (for example after a security concern). It prompts for
confirmation unless `--force` is passed.

> Uses the store the active session driver really occupies. With
> `SESSION_DRIVER=redis` and no `SESSION_CONNECTION`, sessions live in the redis
> **default** database (`REDIS_DB`), not in `REDIS_CACHE_DB`, so
> `php artisan cache:clear` does **not** log anyone out.

### Redis persistence (sessions survive restarts)

`SESSION_DRIVER=redis`, so every logged-in user's session is a Redis key. The
`redis` service therefore runs with **append-only persistence on a named
volume**:

```yaml
command: ... --appendonly yes --appendfsync everysec
volumes:
  - redis_data:/data
```

Without it, restarting Redis — or the host — wiped every session: users were
returned to the login page, and an in-flight POST (ticket issuance, visa
workflow) failed with `419` because the session behind the CSRF token was gone.

Verify after a change:

```bash
docker compose -f docker-compose.prod.yml exec redis redis-cli --pass "$REDIS_PASSWORD" config get appendonly
docker compose -f docker-compose.prod.yml exec redis redis-cli --pass "$REDIS_PASSWORD" dbsize
# restart redis, then run dbsize again: the count must not drop to 0
```

`--maxmemory-policy allkeys-lru` still applies, but the app only uses Redis for
sessions, cache and rate limiting, and there is no `Cache::` usage in the code —
so the 128mb limit is not a practical eviction risk.

### 413 Request Entity Too Large (file uploads)

**Cause:** Uploading passenger documents (passport scans, visa copies) exceeds the body size limits at any of three layers: ISPConfig reverse proxy, container nginx, or PHP.

**Fix:**
1. ISPConfig Nginx proxy — add `client_max_body_size 100M;` to the Nginx directives in ISPConfig Panel (see Section 5 above).
2. Container nginx — `client_max_body_size 100M;` in `docker/nginx/conf.d/default.conf`.
3. PHP — `upload_max_filesize = 100M`, `post_max_size = 100M`, and `max_file_uploads = 20` in `docker/php/conf.d/zz-app.ini`.

After these changes, rebuild the Docker image (push to `main` triggers CI automatically).

## Staging Deployment

The staging workflow (`.github/workflows/staging.yml`) runs tests, builds the image
and pushes `staging` + `staging-<sha>` to ghcr.io when you push to the `staging`
branch:

```bash
git checkout -b staging
git push origin staging
```

**CI does not deploy it.** There is no SSH step and no Watchtower on the staging
server, so the image reaches staging only when you run the script there:

```bash
# On the staging server
IMAGE_TAG=staging-<sha> ./deploy-staging.sh
```

Staging runs the same guard rails as production: cache clears and a final
`sessions:flush --force`. No GitHub secrets are required for the staging
workflow.

**Note:** `/var/www/staging-umrah.binmishaltravels.com/web` is maintained by hand
(no git clone), so `deploy-staging.sh` and `docker-compose.staging.yml` on that
server must be updated in step with this repository.

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
