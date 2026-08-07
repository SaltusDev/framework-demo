# Roadmap — Saltus Framework Demo

## Phase 1 — Rebrand Feature & v2 Refactor [✓] (3/3) @priority high @owner team

- [x] 2.0.0 Rebrand-self UI with ZIP download and Copy & Activate delivery
- [x] 2.0.0 Input validation, sanitization, security hardening for renamer
- [x] 2.0.0 Plugin architecture refactor (Core, Assets, I18n, components)

---

## Phase 2 — v3.0.0 Framework Showcase [✓] (3/3) @priority high @owner team

- [x] 3.0.0 Book model as the complete framework showcase (frontend shortcode, blocks, quick edit, MCP tools, AI context)
- [x] 3.0.0 Frontend templates, shared shortcode/block render, and frontend stylesheet
- [x] 3.0.0 MCP tool validator tooling + docs sync (CONTEXT/BUILD/DESIGN/PROJECT/ROADMAP)

---

## Phase 3 — v3.1.0 Adopt Post-3.0.0 Framework Features [~] (5/9) @priority high @owner team

**Theme:** the prefixed framework gained four feature services and three MCP tools that the demo
never wires up. A demo plugin that ships an unreachable feature is worse than one that omits it,
so each task below either activates a surface or removes a claim.

**Ordering:** tasks 1 and 2 are prerequisites — 1 fixes lifecycle registration that the review
queue's table creation depends on, 2 removes a config key that would otherwise be copied into
every rebranded plugin. Tasks 3–6 are independent. Tasks 7–9 close out docs and verification.

### 1. Pass the plugin file to the framework `Core` @priority high

`framework-demo.php:104` calls `new $framework_core_class( __DIR__ )` with one argument. The
framework constructor is `__construct( string $project_path, ?string $plugin_file = null )` and
falls back to `$plugin_file = $project_path` — a directory, not a file. `Core::register()` then
guards on `is_file( $plugin_file )`, so **`register_activation_hook` and
`register_deactivation_hook` never fire**. Consequences today: `MCP::activate()` never schedules
the `saltus_framework_mcp_audit_cleanup` daily cron, and `MCP::deactivate()` never clears it.

- [x] Change the call to `new $framework_core_class( __DIR__, __FILE__ )`
- [ ] Confirm the audit cleanup event is scheduled on reactivation — needs WP-CLI, currently broken (Phase 4)
- [x] Framework README documents this two-argument form; the demo now models it

### 2. Remove the dead `saltus_rest` option @priority high

`ModelRestPolicy::is_enabled()` reads `options.show_in_rest` and a per-section `show_in_rest`
override; `McpPolicy::is_enabled()` reads `options.mcp_tools` and a per-section `show_in_mcp`.
Neither reads `saltus_rest` — the key is not referenced anywhere in the framework source.

- [x] Delete `'saltus_rest' => true` from `src/models/post-type-all.php`
- [x] Document the per-section `show_in_rest` / `show_in_mcp` overrides inline in the model
- [x] Update `docs/CONTEXT.md` conventions to name the real keys
- [x] Replace the stale `saltus_rest` wording in `bin/validate-mcp-tools.php` failure messages

### 3. Provide an AI assistant action handler @priority high

`AiAssistant` registers an editor sidebar for any post model with a configured `ai_context`, so
the demo's `book` model already gets it. But `AiAssistantProvider::dispatch()` applies
`saltus/framework/ai/assistant_actions` and returns `WP_Error( 'ai_assistant_no_provider', 501 )`
when no filter answers. **The demo ships a visible AI panel where every one of the five actions
fails.** The framework deliberately leaves the provider to the consuming plugin.

Five actions to answer: `improve_title`, `summarize`, `generate_excerpt`, `suggest_terms`,
`validate_content`. Return shape is `[ 'target' => …, 'value' => … ]` with optional
`suggestions` / `violations` string lists; `normalize_result()` clamps `target` to
`post_title|post_excerpt|post_content|terms`.

- [x] Add `src/Plugin/Ai/AssistantProvider.php` hooking the filter, wired from `Core::init()`
- [x] Implement deterministic, offline handlers — no external API calls. `summarize` and
      `generate_excerpt` truncate `post_content` to a sentence boundary; `suggest_terms` proposes
      only existing registered terms matched against the content; `validate_content` returns
      `violations` derived from the model's `ai_context.field_rules`
- [x] Keep it clearly marked as a demo stub so rebranded plugins know to replace it
- [x] Unit-test the return shape against `normalize_result()`'s contract (18 tests)

Deviation from plan: `improve_title` collapses whitespace and trims trailing punctuation but does
**not** apply title case. The model's own `ai_context` says to preserve author intent, and
re-casing a book title contradicts that.

Caveat: the framework enqueues `Feature/AiAssistant/editor.js`, which **is not present** in the
package — so the panel still needs that asset before it renders. See Phase 4.

### 4. Wire editorial review to the model's own governance config [BLOCKED UPSTREAM] @priority medium

The `book` model sets `ai_context.require_human_review => true`, but nothing reads it for
queueing. `ProposalService::should_queue()` returns `true` for all eight mutating tools by
default, so **every MCP mutation on every model is queued**, including `movie`, which configures
no `ai_context` at all.

This cannot be fixed from the plugin. The filter is applied as:

```php
apply_filters( 'saltus/framework/editorial_review/require_human_review', true, $tool );
```

It passes only the tool name — no `post_type`, no `$args`, no post ID. A callback has nothing to
resolve a model from, so per-model behaviour is unreachable. `AiContextProvider::get()` already
exposes `require_human_review` per model; the framework just never consults it here.

Deliberately not worked around: the only plugin-side option is a blanket `true`/`false`, which
would either keep over-queueing or disable review entirely — both worse than the current default.

- [ ] **Upstream:** pass `$args` (or the resolved post type) as a third filter argument, or have
      `should_queue()` consult `AiContextProvider` directly
- [ ] Then: add the demo callback resolving `require_human_review` per post type
- [ ] Then: verify `movie` mutations apply directly and `book` mutations queue

### 5. Surface the review queue and enable WP-CLI @priority medium

`EditorialReview` already self-registers its admin page and `/proposals` routes; `WpCli`
self-registers `wp saltus` when `WP_CLI` is defined. Neither is mentioned in any demo doc, so
neither is discoverable.

- [ ] Document the `wp saltus` command tree, including the `block` and `context` subcommands
      that the framework's own docs omit
- [ ] Document the review queue screen and the approve/reject flow
- [ ] Sanity-check `wp saltus context get book` and `wp saltus block list` against the demo models

### 6. Exercise blocks and context discovery @priority low

`Blocks` contributes `list_block_models` + `GET /blocks`; `AiContext` contributes `get_context` +
`GET /context/{post_type}`. The `book` model enables both. `ModelRestPolicy::CAPABILITY_BLOCKS`
resolves from the `blocks` config section, which the model sets — so these routes are live and
untested.

- [ ] Confirm `/blocks` and `/context/book` return the expected payloads
- [ ] Confirm `saltus/book-list` and `saltus/book-single` render via the configured templates.
      Note `block_editor => false` on the model disables the editor for `book` itself; the blocks
      are for use on pages and posts
- [ ] Check the framework's default template fallback paths resolve, since the demo overrides both

### 7. Extend the MCP tool validator to the full surface @priority medium

`bin/validate-mcp-tools.php` defines 17 validators. The framework now exposes 20 tools — missing
are `update_meta_fields`, `list_block_models`, and `get_context`. The validator already prints
`data.hint` under `--verbose`, so hint coverage is checkable once the routes are in.

- [x] Add validators for the three missing tools
- [x] Add coverage for `PUT /meta/{post_type}/{post_id}` (probed with OPTIONS so the check never
      mutates content), `GET /blocks`, `GET /context/{post_type}`, and `GET /proposals`
- [ ] Run the validator end to end against a live site with app-password auth

### 8. Rewrite the stale handoff @priority high

`HANDOFF.md` describes MCP error hints as pending upstream work. Every hint it specifies is
already in `vendor-prefixed/`, as is the `MetaController` PUT route it flags as missing.

- [x] Replace `HANDOFF.md` with the Phase 3 state
- [x] Remove the "blocked on upstream hints" framing wherever it survives

### 9. Verification @priority high

- [x] `vendor/bin/phpunit` — 47 tests, 127 assertions, green (was 23 tests)
- [x] `vendor/bin/phpcs` — clean across 12 files
- [x] `composer validate --strict` — clean
- [x] `php -l` on every changed file outside `src/`
- [x] Confirmed live that `GET /saltus-framework/v1/health` returns `data.hint` on a 403, proving
      the old handoff's hint work is genuinely shipped
- [ ] `php bin/validate-mcp-tools.php https://saltus.test --user=… --app-pass=… --verbose` — needs
      an application password
- [ ] Browser check of the `[books]` shortcode and the two blocks
- [ ] Decide the `saltus/framework` constraint before release: `composer.json` pins
      `dev-feature/mcp-v1`, and `docs/BUILD.md` already flags that a tagged constraint is needed.
      Framework `Core::VERSION` is `2.0.0` with these features under `[Unreleased]`, so a tag has
      to happen upstream first

---

## Phase 4 — Upstream Bugs Found While Adopting [ ] (0/6) @priority high @owner team

None of these are fixable in this repo: they live in `vendor/` and `vendor-prefixed/`, which
`composer prefix-namespaces` regenerates. Hand-patching them would be silently undone.

### 1. Strauss alias autoloader emits invalid PHP @priority high

> **Escalation (2026-08-07):** this is not warning-only. It produces a **hard fatal** on any admin
> screen rendering a Codestar `typography` field:
> `Call to undefined function saltus_..._csf_get_google_fonts()`.
>
> Mechanism: Codestar resolves field classes by string name (`'CSF_Field_' . $type`,
> `setup.class.php:619`), so it requests the un-prefixed `CSF_Field_typography`. The alias
> autoloader fails to define it (invalid `namespace \;`), **but** the global *function* shims at the
> top of `autoload_aliases.php` are defined eagerly. So `csf_get_google_fonts()` exists and forwards
> to a prefixed target that only `fields/typography/google-fonts.php` defines — a file Codestar
> includes lazily from the real class's `enqueue()`, which never ran. Verified state:
>
> ```
> csf_get_google_fonts() defined ......... true
> saltus_..._csf_get_google_fonts() ..... false   <-- fatal on call
> ```
>
> Worked around in `src/Plugin/CodestarCompat.php`, which eagerly loads the deferred function files
> so the shims always have a target. Verified: `csf_get_google_fonts()` now returns 1599 fonts.
> `icon` shares the aliasing but survives page loads because its `enqueue()` only registers footer
> actions and the AJAX handler includes its icon file immediately before use.
>
> Remove the shim once this is fixed upstream.


`vendor/composer/autoload_aliases.php` records `'namespace' => '\\'` for global-namespace classes
(all of Codestar's `CSF_*`). Its `classTemplate()` checks `isset($class['namespace'])` — true for
`'\\'` — and emits `namespace \;`, a parse error. The generated file is written, included, then
unlinked on every autoload of such a class, so it regenerates continuously.

**Effect:** WP-CLI is completely unusable on a dev install — `wp` fatals before WordPress loads.
Web requests survive only because `www-data` cannot write `vendor/composer/`, so the write fails
and the include is skipped; with Xdebug on, that surfaces as a wall of PHP warnings.

Production is unaffected: Strauss is `require-dev`, and its own bootstrap notes the aliases file
must not load in production, so `composer install --no-dev` omits it.

- [ ] Report upstream to `brianhenryie/strauss` (the `'\\'` namespace should be treated as global)
- [ ] Interim: `composer install --no-dev` when CLI access is needed, or delete
      `vendor/composer/autoload_aliases.php` locally

### 2. `wp saltus` cannot register its subcommands @priority high

With the aliases file removed, WP-CLI gets further and then throws
`Exception: 'wp saltus' can't have subcommands`. `WpCli::register_commands()` registers `saltus` as
a closure-style callable via `SaltusCommand::__invoke()`, then registers `saltus model`,
`saltus post` and seven more beneath it. WP-CLI rejects nesting under a non-class command.

- [ ] Upstream: register the parent as a class with a default subcommand, or drop the bare
      `wp saltus` invocation
- [ ] Until then the entire `wp saltus` tree is unreachable, so ROADMAP Phase 3 task 5 stays open

### 3. Framework asset base points at a deleted directory @priority medium

`Core.php:117` hardcodes `plugins_url( 'vendor/saltus/framework/assets/', … )`. With
`delete_vendor_packages: true`, nothing remains under `vendor/saltus/`, so every framework-enqueued
script and style resolves to a WordPress 404 served as `text/html` instead of the asset. There is
no filter on `root_url` to override it.

Worked around in this plugin via `Assets::correct_framework_asset_url()` on `script_loader_src` /
`style_loader_src`. That is a shim, not a fix.

- [ ] Upstream: derive the asset base from the package's real location, or add a filter
- [ ] Then remove the shim and its tests

### 4. Blocks, assistant, and default templates ship without their assets @priority medium

Referenced by framework code but absent from the package:

| Referenced at | Missing path |
|---------------|--------------|
| `SaltusBlocks.php:240` | `assets/Feature/Blocks/editor.js` |
| `SaltusBlocks.php:245` | `assets/Feature/Blocks/style.css` |
| `AiAssistant.php:50` | `assets/Feature/AiAssistant/editor.js` |
| `AiAssistant.php:57` | `assets/Feature/AiAssistant/editor.css` |
| `BlockRenderer.php:206` | `templates/blocks/{list,single}.php` |
| `FrontendRenderer.php:175` | `templates/{list,single}.php` |

The shipped `assets/Feature/` holds only `DragAndDrop` and `RememberTabs`. The demo overrides both
frontend templates, so its own rendering works, but a consuming plugin that relies on framework
defaults gets an empty string from both renderers.

- [ ] Upstream: ship the block/assistant JS and CSS and the default templates
- [ ] Then verify the demo's editor panel and block previews actually render
### 5. The `active` model key is inverted @priority high

`BaseModel::is_disabled()` reads:

```php
if ( empty( $this->data['active'] ) || $this->data['active'] === true ) {
    return false; // not disabled
}
return true;
```

`empty( false )` is `true`, so `'active' => false` takes the early return and the model **loads
anyway**. Only a truthy-but-not-`true` value disables one. Verified:

| Config | Result |
|--------|--------|
| `active => true` | enabled (correct) |
| `active => false` | **enabled** (bug — should disable) |
| `active => 1` | **disabled** (bug — should enable) |
| key absent | enabled (correct) |

So the documented way to disable a model does nothing, and the demo cannot use it. Worked around
with an explicit gate in each model file (`src/models/_demo-toggle.php`), relying on
`ModelFactory::create()` soft-failing a config with no `type` key.

- [ ] Upstream: `return isset( $data['active'] ) && ! filter_var( $data['active'], FILTER_VALIDATE_BOOLEAN );`
- [ ] Then replace the demo's gate helper with the `active` key

### 6. `Modeler::is_multiple()` misparses mixed model files @priority medium

```php
protected function is_multiple( AbstractConfig $config ): bool {
    return ( is_array( current( $config->all() ) ) );
}
```

It decides how to parse a whole file from its **first element only**. A file whose first key is
`'type' => 'cpt'` is treated as a single model, so any sibling model appended alongside it is
silently dropped — no error, no warning. Cost me a debugging cycle when `event_category`, declared
after the `event` CPT in one file, never registered.

Either every top-level element is a model array (as in `taxonomy-multiple.php`) or the file declares
exactly one model. Mixing is impossible and unreported.

- [ ] Upstream: detect per-element, or warn when a file mixes shapes
- [ ] Demo works around it by splitting `taxonomy-event-category.php` into its own file


---

**Overall: 11/19 tasks — 58%**
