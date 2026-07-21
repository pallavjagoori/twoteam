# Local development

## Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js 24
- Corepack with pnpm 10.2
- Docker with Compose

Laravel 13 is the backend baseline. The web host uses the same Vue, Vite and
pnpm major versions as the pinned Chatwoot frontend where practical.

## Install dependencies

```bash
make setup
```

Copy the backend environment and generate a local application key:

```bash
cp apps/api/.env.example apps/api/.env
cd apps/api
php artisan key:generate
```

## Start infrastructure

```bash
make infra-up
```

This starts PostgreSQL on host port 5433 and Redis on host port 6380. Containers
continue to use their standard internal ports. Override the host ports when
needed:

```bash
TWOTEAM_POSTGRES_PORT=55432 TWOTEAM_REDIS_PORT=56379 make infra-up
```

The credentials in the Compose file are development-only.

Run Laravel migrations:

```bash
cd apps/api
php artisan migrate
```

## Start applications

Run each process in a separate terminal:

```bash
make api-dev
make web-dev
```

- API: `http://localhost:8000`
- API health: `http://localhost:8000/api/health`
- Laravel framework health: `http://localhost:8000/up`
- Web host: `http://localhost:5173`

## Run validation

```bash
make test
corepack pnpm web:build
corepack pnpm web:chatwoot
docker compose -f infrastructure/compose.yml config --quiet
```

Feature and unit tests may isolate framework behavior. Compatibility and
integration tests added in later increments must run with PostgreSQL and Redis;
SQLite is not an acceptable substitute for those suites.
