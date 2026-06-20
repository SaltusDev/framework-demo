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

## Rename UI

The plugin includes a generator UI for creating a renamed copy of the demo plugin.

In wp-admin, open the plugin settings screen and choose **Rename Plugin**. Fill in the new plugin identity:

- plugin name
- slug and main file
- namespace segment
- text domain
- description
- author details
- version
- code prefix

Submitting the form downloads a ZIP file for the renamed plugin. The installed demo plugin is not modified.

## Development Commands

```bash
composer validate --strict
vendor/bin/phpcs
vendor/bin/phpunit
composer package
```

`composer package` creates `dist/framework-demo.zip`.

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
