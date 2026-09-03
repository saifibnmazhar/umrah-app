# Docker Environment

## Selecting a Database Type

Set `DB_TYPE` in your `.env.production` / `.env.staging` file:

```dotenv
DB_TYPE=mysql        # Use MySQL (default)
DB_TYPE=postgresql   # Use PostgreSQL
```

## Project Name

`COMPOSE_PROJECT_NAME` controls the Docker project name (used as a prefix for all container and volume names). If not set, it defaults to `umrah-app`.

For deployments at `/var/www/<domain>/web/`, set it to match the domain:

```dotenv
# For /var/www/umrah.binmishaltravels.com/web/
COMPOSE_PROJECT_NAME=umrah-binmishaltravels-com
```

The `deploy-prod.sh` and `deploy-staging.sh` scripts auto-derive this from the working directory path if not explicitly set.

## Dev Environment

```bash
# MySQL (default)
docker compose --profile mysql up -d

# PostgreSQL
docker compose --profile postgresql up -d
```

## Production / Staging

```bash
# Via deploy scripts (auto-handles profiles + project name)
./deploy-prod.sh       # reads DB_TYPE from .env.production
./deploy-staging.sh    # reads DB_TYPE from .env.staging

# Or manually
docker compose -f docker-compose.prod.yml \
  --env-file .env.production \
  --profile mysql \  # or postgresql
  up -d
```

## Key Variables

| Variable              | Description              | Default                  |
|-----------------------|--------------------------|--------------------------|
| COMPOSE_PROJECT_NAME  | Docker project name      | `umrah-app`              |
| DB_TYPE               | Database engine          | `mysql`                  |
| DB_IMAGE              | DB image:tag             | `mysql:8.0` / `postgres:16-alpine` |
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
The DB is **NOT published** (no host port mapping). Access via SSH tunnel:

```bash
# SSH tunnel to MySQL (port 3306 inside container)
ssh -L 3306:db:3306 youruser@prod-server -N

# SSH tunnel to PostgreSQL (port 5432 inside container, if using DB_TYPE=postgresql)
ssh -L 5432:db-postgres:5432 youruser@prod-server -N
```

Then connect locally to `127.0.0.1:3306` (MySQL) or `127.0.0.1:5432` (PostgreSQL) with the root credentials from your `.env.production`.

### Multiple Containers / Multiple Projects
If multiple Docker projects run on the same server, ensure each has a unique `COMPOSE_PROJECT_NAME` and unique `APP_PORT` / `DB_EXPOSE_PORT` values to avoid conflicts.

## Notes
- No static container names — Docker generates names from `COMPOSE_PROJECT_NAME` (e.g., `umrah-app-db-1`).
- When switching `DB_TYPE`, also update `DB_CONNECTION` in your Laravel `.env`.
- MySQL and PostgreSQL use different default ports (3306 vs 5432). Set `DB_EXPOSE_PORT` accordingly.
