#!/usr/bin/env bash

set -euo pipefail

required_files=(
  README.md
  docs/ARCHITECTURE.md
  docs/COMPATIBILITY.md
  docs/DEFINITION_OF_DONE.md
  docs/DEVELOPMENT.md
  docs/FRONTEND_INVENTORY.md
  docs/PROJECT_PLAN.md
  docs/STATUS.md
  contracts/frontend/inventory.json
  docs/adr/README.md
  .github/CODEOWNERS
  .github/PULL_REQUEST_TEMPLATE.md
)

for file in "${required_files[@]}"; do
  if [[ ! -s "$file" ]]; then
    echo "Missing or empty governance file: $file" >&2
    exit 1
  fi
done

for number in $(seq -w 1 36); do
  if ! grep -Eq "[| ]0*${number}[ |]" docs/STATUS.md; then
    echo "Roadmap PR $number is missing from docs/STATUS.md" >&2
    exit 1
  fi
done

required_domains=(
  Authentication
  Accounts
  Authorization
  Conversations
  Messages
  "Website widget"
  Notifications
  Email
  WhatsApp
  Reports
  "Help Center and CSAT"
)

for domain in "${required_domains[@]}"; do
  if ! grep -Fq "| $domain |" docs/COMPATIBILITY.md; then
    echo "Compatibility domain is missing: $domain" >&2
    exit 1
  fi
done

if grep -Eq '\|[[:space:]]*\|[[:space:]]*(Not started|Researching|In progress|Compatible|Blocked|Unsupported)' docs/COMPATIBILITY.md; then
  echo "Compatibility domain has no owner" >&2
  exit 1
fi

echo "Governance validation passed"
