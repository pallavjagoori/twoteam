# Project plan

## Objective

Twoteam will run Chatwoot's upstream Vue frontend against Laravel while
preserving a predictable path for future frontend updates.

Progress is based on merged and validated capabilities. It is not based on
translated files or lines of code.

## Progress model

| Milestone | Weight |
| --- | ---: |
| Governance and repository foundation | 5% |
| Upstream frontend integration | 10% |
| Contract and test infrastructure | 10% |
| Authentication and tenancy | 10% |
| Core inbox and messaging | 25% |
| Realtime, uploads and website widget | 10% |
| Operations and productivity features | 10% |
| Channels and integrations | 10% |
| Reports and Help Center | 5% |
| Upstream automation and production cutover | 5% |
| **Total** | **100%** |

A PR contributes progress only after it is merged, required tests pass,
documentation is updated and deferred behavior is visible in the compatibility
matrix.

## Delivery roadmap

### Foundation

| PR | Deliverable | Cumulative progress | Exit evidence |
| ---: | --- | ---: | --- |
| 001 | Architecture and development baseline | 1% | Documents reviewed and linked |
| 002 | Tracking dashboard, issue and PR templates | 2% | Every domain has status and owner |
| 003 | Import pinned Chatwoot source | 5% | Re-import is clean; integrity check passes |
| 004 | Scaffold Laravel, Vite, PostgreSQL and Redis | 7% | Local stack and CI builds pass |
| 005 | Build unmodified Chatwoot frontend | 10% | Login view renders; upstream tests run |
| 006 | Add Rails-replacement adapters | 15% | Rails dependencies isolated and tested |

### Compatibility infrastructure

| PR | Deliverable | Cumulative progress | Exit evidence |
| ---: | --- | ---: | --- |
| 007 | Endpoint and dependency inventory | 17% | Every detected frontend request registered |
| 008 | Reproducible Rails reference | 19% | Deterministic fixtures reset successfully |
| 009 | HTTP differential testing | 22% | Meaningful response differences fail CI |
| 010 | Job, mail, webhook and realtime contracts | 25% | One scenario validates all side effects |

### Authentication and tenancy

| PR | Deliverable | Cumulative progress | Exit evidence |
| ---: | --- | ---: | --- |
| 011 | Users and authentication | 28% | Upstream login works against Laravel |
| 012 | Accounts and memberships | 31% | Account switching and bootstrap match |
| 013 | Authorization policies | 35% | Account isolation reaches 100% coverage |

### Core inbox

| PR | Deliverable | Cumulative progress | Exit evidence |
| ---: | --- | ---: | --- |
| 014 | Teams, agents and availability | 38% | Presence and membership workflows pass |
| 015 | Contacts | 41% | List, update and search workflows pass |
| 016 | Inboxes and channel abstraction | 44% | Basic inbox administration works |
| 017 | Conversations | 49% | Upstream inbox list runs on Laravel |
| 018 | Messages | 55% | Replies, notes, retries and history pass |
| 019 | Labels and assignments | 60% | UI and realtime assignment flows pass |

### Realtime, storage and widget

| PR | Deliverable | Cumulative progress | Exit evidence |
| ---: | --- | ---: | --- |
| 020 | Realtime transport | 64% | Two sessions synchronize and reconnect |
| 021 | Attachments and storage | 67% | Secure upload and download flows pass |
| 022 | Website widget | 70% | Visitor-to-agent conversation passes |

PR 022 is the first usable release. It proves the upstream frontend, Laravel
backend and realtime transport work together end to end.

### Operations and productivity

| PR | Deliverable | Cumulative progress | Exit evidence |
| ---: | --- | ---: | --- |
| 023 | Notifications | 73% | Preferences and duplicate prevention pass |
| 024 | Canned responses and macros | 76% | Agent workflows pass |
| 025 | Automations and business hours | 80% | Timezone, idempotency and mutation tests pass |
| 026 | Search | 82% | Scoped results and performance pass |

### Channels and integrations

Every channel requires encrypted credentials, webhook verification, recorded
fixtures, idempotent ingestion, outgoing jobs, retries, delivery status and an
end-to-end sandbox test where available.

| PR | Deliverable | Cumulative progress | Exit evidence |
| ---: | --- | ---: | --- |
| 027 | Email | 85% | Inbound and outbound threading passes |
| 028 | WhatsApp | 87% | Text, media and delivery callbacks pass |
| 029 | Facebook and Instagram | 89% | Auth, webhook and message flows pass |
| 030 | Telegram, LINE and SMS | 91% | Prioritized channel workflows pass |
| 031 | Outgoing webhooks and integrations | 93% | Signing, retries and delivery logs pass |

### Product completion and cutover

| PR | Deliverable | Cumulative progress | Exit evidence |
| ---: | --- | ---: | --- |
| 032 | Reports | 95% | Fixed datasets and performance match |
| 033 | Help Center and CSAT | 97% | Public, draft and survey flows pass |
| 034 | Automated upstream-update workflow | 98% | A real update is analyzed automatically |
| 035 | Production hardening | 99% | Recovery, security and load checks pass |
| 036 | Laravel cutover and Rails removal | 100% | No supported request depends on Rails |

## Coverage gates

| Area | Line coverage | Branch coverage |
| --- | ---: | ---: |
| Laravel domain services | 90% | 85% |
| Authentication and account isolation | 100% | 100% |
| Authorization policies | 100% | 100% |
| Webhook security and sensitive calculations | 100% | 95% |
| Controllers and API resources | 85% | 80% |
| Jobs and event listeners | 90% | 85% |
| Frontend adapters | 90% | 85% |
| Twoteam frontend additions | 85% | 80% |
| Overall Laravel application | 85% | 80% |

Changed Laravel code must reach 90% line coverage. Changed domain logic must
reach 85% branch coverage. Critical authorization, automation, signature and
deduplication logic targets an 80% mutation score.

Coverage does not replace compatibility testing. All frontend-used endpoints,
supported realtime events and supported webhook signatures must be registered
in the contract suite.

## Upstream update process

For each Chatwoot release:

1. Record current and target commits.
2. Import the target source into an update branch.
3. Build and test the frontend.
4. Detect API usage and response-field changes.
5. Detect new Rails-specific imports.
6. Inspect Rails migrations, jobs and events.
7. Run contracts against the updated Rails reference.
8. Run the same contracts against Laravel.
9. Update the compatibility matrix.
10. Implement missing Laravel behavior.
11. Run critical browser workflows.
12. Publish a compatibility report before merging.

`develop` provides early warning. Stable Chatwoot tags or its stable branch are
used for Twoteam production releases.

## Cutover strategy

Rails and Laravel run side by side during migration. Domains move independently.
One system remains authoritative for writes in each domain. Safe reads may be
mirrored and compared. Rollback routing remains available until the migrated
domain is stable.

Uncontrolled dual writes are prohibited. Any required dual-write path needs
idempotency, reconciliation, source-of-truth rules and divergence alerts.

## Completion criteria

The agreed scope is complete when:

- the supported frontend builds from an unmodified upstream snapshot
- no supported frontend request depends on Rails
- Laravel satisfies all registered contracts
- supported channels pass end to end
- database reconciliation passes
- account-isolation tests are complete
- browser, coverage and mutation gates pass
- a real upstream update has completed successfully
- production cutover and rollback have been rehearsed
- known differences and unsupported features are documented
