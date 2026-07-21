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
| Authentication | @pallavjagoori | Not started | Not started | Not started | N/A | Not started | Not started |
| Accounts | @pallavjagoori | Not started | Not started | N/A | Not started | Not started | Not started |
| Authorization | @pallavjagoori | Not started | N/A | N/A | N/A | Not started | Not started |
| Teams and agents | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Contacts | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Inboxes | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Conversations | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Messages | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Labels and assignments | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Attachments | @pallavjagoori | Not started | Not started | Not started | N/A | Not started | Not started |
| Website widget | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
| Notifications | @pallavjagoori | Not started | Not started | Not started | Not started | Not started | Not started |
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
