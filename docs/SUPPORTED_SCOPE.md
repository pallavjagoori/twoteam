# Supported production scope

Twoteam's 1.0 cutover supports the workflows implemented and tested by roadmap
PRs 011-035. Roadmap completion means this declared scope runs without Rails;
it does not mean every Chatwoot Community or Enterprise feature is implemented.

## Supported workflows

- email/password sign-in, token validation and sign-out
- accounts, memberships, policies, teams and agent availability
- contacts, inboxes, conversations, messages, labels and assignments
- realtime replay/presence, private attachments and the website widget
- notifications, canned responses, macros, automations and business hours
- scoped search and reports
- inbound/outbound email, WhatsApp, Facebook and Instagram compatibility flows
- prioritized Telegram, LINE and SMS provider flows
- signed outgoing webhooks and delivery logs
- Help Center authoring/public reads and CSAT issuance, response and reporting
- production health, request correlation, security, rate limits, recovery and
  load gates

The authoritative machine-readable boundary is
`contracts/cutover/laravel-runtime.json`. It requires all 33 supported Laravel
controllers, at least 131 Laravel-owned routes and representative entry routes
for the critical domains. `scripts/verify-laravel-cutover.mjs` fails if that
surface shrinks, a handler stops being Laravel-owned, a production service is
added unexpectedly or an image rollback changes topology.

Unsupported frontend calls fail closed with Laravel HTTP 404/422 responses.
There is no proxy or fallback to Rails.

## Deliberately unsupported upstream families

The pinned source remains unmodified, so navigation or code for upstream
features outside the supported boundary may still exist in the bundle. The 1.0
runtime does not claim support for:

- Chatwoot Cloud billing, marketplace or Enterprise-only behavior
- super-admin and platform API administration
- Captain/AI, agent bots and external knowledge-base integrations
- campaigns, SLA policies, custom roles and audit logs
- OAuth, SAML, MFA, password reset, signup and email-confirmation workflows
- provider capabilities beyond the channel flows explicitly listed above
- Rails-rendered Help Center themes and other server-rendered ERB pages
- historical migration of an existing Chatwoot installation's production data

An existing Chatwoot operator must not treat the greenfield cutover as approval
to migrate live data. That requires a separate, installation-specific inventory,
dry-run import, tenant/count/checksum reconciliation and rollback approval.

## Reference source after cutover

`upstream/chatwoot` and `infrastructure/reference` remain in the repository as
read-only, non-production compatibility evidence. They are excluded from final
runtime images. The production Compose topology contains only Nginx, PHP-FPM,
Laravel migration/queue/scheduler processes, PostgreSQL and Redis.
