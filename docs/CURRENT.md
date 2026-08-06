# Current — Saltus Framework Demo

## Working On
- MCP error hint integration across REST controllers in Saltus Framework (see HANDOFF.md) @since 2026-08-06

## Next
- Rebuild `vendor-prefixed/` via Strauss after framework REST hint commits land
- Add PUT route support to `MetaController` in vendor-prefixed layer

## Blocked By
- Framework REST controller hint changes must be committed to saltus-framework-git first @reason Strauss prefixes framework code; hints must originate upstream

## Notes
- v3.0.0 released — book model now showcases frontend shortcode, blocks, quick edit, MCP tools, and AI context
- The legacy Grunt build system in `build/` is being phased out in favor of Composer scripts + PostCSS
- All new tooling should use Composer scripts or Node scripts, not Grunt
- PHP 8.3+ is required — no legacy polyfills needed