---
rule_id: block.json-assets
domain: block-authoring
topic: block-json
field_cluster: assets
wp_min: "5.0"
wp_recommended: "6.5"
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/
    section: "Block API — asset fields (editorScript, script, viewScript, viewScriptModule, editorStyle, style, viewStyle, render) + Assets (WPDefinedPath / WPDefinedAsset / .asset.php sidecar)"
    captured: 2026-05-09
related:
  - block.json-basic-metadata
  - block.json-supports-field
  - block.register-via-block-json
  - block.dynamic-rendering
  - tooling.wp-scripts-build
  - interactivity.api-overview
---

# RULE — block.json asset fields (script / style / render linkage)

## WHEN

Defining a block via `block.json` and you need to associate JS, CSS, or
PHP render assets with the block. Each asset field has a specific
**enqueue context** (editor only / frontend only / both) — choose by
where the code needs to run.

## SHAPE

### Asset field map by context

| Field | Type | Enqueue context | Since | Notes |
|---|---|---|---|---|
| `editorScript` | `WPDefinedAsset \| WPDefinedAsset[]` | editor only | (original) | Array form since WP 6.1. |
| `script` | `WPDefinedAsset \| WPDefinedAsset[]` | editor + frontend | (original) | Array form since WP 6.1. |
| `viewScript` | `WPDefinedAsset \| WPDefinedAsset[]` | frontend only | WP 5.9 | Array form since WP 6.1. |
| `viewScriptModule` | `WPDefinedAsset \| WPDefinedAsset[]` | frontend only (script module) | WP 6.5 | Use for **Interactivity API** assets. NOT compatible with WP scripts at this time. |
| `editorStyle` | `WPDefinedAsset \| WPDefinedAsset[]` | editor only | (original) | Array form since WP 5.9. |
| `style` | `WPDefinedAsset \| WPDefinedAsset[]` | editor + frontend | (original) | Array form since WP 5.9. |
| `viewStyle` | `WPDefinedAsset \| WPDefinedAsset[]` | frontend only | WP 6.5 | Especially useful for interactive blocks. |
| `render` | `WPDefinedPath` | server (PHP render) | WP 6.1 | Path to PHP file generating dynamic frontend markup. |

### Field value forms

Each `WPDefinedAsset`-typed field accepts:

```json
{ "editorScript": "file:./index.js" }
```
```json
{ "editorScript": "registered-handle-name" }
```
```json
{ "style": [ "file:./style.css", "shared-style-handle" ] }
```

### `render` (server-side template)

```json
{ "render": "file:./render.php" }
```

`render.php` receives these variables in scope:

| Variable | Type | Contents |
|---|---|---|
| `$attributes` | `array` | Block attributes (parsed from delimiter). |
| `$content` | `string` | Block default content (inner HTML). |
| `$block` | `WP_Block` | The block instance. |

Minimal `render.php`:

```php
<div <?php echo get_block_wrapper_attributes(); ?>>
  <?php echo esc_html( $attributes['label'] ); ?>
</div>
```

### `.asset.php` sidecar (dependency / version manifest)

Co-located file (typical name: `index.asset.php`) declaring deps/version
for the corresponding JS file:

```
build/
├── block.json
├── index.js
└── index.asset.php
```

WordPress auto-detects the sidecar via path pattern matching when the
JS file is registered. Sidecar returns an array shaped like:

```php
<?php return array(
    'dependencies' => array( 'wp-blocks', 'wp-element' ),
    'version'      => '1234abcd',
);
```

The `@wordpress/scripts` build tool generates this file automatically
from import analysis (preferred path).

## REQUIRES

- `file:` prefix is **required** when using a relative path
  (`WPDefinedPath` form). Plain `"./index.js"` will not resolve.
- File paths are resolved relative to the `block.json` file's location.
- For handle form, the handle MUST be pre-registered via
  `wp_register_script()`, `wp_register_script_module()`, or
  `wp_register_style()` before the block is registered, OR be a
  WordPress core handle (`wp-blocks`, `wp-element`, etc.).
- `viewScriptModule` requires the asset to be a JS module (export-based).
  Pair with `wp_register_script_module()` (not `wp_register_script()`).
- `render` PHP file MUST be safe to include on every page render of
  the block — declarations of functions/classes at top level cause
  fatal "already declared" errors on multi-instance pages.
- When `register_block_type()` is invoked with a path, file-form assets
  are **auto-registered** by WordPress; you do NOT need to call
  `wp_register_script` / `wp_register_style` for those.

## INVARIANTS

- An object form for `WPDefinedAsset` may also be used with explicit
  `handle` / `dependencies` / `version` keys. If `handle` is omitted,
  WordPress auto-generates one. `version: false` (default) means WP
  inserts the installed WP version as a cache-buster; `version: null`
  means no version query string is added.
- `viewScript` and `viewScriptModule` are **mutually exclusive in
  practice**: WP scripts and script modules are not compatible at this
  time. Use `viewScriptModule` only for Interactivity API assets;
  everything else uses `viewScript`.
- The `render` field is only invoked when no `render_callback` is
  passed in `register_block_type()` `$args`. Both define the dynamic
  rendering source; `$args.render_callback` overrides `block.json.render`.
- `style` (combined editor+frontend) is the recommended starting point;
  split into `editorStyle` + `viewStyle` only when context-specific
  styling becomes unavoidable. Common stylesheet stays in `style`.
- If a sidecar `.asset.php` is missing, WordPress falls back to the
  current WP version as the version string and `[]` as dependencies.
- A registered block automatically links its assets to its lifecycle —
  enqueue happens per block instance presence, not globally.

## ANTIPATTERNS

- ❌ Forgetting the `file:` prefix on relative paths. WordPress treats
  the unprefixed string as a registered handle name and silently fails
  to find it.
- ❌ Using `viewScript` for Interactivity API code. The Interactivity
  API runs as a script module — use `viewScriptModule` and pair with
  `wp_register_script_module()`.
- ❌ Putting heavy top-level function/class declarations directly in
  the file referenced by `render`. Multi-instance blocks include the
  file once per instance — duplicate declarations cause fatals. Move
  declarations to a separately-included library file.
- ❌ Hand-writing the `.asset.php` sidecar with manually-computed
  dependencies. `@wordpress/scripts` analyzes imports at build time
  and produces a correct manifest; manual edits drift.
- ❌ Pointing `editorScript` / `editorStyle` to the same file as
  `style` / `script`. The combined-context fields already enqueue in
  the editor; doubling causes duplicate registration.
- ❌ Splitting one stylesheet across `style` + `editorStyle` +
  `viewStyle` from day one. Premature split. Start with `style` and
  fork only when context-specific overrides force it.
- ❌ Using script handles that haven't been registered before
  `register_block_type()` runs. Order matters; register dependencies
  first or rely on auto-registration via `file:` paths.

## RELATED

- `block.json-basic-metadata` — identity fields including `apiVersion`
  that gates which asset fields are recognized.
- `block.json-supports-field` — `supports` flags that may inject
  inline styles independently of these asset fields.
- `block.register-via-block-json` — `register_block_type()` triggers
  auto-registration of all `file:`-form assets declared here.
- `block.dynamic-rendering` — semantics of the `render` PHP file:
  attributes flow, `get_block_wrapper_attributes()`, and rendering
  contract.
- `tooling.wp-scripts-build` — the build tool that produces
  `.asset.php` sidecars and resolves the `file:` paths under `build/`.
- `interactivity.api-overview` — when to use `viewScriptModule`
  instead of `viewScript`.
