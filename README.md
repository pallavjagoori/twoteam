# Twoteam

Twoteam is a compatibility-focused port of Chatwoot that keeps the upstream
Vue frontend mergeable while replacing the Rails backend with Laravel.

## Project status

The project is in its foundation phase. No production features are complete
yet.

## Documentation

- [Project plan](docs/PROJECT_PLAN.md)
- [Current status](docs/STATUS.md)
- [Compatibility dashboard](docs/COMPATIBILITY.md)
- [Pinned Chatwoot source](docs/UPSTREAM.md)
- [Local development](docs/LOCAL_DEVELOPMENT.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Frontend compatibility adapters](docs/FRONTEND_ADAPTERS.md)
- [Frontend endpoint and dependency inventory](docs/FRONTEND_INVENTORY.md)
- [Development standards](docs/DEVELOPMENT.md)
- [Definition of Done](docs/DEFINITION_OF_DONE.md)
- [Architecture decisions](docs/adr/README.md)

## Planned repository layout

```text
twoteam/
├── upstream/chatwoot/       # Unmodified upstream source
├── apps/web/                # Twoteam frontend host and adapters
├── apps/api/                # Laravel application
├── contracts/               # HTTP, realtime, job and webhook contracts
├── tests/                   # Compatibility, browser and performance tests
├── infrastructure/         # Local and production infrastructure
└── docs/                    # Project documentation and decisions
```

The contents of `upstream/chatwoot` will be treated as generated, read-only
source. Twoteam changes must live outside that directory.
