# Current — Saltus Framework Demo

## Working On
- MCP error hint integration across REST controllers in Saltus Framework (see HANDOFF.md) @since 2026-07-28

## Next
- Rebuild `vendor-prefixed/` via Strauss after framework REST hint commits land
- Add PUT route support to `MetaController` in vendor-prefixed layer

## Blocked By
- Framework REST controller hint changes must be committed to saltus-framework-git first @reason Strauss prefixes framework code; hints must originate upstream

## Notes
- The legacy Grunt build system in `build/` is being phased out in favor of Composer scripts + PostCSS
- All new tooling should use Composer scripts or Node scripts, not Grunt
- PHP 8.3+ is required — no legacy polyfills needed
