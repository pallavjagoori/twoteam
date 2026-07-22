# Development standards

## Change boundaries

- Treat `upstream/chatwoot` as generated read-only source.
- Put frontend integration code in `apps/web`.
- Put Laravel implementation code in `apps/api`.
- Register frontend-facing behavior under `contracts`.
- Add an architecture decision when a change affects long-term compatibility.

## Branches and pull requests

Use small vertical pull requests. A PR should leave the repository in a
verifiable state and should not combine unrelated domains.

Recommended branch form:

```text
agent/<short-description>
```

Every PR must state:

- the user-visible outcome
- included and excluded scope
- Chatwoot version and behavior inspected
- affected HTTP, realtime, job and webhook contracts
- security and account-isolation impact
- tests and coverage
- known compatibility differences
- rollback approach

## Testing

The test stack will contain:

1. Chatwoot's existing frontend tests.
2. Twoteam frontend adapter tests.
3. Laravel unit and feature tests.
4. PostgreSQL, Redis, storage and mail integration tests.
5. Rails-versus-Laravel differential tests.
6. Realtime and webhook contract tests.
7. Playwright browser workflows.
8. Performance and mutation tests for critical domains.

Coverage targets are defined in the project plan. Coverage exclusions require a
reviewed reason. Every production defect must add a regression test.

## Database changes

Until compatibility is complete:

- preserve Chatwoot-compatible names and semantics
- use PostgreSQL in integration tests
- translate every supported upstream Rails migration
- document indexes and constraints that cannot be represented identically
- avoid cleanup migrations mixed with feature ports

## External integrations

Provider code must live behind an interface and include:

- encrypted credentials
- signature verification
- recorded fixture payloads
- idempotent ingestion
- retry and failure behavior
- delivery-status handling
- observable errors

## Documentation

Documentation is part of the change. Update the project plan, compatibility
matrix, architecture decision or operational guide in the same PR that changes
the corresponding behavior.

## Upstream update analysis

The `Upstream update analysis` workflow runs against Chatwoot `develop` every
Monday and accepts any tag, branch or commit through manual dispatch. It checks
out the candidate outside the read-only pinned snapshot and publishes JSON and
Markdown artifacts covering frontend requests, packages, Rails assumptions and
relevant backend changes.

For a local candidate checkout:

```sh
corepack pnpm upstream:analyze -- \
  --baseline upstream/chatwoot \
  --candidate /path/to/chatwoot \
  --baseline-ref v4.16.0 \
  --candidate-ref <commit> \
  --json artifacts/upstream-update/report.json \
  --markdown artifacts/upstream-update/report.md
```

The report is analysis evidence, not approval to import. Complete every listed
compatibility gate before updating the pinned snapshot.

## Website widget demo

Build the unchanged Chatwoot assets with a local asset origin, migrate and seed
Laravel, then start both servers:

```sh
TWOTEAM_ASSET_BASE_URL=http://127.0.0.1:4173/ corepack pnpm --filter @twoteam/web build:chatwoot
cd apps/api && php artisan migrate && php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
cd ../web && corepack pnpm exec vite preview --outDir dist/chatwoot --host 127.0.0.1 --port 4173
```

Create a visitor session with `POST /api/v1/widget/config` using website token
`twoteam-demo-widget`. Open `/widget` with that website token and the returned
`auth_token` as `cw_conversation`. The seeded local-only agent is
`test@example.com` with password `password`.
