---
rule_id: block.json-residual-fields
domain: block-authoring
topic: block-json
field_cluster: coordination-adapters
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#styles
    section: "block.json — styles (block style variations)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#example
    section: "block.json — example (inserter preview)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#block-hooks
    section: "block.json — blockHooks (auto-insertion mechanism, WP 6.4+)"
    captured: 2026-05-09
related:
  - block.json-basic-metadata             # version / apiVersion live here, NOT in this batch
  - block.json-hierarchy-constraints      # parent/ancestor/allowedBlocks — composition CONSTRAINT governance
  - block.variations                      # variations vs styles — semantic vs stylistic projection
  - theme-config.json-patterns            # patterns at theme level — multi-block composition unit (parallel scale)
  - theme-config.json-templateParts       # templateParts at theme level — region composition (parallel scale)
  - block.transforms                      # transforms operate alongside example in editor preview UI
  - block.deprecation                     # serialization compatibility runtime; apiVersion (basic-metadata) is its declaration counterpart
---

# RULE — block.json residual fields batch (`styles` / `example` / `blockHooks`) — editor/runtime coordination adapters

## WHEN

Configuring `block.json` fields that do NOT fit the major
families covered by other chunks (registration / supports /
attributes / hierarchy-constraints / context / assets /
selectors / basic-metadata). The 3 remaining top-level fields
share a **coordination adapter** character — they govern editor
UX / insertion orchestration / preview projection rather than
declaring capabilities or styling authority.

This batch closes the block.json static schema surface. After
this chunk, `block.json` field-level documentation is
substantively complete; remaining authority surfaces escape to
other bounded contexts (interactivity for `bindings`, runtime
for editor mode, theme.json for global concerns).

**Field NOT in this batch (already covered):**
- `version` — basic-metadata (block's own version, asset cache
  invalidation use).
- `apiVersion` — basic-metadata (Block API version, e.g. `3`).
- `parent` / `ancestor` / `allowedBlocks` — hierarchy-constraints
  (composition constraint governance).

## SHAPE

### styles — block style variation registration

```json
{
  "styles": [
    { "name": "default", "label": "Default", "isDefault": true },
    { "name": "outline", "label": "Outline" },
    { "name": "fill",    "label": "Fill",    "inline_style": ".wp-block-my-block.is-style-fill { background: blue; }" }
  ]
}
```

Each entry registers a **block style variation** (NOT a CSS
declaration). When the user picks a variation, the editor adds
class `is-style-{name}` to the block wrapper. The actual CSS for
the variation lives elsewhere:
- In the theme stylesheet (matched against `is-style-{name}`).
- In the `inline_style` field (CSS string, emitted alongside).
- In server-side `register_block_style()` (PHP equivalent).

Block styles are **selectable stylistic archetypes** at single-
block scale — analogous to patterns / templateParts at theme
scale, but operating on a single block's appearance only.

### example — inserter preview synthetic state

```json
{
  "example": {
    "attributes": {
      "message": "Hello there!",
      "level":   2
    },
    "innerBlocks": [
      {
        "name": "core/paragraph",
        "attributes": { "content": "Sample paragraph inside example" }
      }
    ],
    "viewportWidth": 800
  }
}
```

The `example` field provides **synthetic block state** for the
inserter's preview / hover-to-preview UI. It is NOT authored
content (does not appear in saved posts) and NOT runtime
output (does not affect rendered pages); it exists solely to
populate the editor's preview surface.

Setting `example: {}` (empty object) gives a generic preview;
setting `example: null` disables the preview entirely.

### blockHooks — declarative auto-insertion

```json
{
  "blockHooks": {
    "core/post-content": "after",
    "core/heading":      "before"
  }
}
```

(WP 6.4+) Maps "anchor block name → relative position." When
the editor renders a template / template-part / pattern that
contains the anchor block, the hooked block is **automatically
inserted** at the specified position relative to the anchor.

Documented relative positions: `before`, `after`, `firstChild`,
`lastChild`. The user can manually remove the auto-inserted
block in the editor; otherwise it persists as part of the
serialized output.

This is **declarative composition injection** — the third
governance axis at the composition layer.

## REQUIRES

- `styles[].name` MUST be a valid CSS-class-safe string (becomes
  `is-style-{name}` class). Lowercase + hyphen recommended.
- `styles[].isDefault: true` is allowed on at most ONE entry
  (verification-needed for multi-default behavior).
- `styles[].inline_style` is a CSS string; the engine emits it
  alongside the block's other styles when the variation is
  active. ⚠ Verification-needed for exact emission scope (always
  emitted vs only-when-active).
- `example.attributes` MUST conform to the block's declared
  attribute schema; invalid attribute values may break preview
  rendering.
- `example.innerBlocks[]` entries are recursive block specs
  (each can have its own attributes / innerBlocks).
- `example.viewportWidth` (optional) sets the preview canvas
  width in pixels for responsive preview rendering.
- `blockHooks` requires WP 6.4+. Earlier WP versions ignore
  the field silently.
- `blockHooks` keys must be valid registered block names; values
  must be one of `before` / `after` / `firstChild` / `lastChild`.

## INVARIANTS

### 1. Residual block.json fields are editor/runtime coordination adapters

Unlike supports (capability declaration), attributes (state
schema), selectors (style-engine bridge), or hierarchy-
constraints (composition constraint), these 3 fields are
**coordination adapters** — they govern:

| field | coordination domain |
|---|---|
| `styles` | editor UX (style picker) + serialization (class) + theme CSS coordination |
| `example` | editor preview rendering + inserter UX |
| `blockHooks` | editor / template / pattern composition orchestration |

None of them participate in the style-engine compiler pipeline
as authority sources (no preset registration, no variable
emission, no selector synthesis from block.json side). They sit
at the editor / runtime coordination layer.

### 2. `styles` is a variation registry with stylistic identity (not CSS)

The `styles` field is named misleadingly — it does NOT contain
CSS. It registers **selectable stylistic archetypes** at single-
block scale. Each entry establishes:
- An identifier (`name`) the user can pick in the editor.
- A label (`label`) shown in the picker UI.
- An optional fallback CSS string (`inline_style`) for cases
  where theme CSS is insufficient.
- A serialized class (`is-style-{name}`) that becomes part of
  the block wrapper.

The pattern parallels `block.variations` (semantic role
projection) at the same scope but for **stylistic projection**:

| projection axis | mechanism | identity preservation |
|---|---|---|
| semantic role | `block.variations` | same block, different role |
| stylistic appearance | `block.json styles` | same block, different visual variant |

Both register pre-configured selectable presets at the inserter /
editor UI; both emit metadata that the wrapper carries
(variations may set initial attributes; styles add the
`is-style-{name}` class).

### 3. `example` is preview-oriented synthetic block state

The `example` field is unique in block.json — it is NEITHER
authored content NOR runtime output. It is **editor simulation
payload**:

- Authored content lives in serialized post content.
- Runtime output is what `render_callback` / `save()` produces.
- `example` is what the inserter renders for preview only.

This is the only block.json field that creates a **synthetic
state space** distinct from real instances. It anticipates
future ontology categories (placeholder state / optimistic
state / hydrated state) that will likely appear in interactivity
bounded context.

For reasoning: when debugging "why does the block look wrong in
the inserter but fine when inserted?", the answer is usually
that `example` provides preview state that doesn't match
default attribute initialization.

### 4. `blockHooks` is the third composition governance axis

Composition governance now has 3 documented axes at the block
level:

| axis | mechanism | governance type |
|---|---|---|
| **constraint** | `parent` / `ancestor` / `allowedBlocks` | "where MAY this block be?" |
| **edit** | `templateLock` (theme.json template) | "may users add/remove/move?" |
| **insertion** | `blockHooks` | "auto-insert me near other blocks" |

Constraint governance is REACTIVE (gates user actions); edit
governance is RESTRICTIVE (prevents user actions);
**insertion governance is ACTIVE** (declares automatic block
placement). blockHooks is the first declarative mechanism by
which a block can DECLARE its own insertion behavior relative
to other blocks.

This will likely connect to site-building / contextual
composition bounded contexts when those develop.

### 5. blockHooks introduces "declarative composition injection"

Prior composition mechanisms required either user action
(insert manually) or template authoring (theme/pattern
declares the structure). blockHooks introduces a third path:

> The block itself DECLARES where it should appear when its
> anchor block is present.

This is a **plugin-friendly composition extension** mechanism —
plugins can hook their blocks into core templates without
modifying templates. Implications:
- Templates are no longer the sole source of composition truth;
  blockHooks contribute composition decisions at the block
  registration layer.
- Auto-insertion is reversible (user can remove), but defaults
  are plugin-declared.
- Pattern / template authoring must consider that blocks can
  arrive without explicit invocation.

### 6. None of these fields participate in style-engine

`styles` adds a class but the class semantics are theme-CSS-
matched, NOT style-engine-compiled. `example` produces no CSS
at all. `blockHooks` operates at the composition layer entirely.

This separates them from selectors (style-engine attachment
override) and supports.* (capability flags driving style-engine
emission) — neither group's mechanisms apply here. When
debugging styling issues, these 3 fields are NOT in the
diagnostic path.

### 7. block.json static schema is closed after this batch

After this chunk, the documented top-level block.json fields
form a closed set:

| family | fields |
|---|---|
| registration | (covered in registration chunks) |
| basic-metadata | apiVersion, name, title, category, icon, description, keywords, version, textdomain |
| supports | supports.* (10 chunks) |
| attributes | attributes (3 chunks: core / html-sources / query) |
| context | providesContext, usesContext |
| hierarchy | parent, ancestor, allowedBlocks |
| assets | editorScript, script, viewScript, viewScriptModule, editorStyle, style, render |
| selectors | selectors |
| variations | variations |
| transforms | transforms (in block.transforms) |
| deprecated | deprecated (in block.deprecation) |
| **residual coordination** | **styles, example, blockHooks** (this batch) |

NOT in block.json (escapes elsewhere):
- `bindings` — paradigm bridge, separate spike (planned)
- `_experimental*` fields — verification-needed, may be unstable
- Per-block runtime state — block instance attributes / DB

### 8. blockHooks position in the auto-insertion authority chain

⚠ The auto-insertion authority chain (anchor block resolution,
position determination, deduplication, user override
persistence) is implementation-derived. Verification-needed for:
- What happens if multiple blocks hook the same anchor at the
  same position.
- Whether hook insertion runs on every render or once at
  template materialization.
- How user removal of an auto-inserted block is persisted.
- Whether nested template parts trigger blockHooks resolution
  in inner template parts.
- Conflict resolution between blockHooks and explicit pattern
  / template content.

Behavior here is closer to runtime topology synthesis than
declarative schema; treat the field's behavior as
implementation-defined in edge cases.

## ANTIPATTERNS

- ❌ Putting CSS rules in `styles[].inline_style` for primary
  styling. The field is for fallback / always-bundled CSS;
  primary block style variation CSS belongs in the theme
  stylesheet matched against `is-style-{name}`.
- ❌ Setting multiple `styles[].isDefault: true`. Behavior with
  multiple defaults is undefined; pick one.
- ❌ Renaming `styles[].name` after publishing. The name becomes
  the `is-style-{name}` class; renaming breaks both the
  serialized class on existing posts AND theme CSS rules
  matched against the old name.
- ❌ Using `example.attributes` to seed default values for
  newly-inserted blocks. example is preview-only; default
  attributes go in the `attributes.{name}.default` field.
- ❌ Treating `example.innerBlocks` as a template constraint.
  example is preview state; templates / templateLock control
  actual structural constraints.
- ❌ Using `example: null` to hide an unfinished block.
  Disabling preview shows a fallback "no preview" state in the
  inserter; better to provide a minimal example or remove the
  block from the inserter via `supports.inserter: false`.
- ❌ Using `blockHooks` for content the user is expected to
  customize. Auto-insertion is declarative; users can remove
  the block but cannot easily configure complex behavior. For
  configurable insertion, use patterns or templates.
- ❌ Hooking core blocks into other core blocks via blockHooks
  in a plugin without considering theme / pattern overrides.
  blockHooks is plugin-friendly but creates implicit composition
  decisions theme/pattern authors may not expect.
- ❌ Expecting blockHooks to work pre-WP 6.4. Earlier WP
  versions silently ignore the field; the block won't auto-
  insert.
- ❌ Treating these 3 fields as "minor" or omittable. Each has
  a specific coordination role; omitting them just means the
  block doesn't participate in that coordination surface
  (no style picker / generic preview / no auto-insertion).

## RELATED

- `block.json-basic-metadata` — `version`, `apiVersion`, and
  other identity fields. version (block's own version) is
  basic-metadata's concern; serialization compatibility
  contract is `apiVersion`'s role.
- `block.json-hierarchy-constraints` — `parent` / `ancestor` /
  `allowedBlocks` form the constraint axis of composition
  governance. blockHooks (this chunk) is the insertion axis.
- `block.variations` — semantic role projection at single-block
  scale. Pairs with `styles` (this chunk's stylistic projection)
  as the two single-block preset registries.
- `theme-config.json-patterns` — multi-block composition unit
  at theme scale. Parallel scale: patterns/templateParts (theme,
  multi-block) ↔ variations/styles (block, single-block).
- `theme-config.json-templateParts` — region composition at
  theme scale. blockHooks may inject blocks into template part
  contents during composition (verification-needed for exact
  interaction).
- `block.transforms` — transforms appear in editor preview /
  inserter alongside example; both are editor-UX coordination
  surfaces.
- `block.deprecation` — `deprecated` field handles serialization
  compatibility for evolved block schemas; `apiVersion`
  (basic-metadata) declares which Block API version the block
  conforms to.
- (planned) `block.bindings` — separate spike. Authority
  reattachment surface bridging block-authoring to data-layer /
  interactivity bounded contexts. NOT a residual field; the
  ontology jump is too large for batch inclusion.

## NOTE — batch character

This is a batch chunk covering 3 residual fields with shared
"coordination adapter" character. Each field has its own
ontology role (variation registry / preview state / insertion
governance) but they share the non-substrate, non-style-engine,
non-capability character that justifies grouped treatment.
When `blockHooks` ecosystem matures (more concrete usage
patterns, conflict resolution conventions documented), it may
warrant a dedicated chunk; this batch establishes the baseline
ontology framing for now.
