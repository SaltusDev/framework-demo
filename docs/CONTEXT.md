# Context — Saltus Framework Demo

## Architecture

The plugin follows a modular architecture layered on top of the Saltus Framework:

```
framework-demo.php          -- Bootstrap: constants, autoloading, framework init
├── src/Core.php            -- Orchestrator: wires i18n, assets, admin pages
├── src/Plugin/
│   ├── I18n.php            -- Textdomain loader (load_plugin_textdomain)
│   ├── Assets.php          -- Admin styles enqueue (admin-style.css)
│   ├── Field.php           -- Meta field helper wrappers (get, get_img, etc.)
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

The Saltus Framework (`saltus/framework`) is loaded via Composer and prefixed with Strauss into `vendor-prefixed/`. The framework reads model files from `src/models/` and handles: CPT/taxonomy registration, meta boxes (CMB2-style), settings pages, admin columns/filters, drag-and-drop reordering, duplication, and single-item export.

**Data flow:**
1. `framework-demo.php` creates a Framework `Core` instance with the plugin root path
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

## Conventions

- PSR-4 autoloading: `Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\` → `src/`
- Namespace prefix for Strauss: `Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\` (duplicated for uniqueness)
- Classmap prefix: `Saltus_WP_Plugin_Saltus_PluginFrameworkDemo_`
- Model files return plain PHP arrays — no classes
- All user-facing strings use `__()` / `_e()` with `framework-demo` text domain
- Composer scripts over Grunt tasks for new build pipeline items
- Release ZIP built via `bin/package.php` (Composer `package` script)

## Notes

- The `vendor-prefixed/` directory must be rebuilt with `composer prefix-namespaces` after framework updates
- The plugin auto-deactivates after a successful Copy & Activate to prevent slug collision
- Model config supports `block_editor: false` — this plugin opts out of the block editor for demo clarity
- Test coverage currently 2 unit tests for the renamer components; PHPCS is configured to WPCS 3.3 standards
