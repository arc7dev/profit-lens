# v0.2.0 — First functional release

**Branch/commits:** `develop` → `main`, `--no-ff` (merge commit `53ce89c`, folding in `d31a222`/PR #11's version bump, then `a05f0c8` for the follow-up header-comment fix `beb1d7f`). Tagged `v0.2.0` on `53ce89c`, GitHub Release published from that tag. Shipped 2026-08-19.
**Why this doc exists:** the previous learnings note (`PR6-dashboard-scale-and-formatting.md`) captured what a single PR left behind. This one captures what *cutting a release* left behind — a different kind of work (version numbering, branch-policy exceptions, duplicate-avoidance checks) that doesn't fit inside any one PR's commit history and would otherwise only live in a chat transcript nobody re-reads.

## 1. What shipped

`main` went from `0.1.0` scaffolding (admin page, REST contract, dashboard wired to mock data, no real calculation) straight to everything `develop` had accumulated across 8 merged PRs (#1–#6, #9, #10): the real profit calculation engine, the REST endpoint wired to it, the full React dashboard, and the per-order-line cost snapshot fix (#7). Version bumped to `0.2.0` in `profit-lens.php`, `readme.txt` (`Stable tag` + changelog entry), and `package.json`. PHPUnit was 57/57 immediately before the merge — checked, not assumed.

## 2. Two non-obvious findings that will come up again

### Semver line for a plugin that isn't on WordPress.org yet

The instinct when "the engine goes from stub to functional" is to reach for `1.0.0` — that's a real milestone. Decided against it: `1.0.0` is reserved specifically for the public WordPress.org submission, not for "the first time the internals are real." Staying in `0.x.y` (landed on `0.2.0`, not `0.1.1`, since this is a functional leap and not a patch) keeps semver's own convention intact — `0.x` means "still allowed to change shape before committing to a public API," which is exactly this plugin's situation: Free/Pro split and the `.org` listing itself aren't settled yet. Worth keeping this line for future internal releases too, so `1.0.0` doesn't get spent early on a milestone that feels big internally but isn't the actual public commitment.

### Check for an existing issue before filing one — the threshold write-up already had one

The release task's instructions called for filing a new issue about the `/summary` large-payload threshold. `gh issue list` before filing turned up **issue #8**, already open, already describing the identical symptom, measurement (~780KB/75KB gzip, 2,602-product catalog), and fix direction (split `products` into its own paginated request) — filed back in PR #6 and already cross-referenced from `CLAUDE.md`. Filing a second issue would have split one problem across two trackers for no reason. The check cost one `gh` call; skipping it would have cost a de-dup cleanup later. Generalizes: before filing an issue for anything already flagged as a known/documented limitation (`CLAUDE.md`'s "CONTEXTO CRÍTICO" and per-file docblocks are exactly where these tend to already be written down), search first.

## 3. Patterns that worked here — worth repeating deliberately

1. **Run the test suite immediately before a release merge, not "recently."** 57/57 was already known to pass from earlier work on `develop`, but re-running it right before merging to `main` is what actually backs the claim in the release notes — a stale "I remember it passing" isn't the same statement.
2. **Confirm branch topology before merging, don't assume it from a PR list.** `git log --oneline develop..main` (empty) and `main..develop` (8 commits) were checked explicitly before the merge, confirming `main` had zero commits of its own to lose — a fast-forward-safe merge, made explicit with `--no-ff` anyway so the release has a visible marker in the log.
3. **Every direct commit to `develop`/`main` in this release was a named, one-off exception, asked for and granted explicitly per instance** — the version-bump PR's merge into `develop`, the `develop → main` release merge itself, and later the single-line header-comment fix (asked for as "commit direct to develop, no PR" — then, separately, "merge that into main too"). None of these were assumed from a previous exception; each was its own ask. That's the difference between "the rule has an exception clause" and "the exception clause got used correctly" — worth keeping distinct in future releases rather than treating one granted exception as blanket permission for the rest of the release.

## 4. Open as of this writing (2026-08-19)

- **Issue #8** (large-catalog `/summary` payload) — still open, not part of this release's scope; referenced in the GitHub Release notes as a known limitation, not fixed.
- **Issue #7** (cost not versioned per order) — closed by PR #10, shipped in this release.
- `profit-lens.php`'s HPOS-compatibility comment (lines 27-28) had gone stale since the engine landed (`2d5cae6`, "Fase A") — said "documented stubs" months after that stopped being true. Caught after the release was already tagged, fixed as a direct one-line follow-up (`beb1d7f` → `main` via `a05f0c8`). Worth a habit going forward: when a header/module docblock makes a claim about implementation status ("stub," "not yet implemented," "TODO"), treat it as something to grep for and re-check at release-cut time, not just when its own code changes.
