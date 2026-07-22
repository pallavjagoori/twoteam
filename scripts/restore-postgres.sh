#!/usr/bin/env bash

set -euo pipefail

backup="${1:?Usage: restore-postgres.sh BACKUP TARGET_DATABASE}"
target="${2:?Usage: restore-postgres.sh BACKUP TARGET_DATABASE}"
compose_file="${TWOTEAM_COMPOSE_FILE:-infrastructure/compose.yml}"
user="${TWOTEAM_DATABASE_USER:-twoteam}"

if [[ ! "$target" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "Invalid target database name" >&2
  exit 1
fi
if [[ "${TWOTEAM_RESTORE_CONFIRM:-}" != "$target" ]]; then
  echo "Set TWOTEAM_RESTORE_CONFIRM=$target to authorize restore" >&2
  exit 1
fi
test -s "$backup"

docker compose -f "$compose_file" exec -T postgres \
  dropdb --username "$user" --if-exists --force "$target"
docker compose -f "$compose_file" exec -T postgres \
  createdb --username "$user" "$target"
docker compose -f "$compose_file" exec -T postgres \
  pg_restore --username "$user" --dbname "$target" --no-owner --no-acl \
  --exit-on-error < "$backup"
echo "PostgreSQL restore completed: $target"
