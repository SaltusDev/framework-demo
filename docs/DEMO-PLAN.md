# Demo Coverage Plan — One Model Per Framework Story

## Why this shape

Today two CPTs carry the whole demo: `movie` (17 lines, near-empty) and `book` (337 lines, almost
everything). That is the worst of both — a developer who wants to learn one feature has to
reverse-engineer which of the 30 keys in `book` produces it, and a developer skimming `movie`
learns nothing.

The fix is not "add more to `book`". It is **one model per coherent story**, each small enough to
read in a sitting, each the canonical answer to a question a developer actually arrives with.
`book` stays as the kitchen-sink reference precisely because a "does it all together" example is
also worth having.

Coverage is measured against the framework surface enumerated in
[FEATURE-MATRIX.md](FEATURE-MATRIX.md) — 10 feature services, 44 Codestar field types, 5 admin
filter kinds, 5 admin column source kinds, 21 hooks, 3 model file formats.

---

## Target model set

| # | Model | Type | Demonstrates | Lines (actual) |
|---|-------|------|--------------|----------------|
| 1 | `movie` | cpt | The minimum viable model. Nothing but `type` + `name` + labels. | 29 |
| 2 | `book` | cpt | Everything at once — the integration reference. | 430 |
| 3 | `recipe` | cpt | **Meta field gallery**: all 44 Codestar field types across tabbed sections. | 449 |
| 4 | `event` | cpt | **Admin list mastery**: all 5 column source kinds, all 5 filter kinds, sortable columns, capability-gated columns. | 160 |
| 5 | `venue` | cpt | **Frontend & blocks**: shortcode + both blocks with framework default templates (no overrides). | 67 |
| 6 | `staff` | cpt | **Settings pages**: multi-section settings, custom menu parent, tabbed settings. | 157 |
| 7 | `release` | cpt | **AI governance & MCP**: strict `ai_context`, editorial review, per-section `show_in_mcp` opt-outs. | 106 |
| 8 | `artwork` | cpt | **Serialized meta + REST**: `data_type: serialize`, `register_rest_api`, nested repeater paths. | 130 |
| 9 | `snippet` | cpt | **Model format variety**: declared in JSON, not PHP. Proves the loader. | 41 (`.json`) |
| 10 | `internal_note` | cpt | **Lockdown**: `show_in_rest: false`, `mcp_tools` absent, private, no archive. The opt-out reference. | 72 |
| 11 | `genre`, `writer`, `country` | tax | Existing taxonomy trio — hierarchical, non-hierarchical, multi-association. | 55 |
| 12 | `venue_type` | tax | Taxonomy with `show_in_rest: false` + single association, contrasted against the trio. | 32 |

Ten CPTs is a lot for one plugin, so **models 3–10 ship disabled by default** and a settings toggle
enables them.

> **Revised during implementation.** The original plan was to use the framework's own `active` key,
> on the assumption it worked and was merely undemonstrated. It does not: `BaseModel::is_disabled()`
> opens with `empty( $this->data['active'] )`, and `empty( false )` is `true`, so `active => false`
> takes the "not disabled" early return and the model loads anyway. Only a truthy-but-not-`true`
> value disables one. Each opt-in model therefore gates itself with `saltus_demo_model_enabled()`
> (`src/models/_demo-toggle.php`), returning an empty array that `ModelFactory::create()` soft-fails.
> See ROADMAP Phase 4 task 5.

---

## What each model is demoing

### 1. `movie` — the floor
**Question answered:** "What is the least I have to write?"

Stays as-is. `type`, `name`, `supports`, three labels. Registers a working CPT with correct English
labels and messages inherited from `has_one`/`has_many`. Its value is being boring: it proves the
framework's defaults are real, not aspirational. Anything added here damages it.

### 2. `book` — the ceiling
**Question answered:** "What does a real, fully-configured model look like?"

Stays as the kitchen sink: meta, settings, quick edit, admin cols and filters, duplicate, export,
drag-and-drop, remember tabs, shortcode with custom templates, both blocks, `ai_context`. Cross-
referenced from every focused model as "see these combined in `book`".

Only change: a header comment pointing at the focused models, so nobody mines `book` to learn one
feature in isolation.

### 3. `recipe` — every field type
**Question answered:** "What can a meta field be, and what does each one store?"

The gap this closes is the biggest one. `book` uses 10 of 44 Codestar field types. `recipe` uses
**all 44**, grouped into tabbed sections by kind:

- *Text-like*: `text`, `textarea`, `number`, `spinner`, `slider`, `code_editor`, `wp_editor`
- *Choice*: `select`, `radio`, `checkbox`, `switcher`, `button_set`, `image_select`, `palette`
- *Media*: `media`, `upload`, `gallery`, `image_select`
- *Design*: `color`, `color_group`, `background`, `border`, `dimensions`, `spacing`, `typography`,
  `link_color`
- *Structural*: `group`, `repeater`, `fieldset`, `tabbed`, `accordion`, `sortable`, `sorter`
- *Content/UI-only*: `heading`, `subheading`, `content`, `notice`, `submessage`
- *Other*: `date`, `datetime`, `icon`, `link`, `map`, `callback`, `backup`

Each field carries a `desc` naming the meta shape it produces (`string`, `number`, `object`,
`array`) so it doubles as living documentation of the `matched_fields` type map — the thing MCP
clients consume via `normalized.fields`. Also demonstrates `dependency` for conditional fields
beyond `book`'s single example.

### 4. `event` — the admin list
**Question answered:** "How do I make the posts list screen actually useful?"

`book` shows 4 column kinds but only taxonomy filters. `event` covers the full admin surface:

- **Columns**: all four sources — `post_field`, `meta_key`, `taxonomy`, `featured_image` — plus a
  `function` callback column, `date_format` on a meta column, `link` variants, `sortable => false`
  on one column, and `cap`/`post_cap` gating so a column hides from users lacking a capability.
- **Filters**: all five kinds the framework supports — `taxonomy`, `meta_key`, `meta_search_key`,
  `meta_exists`/`meta_key_exists`, and `post_date`. Includes the `key` override that lets a filter
  query a different meta key than its ID.
- **Hooks**: `saltus/framework/admin_filters/{post_type}/filter_query/{filter_id}` to customise one
  filter's query, and `admin_filters/filter_output/{filter_id}` to replace a filter's HTML.

This is the model that makes the framework's extended-cpts heritage legible.

### 5. `venue` — frontend without overrides
**Question answered:** "What do I get for free on the frontend?"

`book` overrides both templates, so it never exercises the framework's own rendering. `venue`
configures `frontend: true` and `blocks: true` with **no `templates` key at all**, falling through
to the framework defaults — which is the path a new plugin takes on day one.

Also demonstrates the theme-override layer: documents that dropping
`saltus/venue/list.php` into a theme takes precedence over the framework default, since template
resolution is config → theme → framework.

> Blocked: the framework does not currently ship `templates/{list,single}.php` or
> `templates/blocks/{list,single}.php`, so both renderers return an empty string. Tracked as
> ROADMAP Phase 4 task 4. This model is the regression test for that fix — it is the *only* model
> that would catch it, precisely because it has no overrides.

### 6. `staff` — settings pages
**Question answered:** "How do I give my CPT an options screen?"

`book` has one settings page with two sections. `staff` covers the rest of what `CodestarSettings`
supports: multiple sections with `icon`s, `menu_parent` pointing somewhere other than the CPT's own
submenu, a custom `capability`, `menu_title` vs `title`, and the `tabbed` field type for grouped
options within a section. Reads settings back on the frontend to show the round trip.

### 7. `release` — AI governance
**Question answered:** "How do I stop an AI agent from doing something reckless?"

`book`'s `ai_context` is permissive-ish. `release` is the locked-down contrast, and the model where
governance is the *point* rather than a footnote:

- `allowed_statuses` restricted to `draft` only
- `forbidden_actions` covering `publish` and `delete`
- `require_human_review => true`, with per-field `field_rules`
- Per-section `show_in_mcp => false` on a sensitive settings section, while REST stays on —
  demonstrating that the REST and MCP gates are independent
- `mcp_tools => true` at model level so discovery works

Paired with a doc note on the editorial review queue at **Tools → AI Review Queue** and the
`AiContextProvider::validate_mutation()` rejection path (`ai_context_violation`, HTTP 403).

### 8. `artwork` — serialized meta and REST shape
**Question answered:** "How does my meta actually reach the REST API and MCP clients?"

The subtlest gap. `MetaFieldProvider::normalize_meta_fields()` behaves very differently for
`data_type => 'serialize'` (one meta key holding a nested object, paths like
`artwork_info.dimensions.width`) versus the default `unserialize` (one meta key per field). `book`
only shows the default.

`artwork` sets `data_type => 'serialize'` plus `register_rest_api => true` on one metabox and keeps
another unserialized, so a developer can diff the two `normalized.rest_meta_keys` payloads side by
side. Includes a nested `repeater` to show `schema.items.properties` generation — the thing that
lets an MCP client write structured meta correctly.

### 9. `snippet` — the loader
**Question answered:** "Do models have to be PHP?"

A model declared as `snippet.json`. The `Modeler` accepts `.php`, `.json`, `.yml` and `.yaml`, and
loads files in ascending filename order — both undemonstrated today. Kept deliberately tiny since
its only job is proving the loader.

> Note: `.yml`/`.yaml` are **not** usable here. The parser requires
> `Symfony\Component\Yaml\Yaml`, which is not installed (`hassankhan/config` declares it as a
> suggestion, not a dependency). Verified: `class_exists()` returns false. A YAML model would throw.
> So this model uses JSON, and the docs should state the YAML caveat rather than imply support.

### 10. `internal_note` — the opt-out
**Question answered:** "How do I keep something *out* of REST, MCP, and the frontend?"

Every other model opts in. This one is the negative space, and it matters because the two gates
default differently: REST is on unless explicitly `false`, MCP is off unless explicitly `true`.

- `show_in_rest => false`, `mcp_tools` omitted entirely
- `public => false`, `publicly_queryable => false`, `has_archive => false`
- `show_ui => true` so it still exists in wp-admin
- A comment stating what a `list_models` call returns for it (nothing) and why

Also the natural home for `active => false` documentation, since it is the model most likely to be
deleted from a rebranded plugin.

### 11. `genre` / `writer` / `country` — taxonomies
**Question answered:** "How do taxonomies differ from CPTs?"

Existing trio, kept. Covers hierarchical (`category`) vs flat (`tag`), single vs array
`associations`, multi-post-type association (`country` → `book` + `post`), and label overrides.

Gains a comment recording a real framework limitation I verified: **taxonomy models accept a `meta`
key and silently ignore it.** `ModelFactory::create()` only calls `process_services()` for
`PostType`; `Taxonomy::set_meta()` stores the config into `args` where nothing consumes it. Codestar
has `createTaxonomyOptions()` but the framework never calls it. Term meta is therefore not a
framework feature today — worth stating outright so nobody wastes an afternoon on it.

### 12. `venue_type` — the taxonomy opt-out
**Question answered:** "Can a taxonomy be private too?"

Small contrast to the trio: `show_in_rest => false` and a single association to `venue`. Shows
that taxonomy defaults (`show_in_rest => true`, hierarchy inferred from `type`) are overridable.

---

## Coverage after this plan

| Surface | Now | After |
|---------|-----|-------|
| Feature services demoed | 7 / 10 | 10 / 10 |
| Codestar field types | 10 / 44 | 44 / 44 |
| Admin column source kinds | 4 / 5 | 5 / 5 |
| Admin filter kinds | 1 / 5 | 5 / 5 |
| Model file formats | 1 / 2 usable | 2 / 2 usable |
| Documented hooks exercised | 1 / 21 | 12 / 21 |
| `active` toggle | no | yes |
| REST/MCP opt-out | no | yes |
| Serialized meta | no | yes |
| Framework default templates | no | yes |

Remaining un-demoable, with reasons: the `wp saltus` CLI tree (broken upstream, Phase 4 task 2),
block/assistant editor JS (not shipped, Phase 4 task 4), YAML models (parser absent), and taxonomy
meta (never wired). Each is a framework gap, not a demo gap — which is itself useful output from
this exercise.

---

## Implementation phases

**Phase A — restructure (no new features)** ✓ complete
1. Add `docs/FEATURE-MATRIX.md`: the enumerated surface, with a demoed-by column
2. Add the header comment to `book` pointing at focused models
3. Add `active => false` + a settings toggle so extra models stay opt-in

**Phase B — the high-value gaps** ✓ complete
4. `recipe` — all 44 field types
5. `event` — full admin list surface
6. `artwork` — serialized vs unserialized meta

**Phase C — the contrast models** ✓ complete
7. `internal_note` — opt-out
8. `release` — AI governance
9. `venue_type` — taxonomy opt-out
10. Taxonomy trio comments re: term meta

**Phase D — the ones with upstream dependencies** ✓ complete
11. `staff` — settings (independent, safe)
12. `snippet` — JSON loader (independent, safe)
13. `venue` — frontend defaults **(blocked on Phase 4 task 4; ship the model, expect empty output,
    let it fail loudly as the regression test)**

**Phase E — documentation** ✓ complete
14. Rewrite `README.md`'s model section as a "which model shows what" table
15. `docs/MODELS.md`: per-model walkthrough, essentially this document's middle section expanded
16. Extend `bin/validate-mcp-tools.php` to assert the opt-out models are absent from `list_models`

**Verification per phase:** `composer tests`, plus a wp-admin pass on each new CPT's edit screen and
list screen. Field-type coverage in `recipe` needs manual save/reload checks — 44 field types is
exactly where a silent Codestar mismatch would hide.
