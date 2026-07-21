#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repository_root="$(cd -- "$script_dir/.." && pwd)"
compose_file="$repository_root/infrastructure/reference/compose.yml"

docker compose -f "$compose_file" down --volumes --remove-orphans
docker compose -f "$compose_file" up -d --wait postgres redis
docker compose -f "$compose_file" run --rm prepare
docker compose -f "$compose_file" run --rm seed
docker compose -f "$compose_file" up -d --wait rails

echo "Chatwoot Rails reference is ready at http://127.0.0.1:3100"
