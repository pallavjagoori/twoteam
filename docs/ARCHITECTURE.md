# Architecture

## Objective

Run Chatwoot's upstream Vue frontend against a Laravel backend without turning
every future Chatwoot update into a manual frontend rewrite.

## Compatibility boundary

Laravel owns the implementation. Chatwoot's observable contracts define the
boundary:

- HTTP methods, paths, status codes and response shapes
- authentication cookies, headers and session behavior
- account isolation and authorization outcomes
- PostgreSQL schema semantics
- uploads and signed asset access
- queued side effects, mail and notifications
- webhook verification and delivery
- realtime subscriptions, event names and payloads

Laravel may use idiomatic internal services, policies, events and jobs, but its
external behavior must match the supported Chatwoot frontend version.

## Components

### Upstream source

`upstream/chatwoot` contains an exact Chatwoot commit. It includes the complete
repository because the frontend depends on translations, assets, Vite settings,
package metadata and Rails-oriented integration code in addition to Vue files.

Twoteam must not directly customize this directory. CI will eventually verify
that it matches the recorded upstream commit.

### Web host

`apps/web` builds Chatwoot's original Vue entry points and provides a narrow
integration layer. Vite aliases and adapters replace Rails-specific facilities:

| Chatwoot expectation | Twoteam adapter |
| --- | --- |
| Rails-configured Axios | Laravel API client bootstrap |
| Rails authentication state | Laravel-compatible auth adapter |
| Action Cable | Realtime compatibility adapter |
| Active Storage | Laravel signed-upload adapter |
| Rails runtime globals | Runtime configuration endpoint |
| Rails asset URLs | Vite/static asset resolver |
| Rails feature flags | Laravel configuration endpoint |

Laravel-specific code must not be scattered through upstream components or
state modules.

### Laravel API

`apps/api` contains domain services, policies, API resources, events, jobs and
provider adapters. Controllers should remain thin. Side effects that were hidden
in Rails callbacks should become explicit services, events or queued listeners.

### Contract registry

`contracts` records every frontend-used endpoint and supported realtime event.
Contracts include requests, responses, authorization, persistence, jobs and
events. The same scenarios will run against a pinned Rails reference and
Laravel.

## Data architecture

PostgreSQL is required. Twoteam initially preserves Chatwoot-compatible table
names, columns, identifiers, enum values, timestamps, indexes and constraints.
This enables data migration, differential testing and rollback. Schema cleanup
is deferred until Laravel compatibility is established.

Redis supports cache, queues and realtime infrastructure. Integration tests
must use PostgreSQL and Redis rather than substituting SQLite or in-memory
implementations.

## Realtime architecture

Laravel may use Reverb internally, but the frontend sees one compatibility
adapter. Internal events use stable domain names such as:

- `message.created`
- `message.updated`
- `conversation.created`
- `conversation.updated`
- `conversation.status_changed`
- `conversation.assignment_changed`
- `contact.updated`
- `presence.updated`
- `notification.created`
- `typing.started`
- `typing.stopped`

Serializers translate these events into the exact payload expected by the
supported Chatwoot frontend.

## Migration strategy

Features are ported as vertical workflows, not by translating Ruby files into
PHP. During cutover, Rails and Laravel run side by side. One implementation is
authoritative for writes in each domain. Safe reads may be mirrored and
compared before traffic is moved.

Rails can be removed only after no supported frontend request depends on it and
the production rollback procedure has been rehearsed.

## Architectural constraints

1. Do not modify upstream source for Twoteam behavior.
2. Do not redesign public payloads for Laravel convenience.
3. Do not redesign the database before compatibility is proven.
4. Do not mark an endpoint complete without its user workflow.
5. Do not use coverage percentage as a substitute for contract tests.
6. Do not permit cross-account access through unscoped identifiers.
7. Do not dual-write without idempotency and reconciliation.
