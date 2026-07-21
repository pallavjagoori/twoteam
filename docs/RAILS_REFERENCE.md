# Reproducible Rails reference

The Rails reference supplies observable Chatwoot v4.16.0 behavior for
differential contract tests. It is isolated from the Laravel development stack
and must never be used for production data.

## Pinned components

| Component | Pin |
| --- | --- |
| Chatwoot | `v4.16.0@sha256:64eafea4d4e8e73dcc534f7012cc93c0b04635f235861eda2d588d1f33166827` |
| PostgreSQL | `pgvector/pgvector:pg16@sha256:1d533553fefe4f12e5d80c7b80622ba0c382abb5758856f52983d8789179f0fb` |
| Redis | `redis:7.4-alpine@sha256:6ab0b6e7381779332f97b8ca76193e45b0756f38d4c0dcda72dbb3c32061ab99` |

The Chatwoot digest is the published multi-platform OCI index for v4.16.0.

## Reset and start

```bash
bash scripts/reference-reset.sh
```

This command removes only the `twoteam-reference` Docker volumes, creates a
fresh schema, loads fixed fixture identities and verifies their canonical
SHA-256 fingerprint before starting Rails on `http://127.0.0.1:3100`.

Reference login:

- email: `agent@twoteam.test`
- password: `Reference1!`

The credentials, tokens and signing key are deliberately deterministic and are
safe only for the local reference environment.

## Verify an existing reference

```bash
docker compose -f infrastructure/reference/compose.yml run --rm verify
```

## Stop

```bash
docker compose -f infrastructure/reference/compose.yml down
```

Add or change fixtures only through `seed.rb` and update the verified digest in
the same PR. Later contract tests must refer to stable fixture IDs rather than
database sequence order.
