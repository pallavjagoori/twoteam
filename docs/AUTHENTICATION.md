# Authentication compatibility

Twoteam accepts the Devise Token Auth headers used by the unchanged Chatwoot
frontend while storing credentials with Laravel primitives.

## Implemented contract

| Operation | Route |
| --- | --- |
| Password sign-in | `POST /auth/sign_in` |
| Validate session | `GET /auth/validate_token` |
| Sign out | `DELETE /auth/sign_out` |

Successful sign-in returns `access-token`, `client`, `expiry`, `token-type` and
`uid` response headers. The frontend stores these headers in its existing
`cw_d_session_info` cookie and sends them on later requests.

Plaintext access tokens are returned once and never stored. PostgreSQL stores a
SHA-256 token digest, client UUID and expiration. Passwords use Laravel's
configured adaptive password hash. Validation requires all headers, a `Bearer`
token type, matching email identity and an unexpired token. Sign-out deletes
the active token.

## Supported behavior

- case-insensitive, whitespace-normalized email login
- confirmed-user enforcement
- Chatwoot-compatible invalid-credential and unconfirmed-user error shapes
- session validation and revocation
- minimal Chatwoot user payload

Account memberships are populated by PR 012 from the `account_users` tenant
boundary. The account list includes role, availability, active timestamp and
permissions required by the frontend bootstrap. MFA, SSO, password recovery
and session-limit management remain
visible follow-up work; they must not be treated as compatible yet.

## Coverage

Authentication tests cover success, invalid credentials, confirmation,
plaintext-token absence, token validation, missing headers, UID mismatch,
expiration and revocation. CI enforces at least 85% total Laravel line coverage;
the authentication slice currently exceeds the stricter project target.
