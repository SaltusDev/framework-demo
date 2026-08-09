# saltus/model-schema

The contract for Saltus Framework model configuration, in three forms generated from one source.

| File | For | Consumed by |
|------|-----|-------------|
| `model.schema.json` | Editors and validators | `$schema` pointers in JSON models, `bin/lint-models.php` |
| `model-enums.php` | PHP without a JSON parser | `bin/lint-models.php`, `StudioPage` boot data |
| `model.d.ts` | TypeScript | Saltus Studio |

All three are **generated** by `bin/generate-model-schema.php`, which reads the framework itself:
field types come from one directory per type under `lib/codestar-framework/fields/`, and the `type`
aliases from the `$type_map` in `ModelFactory::create()`. Nothing here is hand-maintained, so the enums
cannot drift from the code they describe.

```bash
composer schema        # regenerate
composer schema:check  # fail if stale (runs in CI)
```

## Versioning

`x-schema-version` in the JSON Schema, mirrored as `schema_version` in the PHP map. Independent of the
plugin version: consumers pin against the schema, not the demo.

Bump the **minor** for additive changes (a new optional key, a new field type). Bump the **major** when
a previously valid config becomes invalid — that is the signal a consumer needs to look at their models.

## Status: in-repo, ready to extract

This directory carries package metadata but is **not yet published**. It lives inside
`framework-demo` because that is where the generator and its only consumers are today.

Extraction is deliberately a move rather than a rewrite:

- Every artifact is generated, so there is no hand-maintained state to migrate.
- The version is already independent of the plugin.
- Consumers resolve paths through one constant or one `require` each — six call sites in total.

What extraction needs, when someone wants it:

1. `git mv` this directory (plus `bin/generate-model-schema.php`) into its own repository.
2. Publish to Packagist, or add a VCS repository entry.
3. Replace the six path lookups with `vendor/saltus/model-schema/…`.
4. Publish `model.d.ts` to npm — or keep vendoring it, as Omens UI already does with built assets.

Until then the generator writes here and everything reads from here, which is functionally identical
and one commit away from being a dependency.

## Reading the schema

Two things in the model config are counter-intuitive enough to be worth stating twice, and both are
documented on the relevant properties:

- **REST defaults on; MCP defaults off.** `options.show_in_rest` is enabled unless explicitly `false`,
  while `options.mcp_tools` is disabled unless explicitly `true`. A model is therefore never exposed to
  AI clients by accident, but is exposed over REST by default.
- **`active` does not work.** It is present and marked `deprecated`: `BaseModel::is_disabled()` treats
  `false` as "not disabled", so the key enables what it appears to disable. Gate models in PHP instead.
