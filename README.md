# Saltus Framework Demo

This is a modern demo plugin for building WordPress plugins with the Saltus Framework.

It shows how to register custom post types, taxonomies, admin columns, filters, meta boxes, settings pages, assets, and translations using Saltus model configuration files.

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

The demo models live in `src/models`:

- `post-type-basic.php` registers a small `movie` post type.
- `post-type-all.php` registers the larger `book` demo, including meta fields and settings.
- `taxonomy-multiple.php` registers demo taxonomies for books and posts.

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
