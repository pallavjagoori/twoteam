# Compatibility dashboard

This dashboard records observable compatibility with the pinned Chatwoot
reference. A domain cannot be marked compatible based only on implementation or
code coverage; its registered contracts and required browser workflows must
pass.

## Status definitions

| Status | Meaning |
| --- | --- |
| Not started | No implementation or accepted compatibility evidence |
| Researching | Reference behavior is being inventoried |
| In progress | Implementation exists but at least one required gate is open |
| Compatible | All supported contracts and workflows pass |
| Blocked | Progress requires an explicit decision or external dependency |
| Unsupported | Deliberately excluded from the current product scope |

## Domain matrix

The initial owner is the repository owner until additional maintainers are
assigned.

| Domain | Owner | HTTP | Database | Jobs | Realtime | Browser | Overall |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Authentication | @pallavjagoori | In progress | In progress | Not started | N/A | Not started | In progress |
| Accounts | @pallavjagoori | Complete | Complete | Membership bootstrap, policy branches and tenant isolation | In progress | Complete | Not started |
| Authorization | @pallavjagoori | Not started | N/A | N/A | N/A | Not started | Not started |
| Teams and agents | @pallavjagoori | Complete | Complete | Agent availability, team CRUD and tenant isolation | In progress | Complete | Not started |
| Contacts | @pallavjagoori | Complete | Complete | CRUD, search, pagination and tenant isolation | In progress | Complete | Not started |
| Inboxes | @pallavjagoori | Complete | Complete | Website Widget/API CRUD, secret rotation and isolation | In progress | Complete | Not started |
| Conversations | @pallavjagoori | Complete | Complete | Inbox list, create, status, priority and isolation | Complete | Complete | Not started |
| Messages | @pallavjagoori | Complete | Complete | History, replies, notes, delivery, retry and deletion | Complete | Complete | Not started |
| Labels and assignments | @pallavjagoori | Complete | Complete | Label CRUD, tagging and agent/team assignment | Complete | Complete | Not started |
| Realtime transport | @pallavjagoori | Complete | Complete | Durable cursor-based event outbox | Complete | In progress | In progress |
| Attachments | @pallavjagoori | Complete | Complete | Private storage, signed downloads, limits and deletion cleanup | Complete | In progress | In progress |
| Website widget | @pallavjagoori | Complete | Complete | Expiring visitor sessions and scoped conversation bootstrap | Complete | Complete | Complete |
| Notifications | @pallavjagoori | Complete | Complete | Preference-aware delivery foundation and duplicate prevention | Complete | In progress | In progress |
| Canned responses and macros | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Automations and business hours | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Search | @pallavjagoori | Not started | Not started | Not started | N/A | Not started | Not started |
| Email | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| WhatsApp | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Facebook and Instagram | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Telegram, LINE and SMS | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Outgoing webhooks | @pallavjagoori | Not started | Not started | Not started | N/A | Not started | Not started |
| Reports | @pallavjagoori | Not started | Not started | Not started | N/A | Not started | Not started |
| Help Center and CSAT | @pallavjagoori | Not started | Not started | Not started | N/A | Not started | Not started |

## Evidence required for compatibility

Before changing a domain to `Compatible`, link or record:

- the pinned Chatwoot version and reference fixtures
- all frontend-used HTTP contracts
- allowed and denied authorization cases
- relevant database effects
- queued jobs, mail and webhook effects
- supported realtime event contracts
- critical browser workflows
- coverage results
- documented differences and unsupported behavior

Partial support remains `In progress`. Individual unsupported subfeatures must
be identified rather than hidden behind a domain-level compatible status.
