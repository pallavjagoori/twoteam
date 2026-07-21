# 0001: Preserve upstream and implement compatibility

- **Status:** Accepted
- **Date:** 2026-07-21

## Context

Twoteam needs Chatwoot's Vue frontend, a Laravel backend and a sustainable way
to consume future Chatwoot frontend releases. Directly editing or copying the
frontend would create an expanding patch set and recurring merge conflicts.

The frontend is also coupled to Rails through authentication, Action Cable,
Active Storage, runtime configuration and exact API response shapes.

## Decision

Keep a complete, unmodified Chatwoot snapshot under `upstream/chatwoot`.
Twoteam will build that frontend through a separate Vite host and replace
Rails-specific facilities through isolated adapters.

Laravel will implement the observable contracts used by the frontend. A pinned
Rails installation will serve as the compatibility reference until migration is
complete.

## Consequences

- Future source imports remain mechanically reviewable.
- Laravel sometimes needs to reproduce non-idiomatic response shapes.
- Contract and differential tests become first-class project assets.
- Database redesign is delayed until compatibility is established.
- Backend work is measured by complete workflows rather than translated files.

## Alternatives considered

### Fork and edit the frontend directly

Rejected because the patch set would grow and conflict with upstream changes.

### Rewrite both frontend and backend

Rejected because it discards the primary benefit of consuming future Chatwoot
frontend work and greatly expands project scope.

### Keep Rails indefinitely behind Laravel

Rejected as the target architecture because it does not meet the requirement to
replace the backend, though both systems will coexist during controlled cutover.
