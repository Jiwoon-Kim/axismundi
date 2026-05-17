---
rule_id: build-tooling.block-json-build-pipeline
domain: build-tooling
topic: metadata-continuity
field_cluster: declarative-schema-substrate
wp_min: "5.8"
wp_recommended: "6.7+"
package_min: "@wordpress/scripts@^27"
status: stable
language: javascript
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/
    section: "Block metadata (block.json) — schema reference, file:./ resolution, $schema URL"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/scripts/README.md
    section: "wp-scripts — multi-block build behavior, --webpack-src-dir, --blocks-manifest, build-blocks-manifest command"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2024/08/02/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
    section: "WP 6.7 — wp_register_block_metadata_collection() + WP_Block_Metadata_Registry"
    captured: 2026-05-10
  - url: https://schemas.wp.org/trunk/block.json
    section: "block.json JSON Schema — IDE validation surface"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/cli/commands/i18n/make-json/
    section: "WP-CLI i18n make-json — block.json title/description translation extraction adjacency"
    captured: 2026-05-10
related:
  - build-tooling.wp-scripts                            # the orchestrator that drives multi-block builds
  - block.register-via-block-json                       # runtime registration consumer (companion chunk)
  - block.basic-metadata                                # block.json field-level documentation (runtime perspective)
  - block.assets                                        # editorScript/style/render path conventions resolved at registration
  - i18n.script-translations                            # JSON translation files generated alongside block.json
---

# RULE — `block.json` build pipeline — metadata declaration persisting through build into runtime registration

## WHEN

You are responsible for how `block.json` files travel from
your source tree to the artifacts a WordPress site
actually loads. Use this knowledge when:

- Setting up a multi-block plugin where each block has its
  own `block.json` and source folder.
- Choosing between per-block registration calls and the
  WP 6.7+ block metadata collection / manifest pattern.
- Diagnosing "block.json not found" or "asset.php
  missing" errors that point at the *build's* handling of
  metadata files, not the runtime's.
- Configuring `wp-scripts` flags (`--webpack-src-dir`,
  `--blocks-manifest`) for a non-default block layout.
- Authoring tooling around `block.json` (validators,
  generators, IDE integrations) that needs to understand
  the file's lifecycle.

This chunk does **not** cover:

- The runtime semantics of `register_block_type()` or
  `register_block_type_from_metadata()` — what those
  functions do once they read a block.json. Those are
  documented in `block.register-via-block-json`.
- The full schema of every `block.json` field — covered
  field-by-field in `block.basic-metadata`,
  `block.assets`, `block.json-supports-field`, and the
  rest of the block-authoring chunks.
- Translation lookup at runtime (covered in
  `i18n.script-translations`); this chunk only mentions
  the build-time *generation* of translation artifacts
  alongside block.json.

The principle this chunk operates under:
**`block.json` is the declarative substrate that survives
build transformation to be re-interpreted at runtime.**
The same file (modulo path rewriting) lives in both
phases, but its *meaning* differs at each phase.

## SHAPE

### A. The file in three phases

```
authoring time          build time              runtime
─────────────────       ──────────────          ──────────────────
src/myblock/            build/myblock/          register_block_type(
  block.json    ──────►   block.json    ──────►   __DIR__ . '/build/myblock'
  index.js              index.js                )
  edit.js               index.css                       │
  style.css             index.asset.php                 ▼
                        render.php             register_block_type_from_metadata()
                                                        │
                                                        ▼
                                               WP_Block_Type instance
```

**At authoring time** `block.json` is a human-edited
declaration: this block exists, here is its name and
description, here is which JS files are its source
entries, here are its supported features.

**At build time** `block.json` is two things at once:

- An **input to the build itself** — the `wp-scripts`
  webpack config reads each `block.json` to discover
  entry points (whatever `editorScript` / `script` /
  `viewScript` resolve to via `file:` paths).
- An **output the build copies** — after webpack runs,
  `block.json` is copied to `build/` next to the bundles
  it points at. The contents of the file in `build/` are
  largely identical to the source (paths still reference
  `file:./index.js` because the build maintains
  co-location).

**At runtime** `block.json` is the registration source —
a `register_block_type()` call points at the directory,
core reads the file, resolves `file:./` paths against the
file's own directory, and constructs a `WP_Block_Type`
instance.

The same file persists through all three phases. What
varies is *who reads it* and *what they do with what
they read*. The schema does not change between phases.
The meaning at each phase does.

### B. Build-time processing — what happens to `block.json` between `src/` and `build/`

`wp-scripts`'s default webpack config does three things
relevant to `block.json`:

- **Discovery.** It walks `src/` looking for `block.json`
  files and treats each one's `editorScript` /
  `script` / `viewScript` `file:` references as webpack
  entries. A four-block plugin produces (roughly) four
  to twelve webpack entries automatically with no
  per-block configuration.
- **Copying.** It uses `CopyWebpackPlugin` to copy
  `block.json`, `render.php` (when present), and other
  non-JS/non-CSS files from `src/` to `build/`,
  preserving the per-block directory structure.
- **Asset emission.** For each entry, it emits the JS
  bundle, the extracted CSS, and the `*.asset.php`
  sidecar (the second covered in detail in
  `build-tooling.wp-scripts`).

Two configuration flags reshape this default:

- `--webpack-src-dir=blocks` (or any other path) tells
  `wp-scripts` to look for `block.json` files under
  `blocks/` instead of `src/`. Useful when `src/` is
  reserved for non-block code.
- `--webpack-copy-php` (default true for `wp-scripts`
  v25+) controls whether `*.php` files are copied
  alongside `block.json`. Disable it only if you have a
  separate PHP build step that handles `render.php`.

Path rewriting is **not** done. The build copies
`block.json` essentially verbatim. `editorScript:
"file:./index.js"` in `src/myblock/block.json` becomes
`editorScript: "file:./index.js"` in
`build/myblock/block.json`. The path is relative to the
file's own directory; since the bundle ends up next to
it in `build/`, the reference still resolves correctly
at runtime.

This is the central design decision: `block.json` doesn't
need to be rewritten because it was authored to be
location-independent. The `file:` scheme means "relative
to me" — a property that survives the move from `src/` to
`build/` because the file's neighbors (the JS bundle,
the CSS, the asset.php, the render.php) move with it.

### C. The blocks-manifest pathway (WP 6.7+)

For plugins shipping many blocks, per-block `block.json`
parsing at runtime adds up. WP 6.7 introduced
`WP_Block_Metadata_Registry` and the
`wp_register_block_metadata_collection()` function as a
performance optimization that lets a plugin register a
*manifest* covering many blocks at once.

The build-side counterpart is the `wp-scripts
build-blocks-manifest` command. The flow:

```
1. wp-scripts build                    # produces build/<each block>/block.json
2. wp-scripts build-blocks-manifest    # walks build/, generates blocks-manifest.php
3. Plugin calls (in PHP):
     wp_register_block_metadata_collection(
         __DIR__ . '/build',
         __DIR__ . '/build/blocks-manifest.php'
     );
   then
     register_block_type( __DIR__ . '/build/myblock' );
```

After the collection is registered, `register_block_type`
calls within that path resolve their metadata from the
manifest array rather than re-reading each `block.json`
file. The performance gain matters at site scale (many
plugins × many blocks each); for a single-block plugin
the optimization is invisible.

The manifest is build output. Like `*.asset.php`, treat
it as derived: gitignore, regenerate, do not hand-edit.

The manifest does not replace `block.json`. The per-block
`block.json` files still ship in `build/`; they remain
the canonical source of truth. The manifest is a flat
aggregation that runtime registration can consume
faster — the same data, denormalized for read
performance.

### D. Multi-phase declaration continuity

The recurring property across Sections A–C is that the
same declaration travels intact through the pipeline.
Three properties together make this possible:

- **`file:`-relative paths.** Asset references inside
  `block.json` are relative to the file itself, so the
  reference survives any move that preserves the file's
  immediate neighborhood.
- **No transformation needed.** The build does not need
  to edit `block.json` to make it work in `build/`. The
  copy is a copy, not a translation.
- **Schema stability between authoring and runtime.**
  WordPress's `block.json` schema is not "input format
  vs output format." There is one schema, used at both
  ends. IDEs can validate against
  `https://schemas.wp.org/trunk/block.json` at authoring
  time, knowing that what they validate is exactly what
  the runtime will read.

This is what makes "metadata continuity" a useful
phrase here: the metadata is the same metadata, just
re-interpreted at each phase by a different reader.

It is worth distinguishing this from *runtime
continuity*. Runtime continuity (the kind Doctrine 5
addresses head-on, and the kind Law 3b's bridge
sub-pattern is about) is about authority or state
persisting across runtime contexts during a request.
Metadata continuity is about a *file* persisting across
operational phases during a build-and-deploy cycle.
Adjacent ideas; different mechanisms. The chunk's
section on omitted vocabulary expands on why these
should not be conflated.

### E. Translation extraction adjacency

`block.json` carries human-readable strings —
`title`, `description`, `keywords[]`. These are
translatable. The build pipeline interacts with them
through the WP-CLI i18n command family (not part of
`wp-scripts` directly, but commonly run alongside):

```
wp i18n make-pot . languages/myplugin.pot       # extract from PHP + JS + block.json
wp i18n make-json languages/myplugin-ja_JP.po   # produce per-script JSON files
```

`make-pot` knows to read `block.json` files and pull
translatable strings out as if they were `__()` calls.
`make-json` emits the per-handle JSON files that
`wp_set_script_translations()` consumes at runtime.

This is build-side metadata extraction that *adjacent
to* the `block.json` pipeline rather than part of it.
The chunk mentions it because the source-side schema
choices in `block.json` (whether to mark a string as
translatable by placing it in a `title`-shaped field)
have build-side and runtime-side consequences both.
Detail on the runtime side lives in
`i18n.script-translations`.

## WHY

### Why one schema for both phases

The alternative — a richer authoring schema that gets
"compiled down" to a leaner runtime schema — was
considered and rejected by the platform's design. Two
reasons it makes sense not to bifurcate:

- The runtime schema would still need to be readable by
  third-party tooling (IDEs, validators, registries),
  which means it would itself become a public schema —
  meaning two public schemas, one for authors and one
  for runtime, with mapping logic to maintain. That is
  an ongoing tax.
- The current `block.json` is small and structurally
  flat. Compilation would buy little. The opportunity
  cost of a build-time transformation step is real (it
  must work consistently across `wp-scripts`, `vite`,
  custom toolchains, etc.); the gain from running it
  would be marginal.

The one-schema design ties the platform's hands in some
ways (the schema cannot include developer-only fields
that get stripped at build time) but keeps the entire
metadata story legible to anyone reading either a source
tree or a deployed plugin.

### Why `file:` paths instead of build-resolved absolute paths

Authoring `editorScript: "file:./index.js"` is awkward
compared to authoring `editorScript: "./index.js"`. The
explicit `file:` scheme exists because `block.json` is
also consumed by tools that don't run a build step (the
WordPress Plugin Repository's metadata extractor, schema
validators, custom inspectors). The scheme prefix makes
it unambiguous that the value is a path-to-be-resolved,
not a script handle, not a URL, not a function name.

It also makes the *runtime resolution rule* explicit:
when core sees a value beginning with `file:`, it knows
to interpret the rest as relative to the directory of
the `block.json` it found the value in. There is no
ambiguity about whose directory it's relative to.

### Why a separate manifest at all (WP 6.7+)

The honest answer is per-request file IO. On a site with
twenty plugins, each shipping eight blocks, every page
load that involves block registration touches roughly
160 `block.json` files. Even cheap operations
(`file_get_contents` + JSON decode + array assembly)
add up at that scale. A single manifest file with the
same data, parsed once and held in
`WP_Block_Metadata_Registry`, removes the per-block
filesystem hit.

This is performance engineering at the substrate level.
It does not change the *meaning* of `block.json` or
introduce a new declaration source — the manifest is a
read-optimized aggregation of files that still exist
on disk in their original form.

## WHEN NOT

Skip the build pipeline considerations in this chunk if:

- Your plugin has **no `block.json`** because it isn't
  shipping blocks. Use whatever build setup your code
  needs; this chunk doesn't apply.
- You are using a **non-`wp-scripts` build** that
  doesn't consume `block.json` discovery. The runtime
  side of `register_block_type_from_metadata()` still
  works; you just lose the build-time auto-discovery and
  must manage entries explicitly.
- You are operating at the **single-block scale** and
  don't yet need the manifest optimization. WP 6.7's
  collection API is opt-in; per-block registration
  remains supported and will continue to work.

## COUNTER-PATTERNS

### Anti-pattern 1 — Hand-editing copied `block.json` in `build/`

Edits to `build/myblock/block.json` are clobbered on the
next `wp-scripts build`. If a field needs to differ from
the source, edit the source. If you find yourself
wanting to differ between dev and prod, encode that with
a build-time process that emits a *generated* source
file rather than editing build output.

### Anti-pattern 2 — Treating `blocks-manifest.php` as authoritative

```php
// Wrong — manipulating manifest values directly.
$manifest = require 'build/blocks-manifest.php';
$manifest['my/block']['title'] = 'Override';
wp_register_block_metadata_collection( …, …, $manifest );
```

The manifest is build output, regenerated each build.
Mutate the source `block.json` if you need a different
value. The runtime API consumes the manifest as
read-only metadata.

### Anti-pattern 3 — Mixing `file:` and bare paths

```json
{
  "editorScript": "file:./index.js",
  "viewScript":   "./view.js"
}
```

The bare `./view.js` will be interpreted as a script
*handle* by core, not a path. The build will not have
emitted a corresponding asset.php. Always use the
`file:` prefix when you mean a path; reserve bare values
for already-registered handles (`wp-blocks`, etc.).

### Anti-pattern 4 — Putting the manifest in version control

`blocks-manifest.php` is build output. Gitignore it.
Treating it as committed configuration creates merge
conflicts on every build and obscures the source of
truth (the per-block `block.json` files).

### Anti-pattern 5 — Calling `wp_register_block_metadata_collection()` after `register_block_type()`

The collection registration must come *before* the
per-block registrations that should resolve through it,
or core will fall back to per-file reads for the calls
that ran first. Order matters: register the collection
on a hook that fires before your block registration
hook, or register both during `init` with the
collection call first.

## OPERATIONAL NOTES

The pipeline's interpretive shape, in proportional
v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the central
  fit. The `block.json` file *declares* what a block is;
  no exposure happens until a registration call is made
  with that file as input. The file's existence in
  `build/` is a candidate for registration, not a
  registration. Each phase (authoring → build → runtime)
  re-encounters the same declaration as a candidate that
  must be acted on (validated, copied, or registered)
  to take effect. Naming Law 1 here is genuinely
  clarifying because the *persistence* of declaration
  through transformation is exactly what makes the
  pipeline coherent.
- **Law 6 (Compiler ↔ Runtime Split)** is the substrate
  this chunk sits on top of. The build phase and the
  runtime phase are distinct contexts; `block.json` is
  the artifact that survives the boundary. Worth a brief
  reference because it situates this chunk against
  `build-tooling.wp-scripts`; not the chunk's central
  frame.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly* — there is a continuity of *declarative
  authority* across phases. The author's intent, encoded
  in `block.json` at source time, governs what the
  runtime block actually is. This is continuity-of-
  meaning rather than continuity-of-runtime-state.
  Worth one mention; not a section.
- **Federation** appears lightly in the manifest
  pathway: many plugins each shipping their own blocks
  and manifests federate around the single
  `WP_Block_Metadata_Registry` runtime structure.
  Recognizable as the same federation shape from
  plugin-dev and from `wp-scripts`'s externals
  contract; not new.

What this chunk is **not** about:

- **Law 3b (Cross-Runtime Authority Continuity Bridge).**
  This is the most important non-fit to name. The build
  → runtime journey of `block.json` *looks* bridge-like:
  a file moves from one context to another, and the
  destination context interprets it. But Law 3b governs
  cases where *runtime authority* (state, identity,
  capability) is preserved across a runtime boundary
  during request processing — server-render to client
  hydration, and similar. The `block.json` pipeline
  involves no runtime state preservation; the file is
  static, the runtime reads it on registration, no
  authority is being carried across an active execution
  boundary. Same shape (cross-context), different
  mechanism (file copy vs state hydration). This
  distinction matters because conflating the two would
  dilute Law 3b's meaning where it actually applies
  (interactivity hydration, runtime-state).
- **Law 4 (Arbitration Compiler).** No candidate
  selection. There is one `block.json` per block
  directory; no choosing among alternatives. Omitted.
- **Doctrine 6 (Authority Mediation).** No access
  mediation. The build runs locally; capabilities and
  roles are not in scope.
- **Section X archetypes.** A metadata pipeline is not
  a "civilization." The Computational-heavy archetype
  is a frame for whole bounded contexts with sustained
  pipeline character; it is not a frame for individual
  metadata files. Omitted.

A small literacy contribution worth pinning, on the
order of the site-building cluster's "tree of authority
vs tree of existence":

> *Metadata continuity ≠ runtime continuity.* A
> declaration that survives transformation across
> operational phases (build → deploy → register) is not
> the same shape as runtime state surviving across
> execution contexts (server render → client hydrate).
> Both are continuity, but the substrates and the
> mechanisms differ.

Useful for future chunks that need to position similar
"the same artifact appears in two places" mechanisms
without reaching for Law 3b.

## CHECKLIST

When working with the `block.json` build pipeline:

- [ ] Place each block's `block.json` in its own
      directory under `src/` (or your custom
      `--webpack-src-dir`). Co-locate JS, CSS, and
      `render.php` in that same directory.
- [ ] Use `file:./...` paths in `block.json` for
      asset references. Bare values are interpreted
      as already-registered script handles.
- [ ] Let `wp-scripts` discover entries from
      `block.json`; avoid hand-rolling the entry list
      unless your layout genuinely requires it.
- [ ] Treat everything in `build/` as derived output:
      `block.json` copies, asset.php files,
      `blocks-manifest.php`. Gitignore the directory.
- [ ] If your plugin ships more than a handful of
      blocks and you target WP 6.7+, run
      `wp-scripts build-blocks-manifest` after build
      and use `wp_register_block_metadata_collection()`
      before per-block registrations. Order matters.
- [ ] If you author tooling that reads `block.json`,
      validate against the public schema at
      `https://schemas.wp.org/trunk/block.json`.

## REFERENCES

- Block metadata reference. Documents every recognized
  `block.json` field, the `file:` resolution rule, and
  the `$schema` URL.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/
- `@wordpress/scripts` README — multi-block discovery,
  `--webpack-src-dir`, `--blocks-manifest`, the
  `build-blocks-manifest` command.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/scripts/README.md
- WP 6.7 announcement — `wp_register_block_metadata_collection()`
  and `WP_Block_Metadata_Registry` performance rationale.
  https://make.wordpress.org/core/2024/08/02/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
- `block.json` JSON Schema for IDE / validator
  consumption.
  https://schemas.wp.org/trunk/block.json
- WP-CLI `i18n make-json` — the build-adjacent step
  that turns `.po` translations into per-script JSON
  files referenced by runtime `wp_set_script_translations`.
  https://developer.wordpress.org/cli/commands/i18n/make-json/

Cross-context:

- `build-tooling.wp-scripts` — the orchestrator chunk
  that drives multi-block builds and produces the
  `*.asset.php` sidecars referenced here.
- `block.register-via-block-json` — the runtime
  registration consumer. Documents what
  `register_block_type_from_metadata()` does once it
  reads a `block.json`.
- `block.basic-metadata` and the rest of the
  `block-authoring/block-json/...` family — field-by-field
  schema documentation from the runtime perspective.
- `i18n.script-translations` — the runtime side of the
  translation extraction adjacency mentioned in
  Section E.
