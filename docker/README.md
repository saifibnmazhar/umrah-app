# Docker Environment

## Selecting a Database Type

**Development only.** The dev `docker-compose.yml` picks the engine with a
Compose profile:

```dotenv
DB_TYPE=mysql        # Use MySQL (default)
DB_TYPE=postgresql   # Use PostgreSQL
```

`docker-compose.prod.yml` and `docker-compose.staging.yml` have **no
profiles and no PostgreSQL service** — production and staging are MySQL-only.
Setting `DB_TYPE` in `.env.production` / `.env.staging` does nothing; keep it
there only so a developer copying a server env file is not confused.

## Project Name

`COMPOSE_PROJECT_NAME` controls the Docker project name (used as a prefix for all container and volume names). If not set, it defaults to `umrah-app` (`umrah-app-staging` in `docker-compose.staging.yml`).

For deployments at `/var/www/<domain>/web/`, set it to match the domain:

```dotenv
# For /var/www/umrah.binmishaltravels.com/web/
COMPOSE_PROJECT_NAME=umrah-binmishaltravels-com
```

The `docker-compose.prod.yml` default is `umrah-binmishaltravels-com`. **Never
change it on an existing site** — the volume names are prefixed with the
project name, so a rename starts the app empty with a fresh database.

The deploy scripts do not derive this themselves: it comes from
`COMPOSE_PROJECT_NAME` in `.env.production` / `.env.staging`, falling back to
the compose file default.

## Boot-time guard rails

`docker/entrypoint.sh` runs before anything else and:

- creates/owns the persistent storage paths
- **clears** the config, route and view caches (it never *builds* them; this is
  hygiene — the `419 CSRF token mismatch` incidents were idle sessions ageing out
  after `SESSION_LIFETIME`, see `docs/plans/15-*.md` "Round 2")
- runs migrations unless `MIGRATE=false`

`docker/supervisord.conf` then starts `php-fpm` and `nginx`.

## Dev Environment

```bash
# MySQL (default)
docker compose --profile mysql up -d

# PostgreSQL
docker compose --profile postgresql up -d
```

## Production / Staging

```bash
# Via deploy scripts (sets --env-file, project name, ordering, guards)
./deploy-prod.sh       # docker-compose.prod.yml + .env.production
./deploy-staging.sh    # docker-compose.staging.yml + .env.staging

# Or manually — note: no --profile, MySQL only
docker compose -f docker-compose.prod.yml \
  --env-file .env.production \
  up -d
```

## Key Variables

| Variable              | Description              | Default                  |
|-----------------------|--------------------------|--------------------------|
| COMPOSE_PROJECT_NAME  | Docker project name      | `umrah-app` (prod: `umrah-binmishaltravels-com`) |
| DB_TYPE               | Dev DB engine (ignored by prod/staging compose) | `mysql`                  |
| DB_IMAGE              | DB image:tag             | `mysql:8.0` (dev may use `postgres:16-alpine`) |
| APP_IMAGE             | App image (prod/staging) | (must be set)            |
| IMAGE_TAG             | App image tag            | `latest` / `staging`     |
| APP_PORT              | App host port            | `8080` / `8000` / `8001` |
| DB_DATABASE           | Database name            | `umrah_app_dev` / `binmishal_umrah_live` / `umrah_staging` |
| DB_USERNAME           | Database user            | `umrah_app_user` / `binmishal_umrah` / `stageuser` |
| DB_PASSWORD           | Database password        | (must be set)            |
| DB_EXPOSE_PORT        | DB host port (dev only)  | `3306` / `5432`          |
| REDIS_IMAGE           | Redis image              | `redis:7-alpine`         |
| REDIS_MAX_MEMORY      | Redis maxmemory          | `128mb`                  |

## DB Access

### Dev
The dev DB is published on `127.0.0.1:${DB_EXPOSE_PORT}` — connect with any GUI client (TablePlus, DBeaver, etc.):
- **Host:** `127.0.0.1`
- **Port:** `3306` (MySQL) or `5432` (PostgreSQL)
- **User:** `umrah_app_user`
- **Password:** `dev_password`
- **Database:** `umrah_app_dev`

### Production / Staging
The DB is **NOT published** (no host port mapping). Access via an SSH tunnel
(MySQL only — prod and staging have no PostgreSQL service):

```bash
ssh -L 3306:db:3306 youruser@prod-server -N
```

Then connect locally to `127.0.0.1:3306` with the credentials from your
`.env.production`.

### Multiple Containers / Multiple Projects
If multiple Docker projects run on the same server, ensure each has a unique `COMPOSE_PROJECT_NAME` and unique `APP_PORT` / `DB_EXPOSE_PORT` values to avoid conflicts.

## Notes
- No static container names — Docker generates names from `COMPOSE_PROJECT_NAME` (e.g., `umrah-app-db-1`).
- Profiles (`mysql` / `postgresql`) exist only in the dev `docker-compose.yml`.
  The prod and staging compose files select MySQL directly.
