# v3.6.0 Pilot — Phase 3 Architectural Lessons + Token Layering Insight

> **Status**: insight capture document.  
> **Date**: 2026-05-18 (Phase 3 visual QA + Phase 2E reverse mapping discoveries).  
> **Purpose**: lock today's architectural insights before Phase 5 close + v3.6.1 plan-first.  
> **Audience**: Opus / Codex / future cycle entry points.  
> **Use**: authoritative source material for v3.6.1 (Token Architecture Refactor).

---

## §0 — Why this document

Today's session surfaced three architectural insights that go beyond v3.6.0
Pilot's original scope. They are large enough to deserve their own cycle (v3.6.1)
but specific enough that capturing them as raw insight is critical before either
Phase 5 close or v3.6.1 plan-first occurs.

These insights match the AI iteration weakness pattern that v3.5.16 → v3.5.17
lesson lock was designed to defend against: user-driven discoveries that risk
being abstracted away by subsequent plan documents. Capturing the raw form
preserves the meaning.

The three insights:

```txt
1. Build direction reversal (design system vs theme integration)
2. WP core style reset first principle
3. Token layering architecture (ref / sys / wp-preset / wp-custom / ax-comp)
```

---

## §1 — Build Direction Reversal

### §1.1 Original assumption (v3.5.x)

The Wave 1 cycles (v3.5.1 – v3.5.12) implicitly followed a forward build
direction:

```txt
M3 spec (external authority)
  ↓
Axismundi component (Wave 1 module)
  ↓
WordPress block mapping (binding layer)
  ↓
prose contract (post body)
```

This worked perfectly for axismundi-lab because the lab IS the design system
surface — the canonical M3 implementation. Forward direction makes sense when
the goal is "make M3 components real in CSS."

### §1.2 What Pilot revealed

When the Pilot tried to apply this forward direction to a WordPress block
theme, it broke. WordPress core blocks are NOT a blank canvas:

```txt
Markdown / HTML defaults
  ← browser provides baseline
WordPress core block defaults
  ← WP provides fill / outline / stripes / table border / inline patterns
WP global styles
  ← user-facing color picker, custom typography, layout
WP block-supports
  ← per-block override hooks
```

The forward direction (M3 spec → component → block mapping → prose) tries to
write on top of all this without first acknowledging it. The result:

```txt
- Outlined Button visually breaks (WP core adds border:2px on top of M3 outline)
- Table stripes show core gray instead of M3 surface-container
- Code block / separator / quote inherit prose wrapper that doesn't exist
- Color picker shows hex previews that don't reach the runtime token graph
```

### §1.3 The reverse direction discovered

The correct sequence for theme integration:

```txt
1. Markdown / HTML defaults
   = browser baseline
2. WordPress core block defaults
   = WP-provided styling, varies by block
3. WP core style reset
   = explicit removal of non-neutral defaults that conflict with M3
4. WP preset / custom token bridge
   = adapter exposing M3 tokens to WP-managed surface
5. M3 sys / component mapping
   = applies tokens through block selectors
6. Material custom block (only if needed)
   = future plugin work for behavior beyond visual
```

### §1.4 Insight

```txt
Design system construction is FORWARD direction:
  external spec → internal implementation
  (axismundi-lab v3.5.x)

Theme integration is REVERSE direction:
  CMS native surface → M3 overlay
  (axismundi-pilot v3.6.0)
```

Both directions are valid. Both are needed. The mistake was assuming the
forward direction could be reused as-is for the reverse case.

Charter §4 (theme can / plugin should) implicitly recognizes this duality:
- "theme can" = reverse direction (CMS native + M3 overlay)
- "plugin should" = forward direction extension (Material custom blocks)

### §1.5 Implication for axismundi narrative

axismundi is not one project but two complementary motions:

```txt
Lab (forward):
  M3 reference implementation
  Source of truth for design system
  
Pilot / Theme (reverse):
  WordPress integration surface
  Consumer of design system through reverse mapping
```

The 4-tier architecture (Public / Lab / Baseline / Plugin) gains a directional
property: Lab tier authors forward, Plugin tier authors forward (with
WordPress respect), Baseline / Public tier authors reverse.

---

## §2 — WP Core Style Reset First Principle

### §2.1 Phase 3 finding pattern

Three concrete cases revealed the same underlying issue:

```txt
Button:
  WP exposes `fill` / `outline` styles by default.
  These add their own border / box-shadow rules.
  M3 outline applied on top → 3D-looking border (WP border + M3 outline overlay).

Table:
  WP applies grid borders to default tables.
  WP Stripes adds odd-row background.
  M3 stripes uses surface-container even-row only.
  Without reset → both styles fight, odd rows show core gray.

Prose:
  WP post-content has no `.prose` class.
  prose.css written for `.prose > *` wrapper.
  Without per-block selectors → no styles apply.
```

### §2.2 The principle

```txt
WordPress core blocks are not neutral.
They carry their own:
  - class names (.wp-block-*, .wp-element-*)
  - default block styles (fill, outline, stripes, etc.)
  - block supports (color, typography, spacing)
  - global styles (theme.json projection)
  - inline pattern rules (e.g., default table cell border)

These must be explicitly addressed before M3 mapping.
```

### §2.3 The five-step block bridge order

```txt
Step 1 — Inventory
  Identify WP's default class names, styles, supports per block.

Step 2 — Reset
  Explicitly null the defaults that conflict with M3
  (e.g., .wp-block-button__link { border: 0 })
  Or absorb the default slug into M3 mapping
  (e.g., is-style-fill → M3 Filled, is-style-outline → M3 Outlined)

Step 3 — M3 mapping
  Apply tokens through block selectors.
  Use --md-sys-*, --ax-comp-* tokens.

Step 4 — Interaction bridge
  Add state-layer, ripple, focus-ring, hover, active state.
  Requires JS runtime if behavior is dynamic.

Step 5 — Computed-style audit
  Validate that runtime computed values match the M3 contract.
  NOT just selector existence checks.
```

### §2.4 Insight

```txt
WP block ↔ M3 mapping is not a one-step style overlay.
It is a multi-step contract negotiation between
two existing design systems.

The audit gate at step 5 cannot be skipped.
Selector existence and validator PASS do not catch
silent contract mismatches.
```

The Phase 2E commit `07c48a3` added `npm run validate:computed` for exactly
this gate.

---

## §3 — Token Layering Architecture

### §3.1 Existing record alignment

This insight refines, not contradicts, the existing decision in
`bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1`:

```txt
Original strategy doc:
  three-tier architecture
  Stage 1 / Stage 2 / Stage 3 bridge stages
  Theme-only mode default
  
Today's refinement:
  Stage 1 Static slug bridge needed sub-layers
  WP preset is one of TWO downstream targets, not the only target
  WP custom is the second target (theme-managed, non-editor-facing)
  Bridge direction is consistently M3 → WP (Strict M3 mode)
```

### §3.2 Five-layer token model

```txt
md-ref (primitive)
  hex / px / ms / cubic-bezier values
  Lives in tokens/ref.palette.css (or similar)
  Light and dark share this layer
  
        ↓ var() reference

md-sys (semantic)
  M3 system tokens: primary, on-primary, surface, body-large, etc.
  Light and dark have DIFFERENT sys mappings to the same ref values
  Lives in tokens/sys.light.css + tokens/sys.dark.css
  Source of truth for runtime semantic meaning
  
        ↓ projection / bridge

wp-preset (editor-facing)
  --wp--preset--color--primary, --wp--preset--font-size--*, etc.
  Exposed to Gutenberg color picker, font picker, etc.
  Override direction: --wp--preset--color--primary: var(--md-sys-color-primary)
  Only sys values that benefit from user-facing UI go here

wp-custom (theme-managed)
  --wp--custom--axismundi--shape--corner--large, etc.
  WordPress-output CSS variable but NOT in editor picker
  For state-layer, shape, motion, elevation, density, z-index
  Bridge from sys: --wp--custom--axismundi--state-layer--hover: var(--md-sys-state-hover-opacity)

ax-comp (component contract)
  --ax-comp-button-filled-container, etc.
  Consumed by component CSS
  References sys or wp tokens depending on need
```

### §3.3 Dark mode handled at sys layer only

```txt
Light mode:
  data-theme="light"
  --md-sys-color-primary: var(--md-ref-palette-primary40)
  --md-sys-color-surface: var(--md-ref-palette-neutral99)
  
Dark mode:
  data-theme="dark"
  --md-sys-color-primary: var(--md-ref-palette-primary80)
  --md-sys-color-surface: var(--md-ref-palette-neutral6)

ref layer unchanged
preset bridge unchanged (still points at sys)
custom bridge unchanged (still points at sys)
component CSS unchanged (still points at sys / ax-comp)

Result: single attribute toggle changes everything downstream
```

### §3.4 What goes where

```txt
WP preset (editor-facing, user can select):
  color: primary, on-primary, primary-container, on-primary-container
         secondary, tertiary, error variants
         surface, on-surface, surface-variant, on-surface-variant
         outline, outline-variant
         background, on-background
         inverse-surface, inverse-on-surface, inverse-primary
  typography: fontFamilies (Roboto Flex, Noto Sans KR, Roboto Serif,
              Noto Serif KR, Roboto Mono)
              fontSizes (body, title, headline, display levels)
  spacing: spacingSizes (canonical scale)
  layout: contentSize, wideSize

WP custom (theme-managed, NOT in picker):
  state-layer opacity (hover / focus / pressed / dragged)
  shape corners (none / xs / s / m / l / xl / full)
  motion duration (short1-4, medium1-4, long1-4, extra-long1-4)
  motion easing (standard, emphasized, etc.)
  elevation levels
  density tokens
  z-index registry

NOT exposed to WordPress (md-sys / ax-comp direct):
  ref palette (primary 0-100, neutral 0-100, etc.)
  component-internal tokens (button container color, card outline width)
  computed-only values (state-layer mix recipes)
```

### §3.5 Insight

```txt
WordPress theme.json is not the source of design tokens.
It is an editor-facing projection of two layers:

  1. wp-preset = M3 sys values that benefit from user selection
  2. wp-custom = M3 system parameters that benefit from theme.json
                  exposure but should not appear in pickers

The runtime source of truth remains:
  md-ref (primitives) → md-sys (semantics)

This means dark mode is a sys-layer-only operation.
Toggling data-theme rewires sys → ref mappings,
and all four downstream layers (preset / custom / ax-comp / component CSS)
follow automatically.
```

### §3.6 Bridge direction principle

```txt
All bridges flow M3 → WP (downstream projection):
  --wp--preset--color--primary: var(--md-sys-color-primary)
  --wp--custom--axismundi--state-layer--hover: var(--md-sys-state-hover-opacity)

This is the Strict M3 mode direction defined in
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1.

Reverse bridges (WP → M3) are NOT used by the Pilot.
That direction is reserved for the Interpreter Plugin (BACKLOG #21).
```

---

## §4 — Implications for v3.6.0 Phase 5 close

### §4.1 Status framing

Phase 5 close should describe v3.6.0 as:

```txt
"Pilot v0 — scaffold + Wave 1 reverse mapping + block bridge MVP"

NOT:
"Pilot v0 — complete theme implementation"
```

Specifically what is included:
```txt
+ axismundi-pilot/ scaffolded
+ Wave 1 minus Carousel components consumed
+ Reverse mapping bridge MVP (Phase 2E)
+ WP core fill/outline absorbed into M3 styles
+ Table default/stripes contract enforced
+ prose blocks per-selector mapping
+ Computed-style validator gate
+ Font Library registration with fontFace.src
+ blocks.html / prose.html spec consumption
```

Specifically what is deferred:
```txt
- Dark mode infrastructure
- Token layering refactor (ref / sys.light / sys.dark / wp-bridge)
- wp-custom layer for state-layer / shape / motion / elevation
- Block bridge full coverage (only Button + prose blocks done)
- Ripple JS / interaction runtime expansion
- Interpreter Plugin (BACKLOG #21)
```

### §4.2 Lesson lock destinations

```txt
AGENTS.md and CLAUDE.md:
  Add Phase 3 portal/overlay smoke discipline (already from v3.5.18)
  Add WP core style reset principle
  Add computed-style audit gate
  Add build direction reversal note

PRE-ENTRY-ONTOLOGY-GROUNDING.md:
  Add v3.6.0 lessons section
  Capture forward vs reverse build direction
  Capture token layering 5-layer model
  Capture WP core style reset principle

bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md:
  Update §1 to reflect refined Stage 1 (Static slug bridge with
  wp-preset + wp-custom dual targets)
  Add Pilot v3.6.0 validation results
  Note ontology gap for wp-custom layer in existing binding ontology
```

### §4.3 BACKLOG actions

```txt
#20 Theme-only color customization policy:
  Status update: settings.color.custom = false confirmed in Pilot
  Final close trigger: shift to v3.6.1 (Token Architecture Refactor close)
  Reason: v3.6.0 partial close — full policy lock requires dark mode infra

#21 M3 Interpreter Plugin:
  Scope clarification needed
  Now that direction is locked (M3 → WP only),
  Interpreter Plugin handles the reverse direction (WP → M3)
  for user-driven HCT color generation
  Stage 2 / Stage 3 of FEEDBACK-AND-STRATEGY.md §1

#41 (new) Token Architecture Refactor (Pilot + Lab)
  Bucket: A — Architecture
  Source: v3.6.0 Phase 3 + GPT consultation insight
  Scope: tokens.css split into ref/sys.light/sys.dark/wp-preset/wp-custom
  Affects: both axismundi-lab and Pilot
  Target: v3.6.1

#42 (new) Block bridge full coverage
  Bucket: B / D — Component + theme integration
  Source: v3.6.0 Phase 2E MVP (only Button + prose blocks mapped)
  Scope: per-block M3 mapping for all WP core blocks the Pilot uses
  Target: v3.6.x parallel or v3.7.x
```

---

## §5 — Implications for v3.6.1 — Token Architecture Refactor

### §5.1 Cycle scope

v3.6.1 should:

```txt
1. Split tokens.css into layered files (ref / sys.light / sys.dark)
2. Author wp-preset.bridge.css (M3 sys → WP preset)
3. Author wp-custom.bridge.css (M3 system params → WP custom)
4. Update theme.json with settings.custom.axismundi.* entries
5. Add dark mode infrastructure (data-theme + theme switcher)
6. Validate Pilot in both light and dark mode (Wave 1 + WP blocks)
7. Update axismundi-lab tokens.css with same split (cross-cutting)
8. Close BACKLOG #20 (final theme-only policy lock)
9. Update FEEDBACK-AND-STRATEGY.md §1 to refined architecture
```

### §5.2 Cross-cutting scope

Token architecture refactor affects BOTH:

```txt
axismundi-lab (forward direction surface):
  tokens.css split
  style-guide.html dark mode option (was theme switcher in shell)
  lab module CSS validation under dark mode
  publish_styleguide.py file enumeration

axismundi-pilot (reverse direction surface):
  Pilot asset bridge picks up split tokens
  theme.json settings.custom.axismundi.* added
  wp-preset bridge CSS enqueued
  Dark mode toggle integrated
  Wave 1 reverse-mapped components validated under dark mode
```

This is the kind of cross-cutting cycle that BACKLOG bucket A
(Architecture / Constitution-level) holds.

### §5.3 Plan-first discipline

v3.6.1 plan-first must include:

```txt
User Request Log at top:
  Quote today's session insights verbatim
  Do not abstract "token layering" into vague "improvement"
  Specifically:
    "wp-preset = sys projection for editor-facing values"
    "wp-custom = sys projection for theme-managed values"
    "md-sys = source of truth, references md-ref"
    "dark mode = sys layer swap only"
    "build direction reverse for theme integration"

Phase 3 acceptance criteria:
  Light + dark mode both visually validated
  Each Wave 1 component + each WP core block in both modes
  Computed-style audit run in both modes
  Korean prose render in both modes
  Editor / front-end / pattern previews in both modes
```

---

## §6 — Cross-references

### §6.1 Existing records

```txt
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
  §1 Color picker concern and bridge strategy (original strategy)
  Stage 1 / Stage 2 / Stage 3 bridge stages (now refined)
  Three-tier architecture (now five-layer)

bindings/wordpress-material3/binding_summary.md
bindings/wordpress-material3/binding_map.json
bindings/wordpress-material3/gap_report.md
  Existing M3 ↔ WP binding ontology
  Today's insight reveals gap: wp-custom layer not yet modeled

core/wordpress/pilots/p4_theme_settings.json
core/wordpress/ontology.jsonld
  WordPress theme settings ontology
  Today's insight: settings.custom path needs ontology nodes

docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
  §3.4 plugin territory mapping
  §4 theme-can / plugin-should split (build direction insight aligned)

BACKLOG.md
  #20 Theme-only color customization policy
  #21 M3 Interpreter Plugin separation
  Both affected by today's refinement
```

### §6.2 v3.6.0 cycle artifacts

```txt
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-0-PLAN.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-0-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PRE-PHASE-1-REVIEW.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-1-PLAN.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-2A-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-2B-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-2C-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-2D-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-2E-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-FONT-AUDIT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md

tools/validators/validate_pilot_computed_styles.js
products/reference-implementations/axismundi-pilot/bridge/
  pilot-block-bridge.css
  pilot-block-bridge.js
```

### §6.3 Public references consulted today

```txt
WordPress Theme Handbook — theme.json global settings and styles
  https://developer.wordpress.org/themes/global-settings-and-styles/

WordPress Font Library documentation
  https://wordpress.org/documentation/article/the-font-library/

WordPress 6.5 Font Library dev note
  https://make.wordpress.org/core/2024/03/14/new-feature-font-library/

WordPress developer blog — adding custom settings in theme.json
  https://developer.wordpress.org/news/2023/08/adding-and-using-custom-settings-in-theme-json/

WordPress Theme Handbook — settings.custom
  https://developer.wordpress.org/themes/global-settings-and-styles/settings/custom/

Material Design 3 specifications
  https://m3.material.io/
  (used throughout Wave 1 cycles, no new reference needed today)
```

---

## §7 — One-line summary

```txt
axismundi has two complementary build directions:
  lab (forward: M3 → CSS) and theme (reverse: CMS → M3 overlay).
Token layering follows five layers
  (md-ref → md-sys → wp-preset + wp-custom → ax-comp)
with dark mode handled at the sys layer only.
WordPress core blocks must be reset before M3 mapping,
and WordPress tokens are FSE-facing projection, not source of truth.
```

---

## §8 — Author / context

```txt
Authored: 2026-05-18, in-session, before v3.6.0 Phase 5 close.
Authored by: Opus (Claude) at user's explicit request,
             after GPT third-party consultation on WordPress design tokens.
Trigger: v3.6.0 Phase 3 visual QA discoveries (Button border,
         Table stripes, prose selector mismatch) revealed deeper
         architectural questions about build direction and
         token layering.
Lesson lock provenance:
         v3.5.16 (User Request Log) → v3.5.17 (acceptance criteria) →
         v3.5.18 (portal/overlay smoke) → v3.6.0 (computed-style audit) →
         today (build direction + token layering)
         AI iteration weakness countered by explicit insight capture.
```
