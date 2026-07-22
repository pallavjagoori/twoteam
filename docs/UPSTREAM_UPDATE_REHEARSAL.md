# Chatwoot upstream update analysis

Generated automatically from source trees. The candidate source remains outside
Twoteam's pinned, read-only `upstream/chatwoot` snapshot.

| Field | Value |
| --- | --- |
| Baseline | `v4.15.1` |
| Candidate | `v4.16.0@00a50dd79cdf011340106eeb1584fd8cba6cdee4` |
| Changed files | 1775 |
| Added / removed frontend requests | 12 / 0 |
| Dependency changes | 2 |
| New Rails assumptions | 0 |
| Relevant backend changes | 117 |

## Added frontend requests

- `GET `${this.url}/${assistantId}/drilldown` (app/javascript/dashboard/api/captain/assistant.js)`
- `GET `${this.url}/${assistantId}/stats` (app/javascript/dashboard/api/captain/assistant.js)`
- `GET `${this.url}/${assistantId}/summary` (app/javascript/dashboard/api/captain/assistant.js)`
- `GET `${this.url}/${id}/error_logs.csv` (app/javascript/dashboard/api/dataImports.js)`
- `GET `${this.url}/${id}/skip_logs.csv` (app/javascript/dashboard/api/dataImports.js)`
- `GET `${this.url}/${id}` (app/javascript/dashboard/api/dataImports.js)`
- `GET `${this.url}/drilldown` (app/javascript/dashboard/api/reports.js)`
- `GET `${this.url}topup_options` (app/javascript/dashboard/api/enterprise/account.js)`
- `POST `${this.url}/${id}/abandon` (app/javascript/dashboard/api/dataImports.js)`
- `POST `${this.url}/${id}/start` (app/javascript/dashboard/api/dataImports.js)`
- `POST `${this.url}/validate_source` (app/javascript/dashboard/api/dataImports.js)`
- `POST `${this.url}select_billing_currency` (app/javascript/dashboard/api/enterprise/account.js)`

## Removed frontend requests

- None

## New Rails-specific assumptions

- None

## Backend change counts

- routes: 1
- api: 28
- migrations: 18
- jobs: 6
- mailers: 1
- realtime: 0
- models: 29
- services: 34

## Required compatibility gates

- [ ] Build and test the unmodified candidate frontend
- [ ] Register added and removed frontend requests in compatibility contracts
- [ ] Classify new Rails-specific frontend assumptions
- [ ] Translate relevant migrations, jobs, events, and payload changes to Laravel
- [ ] Run HTTP and side-effect contracts against Rails and Laravel
- [ ] Run critical browser workflows and update docs/COMPATIBILITY.md
