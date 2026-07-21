# Side-effect contracts

HTTP compatibility is incomplete when a request returns the right response but
queues the wrong work or emits the wrong external event. Twoteam records four
observable side-effect categories:

| Category | Required identity |
| --- | --- |
| Jobs | class name, queue and normalized arguments |
| Mail | mailer, action, recipients and subject |
| Webhooks | event, method, URL and normalized body |
| Realtime | event, stream and normalized payload |

Contracts are stored under `contracts/side-effects`. Category entries are
compared independent of capture order; payload content remains exact.

## Validate

```bash
node scripts/side-effect-contracts.mjs contracts/side-effects/*.json
node --test tests/contracts/side-effect-contracts.test.mjs
```

The initial outgoing-message scenario exercises all four categories. Its fixed
IDs match the reproducible Rails fixture. Later capture adapters will translate
Rails Active Job/Action Mailer/Action Cable observations and Laravel
queue/mail/broadcast observations into this common representation.

Every domain contract must include all applicable side effects. An intentionally
empty category must be represented by a separate scenario type in a future
schema revision rather than silently omitted from a complete-effect scenario.
