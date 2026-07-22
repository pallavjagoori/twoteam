# Chatwoot upstream update analysis

Generated automatically from source trees. The candidate source remains outside
Twoteam's pinned, read-only `upstream/chatwoot` snapshot.

| Field | Value |
| --- | --- |
| Baseline | `v4.16.0` |
| Candidate | `89b83c65c843e87018fa2e6d190cf3fbc65c880e` |
| Changed files | 94 |
| Added / removed frontend requests | 1 / 0 |
| Dependency changes | 1 |
| New Rails assumptions | 0 |
| Relevant backend changes | 6 |

## Added frontend requests

- `GET this.url (app/javascript/dashboard/api/calls.js)`

## Removed frontend requests

- None

## New Rails-specific assumptions

- None

## Backend change counts

- routes: 1
- api: 4
- migrations: 0
- jobs: 0
- mailers: 0
- realtime: 0
- models: 0
- services: 1

## Required compatibility gates

- [ ] Build and test the unmodified candidate frontend
- [ ] Register added and removed frontend requests in compatibility contracts
- [ ] Classify new Rails-specific frontend assumptions
- [ ] Translate relevant migrations, jobs, events, and payload changes to Laravel
- [ ] Run HTTP and side-effect contracts against Rails and Laravel
- [ ] Run critical browser workflows and update docs/COMPATIBILITY.md
