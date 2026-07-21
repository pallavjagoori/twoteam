# Definition of Done

A feature is complete only when every applicable item below is satisfied.

## Compatibility

- [ ] The pinned Chatwoot reference behavior was inspected.
- [ ] Frontend callers were identified.
- [ ] HTTP contracts are registered.
- [ ] Response fields, types, errors, pagination and ordering match.
- [ ] Database effects match the supported reference behavior.
- [ ] Jobs, mail, webhooks and realtime effects match.
- [ ] Known differences are documented.
- [ ] No upstream Chatwoot file was modified.

## Security and tenancy

- [ ] Authentication behavior is tested.
- [ ] Authorization has allowed and denied cases.
- [ ] Account isolation has negative cross-account tests.
- [ ] Sensitive values do not appear in logs.
- [ ] Upload and webhook inputs are validated where applicable.
- [ ] Replay protection and idempotency exist where applicable.

## Engineering quality

- [ ] Database migrations and indexes are included.
- [ ] Jobs and external callbacks are idempotent.
- [ ] Unit tests pass.
- [ ] Feature tests pass.
- [ ] PostgreSQL and Redis integration tests pass.
- [ ] Differential tests pass.
- [ ] The critical browser workflow passes.
- [ ] Static analysis and formatting pass.
- [ ] Changed Laravel code has at least 90% line coverage.
- [ ] Changed domain logic has at least 85% branch coverage.
- [ ] Critical mutation-testing targets pass where applicable.

## Delivery

- [ ] Documentation is updated.
- [ ] The compatibility dashboard is updated.
- [ ] Deferred work is recorded as an issue or roadmap item.
- [ ] Operational and rollback impacts are documented.
- [ ] The PR explains validation evidence.

Returning a successful HTTP response is not sufficient to mark a feature done.
