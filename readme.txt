=== Saltus Framework Demo ===
Contributors: saltus
Tags: developer, boilerplate, custom post types, taxonomies, meta boxes
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 3.1.0
License: GPL-2.0-or-later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

A rebrandable demo and starter plugin for building WordPress plugins with the Saltus Framework.

== Description ==

This plugin is a working reference for the Saltus Framework. It registers custom post types,
taxonomies, admin columns and filters, meta boxes, settings pages, frontend shortcodes, dynamic
blocks, AI context, and WordPress-native MCP abilities — all from declarative model configuration
files rather than hand-written registration code.

It is built to be read, not just installed. Each model demonstrates one coherent slice of the
framework:

* `movie` — the minimum viable model. Four keys. Its value is being boring.
* `book` — the kitchen sink, showing how every feature composes.
* `recipe` — all 44 Codestar meta field types across tabbed sections.
* `event` — every admin column source and filter kind.
* `venue` — frontend rendering with the framework's default templates.
* `staff` — multi-section settings pages with a custom menu parent.
* `release` — AI governance: strict context, editorial review, per-section MCP opt-outs.
* `artwork` — serialized versus unserialized meta, and the REST shape of each.
* `snippet` — a model declared in JSON instead of PHP.
* `internal_note` — the opt-out reference: REST off, MCP absent, private post type.

Five models are active on install: the `movie` and `book` post types, plus the `genre`, `writer` and
`country` taxonomies (`country` attaches to core Posts). Those five have no toggle — only the nine
opt-in models do, under **Books → Settings → Demo Models**, so the demo stays legible instead of
registering ten post types at once.

= Rebrand it =

**Tools → Rebrand This Demo** generates your own branded copy of this plugin: your name, slug,
namespace, text domain, and prefix, delivered as a downloadable ZIP or copied straight into the
site and activated. The generated plugin is a separate copy; this one is left untouched.

Delete or rename the demo models once you have your own plugin. They exist to explain the
framework, not to ship.

== Installation ==

This plugin ships its dependencies, so a release ZIP installs like any other plugin:

1. Upload the ZIP under **Plugins → Add New → Upload Plugin**.
2. Activate **Saltus Framework Demo**.
3. Open **Books** in the admin menu, or **Tools → Rebrand This Demo** to generate your own copy.

Installing from a git clone instead requires Composer, because the framework is a Composer
dependency and is not committed to the repository:

`composer install`

== Frequently Asked Questions ==

= Is this meant for production sites? =

No. It is a demo and a starter. Use **Rebrand This Demo** to generate your own plugin, then remove
the demo models you do not need.

= Why are most of the post types missing? =

They are opt-in. Enable them under **Books → Settings → Demo Models**.

= Does deleting the plugin remove my content? =

No. Uninstalling removes the plugin's own options, transients, and scheduled event. Posts created
under the demo post types stay in the database, and reinstalling makes them visible again. Delete
them yourself if you want them gone.

= What does it require? =

PHP 8.3 or newer. The framework's AI and MCP features detect the WordPress Abilities API at runtime
and fall back cleanly when it is not present.

== Changelog ==

= 3.1.0 =
* Added an uninstall routine that clears the plugin's options, transients, and cron event.
* Added `readme.txt` and completed the plugin header compatibility fields.
* Added a JSON Schema for model configuration, plus a model linter wired into CI.
* Added an AI assistant action handler, so the in-editor panel answers instead of returning 501.
* Nine focused demo models covering the framework surface, each opt-in via settings.

= 3.0.0 =
* Book model as the complete framework showcase: shortcode, blocks, quick edit, MCP tools, AI
  context.
* Frontend templates, shared shortcode and block rendering, and a frontend stylesheet.
* MCP tool validator tooling.

= 2.0.0 =
* Rebrand-self UI with ZIP download and copy-and-activate delivery.
* Input validation, sanitization, and security hardening for the renamer.
* Plugin architecture refactor.
