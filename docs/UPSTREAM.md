# Chatwoot upstream source

## Pinned release

| Field | Value |
| --- | --- |
| Repository | `chatwoot/chatwoot` |
| Release | `v4.16.0` |
| Release date | 2026-07-18 |
| Commit | `00a50dd79cdf011340106eeb1584fd8cba6cdee4` |
| Root tree | `7c45ef2023cc6cc1f0a29953fbba2b47b9fa7cb7` |
| Import date | 2026-07-21 |

Machine-readable metadata is stored in
[`upstream/CHATWOOT_VERSION`](../upstream/CHATWOOT_VERSION).

## Source policy

`upstream/chatwoot` is an exact source snapshot of the pinned release. It is
not a place for Twoteam customizations. Keeping the complete tree preserves the
Vue application, translations, assets, build configuration, tests and the Rails
reference implementation needed during migration.

Twoteam-specific code belongs in `apps`, `contracts`, `tests`,
`infrastructure`, `scripts` or `docs`.

The imported source retains Chatwoot's upstream license and notices. Twoteam
must preserve those files during every update.

## Integrity verification

The recorded Git tree SHA identifies file contents, executable modes, symbolic
links and directory structure. CI compares the imported subtree with that SHA:

```bash
bash scripts/validate-upstream.sh
```

Before committing a new import, validate the staged snapshot:

```bash
bash scripts/validate-upstream.sh --cached
```

A mismatch means the snapshot is incomplete, modified or recorded against the
wrong upstream commit.

## Update procedure

1. Select a stable Chatwoot release.
2. Resolve the release tag to its exact commit and root tree SHA.
3. Replace the complete `upstream/chatwoot` snapshot.
4. Update `upstream/CHATWOOT_VERSION` and this document.
5. Run cached integrity validation.
6. Build and test the upstream frontend.
7. Inventory API, dependency, migration, job and realtime changes.
8. Run Rails-versus-Laravel contracts.
9. Update the compatibility dashboard.
10. Publish the update as a reviewable pull request.

Stable releases are production candidates. Chatwoot's `develop` branch may be
checked separately for early warnings but is not imported into a production
Twoteam release without an explicit decision.
