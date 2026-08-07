# Current — Saltus Framework Demo

## Working On
- Demo plan complete @since 2026-08-07

## Done this pass (Phase 3 adoption + demo plan implementation)

### Phase 3 — v3.0.0 framework adoption
- Plugin now passes `__FILE__` to the framework `Core`, so activation/deactivation hooks register and the MCP audit-cleanup cron gets scheduled
- Removed the inert `saltus_rest` option; documented the real REST/MCP gate keys inline in the model
- Added `src/Plugin/Ai/AssistantProvider.php` — the demo now answers `saltus/framework/ai/assistant_actions`, so the in-editor AI panel works instead of returning 501
- Added `Assets::correct_framework_asset_url()` to repoint framework asset URLs at `vendor-prefixed/`
- MCP validator extended from 17 to 21 checks (`update_meta_fields`, `list_block_models`, `get_context`, `/proposals`)
- 47 tests passing (was 23); PHPCS clean; `composer validate --strict` clean

### Demo plan implementation (Phases A–D)
- **Phase A complete:** Added header comments to `movie`, `book`, and taxonomy trio pointing at focused models; added "Demo Models" settings section with 9 toggles; each opt-in model self-gates via `saltus_demo_model_enabled()` (`src/models/_demo-toggle.php`), with the JSON model injected through `extra_models` (`src/Plugin/OptionalModels.php`)
- **Phase B complete:** Implemented `recipe` (all 44 field types), `event` (all column/filter kinds), `artwork` (serialized vs unserialized meta comparison)
- **Phase C complete:** Implemented `internal_note` (REST/MCP opt-out), `release` (AI governance), `venue_type` (taxonomy opt-out)
- **Phase D complete:** Implemented `staff` (settings pages), `snippet` (JSON model), `venue` (frontend with framework defaults, blocked but shipped as regression test)
- **Phase E complete:** Updated README with model table; wrote comprehensive docs/MODELS.md walkthrough; created docs/DEMO-PLAN.md and docs/FEATURE-MATRIX.md

### Model count: 12
- Always active: `movie`, `book`, `genre`, `writer`, `country`
- Opt-in (toggled via settings): `recipe`, `event` (+ `event_category`), `venue`, `staff`, `release`, `artwork`, `snippet`, `internal_note`, `venue_type`
- Toggles verified working in both directions against a live site; all 44 `recipe` field types confirmed to resolve to real Codestar classes

### Coverage
- Codestar field types: 10 / 44 → **44 / 44**
- Admin column sources: 4 / 5 → **5 / 5**
- Admin filter kinds: 1 / 5 → **5 / 5**
- Model file formats: 1 / 2 usable → **2 / 2 usable**
- Feature services: 7 / 10 → **10 / 10**

## Next
- Upstream: give `saltus/framework/editorial_review/require_human_review` the model context it needs (see Blocked By)
- Upstream: two generated-code bugs found this pass, both detailed in [ROADMAP.md](ROADMAP.md) Phase 4
- Browser verification of edit screens and blocks / `[books]` / `[venues]` rendering; the framework ships no default templates or block/assistant JS, so the demo's own templates are currently the only render path
- MCP validator end-to-end with app password

## Blocked By
- **Per-model editorial review** — `ProposalService::should_queue()` applies its filter as `apply_filters( 'saltus/framework/editorial_review/require_human_review', true, $tool )`. It passes only the tool name, no `post_type` and no args, so a plugin cannot resolve `require_human_review` per model. Every mutating MCP tool therefore queues for review on every model, including `movie`, which declares no `ai_context`. Fixing this needs the filter to pass `$args` upstream; a global on/off toggle in the demo would be wrong either way. @reason framework filter signature

## Notes
- **The old MCP error-hint handoff is complete.** Every `hint` key is present in `vendor-prefixed/`, as is the `MetaController` PUT route the handoff listed as missing. Confirmed live: `GET /saltus-framework/v1/health` returns `data.hint` on a 403.
- `saltus_rest` was never read by the framework. Real gates are `options.show_in_rest` + per-section `show_in_rest` (`CapabilityPolicy::GATE_REST`), and `options.mcp_tools` + per-section `show_in_mcp` (`GATE_MCP`). REST defaults to on unless explicitly `false`; MCP defaults to off unless explicitly `true`.
- The model's `features` key is `dragAndDrop` while the policy looks up `drag_and_drop`. This is harmless — `resolve_feature_value()` returns `null` for a missing section and falls back to the global gate, and `ModelFactory::process_features()` lowercases to `draganddrop` to match the service key. Left as-is rather than "fixed" into a behaviour change.
- The framework has an unused `CapabilityPolicy` that unifies `ModelRestPolicy` and `McpPolicy`; only the MCP middleware references it. The REST controllers still use the older split classes.
- Framework `Core::VERSION` is `2.0.0` with these features under `[Unreleased]`, while the demo ships 3.0.0. Intentionally decoupled; a tagged framework release is upstream work.
- Local dev note: WP-CLI is unusable on this install (`wp` fatals before loading). Not caused by demo code — see ROADMAP Phase 4. Web requests are unaffected in behaviour, though Xdebug surfaces PHP warnings from the same root cause.
- **Two more framework bugs found while verifying the toggles** (ROADMAP P4.5, P4.6): the `active` model key is inverted (`active => false` does *not* disable, `active => 1` does), and `Modeler::is_multiple()` decides a whole file's shape from its first element, so a taxonomy declared after a CPT in one file is silently dropped. Both worked around; both cost a debugging cycle.
- **`src/Plugin/CodestarCompat.php` is a workaround, not a feature.** The Strauss aliasing bug (ROADMAP P4.1) turned out to cause a hard fatal, not just warnings: the `typography` field's `csf_get_google_fonts()` shim forwards to a prefixed function that never gets loaded. The compat class eagerly loads the deferred Codestar function files. It guards on `defined( 'ABSPATH' )` because every Codestar file opens with `die;` otherwise — without that guard it silently killed the PHPUnit process mid-run. Delete this class once Strauss is fixed upstream.
- The legacy Grunt build in `build/` is being phased out in favour of Composer scripts + PostCSS.
- PHP 8.3+ is required — no legacy polyfills needed.
- **Demo plan rationale:** Two CPTs carried the whole demo — `movie` (4 lines, teaches nothing) and `book` (337 lines, teaches everything at once). The fix is one model per coherent story, each readable in a sitting. Coverage measured against the enumerated framework surface in FEATURE-MATRIX.md.
