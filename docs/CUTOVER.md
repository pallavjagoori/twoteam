# Laravel production cutover

## Preconditions

Cut over only a deployment whose intended workflows fit
[`SUPPORTED_SCOPE.md`](SUPPORTED_SCOPE.md). All Platform, Governance, hardening
and cutover checks must be green on the exact commit. Record the API and web
image digests, take an encrypted PostgreSQL backup and confirm `/api/health/ready`
before changing traffic.

The production stack is rendered with mandatory secrets:

```sh
export APP_KEY='base64:...'
export APP_URL='https://support.example.com'
export POSTGRES_PASSWORD='...'
export REDIS_PASSWORD='...'
export AWS_ACCESS_KEY_ID='...'
export AWS_SECRET_ACCESS_KEY='...'
export AWS_BUCKET='twoteam-production'
export TWOTEAM_API_IMAGE='registry.example.com/twoteam-api:<immutable-tag>'
export TWOTEAM_WEB_IMAGE='registry.example.com/twoteam-web:<immutable-tag>'
docker compose -f infrastructure/compose.production.yml config --quiet
corepack pnpm cutover:verify
```

`APP_URL` must be HTTPS. Secrets belong in the deployment platform, not shell
history or committed files.

## Deploy and verify

```sh
docker compose -f infrastructure/compose.production.yml up -d --wait
corepack pnpm cutover:smoke -- http://127.0.0.1:8080
```

The migration container must exit successfully before PHP-FPM, worker and
scheduler traffic begins. Nginx serves only immutable Vue assets and forwards
all application requests to Laravel. Confirm:

- liveness and dependency readiness are green
- `/app/login` renders the pinned, unmodified Vue login entrypoint
- authentication requests are answered by Laravel
- the API image contains no Ruby, Rails or Gemfile
- queue failures, HTTP 429/5xx rates and provider delivery errors are stable

Shift traffic gradually at the load balancer. Do not configure a Rails fallback;
unsupported requests must remain visible failures rather than silently creating
split authority.

## Rollback rehearsal and execution

CI tags the same verified build as `current` and `previous`, switches both API
and web image references to the previous immutable tags, recreates the stack and
reruns the cutover smoke. This proves topology and operator commands on every PR.

For a real rollback, restore the recorded previous image digests and rerun
Compose. Application rollback is preferred over database reversal:

```sh
export TWOTEAM_API_IMAGE='registry.example.com/twoteam-api:<previous-digest>'
export TWOTEAM_WEB_IMAGE='registry.example.com/twoteam-web:<previous-digest>'
docker compose -f infrastructure/compose.production.yml up -d --wait
corepack pnpm cutover:smoke -- http://127.0.0.1:8080
```

If the release wrote incompatible data, stop writers and follow the isolated
restore procedure in [`OPERATIONS.md`](OPERATIONS.md). Never run an unreviewed
migration down against production.

## Rails disposition

Rails is absent from production services and final image stages. The pinned
Rails repository and deterministic reference Compose file remain offline for
future differential analysis. Removing that evidence would make upstream Vue
updates less safe and is not part of runtime removal.
