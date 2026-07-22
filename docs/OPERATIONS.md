# Production operations

## Release safety gates

Every production release must pass the `hardening` CI job. It validates a
production-shaped environment, migrates PostgreSQL, verifies a custom-format
backup by restoring it into an isolated database, and sends 200 requests at a
concurrency of 20 to the running Laravel health endpoint. The load gate requires
zero errors and p95 latency below 750 ms on the CI runner.

Run the environment guard before deployment without printing secret values:

```sh
corepack pnpm production:validate
```

`apps/api/.env.production.example` lists the required security posture. Replace
every placeholder through the deployment platform's secret manager. Never
commit a populated environment file.

## Health and observability

- `GET /up` is the framework liveness probe.
- `GET /api/health` is the load-balancer liveness probe and performs no remote
  dependency work.
- `GET /api/health/ready` checks the database and cache and returns HTTP 503 if
  either is unavailable.
- Every API response returns `X-Request-ID`. A valid caller-provided ID is
  preserved; invalid values are replaced. Laravel logs receive the same ID as
  structured context.
- API responses enforce MIME sniffing, framing, referrer, browser-permission and
  HTTPS transport protections.
- The default API limit is 300 requests per IP per minute. Tune
  `API_RATE_LIMIT_PER_MINUTE` from observed production traffic, and alert on
  sustained HTTP 429 or 5xx responses.

Readiness must be removed from load-balancer rotation before database maintenance.
Liveness should remain available so the orchestrator does not restart a healthy
process merely because a dependency is being recovered.

## Backup and recovery

Create a PostgreSQL custom-format backup with owner and ACL metadata removed:

```sh
bash scripts/backup-postgres.sh /secure/path/twoteam-$(date +%F).dump
```

The script creates files with owner-only permissions. Production automation
must encrypt the result, copy it to versioned off-site storage, verify its
checksum, and enforce the organization's retention policy.

Restore only during an approved maintenance window. The confirmation value must
exactly match the target database, which is dropped and recreated:

```sh
TWOTEAM_RESTORE_CONFIRM=twoteam_restore \
  bash scripts/restore-postgres.sh /secure/path/twoteam.dump twoteam_restore
```

Rehearse the non-production recovery path with:

```sh
bash scripts/recovery-drill.sh
```

The drill backs up the migrated application database, restores an isolated
database, compares migration counts, and deletes the temporary database. Run it
at least monthly and after PostgreSQL version changes. The initial service
objective is RPO 24 hours and RTO 4 hours; tighten these only after measured
backup frequency and drill duration support the claim.

Application files on object storage need independent versioning and retention.
Redis is disposable: queue producers must be paused during recovery, and failed
or in-flight jobs must be reconciled from provider delivery records afterward.

## Incident rollback

1. Stop new deployments and remove unready instances from rotation.
2. Preserve logs, request IDs, provider delivery IDs and the current database.
3. Route traffic to the previous immutable Laravel release.
4. Roll back application code without reversing a migration unless its reviewed
   down path is proven safe.
5. If data restoration is necessary, pause all writers, restore to an isolated
   database, validate migration and tenant counts, then perform an explicit
   cutover.
6. Reconcile queued jobs and provider callbacks before reopening traffic.
7. Record the incident and add regression, load or recovery coverage.

PR 036 owns the final Laravel-only traffic switch. Its cutover runbook must use
these probes and recovery controls and must preserve a tested routing rollback.
