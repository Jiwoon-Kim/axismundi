---
rule_id: theme-config.json-patterns
domain: theme-config
topic: top-level
field_cluster: registration
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#patterns
    section: "theme.json — patterns (top-level field)"
    captured: 2026-05-09
related:
  - theme-config.json-templateParts        # adjacent top-level filesystem-coupled metadata registry
  - theme-config.json-customTemplates      # adjacent top-level filesystem-coupled metadata registry
  - block.transforms                       # adjacent semantic composition concern (block conversion)
  - block.variations                       # adjacent identity projection concern (single-block presets)
  - block.inner-blocks                     # patterns are typically multi-block compositions inserted via InnerBlocks
  - plugin-dev.register-block-pattern      # cross-context: PHP API for theme-bundled / plugin-bundled patterns (NOT in theme.json)
---

# RULE — `patterns` (theme.json top-level) — pattern slug registration

## WHEN

Configuring a theme's `theme.json` to **register patterns from the
WordPress.org Pattern Directory** for use in your theme. This is the
narrowest of the 3 top-level theme.json fields — a single-line array
of slug strings.

This is also the **first KB chunk in a NEW ontology pattern**:
**filesystem/directory-coupled metadata registries**. Unlike
settings/styles which contain self-contained value declarations,
top-level theme.json fields (customTemplates / templateParts /
patterns) contain **metadata or pointers to externally-defined
content** — `/templates/` filesystem files for customTemplates,
`/parts/` files for templateParts, the remote WordPress.org Pattern
Directory for patterns.

**Important scoping**: this chunk covers ONLY what theme.json's
`patterns` field does. The broader pattern system (PHP
`register_block_pattern`, `/patterns/` folder convention, Pattern
Directory API, pattern composition semantics) lives in OTHER
layers and requires separate chunks.

## SHAPE

### Single field — array of slug strings

```json
{
  "patterns": [ "slug-one", "another-slug", "wordpress-org-directory-slug" ]
}
```

| Property | Type | Description |
|---|---|---|
| (the field itself) | `[ string ]` | An array of pattern slugs to be registered from the Pattern Directory. |

That's the entire documented schema. Source verbatim:
*"An array of pattern slugs to be registered from the Pattern
Directory."*

### What's NOT here

- No pattern content (block markup) — that's in the Directory or in
  `/patterns/` PHP files.
- No pattern title / description / categories — those are defined
  on the Directory side or in PHP `register_block_pattern()`.
- No conditional registration — array is unconditional.
- No preview / icon / keywords / blockTypes — same as above.

theme.json's `patterns` field is **purely a slug registration list**.
All semantic / display / categorization metadata lives elsewhere.

## REQUIRES

- Field MUST be a top-level entry in theme.json (sibling of
  `settings`, `styles`, `customTemplates`, `templateParts`).
- Each slug in the array MUST match an existing pattern slug in
  the WordPress.org Pattern Directory (or theme behavior is
  undefined for unknown slugs — likely silent skip).
- For theme-bundled patterns (defined in `/patterns/` folder of
  the theme), DIFFERENT mechanism applies — they're auto-
  registered by core's pattern loader, NOT via this field.
  This field is for REMOTE Directory patterns specifically.
- Pattern Directory access requires the user's site to have
  internet connectivity and Directory access (which is the
  default for WordPress installations).

## INVARIANTS

- **Field semantics: registration list, not definition.** This
  field tells WordPress "register these patterns from the
  Directory so they're available in this theme's editor". It
  does NOT define the patterns — definitions live remotely.
- **Filesystem-coupled / remote-coupled metadata pattern.** Along
  with customTemplates (filesystem) and templateParts
  (filesystem), patterns shares the structural pattern that
  theme.json fields can be **pointers / metadata extensions over
  externally-defined content**, not just self-contained
  declarations:

  | Top-level field | theme.json contains | External content lives |
  |---|---|---|
  | `customTemplates` | name, title, postTypes metadata | `/templates/{name}.html` |
  | `templateParts` | name, title, area metadata | `/parts/{name}.html` |
  | `patterns` | array of slug strings only | WordPress.org Pattern Directory (remote) |

  This is **fundamentally different** from settings/styles which
  contain the actual values being declared. theme.json's
  authority extends BEYOND its own document into filesystem and
  remote registries.
- **patterns is the most minimal of the 3 top-level fields.**
  customTemplates has 3 metadata properties per entry;
  templateParts has 3; patterns has just slug strings (no
  per-entry metadata at all).
- **The broader pattern ontology spans multiple layers — NOT all
  in theme.json.** Pattern as a CONCEPT in WordPress includes:
  - Theme-bundled patterns: PHP file in `/patterns/` folder of
    theme, picked up by core's pattern loader (NOT this field).
  - Plugin-registered patterns: `register_block_pattern()` PHP
    API call.
  - Pattern Directory patterns: remote slugs registered via
    THIS field, OR via PHP equivalent.
  - Pattern composition: multi-block markup with potential
    InnerBlocks, transforms, variations interactions.
  - Pattern categories: separate registration via
    `register_block_pattern_category()` PHP.
  This theme.json field is **one entry point** into one source
  (the Directory). Not the full pattern system.
- **NO pattern definition syntax in theme.json.** Themes wishing
  to BUNDLE custom patterns (not from the Directory) use the
  `/patterns/` folder convention with PHP front-matter headers
  (Pattern Name / Description / Categories / Block Types /
  Inserter / etc.), NOT this field.
- **Pattern slugs are FLAT strings.** No namespace prefix
  enforced (unlike block names which require `vendor/slug`).
  Pattern Directory slugs use slugs like `"footer-with-social"`
  or `"hero-with-cover"`.
- **Order in the array doesn't determine display order.**
  Pattern display in the inserter is governed by category
  assignment (set Directory-side or via PHP), not by
  registration order.
- **Pattern unregistration is not theme.json-side.** To remove a
  pattern from a theme that registered it via this field,
  modify the array. To unregister patterns registered
  elsewhere (PHP, Directory auto-loaded), use
  `unregister_block_pattern()` PHP.
- **patterns connects semantically to block.variations and
  block.transforms** but is structurally distinct:
  - `block.variations` = projections WITHIN one block (same
    identity, different role).
  - `block.transforms` = conversions BETWEEN block types
    (different identity, semantic adjacency).
  - `patterns` = pre-authored MULTI-BLOCK compositions
    (registered for insertion as a unit).

  Identity ontology comparison:

  | Mechanism | Scope | Identity |
  |---|---|---|
  | block.variations | single block | preserved (same block type, different attributes) |
  | block.transforms | block ↔ block | converted (different block type) |
  | **patterns** | **multi-block tree** | **composition (multiple blocks bound as a unit)** |

  Patterns are **the multi-block extension of the identity-
  projection axis** — same level of "user picks a pre-made
  thing" as variations, but operating on a tree of blocks
  rather than a single block.
- **Pattern insertion is a tree-substitution event.** When the
  user inserts a pattern, WordPress materializes the entire
  multi-block subtree at the insertion point. This is similar
  to template-part insertion but with a key difference:
  template parts are LIVE-LINKED (changes to the part propagate
  to all uses); patterns are SNAPSHOT (the inserted blocks
  become independent and can be edited freely without
  affecting other instances of the same pattern).
- ⚠ **Minimum WP version unknown.** patterns top-level field
  has been part of theme.json since the Pattern Directory
  integration arrived (likely WP 5.8+ era). Frontmatter
  `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Putting pattern content (block markup) in this field. The
  field accepts ONLY slug strings — content is in the Pattern
  Directory or in theme `/patterns/` PHP files.
- ❌ Using bare numeric IDs instead of slugs (e.g.,
  `"patterns": [ "1234" ]`). Patterns are addressed by slug
  strings, not Directory IDs.
- ❌ Expecting patterns array to override or replace theme-
  bundled `/patterns/` folder patterns. The two registration
  paths coexist; this field ADDS Directory-source patterns.
- ❌ Using this field for plugin-provided patterns. Plugins
  register patterns via `register_block_pattern()` PHP; theme
  authors add Directory patterns via this field.
- ❌ Trying to set per-pattern title / description / categories
  in theme.json. The schema only accepts slug strings; no
  per-entry metadata. Customize Directory metadata via
  Directory submission or use PHP for theme-bundled
  customization.
- ❌ Conflating template parts with patterns. Template parts
  are LIVE-LINKED reusable structural regions (header /
  footer); patterns are SNAPSHOT pre-authored multi-block
  compositions. Editing a template part affects all uses;
  editing inserted pattern blocks doesn't.
- ❌ Listing the same slug multiple times. Each unique slug
  registers once; duplicates are presumably deduplicated
  (verification-needed for exact behavior).
- ❌ Treating this field as the complete pattern registration
  system. It's one entry point. The broader system spans
  multiple layers (PHP API, filesystem convention, Directory).

## RELATED

- `theme-config.json-templateParts` — adjacent top-level
  filesystem-coupled metadata registry. theme.json metadata
  for `/parts/{name}.html` files. Similar pattern: theme.json
  contains pointer/metadata, content lives in filesystem.
- `theme-config.json-customTemplates` — adjacent top-level
  filesystem-coupled metadata registry. theme.json metadata
  for `/templates/{name}.html` files.
- `block.transforms` — adjacent semantic composition mechanism
  at the block level. transforms convert between block types;
  patterns insert pre-authored multi-block trees. Both are
  user-initiated composition operations but at different
  scales (single block ↔ multi-block tree).
- `block.variations` — adjacent identity projection mechanism.
  variations are single-block presets; patterns are multi-block
  presets. Both let users insert "pre-configured starting
  states" from the inserter.
- `block.inner-blocks` — patterns are typically multi-block
  compositions, materialized as a tree of blocks (often with
  nested innerBlocks). Pattern insertion = creating a
  populated InnerBlocks-containing subtree at the insertion
  point.
- `plugin-dev.register-block-pattern` (cross-context, planned)
  — PHP API for registering patterns from theme `/patterns/`
  folder OR plugin code. The COMPLEMENT to this theme.json
  field; together they form the complete pattern registration
  surface.
