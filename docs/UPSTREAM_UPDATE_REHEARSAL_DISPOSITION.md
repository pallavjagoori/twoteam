# Completed upstream update rehearsal

Twoteam replayed the official Chatwoot `v4.15.1` → `v4.16.0` stable update
through the automated analysis and the final pinned build. The raw report is in
[`UPSTREAM_UPDATE_REHEARSAL.md`](UPSTREAM_UPDATE_REHEARSAL.md), with its complete
JSON evidence under `contracts/upstream/v4.15.1-to-v4.16.0.json`.

## Result

- [x] Recorded official baseline and candidate tags/commits.
- [x] Analyzed 1,775 changed files automatically without mutating either tree.
- [x] Built all eight unmodified `v4.16.0` Vue entrypoints in local and GitHub
  gates.
- [x] Classified 12 new frontend calls: the report drilldown is supported by
  Laravel; Captain, data-import and Enterprise billing calls are explicitly
  outside the 1.0 scope.
- [x] Confirmed the update introduced no new Rails-specific frontend assumption.
- [x] Inventoried 117 relevant Rails route/controller/migration/job/model/service
  changes and translated the changes that intersect the supported scope.
- [x] Passed the supported HTTP and side-effect contracts against Laravel while
  retaining the deterministic Rails reference for future differential work.
- [x] Passed 100% Laravel line coverage, channel/widget workflows, the unmodified
  frontend build and the final Chrome login render.
- [x] Updated compatibility and unsupported-scope documentation.

The pinned `v4.16.0` source is therefore the completed candidate from this
rehearsal. The separate `v4.16.0` → `develop@89b83c6` report remains an early
warning only and does not authorize importing an unstable branch.
