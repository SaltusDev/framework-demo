# Phase A Handoff — Verification Gap and Next Steps

**Status:** Code complete, test suite passing, toggles verified live, but **edit screens not browser-tested**.

## What was verified

✓ **Registration:** All 9 opt-in models + 2 taxonomies flip correctly when toggled  
✓ **Field resolution:** All 44 `recipe` field types resolve to real prefixed Codestar classes  
✓ **Field assets:** 43/44 field type directories present (`tap_list` missing but unused)  
✓ **Columns & filters:** `event` admin columns (10) and sortables (6) register correctly  
✓ **Capability gates:** `event_internal_col` absent from visible columns, present in sortables  
✓ **Options:** `venue_type` REST disabled, `internal_note` fully locked down  
✓ **Test suite:** 50 tests, PHPCS clean, composer validate strict clean  
✓ **Live site:** Homepage 200, no PHP errors/warnings in response

## What was NOT verified

✗ **Browser pass on opt-in model edit screens** — nobody has loaded `wp-admin/post-new.php?post_type=recipe` and saved a post with all 44 field types populated  
✗ **Meta serialization roundtrip** — the framework serializes complex fields (`dimensions`, `spacing`, `link_color`) to post_meta; a mismatch would only surface after save  
✗ **Frontend rendering** — no template exists for the new models; they'd 404 until templates are added

## Critical risks remaining

1. **Codestar field mismatch under Strauss** — `CodestarCompat.php` fixes the `typography` fatal by eagerly loading deferred function files, but each field type has its own sanitize/encode path. A field that *enqueues* fine might still serialize incorrectly or fatal on save.

2. **The `active` key workaround ships in production code** — `src/models/_demo-toggle.php` and every opt-in model's gate exist only because `BaseModel::is_disabled()` is inverted upstream (ROADMAP P4.5). Once fixed, this needs unwinding.

3. **Mixed model files silently drop siblings** — `event_category` now lives in its own file because `Modeler::is_multiple()` inspects only the first element (ROADMAP P4.6). Easy to re-break if a future contributor doesn't know this.

## What to do next

### Minimum before Phase A sign-off

1. **Browser smoke test the opt-in models:**
   - Enable all toggles from **Books → Settings → Demo Models**
   - Create one post in each: `recipe`, `event`, `venue`, `staff`, `release`, `artwork`, `internal_note`
   - For `recipe`: populate at least the complex fields (`typography`, `dimensions`, `spacing`, `link_color`, `border`, `background`) and **save**
   - Reload the edit screen — if the values roundtrip intact, meta serialization works
   - Disable toggles, confirm models disappear from admin menu

2. **Check for Xdebug warnings on the edit screens** — Strauss aliasing produces PHP warnings even when functional; a clean edit-screen pass is the real test

### Before Phase B (frontend + blocks)

3. **Add basic templates** for the new models — they're registered but 404 on the frontend
4. **Test the shortcodes** — `[books]` / `[venues]` confirmed; new models need equivalents if they'll be public
5. **Blocks verification** — ROADMAP P3.6 still pending; blocks ship without their JS

### Upstream tracking

6. **Monitor Strauss aliasing fix** — once ROADMAP P4.1 lands, remove `CodestarCompat.php` and re-enable the typography field without the shim
7. **Monitor `active` key fix** — once P4.5 lands, replace `_demo-toggle.php` with the real key
8. **Monitor `is_multiple` fix** — once P4.6 lands, can re-nest `event_category` if desired (though separate files are clearer anyway)

## Files added/changed this session

**New:**
- `src/Plugin/CodestarCompat.php` — workaround for Strauss aliasing fatal
- `src/Plugin/OptionalModels.php` — injects `snippet.json` via `extra_models`
- `src/models/_demo-toggle.php` — gate helper (workaround for `active` key bug)
- `src/models/post-type-recipe.php` — kitchen-sink field showcase (44 types)
- `src/models/post-type-event.php` — all 5 admin column sources and all 5 filter kinds
- `src/models/post-type-venue.php` — frontend/blocks with framework default templates (no overrides)
- `src/models/post-type-staff.php` — multi-section settings page, custom menu parent, tabbed fields
- `src/models/post-type-release.php` — strict AI governance, per-section `show_in_mcp` opt-out
- `src/models/post-type-artwork.php` — serialized vs unserialized meta, REST shape comparison
- `src/models/post-type-internal-note.php` — REST/MCP opt-out reference (private CPT)
- `src/models/taxonomy-event-category.php` — split from `event` (P4.6 workaround)
- `src/models/taxonomy-venue-type.php` — REST-disabled taxonomy
- `src/models-optional/snippet.json` — JSON model format demo
- `tests/Plugin/CodestarCompatTest.php` — unit tests for the compat shim
- `tests/Plugin/AssetsTest.php` — unit tests for the framework asset-URL shim
- `src/Plugin/Ai/AssistantProvider.php` — offline handler for the framework's AI assistant actions

**Modified:**
- `src/Core.php` — registered `CodestarCompat` and `OptionalModels`
- `src/models/post-type-all.php` — added the Demo Models settings section (9 toggles) to the `book` model's settings page, plus a header comment pointing at the focused models
- `tests/bootstrap.php` — added `add_action` stub

**Docs:**
- Added ROADMAP P4.5 (active key inverted) and P4.6 (is_multiple misparsing)
- Updated CURRENT.md, MODELS.md, FEATURE-MATRIX.md for gate mechanism
- This handoff doc

---

**Git state:** 34 files changed/added, nothing committed. Suite passes, PHPCS clean, site loads. The code is ready; the browser verification is not done.
