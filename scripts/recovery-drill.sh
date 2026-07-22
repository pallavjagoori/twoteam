#!/usr/bin/env bash

set -euo pipefail

compose_file="${TWOTEAM_COMPOSE_FILE:-infrastructure/compose.yml}"
source_database="${TWOTEAM_DATABASE:-twoteam}"
target_database="twoteam_recovery_drill"
user="${TWOTEAM_DATABASE_USER:-twoteam}"
backup="$(mktemp -t twoteam-recovery.XXXXXX.dump)"

cleanup() {
  rm -f "$backup"
  docker compose -f "$compose_file" exec -T postgres \
    dropdb --username "$user" --if-exists --force "$target_database" >/dev/null
}
trap cleanup EXIT

TWOTEAM_DATABASE="$source_database" bash scripts/backup-postgres.sh "$backup"
TWOTEAM_RESTORE_CONFIRM="$target_database" \
  bash scripts/restore-postgres.sh "$backup" "$target_database"

source_migrations="$(docker compose -f "$compose_file" exec -T postgres \
  psql --username "$user" --dbname "$source_database" --tuples-only --no-align \
  --command 'select count(*) from migrations')"
restored_migrations="$(docker compose -f "$compose_file" exec -T postgres \
  psql --username "$user" --dbname "$target_database" --tuples-only --no-align \
  --command 'select count(*) from migrations')"

if [[ "$source_migrations" != "$restored_migrations" || "$source_migrations" == "0" ]]; then
  echo "Recovery drill migration count mismatch" >&2
  exit 1
fi

echo "Recovery drill passed with $restored_migrations migrations"
