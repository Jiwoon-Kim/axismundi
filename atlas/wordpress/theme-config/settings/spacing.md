---
rule_id: theme-config.json-settings-spacing
domain: theme-config
topic: settings
field_cluster: design-tokens
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#spacing
    section: "theme.json — settings.spacing (8 fields incl. spacingScale generative DSL)"
    captured: 2026-05-09
related:
  - block.supports.spacing                 # block-side counterpart (margin/padding/blockGap)
  - theme-config.json-settings-color       # 1st substrate subtype: registry only
  - theme-config.json-settings-typography  # 2nd substrate subtype: continuous-interpolation computational
  - theme-config.json-settings-layout      # 3rd substrate subtype: cross-capability composition
  - theme-config.json-styles-spacing       # styles.spacing.blockGap is the source for --wp--style--block-gap (this section gates it)
  - theme-config.json-appearanceTools      # bundles blockGap/margin/padding (the 3 boolean gates)
---

# RULE — `settings.spacing` — generative token substrate

## WHEN

Configuring a theme's `theme.json` spacing authority. This chunk
**closes the settings 4-subtype taxonomy**:

| Subtype | Representative | Pattern |
|---|---|---|
| Registry substrate | `settings.color` | Static token declaration |
| Computational substrate | `settings.typography` | Continuous interpolation policy (fluid → clamp) |
| Composition substrate | `settings.layout` | Cross-capability authority (wideSize double-duty) |
| **Generative substrate** | **`settings.spacing`** | **Discrete algorithmic preset generation (spacingScale DSL)** |

`spacingScale` is the **strongest realization-leakage case** in
settings — it is a **mini token generator DSL** embedded in
theme.json. The theme declares not only token values but the
ALGORITHM that synthesizes them. Compare:

| Mechanism | Pattern |
|---|---|
| typography.fluid | `f(viewport) → clamp()` — continuous interpolation |
| spacingScale | `base × ratio^n` — discrete multiplicative progression |

Both are "computational settings" but represent **distinct
computational paradigms**. Settings supports MULTIPLE algorithmic
modes, not one generic "fluid pattern".

## SHAPE

### 8 fields

**Generative + registry (2 — declare token vocabulary, two
mutually-cohabiting forms):**

| Field | Type | Notes |
|---|---|---|
| `spacingSizes` | `[{ name, slug, size }]` | **Static registry** — explicit list of preset values. Mirrors palette / fontSizes pattern. |
| `spacingScale` | `{ operator, increment, steps, mediumStep, unit }` | **Algorithmic generator** — synthesis policy that produces preset values at registration time. |

**Capability gates (3 — per-property boolean toggles):**

| Field | Type | Default | Notes |
|---|---|---|---|
| `blockGap` | `boolean \| null` | `null` | **Tri-state**: enables `--wp--style--block-gap` to be generated from `styles.spacing.blockGap`. |
| `margin` | `boolean` | `false` | Allow users to set custom margin. **Default false (opt-in).** |
| `padding` | `boolean` | `false` | Allow users to set custom padding. **Default false (opt-in).** |

**Unit governance (1 — controls what dimensional language users
may use):**

| Field | Type | Default | Notes |
|---|---|---|---|
| `units` | `[string]` | `["px","em","rem","vh","vw","%"]` | Allowed CSS unit suffixes for custom spacing values. **Only settings field with explicit non-empty default array.** |

**Custom + default gates (2 — preset access governance):**

| Field | Type | Default | Notes |
|---|---|---|---|
| `customSpacingSize` | `boolean` | `true` | Allow custom (non-preset) spacing values via input. |
| `defaultSpacingSizes` | `boolean` | `true` | Allow core's default spacing presets in the picker. |

### `spacingScale` generative DSL

```json
{
  "settings": {
    "spacing": {
      "spacingScale": {
        "operator":   "*",
        "increment":  1.5,
        "steps":      7,
        "mediumStep": 1.5,
        "unit":       "rem"
      }
    }
  }
}
```

Synthesis (illustrative — exact algorithm in core's style engine):
- `mediumStep` (1.5) is the anchor value.
- `operator: "*"` + `increment: 1.5` means each step multiplies the
  previous by 1.5.
- `steps: 7` produces 7 presets total, distributed around the
  `mediumStep` anchor.
- `unit: "rem"` applies to all generated values.

Example synthesis (illustrative, not source-verified exact output):
~`[0.44rem, 0.66rem, 1rem, 1.5rem, 2.25rem, 3.375rem, 5.06rem]`

⚠ The exact synthesis algorithm and preset slug naming convention
are not documented in the captured source. Verify per WP version
implementation.

### `spacingSizes` static registry (alternative or complement)

```json
{
  "settings": {
    "spacing": {
      "spacingSizes": [
        { "name": "Small",  "slug": "20", "size": "0.5rem" },
        { "name": "Medium", "slug": "40", "size": "1rem" },
        { "name": "Large",  "slug": "60", "size": "2rem" }
      ]
    }
  }
}
```

Note: Convention is to use NUMERIC slugs (e.g., `"20"`, `"40"`,
`"60"`) for spacing presets, distinct from color (`"primary"`)
and typography (`"large"`). This reflects the ordinal nature of
spacing scales.

## REQUIRES

- Setting MUST be declared under `theme.json` `settings.spacing`
  (top-level scope or per-block-type via
  `settings.blocks.{name}.spacing`).
- `spacingSizes` entries MUST have `name` + `slug` + `size`
  (CSS length string with unit).
- `spacingScale` parameters semantics (verification-needed for
  exact algorithm):
  - `operator`: likely `"*"` (multiply) or `"+"` (add).
  - `increment`: the numeric amount applied per step.
  - `steps`: total number of preset entries to generate.
  - `mediumStep`: the anchor value at the center of the scale.
  - `unit`: CSS unit applied to generated values.
- For `units` field: each entry MUST be a valid CSS unit string
  (`"px"`, `"em"`, `"rem"`, `"vh"`, `"vw"`, `"%"`, `"ch"`, etc.).
- `blockGap: null` (default) means "unspecified" — distinct from
  `false` (explicit disable). Setting to `true` enables the
  block-gap CSS variable generation; setting to `false`
  explicitly disables it. Setting to `null` defers behavior to
  core defaults.
- `appearanceTools: true` activates `blockGap`, `margin`, and
  `padding` gates as a bundle. Without appearanceTools, themes
  must enable each gate explicitly OR accept the default-false
  for margin/padding.

## INVARIANTS

- **Generative substrate — strongest realization leakage in
  settings layer.** spacingScale embeds a **synthesis algorithm**
  in theme.json. Settings here is no longer "registry +
  governance" — it is also **procedural synthesis policy**.
  Style engine consumes the algorithm at registration time to
  produce the actual preset list.
- **Two registry modes coexist:** spacingSizes (explicit list)
  and spacingScale (algorithmic generation). Same preset
  namespace (preset slugs map to CSS variables
  `--wp--preset--spacing--{slug}`).
- ⚠ **spacingSizes ↔ spacingScale precedence is not documented.**
  When BOTH are present, the merge or override behavior is
  unclear from the captured source. Likely cases:
  - One overrides the other entirely (unknown which wins).
  - Both contribute (with possible slug collision).
  Verify per implementation; recommend choosing ONE form per
  theme to avoid ambiguity.
- **spacingScale = "mini token generator DSL".** The
  `{ operator, increment, steps, mediumStep, unit }` parameters
  together specify a discrete multiplicative scale. This is
  qualitatively different from typography's `fluid` (continuous
  viewport interpolation):
  - **fluid**: `f(viewport) → clamp(min, mid, max)` — runtime
    responsive, single value resolves differently per viewport.
  - **spacingScale**: `g(operator, increment, steps, mediumStep)
    → preset[]` — registration-time synthesis, fixed values
    declared as a generated preset list.
  Settings supports MULTIPLE computational modes — not a single
  "fluid pattern".
- **Vertical pipeline (with generative synthesis):**
  ```
  settings.spacing.spacingScale     (algorithm declaration)
      ↓ at registration time
  Synthesized preset list           (e.g., 7 sizes around mediumStep)
      ↓ each emits as CSS variable
  --wp--preset--spacing--{slug}
      ↓ populates editor spacing pickers (margin/padding/blockGap)
  user picks preset slug
      ↓ supports.spacing.{prop} gates the control
  style.spacing.padding = "var:preset|spacing|{slug}"
      ↓ wrapper emits inline style or class
  CSS resolves var to synthesized value
  ```
  Compare with color (no synthesis), typography (clamp synthesis
  per-render), spacing (synthesis once at theme load).
- **`blockGap` tri-state: `true` / `false` / `null`.** Source:
  *"Enables `--wp--style--block-gap` to be generated from
  styles.spacing.blockGap."* Tri-state implication:
  - `true`: explicitly enable block-gap CSS variable generation.
  - `false`: explicitly disable.
  - `null` (default): defer to core default behavior (most likely
    enabled when blockGap is in use elsewhere).
  ⚠ The exact distinction between `null` and unspecified field is
  not crisply documented; treat `null` as the documented default
  and explicit values as overrides.
- **Asymmetric defaults — 3 fields default to `false` or `null`:**
  - `blockGap: null` (tri-state, defers to core)
  - `margin: false` (opt-in required)
  - `padding: false` (opt-in required)
  Compare to color (only `link: false`) and typography (4
  asymmetric: lineHeight, textColumns, writingMode, textIndent
  string). Spacing's opt-in defaults reflect a **conservative
  margin/padding governance philosophy** — themes must explicitly
  give users this authority.
- **`units` field — governance over dimensional vocabulary.**
  This is settings' DEEPEST governance level: not gating WHETHER
  the user can input custom values (that's `customSpacingSize`),
  but gating HOW custom values may be expressed. Removing
  `vw`/`vh` from the array prevents users from creating viewport-
  responsive spacing inputs. Only settings field with this depth
  of unit governance.
- **`units` is the only settings field with an explicit non-empty
  default array.** Default: `["px","em","rem","vh","vw","%"]`
  — 6 standard CSS units. Other registry arrays (palette,
  fontSizes, spacingSizes) default to empty / unspecified.
- **`blockGap` cross-capability coupling — RECURRENCE.** This
  was first surfaced in `block.supports.spacing` (parent-controlled
  inter-child spacing), then in `block.supports.layout` (layout
  consumes blockGap value for `gap` CSS), and now in
  `settings.spacing.blockGap` (theme gates the CSS variable
  generation). The capability touches 3 layers:
  - block.supports.spacing.blockGap = block opts in to gap UI
    (declares the CONTROL).
  - settings.spacing.blockGap = theme gates whether
    `--wp--style--block-gap` CSS variable is generated.
  - settings.layout / block.supports.layout = consumes the value
    via `gap` CSS property at flex/grid runtime.
  blockGap is the **most cross-coupled spacing concern** — it
  bridges spacing settings, layout settings, and InnerBlocks
  composition.
- **No separate `customMargin` / `customPadding`.** A single
  `customSpacingSize` field gates all spacing custom values
  (margin AND padding AND blockGap). Compare to typography where
  `customFontSize` is one of several `custom{Property}` gates.
- **`appearanceTools: true` covers all 3 spacing capability gates
  (blockGap, margin, padding) as a bundle.** This is the LARGEST
  coverage of any capability by appearanceTools — entire spacing
  governance can be enabled by the meta-bundle. Compare:
  - color: 4 sub-gates bundled (link/heading/button/caption,
    NOT background/text/gradients).
  - typography: 1 sub-gate bundled (lineHeight only).
  - spacing: ALL 3 sub-gates bundled (the entire boolean gate
    triplet).
  Reflects spacing's appearance-centric character in WordPress's
  governance philosophy.
- **Settings ↔ supports asymmetry — spacing case:**
  - `supports.spacing` has 3 sub-flags (margin / padding /
    blockGap), all matching settings names.
  - `settings.spacing` has 8 fields including:
    - **No supports counterpart**: `units`, `customSpacingSize`,
      `defaultSpacingSizes`, `spacingSizes`, `spacingScale`
      (these are theme-side concerns).
    - 1:1 matched: blockGap, margin, padding (the 3 control
      gates that pair with supports).
  Pattern matches color/typography: settings is broader; supports
  is the per-block subset.
- ⚠ **Minimum WP version unknown.** spacingScale arrived later
  than spacingSizes (likely WP 6.1+ era). Pre-6.1 themes used
  spacingSizes only. Frontmatter `wp_min` is
  `"verification-needed"`.

## ANTIPATTERNS

- ❌ Declaring both `spacingSizes` and `spacingScale` for the
  same theme without testing. Merge / precedence behavior is
  not documented; results may be implementation-specific. Choose
  ONE form per theme.
- ❌ Setting `customSpacingSize: true` while restricting `units`
  to `["px"]` and expecting users to input flexible viewport-
  relative values. The units gate constrains what they can
  enter — `300vw` would be rejected even with custom-input
  enabled.
- ❌ Treating `blockGap: false` as equivalent to `blockGap: null`.
  False is explicit disable; null is "defer to core". Behavior
  may differ on whether the CSS variable is generated when no
  block actively uses blockGap.
- ❌ Setting `margin: false` (default) while expecting users to
  see margin controls. Default-false means the margin UI is
  hidden until explicitly enabled. Same for padding.
- ❌ Using `appearanceTools: true` and ALSO setting
  `margin: false` / `padding: false`. The bundle enables them;
  explicit false is presumed to override (verification-needed).
- ❌ Removing standard CSS units from `units` (e.g., shipping
  `units: ["px"]` only) without considering responsive design
  needs. Limits the theme's design vocabulary; users may need
  to express viewport-responsive values for accessibility or
  modern layouts.
- ❌ Using `spacingScale` without `mediumStep`. The anchor value
  is essential — without it, the scale has no center to expand
  from. Default behavior of missing mediumStep is unclear.
- ❌ Renaming spacingScale slugs across theme versions
  (typically auto-generated like "20", "40", "60"). Slug-stable
  contract — existing posts reference these.
- ❌ Hardcoding spacing values in CSS instead of declaring via
  spacingSizes/spacingScale. Bypasses the preset cascade —
  user choices and theme overrides have no effect.
- ❌ Setting `spacingScale` without considering how the algorithm
  produces slug names. If core auto-generates slugs as
  numerical strings (e.g., "20" through "100" in steps of 10),
  collisions with `spacingSizes` slugs become possible.

## RELATED

- `block.supports.spacing` — block-side counterpart with 3
  matching sub-gates (margin / padding / blockGap). The
  block declares per-block opt-in; settings.spacing declares
  global theme governance.
- `theme-config.json-settings-color` — 1st settings subtype:
  pure registry. Compare for "static token registry" pattern.
- `theme-config.json-settings-typography` — 2nd settings subtype:
  continuous-interpolation computational. Same algorithmic
  category but different paradigm (clamp synthesis vs
  multiplicative scale).
- `theme-config.json-settings-layout` — 3rd settings subtype:
  cross-capability composition. Layout's wideSize is consumed
  by typography fluid; spacing's blockGap is consumed by
  layout's flex/grid gap. Both demonstrate cross-capability
  coupling within settings.
- `theme-config.json-styles-spacing` (planned) —
  `styles.spacing.blockGap` is the source FROM which
  `--wp--style--block-gap` CSS variable is generated (gated by
  this section's `blockGap` field). Realization layer
  counterpart.
- `theme-config.json-appearanceTools` — bundles ALL 3 spacing
  control gates (blockGap, margin, padding) — the LARGEST
  bundle coverage of any capability. Reflects spacing's
  appearance-centric governance in WordPress.
