# Context — Saltus Framework Demo

## Architecture

The plugin follows a modular architecture layered on top of the Saltus Framework:

```
framework-demo.php          -- Bootstrap: constants, autoloading, framework init
├── src/Core.php            -- Orchestrator: wires i18n, assets, admin pages
├── src/Plugin/
│   ├── I18n.php            -- Textdomain loader (load_plugin_textdomain)
│   ├── Assets.php          -- Admin + frontend styles enqueue; repoints framework asset URLs
│   ├── Field.php           -- Generic field helper wrappers (get, get_img, etc.)
│   ├── Ai/
│   │   └── AssistantProvider.php -- Offline handler for the framework's AI assistant actions
├── templates/
│   ├── book-list.php       -- Frontend/block list render for the book model
│   └── book-single.php     -- Frontend/block single render for the book model
├── bin/
│   ├── bump.php            -- Semver version bump CLI
│   └── validate-mcp-tools.php -- Validates saltus-framework MCP/REST tools against a live site
│   └── Admin/
│       └── RenamerPage.php -- Rebrand This Demo admin UI + generator handler
│   └── Renamer/
│       ├── PluginIdentity.php     -- Value object + validation for plugin identity
│       ├── PluginRenamer.php      -- ZIP builder: rewrites namespaces/prefixes/headers
│       └── ValidationException.php -- Validation error
└── src/models/
    ├── post-type-basic.php     -- Minimal CPT model (movie)
    ├── post-type-all.php       -- Full CPT model (book): meta, settings, columns, filters
    └── taxonomy-multiple.php   -- Multiple taxonomy registrations (genre, writer, country)
```

The Saltus Framework (`saltus/framework`) is loaded via Composer and prefixed with Strauss into `vendor-prefixed/`. The framework reads model files from `src/models/` and handles: CPT/taxonomy registration, meta boxes (Codestar-based), settings pages, admin columns/filters, drag-and-drop reordering, duplication, single-item export, quick edit, blocks, a frontend shortcode, AI-context governance, and WordPress-native MCP tools/abilities.

The framework registers 16 feature services in `Core::get_service_classes()`. Four are **not yet used by this demo** — see [ROADMAP.md](ROADMAP.md) Phase 3:

| Service | Surface | Demo status |
|---------|---------|-------------|
| `AiAssistant` | Editor sidebar + `POST /ai-assistant/{post_type}/{post_id}/{action}` | Answered by `src/Plugin/Ai/AssistantProvider.php`; still needs the framework's missing `editor.js` to render |
| `EditorialReview` | Tools → AI Review Queue + `/proposals` routes | Live; queues every mutation on every model because the framework filter carries no model context (ROADMAP Phase 3 task 4) |
| `WpCli` | `wp saltus` command tree (9 command groups) | Unreachable — WP-CLI registration throws upstream (ROADMAP Phase 4) |
| `Blocks` / `AiContext` discovery | `GET /blocks`, `GET /context/{post_type}` | Routes live for `book`; covered by the MCP validator, not yet browser-verified |

**Data flow:**
1. `framework-demo.php` creates a Framework `Core` instance with the plugin root path (currently one argument — see ROADMAP Phase 3 task 1; the framework also accepts the plugin file as a second argument, required for activation/deactivation hooks)
2. The Framework scans `src/models/` for PHP files returning arrays
3. Each model array declares `type` (cpt/category/tag), `name`, `features`, `meta`, `settings`, etc.
4. The Framework handles all WordPress hook registrations internally
5. Plugin-specific `Core::init()` runs on `plugins_loaded` to set up i18n, assets, and the renamer admin page

## Decisions

| Decision | Rationale | Date |
|----------|-----------|------|
| Use Strauss for namespace prefixing | Avoids framework conflicts when multiple Saltus-based plugins are active | 2024-11-01 |
| Rebrand-self as the flagship feature | Lets developers bootstrap production plugins from a working demo without manual find-replace | 2025-06-15 |
| Plugin-specific code kept minimal | Maximal delegation to the Saltus Framework reduces maintenance surface | 2024-11-01 |
| Copy & Activate delivery path | Provides one-click install for local/dev environments; ZIP for production/marketplace | 2025-06-30 |
| PHP 8.3 minimum | Enables typed properties, readonly classes, and modern syntax; aligns with framework requirement | 2025-06-01 |
| Book model = full framework showcase | Enables MCP discovery, blocks, `[books]` shortcode, AI governance and quick edit in one model to demonstrate the framework surface | 2026-08-06 |
| `saltus_framework` depends on `dev-feature/mcp-v1` | Exposes the in-progress MCP/abilities/editorial-review features; pinned to the VCS repo until released | 2026-08-06 |
| AI assistant actions stay in the consuming plugin | The framework only defines the action contract and dispatches a filter, so brand/content logic and any model API choice belong to the plugin. The demo ships an offline stub | 2026-08-07 |

## Conventions

- PSR-4 autoloading: `Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\` → `src/`
- Namespace prefix for Strauss: `Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\` (duplicated for uniqueness)
- Classmap prefix: `Saltus_WP_Plugin_Saltus_PluginFrameworkDemo_`
- Model files return plain PHP arrays — no classes
- REST exposure is controlled by `options.show_in_rest` plus a per-section `show_in_rest`; MCP exposure by `options.mcp_tools` plus a per-section `show_in_mcp`. The legacy `saltus_rest` key is no longer read by the framework
- All user-facing strings use `__()` / `_e()` with `framework-demo` text domain
- Composer scripts over Grunt tasks for new build pipeline items
- Release ZIP built via `bin/package.php` (Composer `package` script)

## Notes

- The `vendor-prefixed/` directory must be rebuilt with `composer prefix-namespaces` after framework updates
- The plugin auto-deactivates after a successful Copy & Activate to prevent slug collision
- Model config supports `block_editor: false` — the `book` model opts out, so its `saltus/book-*` blocks are for use on pages and posts, not on books themselves
- `templates/` are shared by the framework's frontend shortcode and blocks renderers (`$posts`/`$meta_by_post` for list, `$post`/`$meta` for single). Both renderers resolve templates config → theme (`saltus/{post_type}/…`) → framework default, and confine configured paths to the plugin root via `realpath`
- Test coverage currently 2 unit tests for the renamer components; PHPCS is configured to WPCS 3.3 standards
- Framework `Core::VERSION` is `2.0.0` and its CHANGELOG keeps the post-2.0.0 features under `[Unreleased]`, while this demo ships as 3.0.0. The versions are intentionally decoupled
