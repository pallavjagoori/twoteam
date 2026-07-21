# HTTP differential testing

Twoteam compares observable HTTP behavior against the pinned Chatwoot Rails
reference. A scenario defines the request made to each service and the smallest
explicit normalization needed to remove known volatile data.

## Contract format

Scenarios live in `contracts/http/scenarios.json`:

```json
{
  "id": "service-health",
  "reference": { "method": "GET", "path": "/health" },
  "candidate": { "method": "GET", "path": "/api/health" },
  "normalization": {
    "ignoreJsonPointers": ["/status", "/service"]
  }
}
```

The runner compares:

- HTTP status
- selected semantic response headers
- recursively sorted JSON bodies or exact text bodies

JSON Pointer exclusions and ignored headers must be declared per scenario.
Broad or implicit response filtering is prohibited. Domain contracts should
ignore only values proven to be volatile, such as generated timestamps or
request identifiers.

## Run

Start the Rails reference and Laravel API, then execute:

```bash
REFERENCE_URL=http://127.0.0.1:3100 \
CANDIDATE_URL=http://127.0.0.1:8000 \
node scripts/http-differential.mjs --report=/tmp/http-report.json
```

Any difference produces a report containing both normalized responses and a
non-zero exit status.

## Harness tests

```bash
node --test tests/contracts/http-differential.test.mjs
```

The tests prove explicit normalization succeeds while JSON body and HTTP status
differences fail. Each migrated Laravel domain must add scenarios for success,
validation, authentication, authorization and not-found behavior.
