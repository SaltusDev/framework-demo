# Handoff: Adopt Post-3.0.0 Framework Features

Plan and status: [docs/ROADMAP.md](docs/ROADMAP.md) Phases 3 and 4. Live state:
[docs/CURRENT.md](docs/CURRENT.md).

## The previous handoff is done

The earlier version of this file specified adding an actionable `hint` key to every `WP_Error`
`$data` array across the REST controllers, and flagged `MetaController` as missing its PUT route.
**All of it is already present in `vendor-prefixed/saltus/framework/`:**

| Claimed missing | Actual location |
|-----------------|-----------------|
| `ExportController` hints | `src/Rest/ExportController.php:69`, `:102` |
| `SettingsController` hints | `src/Rest/SettingsController.php:88`, `:108`, `:154`, `:168`, `:191`, `:217` |
| `MetaController` hints | `src/Rest/MetaController.php:120`, `:177`, `:192` |
| `ModelsController` hints | `src/Rest/ModelsController.php:82`, `:109`, `:165` |
| `MetaFieldProvider` hint | `src/Features/Meta/MetaFieldProvider.php:69` |
| `MetaController` PUT route | `src/Rest/MetaController.php:74-99` + `update_item_permissions_check` at `:165` |

Verified live — `GET /saltus-framework/v1/health` without auth returns:

```json
{"code":"rest_forbidden","message":"You do not have permission to view framework health.",
 "data":{"status":403,"hint":"Assign the edit_posts capability to your user role, or use an administrator account."}}
```

One drift from the original spec: the shipped hints name the **real** config keys
(`show_in_rest` under a section, `mcp_tools` in options) rather than the `saltus_rest` key the old
handoff quoted. That key was never read by the framework at all.

## Fixed in this pass

- `framework-demo.php` now passes `__FILE__` as the framework `Core`'s second argument, so
  activation/deactivation hooks register and the MCP audit-cleanup cron is scheduled
- Dropped the inert `saltus_rest` option; the model now documents the real gate keys inline
- `src/Plugin/Ai/AssistantProvider.php` answers `saltus/framework/ai/assistant_actions` with
  offline, deterministic handlers, wired from `Core::init()`
- `Assets::correct_framework_asset_url()` repoints framework asset URLs to `vendor-prefixed/`
- MCP validator: 17 → 21 checks, plus corrected `saltus_rest` wording in its failure messages
- 47 tests (was 23), PHPCS clean, `composer validate --strict` clean

## Still open

**Blocked upstream — per-model editorial review.** `ProposalService::should_queue()` applies its
filter with only the tool name:

```php
apply_filters( 'saltus/framework/editorial_review/require_human_review', true, $tool );
```

No `post_type`, no `$args`. A plugin callback cannot resolve which model is being mutated, so
`ai_context.require_human_review` is unreachable and every mutating tool queues on every model —
including `movie`, which declares no `ai_context`. Left alone deliberately: the only plugin-side
lever is a blanket on/off, which is worse than the current default. Needs the framework to pass
`$args` or consult `AiContextProvider` itself.

**Three more upstream bugs found while adopting** — all in regenerated directories, so do not
hand-patch. Full detail in ROADMAP Phase 4:

1. Strauss's alias autoloader emits `namespace \;` for global-namespace Codestar classes, which is
   a parse error. **WP-CLI is unusable on this dev install as a result.** Production is unaffected
   (Strauss is `require-dev`).
2. `wp saltus` cannot register subcommands — WP-CLI throws `'wp saltus' can't have subcommands`.
   The whole command tree is unreachable.
3. The framework references block/assistant JS and CSS and default list/single templates that are
   not in the package. The demo overrides both templates, so its own rendering works.

## Verification

Green: `vendor/bin/phpunit` (47 tests, 127 assertions), `vendor/bin/phpcs`,
`composer validate --strict`, `php -l` on changed non-`src` files.

Not yet run: `bin/validate-mcp-tools.php` end to end (needs an application password), and a browser
check of the `[books]` shortcode and the two blocks.
