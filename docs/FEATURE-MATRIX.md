# Feature Matrix — What Saltus Offers vs What This Demo Shows

Enumerated from the framework source in `vendor-prefixed/saltus/framework/`, not from its docs —
the docs lag the code in several places. "Demoed by" refers to the model set planned in
[DEMO-PLAN.md](DEMO-PLAN.md); ✓ means already covered today.

---

## Feature services

All ten are registered in `Core::get_service_classes()`.

| Service | Config key | Model-level? | Demoed by |
|---------|-----------|--------------|-----------|
| `AdminCols` | `features.admin_cols` | yes | ✓ `book`, `event` |
| `AdminFilters` | `features.admin_filters` | yes | ✓ `book`, `event` |
| `Meta` | `meta` | yes | ✓ `book`, `recipe`, `artwork` |
| `Settings` | `settings` | yes | ✓ `book`, `staff` |
| `Duplicate` | `features.duplicate` | yes | ✓ `book` |
| `SingleExport` | `features.single_export` | yes | ✓ `book` |
| `DragAndDrop` | `features.dragAndDrop` | yes | ✓ `book` |
| `RememberTabs` | `features.remember_tabs` | yes | ✓ `book` |
| `QuickEdit` | `features.quick_edit` | yes | ✓ `book` |
| `Blocks` | `blocks` | yes | ✓ `book`, `venue` |
| `Frontend` | `frontend` | yes | ✓ `book`, `venue` |
| `AiContext` | `ai_context` | yes | ✓ `book`, `release` |
| `AiAssistant` | derived from `ai_context` | implicit | ✓ `book`, `release` |
| `EditorialReview` | none (global) | no | `release` (doc only) |
| `MCP` | `options.mcp_tools` | yes | ✓ `book`, `release`, `internal_note` |
| `WpCli` | none (global) | no | **blocked upstream** |

Note the count discrepancy with "10 services": `Core` registers 16 keys, but `AiAssistant`,
`EditorialReview`, `MCP` and `WpCli` are global services with no per-model config, so only 12 are
model-configurable.

---

## Codestar field types (44)

Every type has a directory in `lib/codestar-framework/fields/` **and** an entry in the framework's
`matched_fields` type map — verified parity, no gaps in either direction. The map value is the
JSON-schema-ish type MCP clients see in `normalized.fields`.

| Type | Maps to | Demoed today |
|------|---------|--------------|
| `text`, `textarea`, `code_editor`, `wp_editor` | `string` | ✓ `book` |
| `checkbox`, `switcher` | `string` | ✓ `book` |
| `color`, `icon` | `string` | ✓ `book` |
| `media`, `gallery` | `array` / `string` | ✓ `book` |
| `number`, `spinner`, `slider` | `number` / `string` | — |
| `select`, `radio`, `button_set`, `image_select`, `palette` | `array` / `string` | — |
| `date`, `datetime` | `string` | — |
| `upload`, `link`, `link_color` | `string` | — |
| `background`, `border`, `dimensions`, `spacing`, `typography`, `color_group` | `object` / `string` | — |
| `group`, `fieldset`, `map` | `object` | — |
| `repeater` | `array` | — |
| `accordion`, `tabbed`, `sortable`, `sorter` | `string` | — |
| `heading`, `subheading`, `content`, `notice`, `submessage` | `string` | — |
| `callback`, `backup` | `string` | — |

**10 of 44 covered today.** `recipe` closes the gap.

---

## Admin column sources

Resolved in `SaltusAdminCols::col()`.

| Source key | Behaviour | Demoed |
|-----------|-----------|--------|
| `post_field` | Renders a `WP_Post` property | ✓ `book` |
| `meta_key` | Renders post meta; supports `date_format` | partial — `book` has no `date_format` |
| `taxonomy` | Renders linked terms | ✓ `book` |
| `featured_image` | Renders an image at a given size | ✓ `book` |
| `function` | Arbitrary callback | ✓ `book` |

Modifiers: `sortable` (default on for the three data sources), `link` (defaults to `list`), `cap`
(hides the column), `post_cap` (hides one cell), `title`. Only `title` and `function` are shown
today. `event` covers the rest.

---

## Admin filter kinds

Dispatched in `SaltusAdminFilters::render_filter()`.

| Kind | Config key | Demoed |
|------|-----------|--------|
| Taxonomy dropdown | `taxonomy` | ✓ `book` |
| Meta value dropdown | `meta_key` | — |
| Meta text search | `meta_search_key` | — |
| Meta existence | `meta_exists` / `meta_key_exists` | — |
| Post date | `post_date` | — |

Modifier: `key` overrides which meta key is queried, independent of the filter's array key.

**1 of 5 covered today.** `event` closes the gap.

---

## Meta box options

| Key | Effect | Demoed |
|-----|--------|--------|
| `id` | Metabox ID / option name | ✓ `book` |
| `title` | Box heading | ✓ `book` |
| `sections` | Tabbed sections | ✓ `book` |
| `fields` | Flat field list (alternative to `sections`) | — |
| `context` | WP metabox context, default `normal` | — |
| `priority` | WP metabox priority, default `high` | — |
| `data_type` | `unserialize` (default) or `serialize` | — → `artwork` |
| `register_rest_api` | Exposes meta as writable REST meta | — → `artwork` |
| `show_in_rest` / `show_in_mcp` | Per-section gate overrides | — → `release` |
| `dependency` (per field) | Conditional display | ✓ `book` (one instance) |

`data_type => serialize` materially changes the MCP payload: one meta key holding a nested object,
with dotted paths like `artwork_info.dimensions.width`, versus one key per field.

---

## Model-level keys

| Key | Purpose | Demoed |
|-----|---------|--------|
| `type` | `cpt` / `category` / `tag` (plus aliases) | ✓ |
| `name` | Registration slug; max 20 chars for CPTs, 32 for taxonomies | ✓ |
| `active` | **broken** — `false` does not disable (ROADMAP P4.5). Demo uses an explicit gate instead | n/a |
| `supports` | `register_post_type` supports | ✓ |
| `options` | Raw `register_post_type` / `register_taxonomy` args | ✓ |
| `labels` | `has_one`, `has_many`, `featured_image`, `text_domain`, `overrides.*` | ✓ `book` |
| `block_editor` | `false` disables Gutenberg for the type | ✓ `book` |
| `associations` | Taxonomy → post type links | ✓ taxonomy trio |
| `meta`, `settings`, `features`, `blocks`, `frontend`, `ai_context` | Feature config | ✓ `book` |

`labels.overrides` supports four sub-keys — `labels`, `messages`, `bulk_messages`, `ui` — all
exercised by `book`.

---

## Gating semantics

Two independent gates, with **different defaults**. This is the most misunderstood part of the
framework and the reason `internal_note` exists.

| Gate | Model option | Per-section key | Default when absent |
|------|-------------|-----------------|---------------------|
| REST | `options.show_in_rest` | `show_in_rest` | **enabled** unless explicitly `false` |
| MCP | `options.mcp_tools` | `show_in_mcp` | **disabled** unless explicitly `true` |

Capabilities gated per model: `models`, `meta`, `settings`, `duplicate`, `export`, `reorder`,
`blocks`. `health` is framework-scoped and always available.

The legacy `saltus_rest` key is **not read anywhere** — it appears only in the framework's own
`docs/MCP.md`, which is stale.

---

## Model file formats

| Format | Supported | Usable here |
|--------|-----------|-------------|
| `.php` | yes | ✓ |
| `.json` | yes | ✓ → `snippet` |
| `.yml` / `.yaml` | listed by `Modeler` | **no** — parser needs `Symfony\Component\Yaml\Yaml`, which is not installed |

Files load in ascending filename order, so numeric prefixes control registration sequence.

---

## Hooks (21)

| Hook | Type | Demoed |
|------|------|--------|
| `saltus/framework/services` | filter | — |
| `saltus/framework/modeler/priority` | filter | — |
| `saltus/framework/models/path` | filter | — |
| `saltus/framework/models/extra_models` | filter | — |
| `saltus/framework/meta/matched_fields` | filter | — |
| `saltus/framework/admin_filters/category_list` | filter | — |
| `saltus/framework/admin_filters/filter_output/{id}` | filter | — → `event` |
| `saltus/framework/admin_filters/{type}/filter_query/{id}` | filter | — → `event` |
| `saltus/framework/duplicate_post/args` | filter | — |
| `saltus/framework/duplicate_post/excluded_meta_keys` | filter | — |
| `saltus/framework/duplicate_post/after` | action | — |
| `saltus/framework/drag_and_drop/update_menu_order` | action | — |
| `saltus/framework/frontend/query_args` | filter | — → `venue` |
| `saltus/framework/blocks/attributes` | filter | — |
| `saltus/framework/blocks/query_args` | filter | — |
| `saltus/framework/blocks/template` | filter | — |
| `saltus/framework/ai/assistant_actions` | filter | ✓ `AssistantProvider` |
| `saltus/framework/editorial_review/require_human_review` | filter | **unusable** — carries no model context |
| `saltus/framework/mcp/audit/*` (2) | filter | — |
| `saltus/framework/mcp/rate_limit/*` (4) | filter | — |
| `saltus/framework/mcp/cache/*` (3) | filter | — |
| `saltus/framework/mcp/ability_prefix`, `ability_category` | filter | — |

Deprecated, still honoured: `saltus_models_path`, `saltus_models`, `saltus/cfs/fields`.

---

## Known framework gaps

Not demo gaps — these cannot be demonstrated because the framework does not deliver them.

| Gap | Detail | Tracked |
|-----|--------|---------|
| `active` key inverted | `empty( false )` short-circuits `is_disabled()`; `false` enables, `1` disables | ROADMAP P4.5 |
| Mixed model files misparsed | `is_multiple()` inspects only the first element, silently dropping siblings | ROADMAP P4.6 |
| Taxonomy meta ignored | Taxonomy models accept `meta` and store it, but `ModelFactory` only calls `process_services()` for `PostType`. Codestar's `createTaxonomyOptions()` is never called. | new |
| `wp saltus` unreachable | WP-CLI throws `'wp saltus' can't have subcommands` | ROADMAP P4.2 |
| Block/assistant JS absent | `Feature/Blocks/editor.js`, `Feature/AiAssistant/editor.js` + CSS not shipped | ROADMAP P4.4 |
| Default templates absent | `templates/{list,single}.php`, `templates/blocks/*` not shipped | ROADMAP P4.4 |
| Asset base wrong under Strauss | `root_url` points at deleted `vendor/saltus/`; shimmed in `Assets` | ROADMAP P4.3 |
| Editorial review not per-model | Filter receives only the tool name | ROADMAP P3.4 |
| YAML models unusable | `symfony/yaml` not a dependency | new |
| `CapabilityPolicy` unused | Unifies `ModelRestPolicy`/`McpPolicy` but only the MCP middleware uses it | new |
