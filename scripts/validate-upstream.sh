#!/usr/bin/env bash

set -euo pipefail

metadata_file="upstream/CHATWOOT_VERSION"
snapshot_path="upstream/chatwoot"
mode="${1:-committed}"

if [[ ! -f "$metadata_file" ]]; then
  echo "Missing upstream metadata: $metadata_file" >&2
  exit 1
fi

if [[ ! -d "$snapshot_path" ]]; then
  echo "Missing upstream snapshot: $snapshot_path" >&2
  exit 1
fi

# Values in this file are controlled repository metadata, not user input.
# shellcheck disable=SC1090
source "$metadata_file"

required_values=(
  SOURCE_REPOSITORY
  RELEASE
  TAG_OBJECT_SHA
  COMMIT_SHA
  TREE_SHA
  IMPORTED_AT
)

for name in "${required_values[@]}"; do
  if [[ -z "${!name:-}" ]]; then
    echo "Missing upstream metadata value: $name" >&2
    exit 1
  fi
done

case "$mode" in
  committed)
    actual_tree=$(git rev-parse "HEAD:$snapshot_path")
    ;;
  --cached)
    index_tree=$(git write-tree)
    actual_tree=$(git rev-parse "$index_tree:$snapshot_path")
    ;;
  *)
    echo "Usage: $0 [--cached]" >&2
    exit 1
    ;;
esac

if [[ "$actual_tree" != "$TREE_SHA" ]]; then
  echo "Upstream tree mismatch" >&2
  echo "Expected: $TREE_SHA" >&2
  echo "Actual:   $actual_tree" >&2
  exit 1
fi

echo "Chatwoot $RELEASE integrity verified at $COMMIT_SHA"
