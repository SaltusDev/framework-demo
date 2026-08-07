# Saltus Framework Demo

This is a modern demo plugin for building WordPress plugins with the Saltus Framework.

It shows how to register custom post types, taxonomies, admin columns, filters, meta boxes, settings pages, assets, translations, frontend shortcodes, dynamic blocks, AI governance, and WordPress-native MCP/Abilities using Saltus model configuration files.

## Requirements

- PHP 8.3 or newer
- WordPress with Composer-installed plugin dependencies
- Composer 2

## Setup

From the plugin directory:

```bash
composer install
```

Then activate **Saltus Framework Demo** in WordPress.

## Models

The demo includes 12 models (10 CPTs + 4 taxonomies) demonstrating the framework surface. Two are always active; the rest are opt-in via **Books → Settings → Demo Models**.

### Always active

- **`movie`** — the minimum viable model. Type, name, supports, three labels. Its value is being boring: it proves the framework's defaults work.
- **`book`** — the kitchen sink. Meta, settings, admin cols/filters, duplicate, export, drag-and-drop, remember tabs, quick edit, shortcode + blocks with custom templates, AI context with editorial review. The reference for "all of the above, working together."

### Opt-in (enable via settings)

| Model | Demonstrates |
|-------|--------------|
| **`recipe`** | All 44 Codestar field types across tabbed sections. The meta field gallery. |
| **`event`** | All admin column sources, all filter kinds, sortable/capability-gated columns, admin_filters hooks. |
| **`venue`** | Shortcode + blocks with framework default templates (no overrides). Frontend rendering baseline. |
| **`staff`** | Multi-section settings page with custom menu parent, capability, and tabbed fields. |
| **`release`** | AI governance: strict ai_context, editorial review, per-section show_in_mcp opt-outs. |
| **`artwork`** | Serialized vs unserialized meta, register_rest_api, nested repeater paths. REST shape comparison. |
| **`snippet`** | Model declared in JSON (not PHP). Proves the loader accepts multiple formats. |
| **`internal_note`** | Opt-out reference: show_in_rest: false, mcp_tools absent, private CPT. The negative space. |
| **`venue_type`** | Taxonomy with show_in_rest: false and single association, contrasting the genre/writer/country trio. |

### Taxonomies

- **`genre`** (category-style, hierarchical) → `book`
- **`writer`** (tag-style, flat) → `book`
- **`country`** (tag-style) → `book` + core `post` (multi-association demo)
- **`event_category`** → `event` (ships with the event model)
- **`venue_type`** → `venue` (opt-in, REST-disabled)

See [docs/DEMO-PLAN.md](docs/DEMO-PLAN.md) for the design rationale and [docs/FEATURE-MATRIX.md](docs/FEATURE-MATRIX.md) for the enumerated framework surface.

## MCP & AI Context

For WordPress-native MCP clients, use the `saltus/*` abilities exposed by the active site. The framework handles discovery, permissions, validation, rate limiting, caching, audit logging, and editorial review of mutations. See [HANDOFF.md](HANDOFF.md) and the framework MCP documentation for the current endpoint and client contract.

The `book` model demonstrates permissive-ish AI context; `release` demonstrates strict governance (draft-only, forbidden publish/delete, editorial review required).

## Rebrand This Demo

The plugin includes a generator UI for creating your own branded copy of the demo plugin. In wp-admin, open **Tools > Rebrand This Demo** or use the **Rebrand This Demo** quick action on the plugin row.

Fill in the form with the identity your new plugin should use:

- **Plugin Name**: the public name shown in WordPress.
- **Plugin Slug**: a lowercase folder-safe slug, such as `my-saltus-plugin`.
- **Main Plugin File**: the main PHP file, usually matching the slug, such as `my-saltus-plugin.php`.
- **Namespace Segment**: a PHP namespace segment like `MySaltusPlugin`.
- **Description**: the plugin summary shown in wp-admin.
- **Author details**: the author name and optional author/plugin URLs.
- **Version**: a semver value such as `1.0.0`.
- **Code Prefix**: a lowercase PHP-safe prefix like `my_saltus_plugin`.

After filling in the form, choose one of two install paths:

- **Download Rebranded Plugin ZIP**: download the ZIP, then upload it from **Plugins > Add New > Upload Plugin** and activate it.
- **Copy & Activate Plugin**: copy the generated plugin directly into this WordPress site's plugins directory and activate it automatically.

The generated plugin is a separate copy. The installed demo plugin is not modified.

## Development Commands

```bash
composer validate --strict
vendor/bin/phpcs
vendor/bin/phpunit
composer package
```

`composer package` creates `dist/framework-demo-<version>.zip`.

`composer make-pot` uses WP-CLI to rebuild `languages/framework-demo.pot` when WP-CLI is available.

## Releasing

Build the release ZIP with:

```bash
composer install --no-dev --optimize-autoloader
composer package
```

The package script excludes development-only directories such as `.git`, `node_modules`, `build`, `dist`, `release`, and reports.

## Notes

This repository is intentionally a demo and starter. Keep the Saltus model files that help explain framework capabilities, and delete or rename demo models when generating a production plugin.
