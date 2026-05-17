---
rule_id: block.register-auto-php
domain: block-authoring
topic: registration
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/
    section: "PHP-only blocks with auto-registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#autoregister
    section: "autoRegister support"
related:
  - block.register-via-block-json          # standard PHP+JS dual registration
  - block.register-client-js               # JS counterpart this rule replaces
  - block.dynamic-rendering                # render_callback mechanics
  - block.supports-reference
---

# RULE — Register a PHP-only block with `supports.autoRegister`

## WHEN

- Block needs server-side rendering only (no client-side JS edit
  experience customization).
- You want the block to appear in the editor without writing any JS
  registration code.
- Block content is generated from PHP at request time via
  `render_callback`.

This is a shortcut for purely server-side blocks. If the block needs
custom JS-side `edit` behavior, use `block.register-via-block-json` +
`block.register-client-js` instead.

## SHAPE

```php
register_block_type( 'my-plugin/server-block', array(
    'render_callback' => function( $attributes ) {
        $wrapper_attributes = get_block_wrapper_attributes();
        return sprintf(
            '<div %1$s>Server content</div>',
            $wrapper_attributes
        );
    },
    'supports' => array(
        'autoRegister' => true,
        'color' => array( 'background' => true ),
    ),
) );
```

Note the function-call form: first parameter is the **block name string**
(`vendor/slug`), not a path. This differs from the `block.json`-driven
form where the first parameter is a directory.

## REQUIRES

- `register_block_type()` called on `init` hook.
- First parameter is a fully-qualified block name (`vendor/slug`).
- `$args` includes:
  - `render_callback` — the function (closure or named) producing the
    block's frontend HTML.
  - `supports.autoRegister: true` — the flag enabling editor-side
    auto-availability without a JS registration counterpart.
- Block can use `get_block_wrapper_attributes()` inside the
  `render_callback` to receive editor-set wrapper attributes (block
  classes, inline style from supports, etc.).

## INVARIANTS

- The block name in the first parameter is the canonical identifier.
  No `block.json` is read; the registration is fully programmatic.
- Without `supports.autoRegister: true`, the editor will not see this
  block — it would require a paired JS `registerBlockType()` call.
- Auto-registered blocks default to **block API version 3**; older
  declared versions are automatically upgraded. (Source: block-supports
  reference, autoRegister section.)
- The editor renders these blocks via `ServerSideRender` (uses the
  `render_callback` round-trip on every editor preview).
- The `render_callback` runs on every front-end render of the block;
  keep it cheap or implement caching.
- Block supports declared in `$args['supports']` (e.g. `color`)
  produce editor controls automatically; the editor injects matching
  attributes that arrive in the `$attributes` parameter.
- ⚠ **Minimum WP version unknown.** Source docs confirm the flag exists
  but do not state the introduction version. Until verified, feature-detect
  (`property_exists` / explicit support array key check) before relying on
  this in versioned-target plugins. Frontmatter `wp_min` is set to
  `"verification-needed"` for this reason.

## ANTIPATTERNS

- ❌ Using `autoRegister` for a block that needs custom JS `edit`
  component (e.g., custom inspector controls beyond what supports
  provide). The flag bypasses JS registration entirely; you'll have no
  hook to inject custom client behavior.
- ❌ Combining `autoRegister: true` with a JS `registerBlockType()` call
  for the same block name. Causes double-registration warnings.
- ❌ Putting heavy logic directly in the `render_callback` without
  caching for high-traffic blocks. Runs on every page load.
- ❌ Using a path string as first parameter when also passing
  `autoRegister` — the path form expects `block.json` and ignores the
  programmatic name; `autoRegister` belongs to the name-string form.

## RELATED

- `block.register-via-block-json` — when block has a `block.json` and
  needs both server + client registration.
- `block.register-client-js` — the JS-side registration this rule
  obviates.
- `block.dynamic-rendering` — `render_callback` semantics, attribute
  flow, and `get_block_wrapper_attributes()` usage.
- `block.supports-reference` — full list of supported `supports.*` flags
  and their editor effects.
