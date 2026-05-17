---
rule_id: block.json-basic-metadata
domain: block-authoring
topic: block-json
field_cluster: identity
wp_min: "5.7"
wp_recommended: "6.3"
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/
    section: "Block API — identity fields (apiVersion, name, title, category, icon, description, keywords, version, textdomain)"
    captured: 2026-05-09
related:
  - block.register-via-block-json
  - block.json-assets
  - block.json-supports-field
  - block.json-attributes
  - block.json-context
  - block.api-versions
---

# RULE — block.json identity fields (basic metadata)

## WHEN

Defining a block via `block.json` and you need to set the fields that
identify the block to the editor and the user: name, what to call it,
where it appears in the inserter, what icon to show, how it's described,
and which Block API version it follows.

## SHAPE

Identity field cluster — all fields go at the top level of `block.json`:

| Field | Type | Req | Localized | Since | Default | Notes |
|---|---|---|---|---|---|---|
| `apiVersion` | `number` | optional | no | (v1=original; v3 since WP 6.3) | `1` | Use latest (`3`) unless legacy constraint. |
| `name` | `string` | **required** | no | — | — | Format `namespace/block-name`. Lowercase alphanumeric + dashes. At most one `/`. Must begin with a letter. |
| `title` | `string` | **required** | yes | — | — | Display name shown in Inserter. Keep short for UI readability. |
| `category` | `string` | optional | no | — | — | One of: `text`, `media`, `design`, `widgets`, `theme`, `embed`. Or a custom-registered category. |
| `icon` | `string` | optional | no | — | — | Dashicon slug (fallback in non-JS contexts). Can be overridden client-side with SVG source or `{background,foreground}` color object. |
| `description` | `string` | optional | yes | — | — | Short description shown in the block inspector. |
| `keywords` | `string[]` | optional | yes | — | `[]` | Search aliases — help users discover the block via Inserter search. |
| `version` | `string` | optional | no | WP 5.8 | (WP version) | Block's own version, e.g. `"1.0.3"`. May be used for asset cache invalidation. Falls back to installed WP version if omitted. |
| `textdomain` | `string` | optional | no | WP 5.7 | — | gettext text domain. Required for localization to resolve correctly. |

Minimal required-only example:

```json
{
  "apiVersion": 3,
  "name": "my-plugin/example-block",
  "title": "Example Block"
}
```

Typical fully-populated example:

```json
{
  "apiVersion": 3,
  "name": "my-plugin/notice",
  "title": "Notice",
  "category": "text",
  "icon": "info",
  "description": "Highlight a short message.",
  "keywords": ["alert", "warning", "callout"],
  "version": "1.0.0",
  "textdomain": "my-plugin"
}
```

## REQUIRES

- `name` and `title` are the only required fields; everything else has a
  default or is optional.
- `name` MUST follow the `namespace/block-name` format. Validation rules:
  lowercase alphanumeric + dashes, at most one `/`, must begin with a
  letter. Invalid names cause registration failure or silent breakage in
  the editor.
- `textdomain` MUST be present for localized fields (`title`,
  `description`, `keywords`) to resolve via `__()` / equivalents at
  runtime; otherwise translations will not load.

## INVARIANTS

- `name` is **globally unique** across all installed plugins/themes.
  Collisions cause editor registration to overwrite silently — last
  registration wins.
- The `name` is the canonical identifier on the comment delimiter
  (`<!-- wp:my-plugin/book -->`). Core blocks omit the namespace when
  serialized (`<!-- wp:paragraph -->` not `<!-- wp:core/paragraph -->`).
- `apiVersion` defaults to `1` when omitted — this is **not** the latest.
  Older API versions miss editor improvements (e.g., iframed editor,
  block-level wrapper attribute changes). Explicitly set `3` for new
  blocks.
- Unknown `category` values should be tolerated by consumers with a
  fallback (typically to `text`); do not assume custom categories load
  before your block.
- Localized fields must have `textdomain` set OR localization will not
  function for them. (`title`, `description`, `keywords` are localized.)
- `icon` declared in `block.json` is a string only. To use an SVG /
  React element / color object form, override on the JS side — `block.json`
  cannot express those shapes.

## ANTIPATTERNS

- ❌ Omitting `apiVersion`. Defaults to `1`, missing post-WP-6.3 features
  (iframed editor support, default block API version 3 features). Always
  declare explicitly.
- ❌ Using uppercase or special characters in `name`
  (e.g., `MyPlugin/MyBlock`, `my_plugin/my_block`). Validation rejects;
  block silently fails to register.
- ❌ Multiple `/` in `name` (e.g., `my-plugin/sub/block`). Format allows
  one `/` only.
- ❌ Hardcoding the `name` string in PHP / JS / `block.json` separately.
  Drift risk. Read `block.json` programmatically (`json_decode` or
  `import metadata from './block.json'`) and use `metadata.name`.
- ❌ Localized fields (`title`, `description`, `keywords`) without
  `textdomain`. Translations will not resolve.
- ❌ Long `title` values. UI truncates; affects accessibility and
  Inserter scanability.
- ❌ Relying on a custom `category` registering before your block. Race
  condition; provide fallback to a core category if uncertain.

## RELATED

- `block.register-via-block-json` — registers the block server-side
  using this metadata file.
- `block.json-assets` — `editorScript` / `script` / `style` / etc.
  fields that link this block to its JS / CSS / PHP render assets.
- `block.json-supports-field` — `supports` field for capability flags
  (color, typography, spacing, etc.).
- `block.json-attributes` — `attributes` field schema.
- `block.json-context` — `providesContext` / `usesContext` for
  parent→descendant data flow.
- `block.api-versions` — what changed across API versions 1 → 2 → 3,
  and migration considerations.
