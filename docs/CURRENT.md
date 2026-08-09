# Current — Saltus Framework Demo

**Last updated:** 2026-08-09  
**Active phase:** Phase 6 — v3.0.0 Release Review (Phase 7 scoped, not started)  
**Status:** 73/120 tasks (61%) — **Phase 6 closed at 48/48**, all six gates green on a bare checkout

The percentage moved from 70% without any work being undone. Phase 7 added 21 tasks for the Studio work
the epic's own count never covered, and Phases 3 and 4 had been counting completed *subsections* in their
headers while other phases counted checkboxes — Phase 3 read as 3/9 when its checkboxes were 19/33. All
phase headers now count checkboxes; see the convention note at the top of ROADMAP.md.

## What We're Working On

**Phase 6 is closed — all 31 findings fixed**, with 282 tests (764 assertions), 0 phpcs errors, 0 linter
errors, and all six gates at exit 0. Nothing in-repo blocks the v3.0.0 release.

**Finding 1a landed 2026-08-09, having been deferred as blocked.** It was recorded as waiting on the
Strauss build rework, but the fix did not depend on it: the generator needed to resolve its inputs from
whichever framework tree exists, which holds regardless of how the build produces them.
`SALTUS_FRAMEWORK_CANDIDATES` now tries `vendor-prefixed/` then `vendor/`, requiring all three inputs from
one tree so a prefixed and unprefixed tree can never be mixed.

Verified by running every gate against a copy of the tree with `vendor-prefixed/` removed — the state CI
and a fresh checkout are in, since nothing runs `prefix-namespaces` first. Before: `schema:check` exit 1,
phpunit 281/282. After: **all six at exit 0, 282/282 with zero skips** — identical to the full
environment, not merely green.

Two pre-existing skips went with it, both prefixed-only path resolutions a bare checkout never satisfies:
`ModelSchemaTest::test_type_enum_matches_the_model_factory` (a second hardcoded path the finding did not
mention) and `StudioGeneratedConfigTest::test_the_framework_parses_both_sections`, which had been losing
real framework-parsing coverage on every CI run.

Remaining, neither in-repo:

1. **Finding 6a (external)** — Review rejected a `$since` docblock on a constructor that does not
   appear in reference docs; removed locally but not committed because WordPress-Extra is deciding
   whether the sniff is a real requirement.

2. **P3.9 release decision** — `composer.json` pins `saltus/framework: dev-feature/mcp-v1`. A tagged
   constraint needs an upstream tag first. This is the last open item on the v3.0.0 release itself.

## What Changed This Phase

**Groups 1–6 fixed (all 31 findings, 48 tasks):**

- **Group 1:** CI now triggers on `release/**` branches, exits 0 on warnings-only trees, and — with 1a
  closed — passes every gate on a bare checkout rather than needing a gitignored `vendor-prefixed/`.
- **Group 2:** Renamer no longer corrupts `$0`, `${1}`, `\1` literals; all identity replacements now
  use a safe delimiter. Generated plugins parse and activate.
- **Group 3:** Studio's overwrite gate inverted at shipped defaults (would replace 12 KB models with
  62-byte stubs); now refuses multi-model files outright and respects `overwrite: false` correctly.
  Round-trip verified: 12 of 13 files refused, `src/models/` byte-identical afterwards.
- **Group 4:** Model linter gained tests for every rule (18 new tests), fixed two wrong hints, and now
  measures name length after `sanitize_key()` instead of before.
- **Group 5:** Five tests that passed throughout were actually broken (wrong assertion, wrong fixture,
  incomplete phpcs probe). All repaired and re-verified.
- **Group 6:** Studio printer now refuses non-finite floats, multi-model writes, and files that violate
  the scalar-first invariant. `bin/sync-studio-build.php` discovers the Omens UI checkout relatively
  instead of via one machine's absolute path. Settings uninstaller deletes per-post-type rows only.
  `readme.txt` now counts five active models, not two.

**Group 7 verification complete:** all six gates green in both environments — with `vendor-prefixed/`
present and on a bare checkout — renamer output parses and activates, Studio menu renders under the
correct parent, and the round-trip leaves `src/models/` unchanged.

## What's Next — Phase 7, Finish Saltus Studio (0/21)

Scoped from the epic's own success criteria and then checked against the shipped bundle and
`RestController`, so the gaps are measured rather than assumed:

1. **Studio cannot open an existing model.** Both REST routes are `CREATABLE`
   (`src/Plugin/Studio/RestController.php:64-85`), so the UI authors new models only. `ModelReader` ships
   and is injected, but is used solely to count models in the file just written (`:259`). Editing `book`
   still means hand-writing PHP. This is task 1 and gates the rest.
2. **`settings`, `blocks` and `frontend` are in the schema and absent from the UI** — zero occurrences of
   those labels in `assets/studio/saltus-studio.js`. Linter and printer already handle all three.
3. **Six composite field types** (`repeater`, `group`, `fieldset`, `tabbed`, `accordion`, `sortable`) need
   a recursive editor. The one item genuinely gated on Omens UI's Codestar parity.
4. **No CI gate on the TypeScript or the committed bundle** (84,131 bytes, built in another repo).

## Recent Milestones (Phases 1–5)

**Saltus Studio shipped** — eight epic phases, 53/56 tasks. Config layer: generated schema, model
linter in CI, reader/printer with a passing round-trip gate, guarded REST write surface. UI:
`apps/saltus-studio` in Omens UI, shipped as a built asset at `assets/studio/saltus-studio.js`.
Studio ships *disabled* in generated plugins with an "include model authoring tools" opt-in on the
renamer. Antd → `wp.components` migration cut the bundle from 1,383 kB to 73.9 kB (95%). That 53/56
counts the tasks the epic planned; the reach it never covered is Phase 7 above.

**Phase 3 complete** — framework adoption. Plugin now passes `__FILE__` to the framework `Core` so
activation/deactivation hooks register. Removed the inert `saltus_rest` option. Added
`src/Plugin/Ai/AssistantProvider.php` implementing the five AI assistant actions, so the in-editor AI
panel works. MCP validator extended from 17 to 22 checks (23 classes, one abstract base). Assets service rewrites framework asset URLs
to `vendor-prefixed/`.

**Demo plan complete** — 15 models across 14 files (the "golden file set"). Phases A–D implemented:
opt-in toggles, focused showcases (`recipe` for all 44 field types, `event` for admin columns,
`artwork` for serialization), opt-out models (`internal_note`, `venue_type`), AI governance
(`release`), settings pages (`staff`), JSON model (`snippet`), and frontend templates (`venue`).

**PHPCS now covers the whole plugin**, not just `src/`. 645 violations → 0 errors: 623 were four
legacy CLI scripts (excluded, with counts recorded as technical debt), and the rest were fixed.
