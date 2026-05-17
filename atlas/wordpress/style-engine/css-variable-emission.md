---
rule_id: style-engine.css-variable-emission
domain: style-engine
topic: runtime-synthesis
field_cluster: variable-graph
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: css
sources:
  - url: https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/
    section: "Global Settings & Styles — preset CSS variable emission"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/
    section: "theme.json living spec — settings.* presets and var:preset| reference grammar"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-style-engine/
    section: "@wordpress/style-engine — runtime CSS generation"
    captured: 2026-05-09
related:
  - style-engine.generated-selectors           # symmetric counterpart — selector synthesis
  - theme-config.settings.color                # primary --wp--preset--color--* source
  - theme-config.settings.typography           # --wp--preset--font-{family,size}--* + fluid clamp emission
  - theme-config.settings.spacing              # --wp--preset--spacing--* + spacingScale generation
  - theme-config.styles                        # var:preset|category|slug reference grammar consumer
  - block-authoring.supports.spacing           # blockGap → --wp--style--block-gap special case
---

# RULE — CSS variable emission (`--wp--preset--*` / `--wp--style--*`) — runtime value graph synthesis

## WHEN

Reading rendered Gutenberg CSS output and encountering CSS custom
properties the theme never authored — `--wp--preset--color--primary`,
`--wp--preset--spacing--50`, `--wp--style--block-gap`. Use this
chunk to understand:

- Why `:root { --wp--preset--color--primary: #0055ff; }` appears in
  output without theme CSS authoring it.
- Who compiles `palette: [{ slug: "primary", color: "#0055ff" }]`
  into the `--wp--preset--color--primary: #0055ff` declaration.
- Why styles.color.text written as `"var:preset|color|primary"`
  resolves to `var(--wp--preset--color--primary)` in emitted CSS.
- The difference between `--wp--preset--*` and `--wp--style--*`
  namespaces and why both exist.
- Why `--wp--style--block-gap` is special — the only blockGap-aware
  variable bridging value realization and composition realization.

This chunk is the **value-topology counterpart** to
`generated-selectors` (which documents attachment-topology
synthesis). Together they cover the two output namespaces of the
style engine.

## SHAPE

The style engine emits **two parallel CSS variable namespaces** at
build/render time. Both are emitted as CSS custom property
declarations, typically on `:root` for theme-global scope or on
scoped selectors for block/element instances.

### Preset variable namespace — `--wp--preset--{category}--{slug}`

Stable, registry-derived. Compiled directly from
`settings.{capability}.{registry}` arrays in theme.json.

```json
// theme.json
{
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary",   "color": "#0055ff", "name": "Primary" },
        { "slug": "secondary", "color": "#ff5500", "name": "Secondary" }
      ]
    },
    "spacing": {
      "spacingSizes": [
        { "slug": "20", "size": "0.44rem", "name": "2X-Small" },
        { "slug": "50", "size": "1.5rem",  "name": "Medium" }
      ]
    }
  }
}
```

```css
/* style engine emits */
:root {
  --wp--preset--color--primary: #0055ff;
  --wp--preset--color--secondary: #ff5500;
  --wp--preset--spacing--20: 0.44rem;
  --wp--preset--spacing--50: 1.5rem;
}
```

Documented categories include: `color`, `gradient`, `duotone`,
`font-family`, `font-size`, `spacing`, `shadow`. Slugs are
author-declared.

### Style variable namespace — `--wp--style--{name}`

Synthesized, runtime-state-derived. NOT directly mapped to a
single registry entry — emitted from styles.* values that need
runtime propagation across block / layout boundaries.

```css
/* example emissions */
:root {
  --wp--style--block-gap: 1.5rem;
  --wp--style--root--padding-top: 1rem;
  --wp--style--root--padding-right: 2rem;
  --wp--style--root--padding-bottom: 1rem;
  --wp--style--root--padding-left: 2rem;
  --wp--style--global--content-size: 800px;
  --wp--style--global--wide-size: 1200px;
}
```

These variables exist because their VALUES need to be CONSUMED
by other style emission (e.g., layout engine reads
`--wp--style--block-gap` as the `gap` property of layout
containers). They are runtime communication channels, not
catalog entries.

### Reference grammar — how authored declarations consume variables

```json
// theme.json styles.* consumes presets via this syntax
{
  "styles": {
    "color": { "text": "var:preset|color|primary" },
    "spacing": { "padding": { "block": "var:preset|spacing|50" } }
  }
}
```

Compiled to:

```css
body { color: var(--wp--preset--color--primary); }
.editor-styles-wrapper { padding-block: var(--wp--preset--spacing--50); }
```

The `var:{namespace}|{category}|{slug}` reference syntax is
theme.json's authored interface to the variable namespace —
the compiler maps it to the runtime `var(--wp--{namespace}--{category}--{slug})`
form.

### What this is NOT

- NOT a CSS-in-JS runtime. Variables are emitted as static CSS
  declarations during render / build, not computed in JavaScript
  at use site.
- NOT only `:root`-scoped. Per-block and per-element values can
  emit scoped variable declarations (verification-needed for
  exact scoping rules).
- NOT a complete representation of all theme.json values.
  Some styles.* values are emitted directly as property
  declarations (not via variable indirection); only values that
  need cross-rule reuse or runtime propagation get variable
  representation.
- NOT user-author-extensible at runtime. Theme/plugin can
  contribute presets via theme.json or PHP filters; end-users
  do not author new variables (they pick from existing presets).

## REQUIRES

- A theme.json `settings.*` registry entry (for preset variables)
  OR a styles.* value needing runtime propagation (for style
  variables).
- For preset variables: registry must declare `slug` (becomes
  variable name suffix) and `color`/`size`/`fontSize`/etc.
  (becomes value).
- For style variables: the value must be REFERENCED by another
  emission consumer (e.g., layout engine reading block-gap).
  Standalone styles.* values without consumer needs may emit
  directly without going through variable indirection.
- Style engine has authority to compile theme.json and emit CSS
  (server-side render or editor-side global styles compilation).
- ⚠ Exact emission scope (when :root vs when scoped), aggregation
  strategy (single stylesheet vs concatenated), cache invalidation
  on theme.json changes — verification-needed.

## INVARIANTS

### 1. Two namespaces, two ontology roles

`--wp--preset--*` and `--wp--style--*` are NOT just two arbitrary
naming groups — they encode two different kinds of authority and
lifecycle:

| namespace | character | source authority | lifecycle |
|---|---|---|---|
| `--wp--preset--{category}--{slug}` | **stable registry ABI** | settings.{capability}.{registry} entries | persistent across renders; tied to theme/plugin config |
| `--wp--style--{name}` | **synthesized runtime state** | styles.* values needing cross-rule consumption | per-render / per-context; tied to current global styles state |

Treating both as "just CSS variables" loses the distinction.
Preset variables are the **interface layer** authored declarations
bind to; style variables are the **runtime communication channels**
between style engine subsystems.

### 2. Variables are runtime ABI (interface contract)

`--wp--preset--{category}--{slug}` is the **stable interface layer**
between three otherwise-decoupled authorities:

```
settings.color.palette          (theme registry)
   ↕
--wp--preset--color--primary    (compiler-emitted ABI)
   ↕
styles.color.text: var:preset|color|primary    (authored reference)
   ↕
block markup style attribute references         (instance consumption)
```

Without this ABI, every authored reference would need direct
knowledge of the source registry value. With it:
- Theme authors reference by slug (not by literal value).
- Block authors reference by slug from block.json supports.
- Plugin authors reference by slug across boundaries.
- Settings registry can change underlying values without
  invalidating references.

This is **a contract at the CSS layer**, not just a name. Plugin/
theme/block code that uses `var(--wp--preset--color--primary)`
across boundaries can rely on the ABI even when registry
values change.

### 3. Variables are deferred realization

Settings/styles declarations do NOT directly produce final values.
Realization happens at variable emission:

```
theme.json settings.color.palette   = registry declaration
   ↓
                                    = NOT YET REALIZED
   ↓
:root { --wp--preset--color--primary: #0055ff; }   = REALIZATION CHECKPOINT
   ↓
property: var(--wp--preset--color--primary);       = RUNTIME CONSUMPTION
```

This explains the typography fluid case retroactively: settings.
typography.fluid declared a clamp synthesis policy; that policy
materializes when the corresponding font-size preset variable
is emitted with the synthesized clamp() value as the variable's
content. The variable IS the materialization checkpoint.

### 4. Preset slugs are dual-role: design token identity + compiler linkage key

A preset slug like `"primary"` plays two roles simultaneously:

- **Design token identity** — semantic name for the design system
  ("primary color"); editor UI displays it as a labeled choice.
- **Compiler linkage key** — string that the variable emission
  pipeline turns into the variable name suffix; the same string
  is what `var:preset|color|primary` compiles against.

These two roles overlap by design but are conceptually distinct.
A theme author thinking only about design language will see
"primary"; the same string is also a load-bearing compiler key
that mustn't change without breaking authored references.

### 5. Symmetry with generated-selectors — value topology pair

CSS variable emission is the **value-topology counterpart** to
generated-selectors' attachment-topology synthesis:

| runtime output | responsibility | what it builds |
|---|---|---|
| generated selectors | attachment topology | per-instance scoping graph (`.wp-container-*`, `.wp-elements-*`) |
| emitted variables | value topology | preset/style value graph (`--wp--preset--*`, `--wp--style--*`) |

Together they construct the final cascade graph. A complete
style emission needs BOTH:
- Where does the rule attach? → selector synthesis answers.
- What value does the rule carry? → variable synthesis answers.

Style engine's two main runtime outputs are these two synthesis
layers. Almost every other engine artifact (inline styles, class
attributes, stylesheet aggregation) combines these two.

### 6. Emission scope hierarchy: :root → scoped variants

Preset variables for theme-global presets emit on `:root`. When
theme.json carries per-block or per-element overrides via
`styles.blocks.{name}.*` or `styles.elements.{role}.*`, the
corresponding variables (where applicable) emit on scoped
selectors instead of :root. ⚠ Exact scoping rules (which values
get variable indirection vs direct property emission, what
selector wraps scoped variables) are verification-needed; the
emission engine decides per-value whether variable indirection
is necessary.

### 7. Computational realization materializes at variable emission

For computational settings (typography fluid, spacingScale), the
emission step is where the algorithm RUNS:

```
settings.typography.fluid: { min: "16px", max: "24px" }
+ settings.typography.fontSizes[0]: { slug: "lg", fluid: { min: "1rem", max: "2rem" } }
   ↓ (fluid clamp synthesis runs HERE)
:root {
  --wp--preset--font-size--lg: clamp(1rem, 0.8rem + 1vw, 2rem);
}
```

The typography "realization leakage" identified in settings layer
ontology is closed here: the leakage is structural — fluid
declares a synthesis policy that materializes at variable emission
time. Same mechanism applies to spacingScale (which emits a
discrete sequence of preset variables from the algorithm's
parameters).

### 8. `--wp--style--block-gap` is the canonical relational variable

The block-gap variable is the most-discussed `--wp--style--*`
case because it bridges two realization paths:

- **Value realization path**: emits as variable value from
  styles.spacing.blockGap declaration.
- **Composition realization path**: layout engine consumes the
  variable as the `gap` CSS property of generated container
  selectors (`.wp-container-*`).

This bridge is documented as the first **full-stack capability**
in KB. The style variable namespace exists in part to enable
exactly this kind of bridge — value declared in one capability
needs runtime consumption in another (layout reads spacing).

### 9. theme.json is not directly rendered — variables are the materialization checkpoint

This invariant is now explicitly statable:

> theme.json is NOT directly rendered to CSS.
> The compiler pipeline is:
>
> theme.json
>   → normalized config graph
>   → preset registry
>   → CSS variable emission   ← MATERIALIZATION CHECKPOINT
>   → selector synthesis
>   → style aggregation
>   → final stylesheet

Variable emission is the structural mid-point of the pipeline
where declarative authority crosses into runtime CSS. Anything
upstream is configuration; anything downstream consumes the
emitted variables. This makes the variable layer the load-
bearing observability point — what the engine emits as variables
is the contract surface readable from rendered output.

### 10. Variables enable per-instance cascade without per-instance rule duplication

Generated selectors give per-instance ATTACHMENT scoping.
Variables give per-context VALUE substitution. Together they
let the engine emit ONE rule body and have it specialize per
instance:

```css
/* theme-global */
:root { --wp--preset--color--primary: #0055ff; }

/* per-block override */
.wp-block-button { --wp--preset--color--primary: #ff0000; }

/* one rule consumes both */
.wp-block-button .wp-element-button {
  background-color: var(--wp--preset--color--primary);
  /* resolves to red inside .wp-block-button, blue elsewhere */
}
```

This pattern minimizes generated CSS volume and enables theme-
author overrides without engine intervention. It is also why
the variable layer cannot be replaced with literal value
inlining without losing this composability.

## VERIFICATION NEEDED

Style-engine bounded context epistemic limit applies. The
following are inferred from observation and require source-side
verification before relying on:

- Exact emission scope decisions: when does a value go through
  variable indirection vs emit directly as property value?
- :root vs scoped selector emission rules for per-block /
  per-element preset overrides.
- Variable name encoding rules — handling of slugs containing
  special characters, unicode, conflicting names across
  categories.
- Cache invalidation on theme.json changes — when is the
  variable graph regenerated?
- Aggregation strategy: single concatenated stylesheet vs
  multiple style blocks vs inline injection.
- Fallback value emission — does the engine emit
  `var(--wp--preset--color--primary, fallback)` anywhere, and
  if so under what conditions?
- Behavior when a referenced slug doesn't exist (missing preset).
- Inline style emission for per-instance attribute style
  values vs variable indirection.
- Editor canvas (iframe) variable emission vs front-end render
  consistency.
- Plugin contribution path: how do plugins extend the variable
  namespace via PHP filters, and does that path differ from
  theme.json contribution?

## ANTIPATTERNS

- ❌ Hardcoding `var(--wp--preset--color--primary)` in author CSS
  outside the WordPress style engine pipeline. The variable name
  IS stable (ABI), but its presence in a given context depends
  on theme.json configuration. Use the reference grammar
  (`var:preset|color|primary`) in theme.json or use the variable
  in stylesheets that load WHEN the theme is active.
- ❌ Renaming a preset `slug` after publishing the theme. Slug
  is a compiler linkage key — renaming breaks all `var:preset|*|*`
  references in theme.json AND any external CSS using the
  generated variable name.
- ❌ Treating `--wp--style--*` as a registry. It's NOT a catalog
  to populate; it's runtime communication channels between style
  engine subsystems. Don't try to declare new `--wp--style--*`
  variables from theme.json — the namespace is engine-owned.
- ❌ Using `--wp--style--block-gap` outside layout consumption
  contexts. The variable exists for the layout engine's gap
  consumption; using it as a generic "1.5rem" reference
  conflates value-realization with composition-realization.
- ❌ Authoring CSS that overrides `--wp--preset--*` values for
  individual selectors expecting global cascade. Variables
  cascade, but if multiple subsystems compose, scope conflicts
  may surface. Use theme.json `styles.blocks.*` / `styles.elements.*`
  for engine-coordinated scoping.
- ❌ Relying on inline-style emission character (whether the
  engine emits `style="color: var(--wp--preset--color--primary)"`
  vs `style="color: #0055ff"`) for per-instance values. Both
  are documented engine outputs; treating one as canonical breaks
  across versions.
- ❌ Bypassing the variable layer with literal hex values in
  styles.* declarations when a preset exists. Loses the ABI
  benefit (theme author can't redefine the value globally
  later).
- ❌ Treating preset slugs as purely human-facing labels.
  They're load-bearing compiler keys; UI renaming is fine
  (titles are translatable), but slug rename is breaking.

## RELATED

- `style-engine.generated-selectors` — symmetric counterpart.
  Variable emission = value topology synthesis;
  generated selectors = attachment topology synthesis. Together
  they form the complete style engine output graph.
- `theme-config.settings.color` — primary source of
  `--wp--preset--color--*` (palette → variable emission) and
  `--wp--preset--gradient--*` / `--wp--preset--duotone--*`.
- `theme-config.settings.typography` — source of
  `--wp--preset--font-family--*` / `--wp--preset--font-size--*`,
  including the fluid clamp() synthesis path (computational
  realization materializing at variable emission).
- `theme-config.settings.spacing` — source of
  `--wp--preset--spacing--*`, including spacingScale's
  algorithmic generation path.
- `theme-config.styles` — consumer of preset variables via the
  `var:preset|category|slug` reference grammar. The reference
  resolution at compile time is paired with this chunk's
  emission step.
- `block-authoring.supports.spacing` (blockGap) — declares the
  per-instance opt-in that, when configured at theme level,
  drives `--wp--style--block-gap` emission.
- `block-authoring.markup-representation` — block instance
  attributes can encode preset references that resolve to
  variable consumption in serialized markup; the IR contract
  intersects the variable ABI here.
- (planned) `style-engine.preset-materialization` — registry →
  declaration → applied-value transformation stages. Variable
  emission is one stage of this larger pipeline.
- (planned) `style-engine.cascade-aggregation` — final cascade
  ordering across theme.json output, generated selectors,
  styles.css, theme stylesheet. Variable scope and override
  ordering interact here.

## META

This chunk sits in the **style-engine bounded context**, where
the base 6-slot DSL is extended with `VERIFICATION NEEDED` and
`META` sections. The extension is **bounded-context-specific**,
not retroactively applied to declarative-schema chunks.

**Justification for the extension:**

> Style-engine chunks extend the base DSL because runtime
> ontology introduces unverifiable or implementation-derived
> authority surfaces. Declarative-schema chunks (settings/
> styles/supports) operate against a documented schema where
> certainty is high. Style-engine chunks operate against a
> compiler internals layer where handbook prose only partially
> exposes the ontology — empirical verification, package
> source reading, and runtime observation become first-class
> evidence. The DSL extension reflects this epistemic shift.

**Three-source authority hierarchy** (style-engine bounded
context only):

| source | authority |
|---|---|
| handbook | declarative intent, schema documentation |
| @wordpress/style-engine package source | actual ontology, synthesis logic |
| rendered runtime output | empirical verification |

**Compiler pipeline framing (style-engine backbone):**

> theme.json is NOT directly rendered to CSS.
> The actual pipeline is:
>
> theme.json → normalized config graph → preset registry →
> CSS variable emission → selector synthesis → style
> aggregation → final stylesheet

This chunk documents the **variable emission** stage. Adjacent
stages (preset materialization, selector synthesis, cascade
aggregation) are separate chunks. The pipeline is the bounded
context backbone.

**Position in style-engine bounded context (after this chunk):**

| chunk | pipeline stage | status |
|---|---|---|
| (block-authoring) selectors | declaration | done |
| generated-selectors | selector synthesis | done |
| **css-variable-emission** | **variable emission** | **this chunk — done** |
| preset-materialization | full registry → applied-value pipeline | planned |
| cascade-aggregation | final stylesheet ordering | planned |
| wrapper-attributes (re-frame) | wrapper class contributions | possibly re-frame existing chunk |

After this chunk, style engine's two primary runtime outputs
(selectors, variables) are documented. Subsequent chunks
formalize the upstream pipeline (materialization) and downstream
aggregation (cascade ordering).
