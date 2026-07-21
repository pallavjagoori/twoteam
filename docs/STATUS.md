# Project status

## Summary

| Field | Value |
| --- | --- |
| Current phase | Authentication and tenancy |
| Completed roadmap work | PRs 001-011: authentication complete |
| In progress | PR 016 inboxes and channel abstraction |
| Completion on `main` | 41% |
| Completion after this PR | 44% |
| First usable release target | PR 022 website widget |
| Supported Chatwoot version | `v4.16.0` pinned for compatibility work |
| Production readiness | Not ready |

Progress changes only when a roadmap deliverable is merged and satisfies the
[Definition of Done](DEFINITION_OF_DONE.md). Open branches and draft pull
requests do not count as completed progress.

## Roadmap tracking

| PR | Deliverable | Weight reached | Status | Evidence |
| ---: | --- | ---: | --- | --- |
| 001 | Architecture and development baseline | 1% | Complete | Commit `82557bf` on `main` |
| 002 | Tracking dashboard, issue and PR templates | 2% | Complete | Governance checks and tracking files on `main` |
| 003 | Import pinned Chatwoot source | 5% | Complete | Chatwoot `v4.16.0` source and integrity metadata on `main` |
| 004 | Scaffold Laravel, Vite, PostgreSQL and Redis | 7% | Complete | Laravel 13, Vue host and local infrastructure on `main` |
| 005 | Build unmodified Chatwoot frontend | 10% | Complete | All upstream entry points compile in CI |
| 006 | Add Rails-replacement adapters | 15% | Complete | Tested runtime adapter injected into all upstream entry points |
| 007 | Endpoint and dependency inventory | 17% | Complete | 306 request sites and 71 package imports tracked by CI |
| 008 | Reproducible Rails reference | 19% | Complete | Pinned Rails image and deterministic fixture fingerprint on `main` |
| 009 | HTTP differential testing | 22% | Complete | Status, header and body comparison with CI failure-mode tests |
| 010 | Job, mail, webhook and realtime contracts | 25% | Complete | Four-category schema, scenario and CI validator on `main` |
| 011 | Users and authentication | 28% | Complete | Compatible token headers, validation, revocation and 100% application coverage on `main` |
| 012 | Accounts and memberships | 31% | Complete | 14 Laravel tests, 50 assertions, 100% coverage; membership bootstrap and tenant isolation pass |
| 013 | Authorization policies | 35% | Complete | 16 tests, 57 assertions; account policy and application at 100% coverage |
| 014 | Teams, agents and availability | 38% | Complete | 18 tests, 77 assertions, 100% Laravel coverage; agent availability and team CRUD pass |
| 015 | Contacts | 41% | Complete | 21 tests, 99 assertions, 100% Laravel coverage; contact CRUD, search, pagination and isolation pass |
| 016 | Inboxes and channel abstraction | 44% | Complete | 23 tests, 122 assertions, 100% coverage; Website Widget/API inbox workflows pass |
| 017 | Conversations | 49% | Not started | — |
| 018 | Messages | 55% | Not started | — |
| 019 | Labels and assignments | 60% | Not started | — |
| 020 | Realtime transport | 64% | Not started | — |
| 021 | Attachments and storage | 67% | Not started | — |
| 022 | Website widget | 70% | Not started | — |
| 023 | Notifications | 73% | Not started | — |
| 024 | Canned responses and macros | 76% | Not started | — |
| 025 | Automations and business hours | 80% | Not started | — |
| 026 | Search | 82% | Not started | — |
| 027 | Email | 85% | Not started | — |
| 028 | WhatsApp | 87% | Not started | — |
| 029 | Facebook and Instagram | 89% | Not started | — |
| 030 | Telegram, LINE and SMS | 91% | Not started | — |
| 031 | Outgoing webhooks and integrations | 93% | Not started | — |
| 032 | Reports | 95% | Not started | — |
| 033 | Help Center and CSAT | 97% | Not started | — |
| 034 | Automated upstream-update workflow | 98% | Not started | — |
| 035 | Production hardening | 99% | Not started | — |
| 036 | Laravel cutover and Rails removal | 100% | Not started | — |

Allowed statuses are `Not started`, `Researching`, `In progress`, `Blocked`,
`Complete` and `Unsupported`.

## Update rules

Every roadmap PR must update this file in the same change:

1. Set its status to `In progress` while the PR is open.
2. Replace the evidence placeholder with test, contract or release evidence.
3. Set its status to `Complete` only when the Definition of Done is satisfied.
4. Update the summary percentage only after the change reaches `main`.
5. Record blockers or deliberately unsupported behavior explicitly.
