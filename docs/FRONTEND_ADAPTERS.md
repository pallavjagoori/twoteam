# Frontend compatibility adapters

Twoteam compiles Chatwoot's Vue entry points without editing the pinned upstream
tree. A Twoteam-owned Vite transform imports the runtime adapter before each
entry point executes.

## Runtime configuration contract

Laravel pages provide configuration as inert JSON instead of generating
executable JavaScript:

```html
<script id="twoteam-runtime-config" type="application/json">
  {"chatwootConfig":{"hostURL":"https://app.example.com"}}
</script>
```

Blade must generate this payload with Laravel's JSON rendering facilities, not
string concatenation. The adapter accepts only these compatibility keys:

- `analyticsConfig`
- `authToken`
- `browserConfig`
- `chatwootConfig`
- `chatwootPubsubToken`
- `chatwootSettings`
- `chatwootWebChannel`
- `errorLoggingConfig`
- `globalConfig`
- `portalConfig`

Unknown top-level keys are discarded. A JSON byte array supplied as
`chatwootConfig.vapidPublicKey` is restored to the `Uint8Array` expected by the
upstream application.

## Rails replacement boundaries

| Rails-provided facility | Twoteam boundary | Delivery milestone |
| --- | --- | --- |
| ERB-generated browser globals | Runtime configuration adapter | PR 006 |
| CSRF meta tag | Laravel Blade and CSRF middleware | Authentication slice |
| Devise token headers and cookies | Laravel authentication endpoints | PRs 009-010 |
| Action Cable protocol | Realtime compatibility service | PR 018 |
| Active Storage uploads | Attachment API and signed storage adapter | PR 016 |
| Vite Ruby entry tags | Laravel manifest renderer | First server-rendered frontend slice |

New Rails assumptions discovered during upstream updates must be added to this
table and isolated outside `upstream/chatwoot`.

## Verification

`corepack pnpm web:test` verifies parsing, key allow-listing and Web Push key
conversion. `corepack pnpm web:chatwoot` proves the adapter is bundled with all
eight upstream entry points. `scripts/validate-upstream.sh` proves the adapter
did not alter the pinned Chatwoot snapshot.
