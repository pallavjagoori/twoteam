#!/usr/bin/env bash

set -euo pipefail

output="${1:-artifacts/backups/twoteam.dump}"
compose_file="${TWOTEAM_COMPOSE_FILE:-infrastructure/compose.yml}"
database="${TWOTEAM_DATABASE:-twoteam}"
user="${TWOTEAM_DATABASE_USER:-twoteam}"

if [[ ! "$database" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "Invalid database name" >&2
  exit 1
fi

umask 077
mkdir -p "$(dirname "$output")"
docker compose -f "$compose_file" exec -T postgres \
  pg_dump --username "$user" --dbname "$database" --format custom \
  --no-owner --no-acl > "$output"
test -s "$output"
echo "PostgreSQL backup verified: $output"
