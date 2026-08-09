# Model Walkthrough

Each model in this demo answers one question a developer arrives with. This document expands on what
each model demonstrates and how to use it as a learning reference.

---

## Always-active models

### `movie` — the floor

**File:** `src/models/post-type-basic.php`  
**Question answered:** "What is the least I have to write?"

```php
return array(
	'type'    => 'cpt',
	'name'    => 'movie',
	'labels'  => array(
		'has_one'  => 'Movie',
		'has_many' => 'Movies',
	),
	'options' => array(
		'supports' => array( 'title', 'editor' ),
	),
);
```

That's it. 11 lines. The framework infers:
- English labels and admin messages from `has_one`/`has_many`
- Menu position, capability (`edit_posts`), public/queryable defaults
- `show_in_rest => true` for block editor and REST API access

**Its value is being boring.** It proves the framework's defaults are real, not aspirational. Anything
added here damages it. For richer examples, see `book` or the focused models.

---

### `book` — the ceiling

**File:** `src/models/post-type-all.php`  
**Question answered:** "What does a real, fully-configured model look like?"

The kitchen sink: meta, settings, admin cols and filters, duplicate, export, drag-and-drop,
remember tabs, quick edit, shortcode with custom templates, both blocks, and `ai_context` with
editorial review.

**430 lines** (337 before the Demo Models settings section was added). Uses 10 of 44 Codestar field types, 4 of 5 column source kinds, 1 of 5 filter kinds,
and 1 of 21 framework hooks.

**Don't mine this to learn one feature.** Its value is showing how features compose. For isolated
study, see the focused models below.

Key sections to grep:
- `features.admin_cols` — 4 column sources demonstrated
- `features.admin_filters` — taxonomy filter only (see `event` for the other 4 kinds)
- `features.duplicate` / `single_export` / `dragAndDrop` / `remember_tabs` / `quick_edit` — single-line enables
- `meta` → two metaboxes with tabbed sections, 10 field types, one `dependency` example
- `settings` → two sections (now three, with the Demo Models toggle section added)
- `frontend` → shortcode + custom `templates/book/list.php` and `single.php`
- `blocks` → both blocks with custom `templates/book/blocks/*`
- `ai_context` → permissive-ish governance (contrast `release` for strict)

---

## Opt-in models

Enable from **Books → Settings → Demo Models**.

Each opt-in model file opens with a gate:

```php
if ( ! saltus_demo_model_enabled( 'recipe' ) ) {
	return array();
}
```

`ModelFactory::create()` soft-fails any config without a `type` key, so returning an empty array
skips the model cleanly. The framework's own `active` key is not used because it is inverted —
`'active' => false` does **not** disable a model (ROADMAP Phase 4 task 5). The JSON model cannot call
a PHP gate, so it lives in `src/models-optional/` and is injected via `extra_models`; see
`src/Plugin/OptionalModels.php`.

---

### `recipe` — every field type

**File:** `src/models/post-type-recipe.php`  
**Question answered:** "What can a meta field be, and what does each one store?"

**All 44 Codestar field types**, grouped into tabbed sections by kind:

- **Text-like:** `text`, `textarea`, `number`, `spinner`, `slider`, `code_editor`, `wp_editor`
- **Choice:** `select`, `radio`, `checkbox`, `switcher`, `button_set`, `image_select`, `palette`
- **Media:** `media`, `upload`, `gallery`
- **Design:** `color`, `color_group`, `background`, `border`, `dimensions`, `spacing`, `typography`, `link_color`
- **Structural:** `group`, `repeater`, `fieldset`, `tabbed`, `accordion`, `sortable`, `sorter`
- **Other:** `date`, `datetime`, `icon`, `link`, `map`, `callback`, `backup`
- **Content/UI-only:** `heading`, `subheading`, `content`, `notice`, `submessage`

Each field's `desc` names the JSON-schema-ish type it maps to in `normalized.fields` — the shape MCP
clients consume. Example:

```php
'color_field' => array(
	'type'  => 'color',
	'title' => 'Color',
	'desc'  => 'Maps to: string (hex)',
),
```

**What to check:**
1. Edit a Recipe in wp-admin and save. Open the browser dev console network tab.
2. Inspect the PUT request to `/wp/v2/recipe/{id}` — see how each field type serializes.
3. Call `GET /saltus-framework/v1/models/recipe` — see the `normalized.fields` payload listing each
   field's `type`, `properties`, `items`, etc.

**Known gap:** The `callback` and `backup` field types are render-only (no stored value), so they
don't appear in `normalized.fields` at all. This is a Codestar behaviour, not a framework choice.

---

### `event` — the admin list

**File:** `src/models/post-type-event.php`  
**Question answered:** "How do I make the posts list screen actually useful?"

Covers the full admin surface that `book` only partially demonstrates:

#### Admin columns (all 5 source kinds)

```php
'features.admin_cols' => array(
	'event_status'       => array( 'post_field' => 'post_status' ),       // WP_Post property
	'event_date_col'     => array( 'meta_key' => 'event_date', 'date_format' => 'M j, Y' ),
	'event_venue_col'    => array( 'meta_key' => 'event_venue', 'link' => 'edit' ),
	'event_capacity_col' => array( 'meta_key' => 'event_capacity', 'sortable' => true ),
	'event_category_col' => array( 'taxonomy' => 'event_category' ),      // Linked terms
	'event_thumbnail_col' => array( 'featured_image' => 'thumbnail', 'sortable' => false ),
	'event_computed_col' => array( 'function' => function($post) { /* ... */ } ),
	'event_internal_col' => array( 'meta_key' => '...', 'cap' => 'edit_others_posts' ), // Hidden from non-admins
),
```

#### Admin filters (all 5 kinds)

```php
'features.admin_filters' => array(
	'event_category_filter'  => array( 'taxonomy' => 'event_category' ),    // Dropdown of terms
	'event_venue_filter'     => array( 'meta_key' => 'event_venue' ),       // Dropdown of distinct values
	'event_organizer_filter' => array( 'meta_search_key' => 'event_organizer' ), // Text input
	'event_featured_filter'  => array( 'meta_exists' => 'event_featured' ), // Has/hasn't
	'event_date_filter'      => array( 'post_date' => 'event_date', 'key' => '...' ), // Date range
),
```

**Hooks demonstrated** (not yet wired in this model, but documented in the config):
- `saltus/framework/admin_filters/filter_output/{filter_id}` — replace a filter's HTML
- `saltus/framework/admin_filters/{post_type}/filter_query/{filter_id}` — customize one filter's query

**What to check:**
1. Add a few Events with different venues, dates, and the "Featured Event" toggle.
2. Visit **Events → All Events** — see all 8 columns, including the computed "Days Until" and the
   capability-gated "Internal Notes" (visible only to users with `edit_others_posts`).
3. Use the 5 filter dropdowns/inputs at the top of the list to narrow the results.
4. Click a column header to sort (works for `post_field`, `meta_key`, and `taxonomy` sources when
   `sortable` is not explicitly `false`).

---

### `venue` — frontend without overrides

**File:** `src/models/post-type-venue.php`  
**Question answered:** "What do I get for free on the frontend?"

Configures `frontend: true` and `blocks: true` with **no `templates` key at all**, falling through
to the framework's own default templates.

```php
'frontend' => array(
	'enabled'   => true,
	'shortcode' => true,
	// No 'templates' key — falls through to framework defaults
),
'blocks'   => array(
	'enabled' => true,
	// No 'templates' key — falls through to framework defaults
),
```

**BLOCKED:** The framework does not currently ship `templates/{list,single}.php` or
`templates/blocks/{list,single}.php`, so both renderers return an empty string. Tracked as
ROADMAP Phase 4 task 4. This model is deliberately kept as the regression test for that fix —
it is the only model with no overrides, so it's the only one that would catch the absence.

**Theme override layer:** Even without plugin templates, dropping `saltus/venue/list.php` into the
active theme takes precedence over the framework default. The resolution order is:
1. Config `templates` key (like `book`)
2. Theme `saltus/{post_type}/` directory
3. Framework `templates/` directory

**What to check (once unblocked):**
1. Add a few Venues.
2. Use the `[venues]` shortcode in a post or page.
3. Add the `saltus/venue-list` block to a post.
4. Verify both render using the framework's default template, not `book`'s custom one.

---

### `staff` — settings pages

**File:** `src/models/post-type-staff.php`  
**Question answered:** "How do I give my CPT an options screen?"

`book` has one settings page with two sections. `staff` covers the rest:

```php
'settings' => array(
	// The key is the option name. Keep the plugin slug in it: an unqualified id survives rebranding
	// verbatim, so every generated plugin would share one row and uninstalling any of them would
	// delete the others' settings.
	'framework-demo-staff-options' => array(
		'menu_title'  => 'Directory Options',          // Differs from 'title'
		'menu_parent' => 'options-general.php',        // Under Settings, not Staff submenu
		'capability'  => 'manage_options',
		'sections'    => array(
			'display' => array(
				'title'  => 'Display Settings',
				'icon'   => 'fa fa-desktop',
				'fields' => array( /* ... */ ),
			),
			'styling' => array(
				'title'  => 'Styling',
				'icon'   => 'fa fa-paint-brush',
				'fields' => array(
					'staff_colors' => array(
						'type'  => 'tabbed',       // Tabbed field type
						'tabs'  => array( /* ... */ ),
					),
				),
			),
			'advanced' => array( /* ... */ ),
		),
	),
),
```

**What to check:**
1. Enable the Staff model.
2. Visit **Settings → Directory Options** (note: under Settings, not under Staff).
3. See three sections with icons: Display Settings, Styling, Advanced.
4. In the Styling section, see the tabbed "Color Scheme" field with Primary/Secondary tabs.
5. Save settings and verify they round-trip via `get_option('framework-demo-staff-options')`.

---

### `release` — AI governance

**File:** `src/models/post-type-release.php`  
**Question answered:** "How do I stop an AI agent from doing something reckless?"

`book`'s `ai_context` is permissive-ish. `release` is the locked-down contrast:

```php
'ai_context' => array(
	'model_purpose'         => 'Track software releases with strict governance: drafts only, no publish or delete from AI clients, editorial review required.',
	'allowed_statuses'      => array( 'draft' ),
	'forbidden_actions'     => array( 'publish', 'delete' ),
	'require_human_review'  => true,
	'field_rules'           => array(
		'release_version' => array(
			'required'    => true,
			'format'      => 'Semantic versioning (e.g., 1.2.0)',
			'max_length'  => 20,
		),
		// ...
	),
	'editorial_guidelines'  => '...',
),
```

**Per-section MCP opt-out:**

```php
'meta' => array(
	'release_public' => array(
		'show_in_rest' => true,
		'show_in_mcp'  => true,   // MCP clients can read and write
	),
	'release_internal' => array(
		'show_in_rest' => true,
		'show_in_mcp'  => false,  // REST clients can read; MCP clients cannot
	),
),
```

**What to check:**
1. Call `GET /saltus-framework/v1/context/release` — see the `ai_context` payload.
2. Attempt a mutation via MCP that violates `field_rules` (e.g., `release_version` > 20 chars) —
   receive HTTP 403 with `code: "ai_context_violation"` and a `data.hint`.
3. Attempt to publish a Release via MCP — receive a rejection citing `forbidden_actions`.
4. Create a Release via MCP — see it queue in **Tools → AI Review Queue**.

**Known limitation:** `require_human_review` currently applies globally to all models because the
framework filter receives only the tool name, no post type or args. See ROADMAP Phase 3 task 4.

---

### `artwork` — serialized meta and REST shape

**File:** `src/models/post-type-artwork.php`  
**Question answered:** "How does my meta actually reach the REST API and MCP clients?"

The subtlest framework behaviour. Two metaboxes side by side:

```php
'meta' => array(
	// Unserialized (default): one meta key per field
	'artwork_flat' => array(
		'data_type'         => 'unserialize',
		'register_rest_api' => true,
		'fields'            => array(
			'artwork_artist' => array( 'type' => 'text' ),  // Stored as: artwork_artist
			'artwork_year'   => array( 'type' => 'number' ), // Stored as: artwork_year
			'artwork_tags'   => array( 'type' => 'repeater' ), // Stored as: artwork_tags (array)
		),
	),

	// Serialized: one meta key holding nested object
	'artwork_info' => array(
		'data_type'         => 'serialize',
		'register_rest_api' => true,
		'fields'            => array(
			'title'      => array( 'type' => 'text' ),       // Path: artwork_info.title
			'dimensions' => array( 'type' => 'group' ),      // Path: artwork_info.dimensions.width
			'provenance' => array( 'type' => 'repeater' ),   // Path: artwork_info.provenance[].owner
		),
	),
),
```

**What to check:**
1. Call `GET /saltus-framework/v1/models/artwork` — diff the two `normalized.rest_meta_keys` arrays.
2. For `artwork_flat`, see top-level keys: `artwork_artist`, `artwork_year`, `artwork_tags`.
3. For `artwork_info`, see one key `artwork_info` with a nested `schema.properties` object.
4. Edit an Artwork and inspect the REST API PUT payload — see how nested repeaters serialize.

**Why this matters:** MCP clients use `normalized.fields` to build their mutation payloads. A
serialized metabox with a nested repeater produces `schema.items.properties`, which tells the client
how to write `artwork_info.provenance[0].owner` correctly.

---

### `snippet` — the loader

**File:** `src/models-optional/snippet.json`  
**Question answered:** "Do models have to be PHP?"

A model declared as JSON. The `Modeler` accepts `.php`, `.json`, `.yml`, and `.yaml`, loading files
in ascending filename order.

```json
{
	"type": "cpt",
	"name": "snippet",
	"labels": {
		"has_one": "Snippet",
		"has_many": "Snippets"
	},
	"meta": {
		"snippet_details": {
			"fields": {
				"snippet_language": {
					"type": "select",
					"options": {
						"php": "PHP",
						"js": "JavaScript"
					}
				}
			}
		}
	}
}
```

**Note on YAML:** `.yml`/`.yaml` are **not** usable here. The parser requires
`Symfony\Component\Yaml\Yaml`, which is not installed (`hassankhan/config` declares it as a
suggestion, not a dependency). Verified: `class_exists()` returns false. A YAML model would throw.

**What to check:**
1. Enable the Snippet model.
2. Visit **Snippets → Add New** — see the "Snippet Details" metabox with Language dropdown and Code field.
3. Grep `src/models/` and confirm `post-type-snippet.json` loads alongside the `.php` files.

---

### `internal_note` — the opt-out

**File:** `src/models/post-type-internal-note.php`  
**Question answered:** "How do I keep something out of REST, MCP, and the frontend?"

Every other model opts in. This one is the negative space, and it matters because the two gates
default differently:

- **REST**: on by default unless explicitly `show_in_rest: false`
- **MCP**: off by default unless explicitly `mcp_tools: true`

```php
'options' => array(
	// Lockdown: not visible on the frontend
	'public'             => false,
	'publicly_queryable' => false,
	'has_archive'        => false,
	'show_ui'            => true,
	'show_in_menu'       => true,
	// REST gate: explicitly disabled
	'show_in_rest'       => false,
	// MCP gate: omitted entirely, so it defaults to false
),
```

**What `list_models` returns for this CPT:** Nothing. MCP discovery only includes models with
`mcp_tools: true`. REST controllers honor `show_in_rest: false` and return 404. The type exists
only in wp-admin.

**What to check:**
1. Enable the Internal Note model.
2. Call `GET /saltus-framework/v1/models` — confirm `internal_note` is absent.
3. Attempt `GET /wp/v2/internal_note` — receive 404 (REST disabled).
4. Visit **Internal Notes** in wp-admin — it exists, editable by admins.

---

### `venue_type` — taxonomy opt-out

**File:** `src/models/taxonomy-venue-type.php`  
**Question answered:** "Can a taxonomy be private too?"

Small contrast to the `genre`/`writer`/`country` trio (all REST-enabled):

```php
return array(
	'type'         => 'tag',
	'name'         => 'venue_type',
	'options'      => array(
		'show_in_rest' => false,  // Explicit opt-out
	),
	'associations' => array( 'venue' ),
);
```

Shows that taxonomy defaults (`show_in_rest => true` inferred, hierarchy from `type`) are overridable.

**What to check:**
1. Enable both Venue and Venue Type models.
2. Edit a Venue — see the "Venue Type" taxonomy box.
3. Attempt `GET /wp/v2/venue_type` — receive 404 (REST disabled).
4. Call `GET /saltus-framework/v1/models` — confirm `venue_type` is absent (no `mcp_tools` key).

---

## Taxonomies

Four taxonomies ship with the demo:

| Name | Type | Associations | REST | Demoed by |
|------|------|--------------|------|-----------|
| `genre` | hierarchical (category) | `book` | enabled | Always active |
| `writer` | flat (tag) | `book` | enabled | Always active |
| `country` | flat (tag) | `book` + `post` | enabled | Always active (multi-association) |
| `event_category` | hierarchical | `event` | enabled | Opt-in (ships with `event`) |
| `venue_type` | flat | `venue` | **disabled** | Opt-in (the taxonomy opt-out) |

**Note on taxonomy meta:** Taxonomy models accept a `meta` key and silently ignore it.
`ModelFactory::create()` only calls `process_services()` for `PostType`; `Taxonomy::set_meta()`
stores the config into `args` where nothing consumes it. Codestar has `createTaxonomyOptions()`,
but the framework never calls it. **Term meta is not a framework feature as of v3.0.0.**

---

## Coverage Summary

| Surface | Before | After |
|---------|--------|-------|
| Codestar field types | 10 / 44 | 44 / 44 |
| Admin column sources | 4 / 5 | 5 / 5 |
| Admin filter kinds | 1 / 5 | 5 / 5 |
| Model file formats | 1 / 2 usable | 2 / 2 usable |
| Feature services | 7 / 10 | 10 / 10 |

See [FEATURE-MATRIX.md](FEATURE-MATRIX.md) for the complete enumerated surface.

---

## Verification Checklist

Per model, after enabling:

1. **wp-admin existence** — visit the CPT/taxonomy screen, confirm it appears
2. **Meta save/load** — add a post, fill fields, save, reload, confirm values persist
3. **Settings save/load** — (for models with settings) change options, save, reload
4. **Admin list** — (for models with columns/filters) confirm columns appear and filters work
5. **REST API** — `GET /wp/v2/{post_type}` (if `show_in_rest: true`)
6. **MCP discovery** — `GET /saltus-framework/v1/models` (if `mcp_tools: true`)
7. **Frontend** — (for models with shortcode/blocks) render and confirm output

Not all models have all surfaces. `movie` has no meta; `internal_note` has no REST; `venue` has no
blocks JS (blocked upstream).

---

## Next Steps

1. **Browse the code.** Each model is 40–400 lines, readable in a sitting.
2. **Enable one model** and explore it in wp-admin.
3. **Compare `book` vs a focused model** side by side to see the contrast.
4. **Check the REST/MCP payloads** with curl or your MCP client.
5. **Read the blocked notes** for `venue` and `release` to understand the upstream gaps.

For design rationale and implementation phases, see [DEMO-PLAN.md](DEMO-PLAN.md).
