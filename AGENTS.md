# Twoteam agent guide

## Mission

Port the pinned Chatwoot frontend to a Laravel backend without editing
`upstream/chatwoot`. Preserve its ability to receive future upstream updates.

## Roadmap workflow

- Follow `docs/PROJECT_PLAN.md` in numeric order. One roadmap increment maps to
  one pull request; do not mark later work complete early.
- Start from current `main`, use a focused branch, and update `docs/STATUS.md`
  plus `docs/COMPATIBILITY.md` in every roadmap PR.
- State an observable completion signal in the PR description.
- Keep PRs draft until every required check passes. Merge only green PRs, then
  sync `main` before starting the next increment.
- Never claim the frontend is usable until the unchanged Chatwoot UI has been
  exercised against Laravel and a working URL is available.

## Compatibility and architecture

- Treat `upstream/chatwoot` as generated, read-only source.
- Put adaptations in `apps/web`, Laravel behavior in `apps/api`, and executable
  compatibility definitions in `contracts` and `tests`.
- Compare API behavior with the pinned Rails reference. Match status, response
  shape, authentication headers, side effects, tenant isolation, and errors.
- Use PostgreSQL and Redis behavior that is valid in production; SQLite-only
  success is insufficient.

## Quality gates

- Add positive, negative, authorization, and tenant-isolation tests for each
  backend behavior.
- Maintain 100% measured Laravel line coverage for every roadmap PR, including
  authorization policies and security-critical branches.
- Run formatting, Laravel tests, contract tests, Vue host tests/build, unchanged
  Chatwoot frontend build, upstream-integrity, and infrastructure validation as
  applicable.
- Do not weaken tests or coverage thresholds to obtain a green check.

## Safety

- Never commit credentials, tokens, generated secrets, or local environment
  files.
- Preserve unrelated user changes and avoid destructive Git operations.
- Record intentional incompatibilities explicitly in `docs/COMPATIBILITY.md`.
