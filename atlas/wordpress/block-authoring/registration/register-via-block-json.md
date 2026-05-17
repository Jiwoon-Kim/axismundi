---
rule_id: block.register-via-block-json
domain: block-authoring
topic: registration
wp_min: "verification-needed"
wp_recommended: "6.8"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/
    section: "Registering a single block with register_block_type()"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/register_block_type/
    section: function reference
related:
  - block.register-collection-php       # WP 6.8+ multi-block recommended
  - block.register-collection-with-args  # WP 6.7+ per-block args
  - block.register-auto-php              # PHP-only autoRegister variant
  - block.register-client-js             # JS-side registerBlockType
  - block.json-schema                    # block.json fields
---

# RULE — Register a single block via `register_block_type()`

## WHEN

- Single-block plugin, OR plugin targeting WordPress < 6.7 (no manifest API).
- Block has a `block.json` metadata file.
- You want server-side registration so the block can use Dynamic Rendering,
  Block Supports, Block Hooks, Style Variations, or be styled via
  `theme.json`. (Client-only registration disables all of these.)

## SHAPE

Function signature:

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `$block_type` | `string` | yes | Directory containing `block.json`, OR full path to a metadata file with non-default name. |
| `$args` | `array` | no | Additional registration args. Common keys: `render_callback`, `attributes`, `supports`. |

**Returns:** `WP_Block_Type` on success, `false` on failure.

Minimal example:

```php
function my_plugin_register_block() {
    register_block_type( __DIR__ . '/build' );
}
add_action( 'init', 'my_plugin_register_block' );
```

With optional args (e.g. `render_callback` overriding block.json's `render`):

```php
register_block_type( __DIR__ . '/build', array(
    'render_callback' => 'my_plugin_render_callback',
) );
```

## REQUIRES

- File `block.json` exists at the resolved `$block_type` path
  (path may be a directory or a full path to the metadata file).
- `block.json` has at minimum: `name` (format: `vendor/slug`) and `title`.
- Call must occur on the `init` action (or later) — not at file load time.
- Path should point to the **build** directory, not `src`, since the build
  step typically copies / processes block.json into `build/`.

## INVARIANTS

- The `name` declared in `block.json` MUST be globally unique across all
  plugins/themes installed on the site.
- Server registration MUST happen on `init`. Earlier (e.g. `plugins_loaded`)
  is too early; later loses block-type availability for early consumers.
- If both `block.json.render` and `$args['render_callback']` are provided,
  `$args` overrides.
- ⚠ **Minimum WP version unknown.** Source docs describe the function
  but do not state when it was introduced. Use feature detection
  (`function_exists('register_block_type')`) when targeting older
  WP versions. Frontmatter `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Registering the block **only** on the client side
  (via JS `registerBlockType()` without a server-side companion). Disables
  Dynamic Rendering, Block Supports, Block Hooks, Style Variations, and
  `theme.json` per-block styling. Source: *"Without server-side
  registration, these functionalities will not operate correctly."*
- ❌ Pointing `$block_type` to the `src/` directory in a build-based plugin.
  block.json typically moves to `build/` during compilation — `src/` may
  not contain the production-ready manifest.
- ❌ For multi-block plugins targeting WP 6.8+: calling `register_block_type()`
  N times in a loop. Use `wp_register_block_types_from_metadata_collection()`
  instead — single call, avoids reading N block.json files from disk.
  See `block.register-collection-php`.
- ❌ Calling outside an action hook (top-level in plugin file). Order is
  not guaranteed and registration may run before WordPress core block APIs
  are available.

## RELATED

- `block.register-collection-php` — WP 6.8+ batch registration via
  `blocks-manifest.php` (preferred for multi-block plugins).
- `block.register-collection-with-args` — WP 6.7+ manifest + per-block
  `$args` control via `wp_register_block_metadata_collection()` +
  individual `register_block_type()` calls.
- `block.register-auto-php` — PHP-only blocks via `supports.autoRegister`
  flag, no JS counterpart needed.
- `block.register-client-js` — JS-side `registerBlockType()` from
  `@wordpress/blocks`. Pairs with this rule for non-PHP-only blocks.
- `block.json-schema` — full `block.json` field reference.
