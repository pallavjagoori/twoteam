#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repository_root="$(cd -- "$script_dir/.." && pwd)"
manifest="$repository_root/apps/web/dist/chatwoot/.vite/manifest.json"

if [[ ! -s "$manifest" ]]; then
  echo "Missing Chatwoot build manifest: $manifest" >&2
  exit 1
fi

entrypoints=(
  dashboard
  v3app
  portal
  widget
  sdk
  superadmin
  survey
  superadmin_pages
)

for entrypoint in "${entrypoints[@]}"; do
  if ! grep -Fq "entrypoints/$entrypoint.js" "$manifest"; then
    echo "Chatwoot build is missing entry point: $entrypoint" >&2
    exit 1
  fi
done

echo "All Chatwoot frontend entry points were built"
