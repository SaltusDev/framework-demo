# Design — Saltus Framework Demo

## Brand Identity

Tagline: *A modern, rebrandable demo plugin for the Saltus Framework.*  
Official domain: https://saltus.dev/

### Logo

No dedicated logo — the plugin inherits the Saltus brand identity. The Rebrand This Demo admin page uses a clean form layout with no special branding so the generated output remains neutral.

## Color Palette

| Token | Hex | Usage |
|-------|-----|-------|
| `--wp-admin-theme-color` | `#2271b1` | Standard WP admin blue for primary buttons and links |
| `--wp-admin-theme-color-darker-10` | `#1d6da3` | Primary button hover |
| `--wp-admin-theme-color-darker-20` | `#185d8a` | Primary button active |
| `--framework-demo-bg` | `#f0f0f1` | Admin page background (WP default) |

The plugin does not override WordPress admin colors. It relies on standard WP admin CSS for all UI elements.

## Typography

| Face | Usage | Fallback |
|------|-------|----------|
| System fonts | Admin UI | `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif` |
| `Consolas, Monaco, monospace` | Code editor field | `monospace` |

Typography follows WordPress admin defaults. No custom fonts are loaded.

## Components

**Rebrand This Demo Form** (`src/Plugin/Admin/RenamerPage.php`):
- Two-column layout: form on the left, usage guide on the right
- Text fields for all plugin identity properties (name, slug, namespace, author, etc.)
- Checkbox for "Credit Saltus as contributor"
- Two submit buttons: "Download Rebranded Plugin ZIP" (primary) and "Copy & Activate Plugin" (secondary)
- Success/error notices with WP admin `notice` classes
- Activation banner: info notice with "Open Rebrand This Demo" and "Dismiss" buttons on plugin activation

**Admin Styles** (`assets/css/admin-style.css`):
- Loaded only on plugin admin pages (hook suffix matches `framework-demo`)
- Scoped layout styles for the renamer page `.framework-demo-renamer` namespace
- `.framework-demo-renamer__layout` — flexbox two-column
- `.framework-demo-renamer__form` — left column, `flex: 2`
- `.framework-demo-renamer__guide` — right sidebar, `flex: 1`

## Layout

**Renamer Page:**
```
┌──────────────────────────────────────────────────┐
│  Rebrand This Demo                                │
│  Make it yours...                                  │
├───────────────────────┬──────────────────────────┤
│  Form (2/3 width)     │  Guide (1/3 width)        │
│  ┌─────────────────┐  │  ┌────────────────────┐  │
│  │ Plugin Name      │  │  │ How to rebrand     │  │
│  │ Plugin Slug      │  │  │ this demo          │  │
│  │ Main File        │  │  │                    │  │
│  │ Namespace        │  │  │ 1. Fill in...      │  │
│  │ Description      │  │  │ 2. Choose install  │  │
│  │ Author           │  │  │ 3. Continue from.. │  │
│  │ ...              │  │  └────────────────────┘  │
│  └─────────────────┘  │                          │
│  [Download ZIP] [Copy] │                          │
└───────────────────────┴──────────────────────────┘
```

**Plugin Row Quick Action:**
- "Rebrand This Demo" link added to the plugin action row in **Plugins** screen
- Links directly to the Tools > Rebrand This Demo page

## Responsive

The renamer page layout collapses to a single-column stack on narrow viewports via the `_admin-style.css` stylesheet. The activation notice uses standard responsive WP admin notice patterns.
