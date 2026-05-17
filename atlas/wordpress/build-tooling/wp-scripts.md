---
rule_id: build-tooling.wp-scripts
domain: build-tooling
topic: build-pipeline-substrate
field_cluster: zero-config-orchestration
wp_min: "5.0"
wp_recommended: "6.0+"
package_min: "@wordpress/scripts@^27"
status: stable
language: javascript
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/
    section: "@wordpress/scripts — package reference, scripts, configuration"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/scripts/README.md
    section: "wp-scripts CLI commands + default config behavior"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dependency-extraction-webpack-plugin/
    section: "@wordpress/dependency-extraction-webpack-plugin — externals + asset.php emission"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-babel-preset-default/
    section: "@wordpress/babel-preset-default — transpile target + JSX pragma"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/getting-started/devenv/
    section: "Block development environment — wp-scripts as recommended starting point"
    captured: 2026-05-10
related:
  - block.register-via-block-json                # block.json + asset.php is the consumer of wp-scripts output
  - block.register-client-js                     # editorScript handle resolution depends on asset.php deps
  - block.markup-representation                  # built JS produces blocks parsed by the IR pipeline
  - interactivity.directive-protocol             # @wordpress/interactivity is consumed via the same externals pattern
  - i18n.script-translations                     # build phase generates POT/JSON for runtime translation lookup
---

# RULE — `@wordpress/scripts` — zero-config build pipeline for WordPress block / interactivity / editor code

## WHEN

You are setting up, modifying, or debugging the JavaScript /
CSS build for a WordPress plugin or theme that ships
block-editor code, interactivity directives, custom editor
extensions, or any other JS that consumes `@wordpress/*`
packages. Use this knowledge when:

- Bootstrapping a new block plugin (`@wordpress/create-block`
  scaffolds with `@wordpress/scripts`).
- Customizing the build (extra entries, custom loaders,
  alternate output paths) without abandoning the zero-config
  baseline.
- Reading or writing the generated `*.asset.php` files that
  bridge the build output into PHP-side script
  registration.
- Diagnosing "module not found" / "duplicated React" /
  "unexpected token" errors that almost always trace back
  to externals, transpile targets, or entry resolution.
- Migrating a custom webpack config to `wp-scripts`'
  zero-config baseline.

This chunk does **not** cover:

- Specific block authoring patterns (covered under
  `block-authoring/...` chunks).
- The `@wordpress/create-block` scaffolder (separate
  concern; consumes `wp-scripts` as a dependency).
- WordPress's PHP-side script registration mechanics
  (`wp_register_script`, `wp_enqueue_script`); the
  build's interaction with them is covered briefly in
  Section C and Section D.

## SHAPE

### A. What `@wordpress/scripts` actually is

A single npm package — `@wordpress/scripts` — that wraps
webpack, Babel, PostCSS, Jest, ESLint, and Prettier into a
small CLI with opinionated defaults. The contract:

- Source files live under `src/`. The default entry is
  `src/index.js`.
- Build output goes to `build/`.
- Run `wp-scripts build` for a production bundle, or
  `wp-scripts start` for a watching dev build.
- The defaults assume you are building for the WordPress
  runtime — meaning `@wordpress/*` imports are externalized
  (Section D), and the output ships with metadata that
  WordPress's PHP-side registration can consume directly
  (Section C).

The reason for the package's existence is straightforward:
without it, every block plugin would re-author the same
webpack config, the same Babel preset choices, the same
externals plugin invocation, the same asset-metadata
emitter. `wp-scripts` centralizes all of that into one
versioned dependency.

The CLI surface (the commands you actually run):

| Command                  | Purpose                                            |
| ------------------------ | -------------------------------------------------- |
| `wp-scripts build`       | Production build (minified, no source maps)        |
| `wp-scripts start`       | Watch build (unminified, source maps)              |
| `wp-scripts test-unit-js`| Jest test runner                                   |
| `wp-scripts lint:js`     | ESLint on JS sources                               |
| `wp-scripts lint:css`    | stylelint on CSS sources                           |
| `wp-scripts format`      | Prettier on JS/CSS                                 |
| `wp-scripts plugin-zip`  | Produce a distributable plugin zip                 |
| `wp-scripts packages-update` | Sync `@wordpress/*` dependency versions       |

The defaults are extension points, not handcuffs: you can
extend or override any of them via `webpack.config.js`,
`babel.config.js`, `jest.config.js`, etc. in your project
root. `wp-scripts` will detect and merge them.

### B. The build / runtime split

The most important conceptual property: `wp-scripts`
operates **before** WordPress runs. It produces *artifacts*
— bundled JS files, extracted CSS files, asset metadata —
that WordPress will later register and enqueue at request
time.

This is a literal pipeline → runtime split:

```
authoring time          build time             runtime
─────────────────       ────────────           ─────────────
src/index.js     ──►    webpack pipeline  ──►  build/index.js
                        Babel transpile        build/index.css
                        externals applied      build/index.asset.php
                        deps extracted              │
                                                    ▼
                                              wp_register_script(
                                                'my-block-editor',
                                                plugin_url . 'build/index.js',
                                                $deps_from_asset_php,
                                                $version_from_asset_php
                                              );
```

Two things follow from this split:

- **The build does not know about WordPress's request
  cycle.** It cannot read site options, query posts, check
  capabilities, or react to which page is being rendered.
  Its outputs are static files generated once per build.
- **The runtime does not know about the build's source
  files.** WordPress sees only the produced artifacts. If
  `src/index.js` references a module that `wp-scripts`
  declines to bundle, the runtime will see a broken
  reference unless the build was configured to resolve it.

The split is the reason `*.asset.php` exists at all
(Section C): it is the build's *declaration* of what the
artifact requires from the runtime, written in a form the
runtime can read.

### C. Entry points and the `*.asset.php` artifact

Default behavior: `wp-scripts` looks for `src/index.js` and
emits `build/index.js` plus `build/index.asset.php`. If
your project contains multiple blocks or multiple entries,
two patterns scale up:

- **`block.json` co-location.** If `wp-scripts` finds
  `block.json` files that reference `file:./*.js` paths
  for `editorScript` / `script` / `viewScript`, it builds
  each referenced JS file as a separate entry. This is the
  recommended multi-block pattern.
- **Custom `webpack.config.js`.** Override `entry` (and
  potentially `output`) to declare entries explicitly. The
  zero-config baseline still applies to everything you
  don't override.

The emitted `*.asset.php` looks like this:

```php
<?php return array(
    'dependencies' => array( 'react', 'wp-block-editor', 'wp-i18n', 'wp-element' ),
    'version'      => 'a1b2c3d4e5f6g7h8'
);
```

Two fields, both load-bearing:

- `dependencies` — the list of WordPress script handles
  the artifact requires. Generated from the actual
  `@wordpress/*` imports the source touched, plus React.
  Pass this array as the third argument to
  `wp_register_script()` (or let `register_block_type()`
  consume it automatically through `block.json`).
- `version` — a content-hash of the build artifact.
  Passing this to `wp_register_script()` ensures cache
  busting whenever the artifact actually changes.

The asset file is regenerated on every build. Treat it as
build output (gitignore `build/`); do not hand-edit it.

### D. Externals — the contract that makes `import { __ } from '@wordpress/i18n'` work

The defining mechanism. WordPress ships `@wordpress/*`
JavaScript packages to the browser as global scripts —
`window.wp.i18n`, `window.wp.element`, `window.wp.blockEditor`,
etc. Each is registered as a script handle with names like
`wp-i18n`, `wp-element`, `wp-block-editor`.

`wp-scripts` includes
`@wordpress/dependency-extraction-webpack-plugin`, which
does two coordinated things at build time:

1. **Externalize.** When the source code imports from
   `@wordpress/foo`, webpack is told **not** to bundle the
   package. Instead, the import is rewritten to read from
   `window.wp.foo` at runtime.
2. **Declare.** The plugin records that the artifact
   requires the corresponding script handle (e.g.,
   `wp-foo`) and writes that into `*.asset.php`.

The import you write:

```js
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
```

The runtime behavior:

```js
// Roughly equivalent to:
const { __ } = window.wp.i18n;
const { useState } = window.wp.element;
```

The PHP-side consequence:

```php
// asset.php contains:
'dependencies' => array( 'react', 'wp-i18n', 'wp-element' )
```

Three properties of this design worth holding onto:

- **No duplication.** Every plugin externalizes the same
  packages, so all plugins on a page share the single
  WordPress-provided runtime. There is one React, one
  `@wordpress/element`, one `@wordpress/data` store
  registry. This is the only way the editor scales.
- **Implicit version contract.** The plugin builds against
  some `@wordpress/foo` package version (in
  `node_modules`), but at runtime resolves against
  whatever `window.wp.foo` happens to be on the user's
  WordPress install. Major-version drift between build
  and runtime is the dominant source of "works on my
  site, breaks on theirs" bugs. Use
  `wp-scripts packages-update` to keep build-time and
  runtime aligned.
- **The `peerDependencies` pattern.** A plugin that
  imports `@wordpress/foo` declares that *the runtime
  must provide it*. This is functionally a peer
  dependency, even though it appears in `dependencies`
  in `package.json`.

### E. Relationship with `block.json`

`block.json` is the registration surface; `wp-scripts` is
what builds the JS that `block.json` points at. The two
intersect in three concrete ways:

- **Path conventions.** A `block.json` field like
  `"editorScript": "file:./index.js"` is a hint to
  `wp-scripts` to treat that file as an entry point.
- **`*.asset.php` consumption.** When PHP calls
  `register_block_type()` on a `block.json` file, core
  reads the `editorScript` path, computes the
  corresponding `*.asset.php` path, requires it, and uses
  its `dependencies` and `version` to register the script.
  The bridge is automatic because the path conventions
  match.
- **CSS handle resolution.** `style` and `editorStyle`
  fields in `block.json` are similarly resolved — the
  build emits CSS, the asset.php records dependency
  metadata, the registration pulls them together.

This is why most block plugins do not write
`wp_register_script` calls themselves. The
`register_block_type( __DIR__ . '/build/my-block' )` call
plus the build artifacts is the entire registration story.
The build pipeline and the registration API are deliberately
designed to slot together.

### F. Customization extension points

The zero-config baseline is meant to be enough for the
common case. When it isn't, the extension points (in order
of escalation):

- **`package.json` script overrides.** Pass flags:
  `"build": "wp-scripts build --webpack-src-dir=blocks"`.
  Documented flags include `--webpack-src-dir`,
  `--output-path`, `--webpack-copy-php`, `--blocks-manifest`.
- **`webpack.config.js`.** Export an extended config:

  ```js
  const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
  module.exports = {
      ...defaultConfig,
      entry: {
          editor: './src/editor.js',
          frontend: './src/frontend.js',
      },
  };
  ```

  This is the most common escape hatch. `wp-scripts` will
  merge your config into the defaults rather than replacing
  them entirely.
- **Sibling configs.** `babel.config.js`,
  `postcss.config.js`, `jest.config.js`, `.eslintrc.js`,
  `.stylelintrc.json`, `.prettierrc.json` are detected
  in the project root and override the corresponding
  baseline.

The escalation principle: do as little as you can and let
the baseline handle the rest. Each layer of customization
is a layer of long-term maintenance you take on yourself.

## WHY

### Why a wrapper around webpack at all

The honest answer is amortization. Every block plugin
needs the same set of build features — externals, asset
emission, JSX, CSS extraction, Babel target alignment.
Letting each plugin re-derive that configuration would
mean fragmentation: subtle differences in transpile
targets, extension handling, or externals coverage. A
shared wrapper makes the WordPress JS ecosystem behave
consistently and lets WordPress core upgrade everyone's
build at once by bumping the package version.

### Why externals instead of bundling `@wordpress/*`

Bundling each plugin's own copy of `@wordpress/element`
would mean every page with three editor extensions ships
three Reacts. Beyond the size cost, multiple Reacts on
one page break component identity, hooks rules, and the
editor's own assumptions about a single `wp.data` store
registry. The externalized-packages design is not a
performance optimization; it is a correctness requirement.

### Why a separate `*.asset.php` rather than a JSON sidecar

Three small reasons compound:

- PHP can `require` and use the array immediately, with
  no JSON parser invocation per request.
- The file is trivially `opcache`-able along with the rest
  of the plugin's PHP, making repeat reads essentially
  free.
- It avoids requiring a `file_get_contents` + JSON decode
  on every script registration. At plugin scale that's
  noise; at site scale (dozens of blocks across multiple
  plugins) it isn't.

A JSON sidecar would have worked, but the PHP-array
sidecar is meaningfully cheaper at runtime. The cost — a
file written from a JS build process in PHP syntax — is
paid once at build time.

## WHEN NOT

Skip `wp-scripts` if:

- You are shipping **no JavaScript** (a pure PHP plugin or
  a classic theme without editor extensions). There is
  nothing to build.
- You have **strong, specific webpack requirements** that
  would mean overriding most of the default config
  anyway. At that point, your own webpack setup may be
  cleaner than fighting the wrapper. (This is rare;
  `wp-scripts`'s extension points cover most legitimate
  needs.)
- You are building **non-WordPress JS** that happens to
  live in the same repository. Use a separate, plain
  webpack/Vite/esbuild setup for that — `wp-scripts`'
  externals defaults will silently break code that
  expects `@wordpress/*` packages to be bundled.

## COUNTER-PATTERNS

### Anti-pattern 1 — Hand-editing `*.asset.php`

The file is build output. Hand-edits will be erased on
the next build. If the dependency list is wrong, fix the
imports in source — the build will regenerate the file
correctly.

### Anti-pattern 2 — Bundling `@wordpress/*` packages on purpose

```js
// Wrong — disables externals for @wordpress/element.
module.exports = {
    ...defaultConfig,
    externals: {},
};
```

Disabling externals ships your own React + `@wordpress/element`
into the page alongside WordPress's. The editor will
behave erratically; React will warn about multiple copies;
state from `@wordpress/data` will be invisible to your
components. Externals are not optional.

### Anti-pattern 3 — Drifting from the runtime's `@wordpress/*` versions

```json
// package.json
"dependencies": {
    "@wordpress/element": "^4.0.0"   // ancient
}
```

If `node_modules` has an old version but the WordPress
install ships a new one, the build's type assumptions
won't match runtime behavior. Use
`wp-scripts packages-update` regularly, or pin to
versions matching the WordPress version your plugin
declares as `Requires at least`.

### Anti-pattern 4 — Treating `wp-scripts start` output as production-ready

`start` produces unminified, source-mapped builds for
development. Shipping `build/` after `start` (instead of
after `build`) gives end users a much larger, slower
asset. Always run `wp-scripts build` for releases.

### Anti-pattern 5 — Using `wp_enqueue_script()` in a way that bypasses `*.asset.php`

```php
// Misses the asset.php-derived dependency list.
wp_enqueue_script(
    'my-block-editor',
    plugin_dir_url( __FILE__ ) . 'build/index.js',
    array(),  // empty deps — wrong
    '1.0',    // hand-versioned — fragile
);
```

Read the asset.php and use its values:

```php
$asset = require __DIR__ . '/build/index.asset.php';
wp_enqueue_script(
    'my-block-editor',
    plugin_dir_url( __FILE__ ) . 'build/index.js',
    $asset['dependencies'],
    $asset['version'],
);
```

Or, more commonly, use `register_block_type( __DIR__ . '/build/my-block' )`
and let core do the asset.php read for you.

## OPERATIONAL NOTES

The build pipeline's interpretive shape, in proportional
v2 vocabulary:

- This is the canonical home of **Law 6 (Compiler ↔
  Runtime Split)** — the build operates entirely in a
  Node.js / webpack context that has no access to the
  WordPress runtime, and produces artifacts that the
  WordPress runtime later consumes without any access
  back into the build context. The split is literal: two
  environments, one bridge artifact (`*.asset.php`).
  Naming Law 6 here is genuinely clarifying because the
  function of the entire pipeline is to make this split
  *workable*.
- **Law 1 (Declaration ≠ Exposure)** appears at the
  asset-handle level: source `import` declares a runtime
  need; `*.asset.php` records that need; the registration
  call exposes the script to WordPress; the enqueue call
  exposes it to a specific request. Source imports are
  *candidates*; runtime enqueue is *commitment*. Worth a
  brief mention; not the central frame.
- The externals contract has a **federation** quality —
  every plugin agrees not to bundle `@wordpress/*`
  packages, and the runtime provides a single shared
  copy of each. This is recognizable as the same
  federation shape that appears in plugin-dev (multiple
  plugins federating around a shared registration
  surface), just expressed at the JavaScript runtime
  level rather than at PHP registration. Worth naming
  *as a federation expression*; not a new pattern.

What this chunk is **not** about:

- **Section X archetypes.** A build pipeline is not a
  "civilization." Naming it that way would inflate
  ordinary toolchain mechanics into ontological language
  that does not aid understanding. The Computational-heavy
  archetype is a useful frame for whole bounded contexts
  with sustained pipeline character; it is not a frame
  for single tools.
- **Law 4 (Arbitration Compiler).** Webpack does perform
  module-resolution arbitration, but that is internal
  webpack mechanics and not the user-facing surface this
  chunk documents. Naming Law 4 here would describe
  webpack, not `wp-scripts`. Omitted on purpose.
- **Doctrine 6 (Authority Mediation).** No access
  mediation surface. The build runs in CI / on a
  developer machine; capabilities and roles are not in
  scope.
- **Law 3b (Cross-Runtime Authority Continuity Bridge).**
  Despite the cross-context nature of the build/runtime
  split, this is *not* an authority bridge. Authority
  isn't being transferred across the boundary; an
  *artifact* is. 3b governs cases where a producing
  context's authority needs to be preserved in a
  consuming context's runtime decisions; the asset.php
  doesn't encode authority, just dependency metadata.
  Adjacent shape, different mechanism.

The framework-omission discipline from Phase 8.27 applies
straightforwardly here: name what fits, briefly note what
nearly-but-doesn't, omit the rest.

## CHECKLIST

When using `wp-scripts` in new code:

- [ ] Start from `@wordpress/create-block` if scaffolding
      a new plugin; it produces a working `wp-scripts`
      setup with no further configuration.
- [ ] Keep `@wordpress/*` dependency versions roughly
      aligned with the WordPress version you target.
      Run `wp-scripts packages-update` periodically.
- [ ] Always pass `*.asset.php`'s `dependencies` and
      `version` through to `wp_register_script` /
      `wp_enqueue_script`, or use `register_block_type`
      and let core do it.
- [ ] Don't disable externals. If a single
      `@wordpress/*` package needs special treatment,
      filter the externals list rather than removing it
      wholesale.
- [ ] Run `wp-scripts build` (not `start`) before
      shipping.
- [ ] Gitignore `build/`. Treat it as derived output.
- [ ] If you need a non-default entry layout, extend
      `webpack.config.js` rather than abandoning
      `wp-scripts`. Use the spread-and-override pattern.

## REFERENCES

- `@wordpress/scripts` package reference. Documents all
  CLI commands, default config behavior, and supported
  flags.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/
- `@wordpress/scripts` README on GitHub. Most up-to-date
  source for default webpack/Babel/Jest configuration
  shape.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/scripts/README.md
- `@wordpress/dependency-extraction-webpack-plugin`.
  Documents the externals + asset.php emission mechanism
  in detail (Section D).
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dependency-extraction-webpack-plugin/
- `@wordpress/babel-preset-default`. Documents transpile
  targets and JSX pragma resolution for the default
  build.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-babel-preset-default/
- Block editor handbook — development environment.
  Recommends `wp-scripts` as the default starting point.
  https://developer.wordpress.org/block-editor/getting-started/devenv/

Cross-context:

- `block.register-via-block-json` — describes how
  `register_block_type()` consumes the build artifacts
  via `block.json` paths and `*.asset.php` metadata.
- `block.register-client-js` — describes the JS-side
  registration pathway whose dependencies originate
  from the same build process.
- `i18n.script-translations` — describes the JSON
  translation files generated as part of the build, and
  the runtime `wp_set_script_translations` call that
  consumes them.
