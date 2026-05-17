# Button Module — v3.5.1 Phase 0 Report

> **Phase 0A** — first real validation of the v3.5.0 public-surface framework against a Wave 1 Component Full-Spec Module candidate. Inventory + dependency analysis + G1–G26 applicability + Phase 1 readiness verdict. **No implementation.**
>
> Source authority (light scan only):
> - `components.css §2 Button` (L122–L234)
> - `style-guide.html #components-button` (L624–L693)
> - `lab/modules/ripple/` directory
> - `stylesheets/icons.css` (icon-system surface)
> - `components.css §0 State-layer foundation` (L22–L79)

## §1 — Critical framing

```
Button is the first component module authored under the v3.5.0
framework. Its Phase 0 doubles as a stress-test of v3.5.0:
  - Does the 4-category framework fit Button cleanly?
  - Does the DISTINCT but COUPLED dependency principle hold up
    when applied to a concrete component-vs-infrastructure pair?
  - Are the G1-G26 promotion gates the right set?
  - Does the Phase 0 inventory accuracy rule (G13, snackbar lesson)
    catch the same class of error?
```

```
v3.5.0 = framework freeze
v3.5.1 Button = framework first-use test

If Button passes Phase 0 cleanly, the framework is validated against
a real case and Wave 1 has a stable baseline. If Button surfaces
framework gaps, those gaps go back to v3.5.x charter amendments
BEFORE more Wave 1 work compounds the issues.
```

## §2 — Baseline inventory (with G13 indented-selector accuracy)

### Range

| Surface | Range | Lines |
|---|---|---:|
| `components.css §2 Button` | L122–L234 | ~113 (incl. section header) |
| `style-guide.html #components-button` | L624–L693 | ~70 (1 section, no sub-anchors) |

### `components.css §2` baseline rule block inventory (corrected with G13 regex)

**11 rule blocks** found with indent-aware regex. Distinct base-selector tokens: **2** (`.ax-button`, `.ax-button-icon`).

| L# | Selector | Role |
|---:|---|---|
| 130 | `.ax-button` | Container: 40px height, inline-flex, label-large typescale, corner-full radius, motion tokens (shape-morph + color/opacity transitions) |
| 166 | `.ax-button:active` | §4.3 pressed morph — corner-full → corner-small |
| 171 | `.ax-button > .ax-button-icon` | Icon slot: `--comp-icon-size-sm`, inline-flex centered |
| 181 | `.ax-button.is-filled` | Primary fill — `--md-sys-color-primary` bg + `on-primary` text |
| 186 | `.ax-button.is-tonal` | Secondary container fill |
| 191 | `.ax-button.is-elevated` | Surface-container-low bg + primary text + level1 shadow |
| 196 | `.ax-button.is-elevated:hover` | Elevated hover → level2 shadow |
| 199 | `.ax-button.is-outlined` | Transparent bg + on-surface-variant text + outline 1px |
| 205 | `.ax-button.is-text` | Transparent bg + primary text + tighter padding |
| 212 | `.ax-button:disabled, .ax-button[aria-disabled="true"]` | Pattern A disabled (§0.8) — 10% surface fill + 38% text |
| 231 | `.ax-button.is-text:disabled, .ax-button.is-text[aria-disabled="true"]` | Text variant disabled exception — transparent bg |

### Baseline header inventory (per L122–L129 comment)

```
Scope: S size (40px) only this chunk.
       XS / M / L / XL deferred (Phase 1 expansion candidate or BACKLOG).
Variants: filled, tonal, elevated, outlined, text.
Default shape corner-full (round). On press, square morph
via spatial spring.
```

### `style-guide.html #components-button` specimen inventory

| Metric | Count |
|---|---:|
| Total `<button class="ax-button*">` elements | 25 |
| With `is-filled` | 5 |
| With `is-tonal` | 4 |
| With `is-elevated` | 4 |
| With `is-outlined` | 4 |
| With `is-text` | 3 (remainder are demo bare-button + with-state-layer) |
| With `has-state-layer` class applied | 20 (of 25) |
| With icon child (Material Symbols / `.ax-button-icon`) | 5 of 19 button tags |

5 of the 25 elements omit `has-state-layer` — likely intentional for demonstration of state-layer-off rendering. Phase 1 audit doc records this explicitly.

## §3 — `has-state-layer` mechanism + consumer-state classification (Phase 0 ontology refinement)

**Critical Phase 0 finding** (parallel to snackbar v3.4.10 Phase 0 correction):

```
Phase 0 refinement (NOT correction-of-existence):

There are TWO related but distinct interaction layers in this family:

  1. State-layer foundation (CSS-only, static, opacity-based)
     - Defined in components.css §0 (L22-L79, 6 rule blocks)
     - Provides hover / focus-visible / pressed opacity layer via
       .has-state-layer + ::before pseudo
     - Currently wired into 13 component surfaces

  2. Ripple effect (JS, click-position-anchored, animated)
     - Defined in lab/modules/ripple/ (lab-ripple.css + lab-ripple.js)
     - Provides animated wave anchored to click position
     - Currently NOT wired into baseline component surfaces

The MODULE-STATUS-MATRIX row #36 listing Button (and the other 12)
as "ripple/ consumers" is NOT WRONG — those components are valid
TARGET consumers of ripple/. The matrix simply did not distinguish
between target-consumer (designed dependency) and current-wired
(presently implemented).
```

```
Phase 0 정정:

state-layer foundation과 ripple/은 서로 다른 두 interaction layer다:

  1. State-layer foundation — CSS static, .has-state-layer + ::before
     hover/focus/pressed opacity. baseline에 13개 component가 적용됨.

  2. Ripple module — lab/modules/ripple/, JS click-position-anchored
     animated wave. baseline에는 wiring 안 됨.

Button은 ripple/의 유효한 target consumer다. 다만 현재 baseline에는
state-layer foundation만 적용되어 있고, ripple/는 향후 적용될 수 있는
enhanced interaction runtime이다. MODULE-STATUS-MATRIX 표기가 틀린
것이 아니라, consumer 상태 구분이 부족했다.
```

### Consumer-state classification (proposed amendment)

The matrix needs a **consumer-state column** to distinguish design intent from current implementation:

| Consumer state | Meaning |
|---|---|
| **current** | Provider is presently wired into this consumer (live in baseline or in a published lab module) |
| **target** | Provider is a designed dependency; consumer is meant to use it; not yet wired |
| **candidate** | Possible consumer; design decision not yet made |
| **none** | No dependency on this provider |

### Button's actual dependency profile (with consumer-state)

```
Button depends on:

  components.css §0 state-layer foundation        (Foundation)
    Consumer state: CURRENT
    - Opt-in via .has-state-layer class on the button
    - 20 of 25 specimens already wired in style-guide.html
    - Provides hover/focus-visible/pressed opacity layer
    - This is a Foundation (baseline-resident), not Infrastructure
      module — recorded as such in CHARTER §2

  lab/modules/ripple/                              (Infrastructure)
    Consumer state: TARGET
    - Material Web spec: ripple "communicates state via animated
      state layer" attached to interactive surfaces
    - Button is a designed target consumer
    - Currently NOT wired in baseline; Phase 1+ may add it as
      an enhanced interaction (no-JS fallback retained via §0 layer)
    - Phase 1 records this as a target dependency

  icon-system/                                     (Infrastructure)
    Consumer state: CURRENT (conditional — when icon slot is used)
    - .ax-button-icon slot + --comp-icon-size-sm + material-symbols-rounded
    - 5 of 19 button instances in style-guide use icons
```

### Phase 0 finding for `MODULE-STATUS-MATRIX.md` (amendment candidate, NOT correction)

```
The matrix is not wrong about Button being a ripple/ consumer.
The matrix is incomplete about consumer state.

Proposed amendment (NOT v3.5.1 work — handed to v3.5.x matrix
amendment release):

Add a "Consumer state" column to infrastructure provider rows
(or to a separate "Infrastructure dependency edges" sub-table)
recording per-consumer state values: current / target / candidate / none.

Button entry (under ripple/ row #36):
  before: "ripple/ consumers: Button #1, Icon button #2, ..."
  after:  Per-edge state recorded:
            ripple/ → Button #1: target (not yet wired)
            ripple/ → Icon button #2: target (not yet wired)
            ...

State-layer foundation §0 deserves its own row in a
"Foundation consumers" sub-table — it serves the same
13 consumers but as Foundation (baseline-resident), not as
Infrastructure (lab module). This is a meaningful CHARTER §2 tier
distinction (Foundation tier ≈ Baseline tier; Infrastructure ≈ Lab
tier with public dependency contract).
```

This refinement is the same class of finding as the snackbar v3.4.10 Phase 0 inventory correction — surfaced honestly here, scheduled for a v3.5.x matrix amendment release. It is NOT a Phase 1 blocker for Button.

### Impact on Button dependency declaration

Button's Phase 1 audit doc declares dependencies with consumer state explicit:

```
DEPENDS ON:
  1. components.css §0 state-layer foundation (FOUNDATION)
     Consumer state: CURRENT
     - opt-in via .has-state-layer class
     - hover/focus-visible/pressed opacity tokens via ::before
     - baseline-resident; Foundation tier per CHARTER §2

  2. lab/modules/ripple/ (INFRASTRUCTURE)
     Consumer state: TARGET
     - designed dependency; not yet wired in baseline
     - future enhanced interaction layer (animated click ripple)
     - opt-in path: lab/modules/button/ Phase 1+ may add a wiring
       option WITH no-JS fallback via §0 layer

  3. icon-system/ (INFRASTRUCTURE)
     Consumer state: CURRENT (conditional — icon slot only)
     - .ax-button-icon slot, --comp-icon-size-sm token,
       .material-symbols-rounded conventions

DOES NOT DEPEND ON: (no exclusion list needed — Button has 3 active
dependencies with explicit state per above)
```

## §3.5 — Material Web ripple spec ↔ Axismundi current implementation

### §3.5.1 — Material Web spec (per https://material-web.dev/components/ripple/)

```
Concept: State layer used to communicate component status via
         semi-transparent overlay on hover/press.

Attach methods (3):
  1. Parent element: <md-ripple> placed inside position:relative container
  2. Referenced: <md-ripple for="control-id">
  3. Imperative: ripple.attach(control)

Variants:
  - bounded   (fills container, overflow clipped)
  - unbounded (circular, centered on element)

Tokens:
  --md-ripple-hover-color   (default: --md-sys-color-on-surface)
  --md-ripple-pressed-color (default: --md-sys-color-on-surface)

Constraint: position: relative on container required.

A11y: visual only — no AT requirements.

API surface (md-ripple element):
  Properties: disabled, htmlFor, control
  Methods: attach(control), detach()
```

### §3.5.2 — Axismundi `lab/modules/ripple/` current implementation (v3.3.3)

```
Origin: Beer CSS intake (per BEER-CSS-INTAKE.md), refined to Axismundi-
        native code. NOT Material Web port.

Concept alignment: ✓ Same state-layer concept; visual overlay on press.

Attach method (1, different from Material Web):
  - document-level delegated pointerdown listener
  - allowlist match via HOST_SELECTOR (7 selectors)
  - implicit auto-attach when match succeeds
  - NO per-element <md-ripple>; NO for=; NO attach() imperative API

Variants:
  - bounded   ✓ implemented (.ax-ripple-host {overflow: hidden})
  - unbounded ✗ NOT implemented

Tokens:
  --ax-ripple-opacity: 0.16 (module-local)
  uses currentColor for the ripple wave (NOT --md-ripple-* tokens)

Constraint: ✓ .ax-ripple-host {position: relative}

A11y: ✓ aria-hidden="true" on injected ripple span.

Public API: ✗ none. Auto-applied via HOST_SELECTOR + forbidden-ancestor
            bail-out. No attach/detach.

Forbidden ancestors: ✓ .prose, .wp-block-post-content, .entry-content,
                        [contenteditable] — Material Web does not specify
                        this; Axismundi-specific (Charter §5).

Reduced motion: ✓ animation: none + opacity fade transition. Material
                  Web docs do not specify reduced-motion handling.
```

### §3.5.3 — Alignment table

| Aspect | Material Web | Axismundi v3.3.3 | Alignment |
|---|---|---|:---:|
| Concept (state-layer overlay) | ✓ | ✓ | ✓ matched |
| `position: relative` host requirement | ✓ | ✓ | ✓ matched |
| `disabled` handling | ✓ (property) | ✓ (`isDisabled()` helper) | ✓ matched |
| Bounded ripple | ✓ | ✓ | ✓ matched |
| **Unbounded ripple** | ✓ | ✗ | ✗ gap |
| **Per-element `<md-ripple>` API** | ✓ | ✗ (delegated model) | ✗ different model |
| **`for="control-id"` reference API** | ✓ | ✗ | ✗ gap |
| **Imperative `attach(control)` API** | ✓ | ✗ | ✗ gap |
| **`--md-ripple-hover-color` token** | ✓ default `on-surface` | ✗ uses `currentColor` | ✗ gap |
| **`--md-ripple-pressed-color` token** | ✓ default `on-surface` | ✗ uses `currentColor` | ✗ gap |
| Reduced motion | ✗ not specified | ✓ implemented | ✓+ Axismundi extra |
| Forbidden-ancestor bail-out | ✗ not specified | ✓ implemented | ✓+ Axismundi extra |

### §3.5.4 — Actual HOST_SELECTOR allowlist (7 components, not 13)

The `MODULE-STATUS-MATRIX.md` row #36 lists 13 ripple/ consumers (Button, Icon button, FAB family, FAB menu, Button group, Split button, Toolbar, Card action, App bar action, Nav bar, Nav rail, List item, Chip). Phase 0 light scan of `lab-ripple.js` reveals the actual allowlist contains **7 selectors**:

```js
const HOST_SELECTOR = [
  ".ax-button",        // → Button #1                 TARGET (in allowlist)
  ".ax-icon-button",   // → Icon button #2            TARGET (in allowlist)
  ".chip",             // → Chip #24                  TARGET (in allowlist) — DONE component
  ".ax-menu__item",    // → Menu #15                  TARGET (in allowlist)
  ".nav-bar__item",    // → Nav bar #12               TARGET (in allowlist)
  ".nav-rail__item",   // → Nav rail #13              TARGET (in allowlist)
  "[role='tab']"       // → Tabs #14                  TARGET (in allowlist)
].join(",");
```

The remaining 6 candidates from matrix row #36 (FAB family, FAB menu, Button group, Split button, Toolbar, Card action, App bar action, List item) are **NOT in the allowlist** — they are `CANDIDATE` state, not `TARGET`.

Refined matrix row #36 (proposal for v3.5.x amendment):

```
ripple/ consumer states (7 TARGET + N CANDIDATE):

  TARGET (allowlist-bound, in current lab-ripple.js):
    Button #1, Icon button #2, Chip #24, Menu #15,
    Nav bar #12, Nav rail #13, Tabs #14

  CURRENT (baseline-wired): 0
    (lab-ripple is loaded ONLY by lab-ripple-pattern.html;
     main style-guide.html does NOT load it; baseline Button surface
     has no animated-ripple wiring)

  CANDIDATE (designed/possible, not in current allowlist):
    FAB #3+#4, FAB menu #5, Button group #6, Split button #7,
    Toolbar #8, Card #9 (action), App bar #11 (action slots),
    List item under List #33

  NONE: other components (Dialog, Sheet, Snackbar, Tooltip, Loading,
        Progress, Slider, Switch, Checkbox, Radio, Text field,
        Search bar, Carousel, Divider, Badge, Avatar)
```

This refinement is the v3.5.x matrix amendment that Risk 1 routes to.

## §3.6 — Two-layer hierarchy (static state-layer + animated ripple)

```
The two interaction layers are NOT alternatives. They form a hierarchy:

  Layer 1 — Static state-layer (baseline-resident)
    Source: components.css §0 (L22-L79)
    Mechanism: .has-state-layer + ::before pseudo + opacity tokens
    Trigger: CSS :hover / :focus-visible / :active
    Cost: 0 JS, paint-only
    Coverage: 13 components opt in via class

  Layer 2 — Animated ripple (lab/modules/ripple/, progressive enhancement)
    Source: lab/modules/ripple/ (lab-ripple.css + lab-ripple.js)
    Mechanism: pointerdown → inject <span class="ax-ripple"> → animate
    Trigger: JS pointerdown event
    Cost: 1 delegated listener, transient DOM insertion per press
    Coverage: 7 components on HOST_SELECTOR allowlist (TARGET);
              0 baseline-wired (lab-internal)

Relationship:
  - Layer 1 is the DEFAULT — every interactive surface in baseline gets it
  - Layer 2 is OPT-IN ENHANCEMENT — on top of Layer 1, for richer feedback
  - Layer 1 functions WITHOUT Layer 2 (no-JS, reduced-data, slow JS load)
  - Layer 2 does NOT replace Layer 1 — they layer compositionally
```

Implication for Button: baseline currently has Layer 1 only. Layer 2 is a future enhancement that Phase 2+ MAY wire (with Layer 1 always present as the no-JS fallback). This is the same posture as `prefers-reduced-motion` — graceful degradation by layer.

## §3.7 — Axismundi Ripple v2 contract proposal (FUTURE — NOT v3.5.1 work)

Per Phase 0 framework first-use lesson: the current Beer CSS-derived ripple module is a **valid lab implementation** but lacks the public API contract needed for graduation to Wave 1+ infrastructure status. A v3.5.x+ ripple module amendment (informally "Ripple v2") would align with Material Web's contract while preserving Axismundi's namespace + WordPress-compatibility constraints.

### Why NOT just import `<md-ripple>` directly

```
WordPress theme/plugin environment constraints:
  - <md-ripple> is a custom element (Web Component) requiring JS load
  - Bundling/build dependency complicates theme distribution
  - WordPress theme directory review may flag custom-element dependencies
  - Editor/front-end synchronization needs separate handling
  - Graceful degradation (no-JS, JS-blocked) needs explicit handling

Axismundi posture (CHARTER §2 lab tier + §4 dependency principle):
  Public-tier modules MUST be theme-distribution-safe.
  CSS-first, JS as progressive enhancement.
  No external custom-element dependencies in public tier.
```

### Proposed v2 contract — 4 alignment axes

```
1. Official model alignment (Material Web)
   - ripple = animated state layer (concept)
   - visual only, no AT requirements
   - bounded / unbounded variants
   - position: relative host requirement
   - hover / pressed token separation

2. API alignment (Axismundi-native, spec-compatible)
   Declarative host:
     [data-ax-ripple]
   Optional target reference:
     data-ax-ripple-for="control-id"
   Imperative API:
     window.axRipple.attach(control)
     window.axRipple.detach(control)
   Allowlist (current model) retained as a default but configurable.

3. Token alignment
   - --ax-ripple-hover-color
   - --ax-ripple-pressed-color
   - --ax-ripple-hover-opacity
   - --ax-ripple-pressed-opacity

4. M3 bridge aliases (compatibility with downstream consumers
   expecting Material Web token names)
   - --md-ripple-hover-color   (aliased to --ax-ripple-hover-color)
   - --md-ripple-pressed-color (aliased to --ax-ripple-pressed-color)
```

### Scope clarification

```
v3.5.1 Button Phase 1:    DECLARES ripple/ as TARGET dependency
                          DOCUMENTS Material Web alignment gaps
                          DOES NOT execute Ripple v2 contract

v3.5.x (separate release): Ripple v2 contract design + implementation
                            (matrix amendment + module re-author)
                            Scheduled, not blocking Wave 1+ component work
                            Wave 1+ component modules author against
                            current ripple/ allowlist; Wave migration to
                            v2 contract is a separate sweep
```

## §3.8 — Current ripple module amendment candidates (recorded, NOT executed)

Phase 0 surfaces five amendment candidates for `lab/modules/ripple/`. Each is a v3.5.x+ scheduled item, NOT v3.5.1 Button Phase 1 work.

```
1. HOST_SELECTOR allowlist → data-ax-ripple opt-in
   Replace implicit class-match auto-attach with explicit data attribute.
   Current allowlist becomes an optional "default mapping" applied at
   ripple init time. Per-component opt-in via [data-ax-ripple] gives
   downstream authors control.

2. Bounded / unbounded variant split
   Current: bounded only (overflow:hidden host).
   v2:      bounded (Button, Card) + unbounded (Icon button, FAB) per
            Material Web convention.

3. Material token bridge
   Add --md-ripple-hover-color + --md-ripple-pressed-color tokens,
   internally aliased to --ax-ripple-*. Allows downstream consumers
   that expect Material Web token names to work without translation.

4. attach() / detach() imperative API
   Add window.axRipple.attach(control) + .detach(control) for plugin/
   editor/component code that needs explicit binding (e.g., dynamic
   component instances inserted after page load).

5. Consumer-state column in MODULE-STATUS-MATRIX
   Per Risk 1 — add CURRENT / TARGET / CANDIDATE / NONE state values
   to ripple/ row #36 (and ripple consumers' rows). v3.5.x matrix
   amendment.
```

## §4 — M3 §4 variant coverage analysis

The baseline header declares: "Variants: filled, tonal, elevated, outlined, text." Five variants confirmed in §2 and in style-guide specimens.

| M3 §4 variant | Baseline rule | Specimen count | Notes |
|---|:---:|---:|---|
| filled | `.is-filled` (L181) | 5 | Primary fill |
| tonal | `.is-tonal` (L186) | 4 | Secondary container fill (M3 §4.4 "Filled tonal") |
| elevated | `.is-elevated` (L191) + hover (L196) | 4 | Surface-container-low + level1 shadow + level2 on hover |
| outlined | `.is-outlined` (L199) | 4 | Outline 1px outline-variant |
| text | `.is-text` (L205) + disabled exception (L231) | 3 | Transparent bg + primary text |

### M3 size variants — DEFERRED

Baseline header line 125: "Scope: S size (40px) only this chunk. XS/M/L/XL deferred."

M3 §4 defines 5 sizes: XS, S, M, L, XL. Only S is in baseline. Phase 1 decision needed:

```
Option (a) Full-Spec module covers S only (matches baseline scope)
           → defer XS/M/L/XL to a baseline expansion release first

Option (b) Full-Spec module covers all 5 sizes
           → module owns size variants module-side (baseline stays S only)
           → BUT this would push Button toward dual-category
             (Full-Spec + Interaction? No, just expansion) and risk
             absorbing baseline territory

RECOMMENDATION: (a) — Phase 1 audits S-size only. Other sizes are
either a separate baseline release or a separate Wave 1+ sub-release.
Records the deferral in BUTTON-SPEC-AUDIT.md §exception inventory.
```

### M3 §4.3 shape morph (press)

Baseline implements corner-full → corner-small on `:active` using the spatial motion token (L154 + L168). This matches M3 §4.3.

### M3 §4.4 color tokens

Baseline uses M3 system tokens throughout (primary / on-primary / secondary-container / etc.). No literals. Phase 1 Measurement audit verifies token coverage formally.

## §5 — Infrastructure dependency declaration (Phase 1 template draft)

Per `PROMOTION-CRITERIA.md §6` and `PUBLIC-SURFACE-CHARTER.md §4`, Button module's audit doc will declare dependencies with explicit **consumer state** (see §3 amendment proposal).

### Button audit doc — dependency declaration text (Phase 1 will use this verbatim or close)

The Button audit doc's framing paragraph (drawn from user-provided wording):

```
Button is a valid target consumer of ripple/.

Current baseline Button uses the CSS state-layer foundation for
static hover/focus/pressed states. Animated ripple is not currently
wired into the baseline Button surface.

Button Phase 1 treats ripple/ as a target enhancement dependency,
not as a current baseline dependency. Future ripple work should
align its public contract with Material Web's ripple model:
state-layer concept, bounded/unbounded variants, explicit attach
semantics, and hover/pressed color tokens.
```

```
Button은 ripple/의 유효한 target consumer다.

현재 baseline Button은 CSS state-layer foundation으로 정적
hover/focus/pressed 상태를 처리한다. animated ripple은 아직 baseline
Button에 배선되어 있지 않다.

Button Phase 1에서는 ripple/을 current dependency가 아니라 target
enhancement dependency로 기록한다. 향후 ripple 작업은 Material Web의
ripple 모델 — state-layer 개념, bounded/unbounded, 명시적 attach 방식,
hover/pressed color token — 과 정렬해야 한다.
```

### Structured dependency declaration

```
### Dependencies

This module depends on:
- components.css §0 state-layer foundation (Foundation)
  - Consumer state: CURRENT
  - Public API used: .has-state-layer class, [data-state-layer] attribute
  - Consumer-side responsibilities: opt in by adding the class
  - DISTINCT but COUPLED contract:
    - this module owns: button container shape, variants, label/icon slot,
      disabled rendering, size convention (S)
    - state-layer foundation owns: hover/focus-visible/pressed opacity layer

- lab/modules/ripple/ (Infrastructure)
  - Consumer state: TARGET (not yet wired)
  - Designed enhanced interaction runtime: animated click-position-anchored
    state layer
  - Material Web spec alignment: ripple "communicates state via animated
    state layer" attached to interactive surfaces
  - Phase 1 records this as a target dependency; actual wiring is a
    Phase 2 decision (with no-JS fallback retained via state-layer
    foundation §0)
  - DISTINCT but COUPLED contract (when wired):
    - this module owns: button container, variants, slot layout, when to
      attach ripple host
    - ripple/ owns: animation timing, click-position math, fade-out,
      reduced-motion behavior

- icon-system/ (Infrastructure)
  - Consumer state: CURRENT (conditional — when icon slot is used)
  - Public API used: --comp-icon-size-sm token, .material-symbols-rounded class
  - Consumer-side responsibilities: place .ax-button-icon slot inside button,
    use icon-system conventions for the actual glyph
  - DISTINCT but COUPLED contract:
    - this module owns: icon SLOT placement, sizing token reference, layout
    - icon-system/ owns: font loading, glyph rendering, size token definition

This module is depended on by:
  - (none yet; future Button group, Split button, FAB family will use
    Button-like surfaces but each is its own component module, not
    a Button consumer per se)
```

## §6 — WordPress block-style mapping candidates (Phase 1 stub)

Phase 0 surfaces candidates; Phase 1 `BUTTON-WP-MAPPING.md` formalizes.

| WP block / context | Mapping path | Notes |
|---|---|---|
| `core/buttons` + `core/button` | Block style variations | Primary use case. Block style "Filled" / "Tonal" / "Elevated" / "Outlined" / "Text" maps to `.is-*` variants. theme.json block style additions only (no plugin needed). |
| `core/button` inside `core/buttons` group | Layout pattern | The `core/buttons` block already provides layout container; `core/button` maps to `.ax-button`. |
| `core/group` with action | Pattern + block style | When a "card with action" pattern includes a button, the button itself maps via the same block style variations. |
| Form submit button | Theme territory + plugin form integration | The button surface is theme; the form behavior is plugin/integration territory. Anti-pattern: button as form behavior. |
| Custom block needing button | Theme `<button class="ax-button is-*">` | If a custom block needs a button, it uses the same theme classes. No new component needed. |

Anti-patterns to record in Phase 1:

```
1. Hardcoded button literals (color, height, padding) in block templates
   → MUST use .ax-button.is-* + theme tokens
2. Custom button block styled outside the system
   → MUST use the same .ax-button surface
3. Button as form-submit primary behavior owner
   → Form behavior is plugin territory; button is just the surface
```

## §7 — G1–G26 promotion gate applicability

Per `PROMOTION-CRITERIA.md §7`. Button is Component Full-Spec category → G1–G10 apply universally; G11–G16 do NOT (Button is not an Interaction Runtime); G17–G21 do NOT (Button is not Record nor Plugin-territory); G22–G26 do NOT (Button is Consumer, not Provider).

### Applicable to Button (Phase 1+ work must satisfy ALL)

| Gate | Description | Phase 0 readiness |
|---:|---|:---:|
| G1 | `validate_theme_pilot.py` 1.000 PASS on working tree | ✓ baseline 1.000 maintained |
| G2 | Baseline untouched (components.css §2 + style-guide.html L624–L693) | ✓ targetable — no baseline edits planned |
| G3 | `publish_styleguide.py` runs cleanly | ✓ no current errors |
| G4 | Module artifacts present per §4.1 | (Phase 1+ deliverable) |
| G5 | CHANGELOG entry added | (Phase 5 deliverable) |
| G6 | Static Visual QA Gate PASS (0 actual issues) | (Phase 2+ deliverable) |
| G7 | Principle 1 compliance per pattern HTML | ✓ targetable — all controls real `<button>` |
| G8 | Principle 2 (native semantics) applied | ✓ — `<button type="button">` already standard |
| G9 | WCAG SC citations accurate (SC 2.5.8 AA / SC 2.5.5 AAA distinction) | ✓ — Phase 1 will cite per measurement (40×40 in baseline → SC 2.5.8 met) |
| G10 | 3-doc audit pattern complete (SPEC + MEASUREMENT + WP-MAPPING) | (Phase 1+ deliverable) |

### G13 (Phase 0 inventory accuracy) — already applied

```
G13 in Promotion §7.2 is for Interaction Runtime modules; Button is
Component Full-Spec, so G13 does not strictly apply by category.

HOWEVER, the SPIRIT of G13 (snackbar lesson — catch indented selectors,
catch foundation-vs-module conflation) WAS applied in this Phase 0,
and it surfaced the state-layer-foundation-vs-ripple-module conflation
in §3 above.

RECOMMENDATION: amend PROMOTION-CRITERIA.md §7 to add a universal
"Phase 0 accuracy" gate (call it G1.5 or extend G2) that applies to
ALL categories, not just Interaction Runtime. The snackbar lesson is
the same lesson regardless of category.

Phase 1 recommendation: open this as a charter amendment candidate.
v3.5.x charter amendment release (NOT v3.5.1 Button work itself).
```

### G22–G26 (Infrastructure) — not applicable

Button is a Consumer, not a Provider. G22–G26 apply only to infrastructure modules. Phase 1 audit will note this for completeness.

## §8 — Risk notes and Phase 1 recommendations

### Risk 1 — Matrix consumer-state column missing (HIGHEST PRIORITY)

```
Description: MODULE-STATUS-MATRIX row #36 (ripple/) lists 13 consumers
             including Button. This is NOT wrong — Button is a valid
             target consumer of ripple/. What is missing is per-edge
             consumer-state distinguishing CURRENT-wired from TARGET-
             designed-but-not-wired.

             Without this distinction, two different dependency states
             read the same way:
               - "Chip uses ripple/" (CURRENT — wired via has-state-layer
                 foundation? — needs verification; or maybe wired some
                 other way)
               - "Button uses ripple/" (TARGET — designed dependency,
                 not yet wired in baseline)

             Additionally, the §0 state-layer foundation is a separate
             interaction layer from ripple/ (static vs animated). The
             matrix could benefit from a Foundation dependency sub-table
             OR a per-edge mechanism column to disambiguate.

Impact: If Wave 1+ component modules declare dependencies without
        consumer state, the matrix loses precision over time. Risk
        of either "false dependency claims" (component cites ripple/
        without actually wiring it) or "missed dependencies" (component
        wires ripple/ but doesn't declare it).

Resolution path (NOT in Phase 1 Button — handed to v3.5.x):
  - Button Phase 1 audit declares dependencies WITH consumer state
    (CURRENT / TARGET / CANDIDATE / NONE per §5)
  - v3.5.x matrix amendment release adds consumer-state column
    to MODULE-STATUS-MATRIX.md infrastructure rows
  - Same amendment may add Foundation dependency sub-table to
    distinguish baseline-resident state-layer foundation from
    lab-module-resident ripple/

Phase 1 action: Button audit declares state-layer §0 (CURRENT),
                 ripple/ (TARGET), icon-system/ (CURRENT conditional).
                 Opens a BACKLOG entry for the v3.5.x matrix amendment.

What this Phase 1 MUST NOT do:
  - Remove Button from ripple/ consumer graph (Button IS a designed
    ripple consumer; the matrix already records this correctly)
  - Declare Button as "NOT ripple-dependent" (false framing)
  - Execute the matrix amendment itself (separate v3.5.x release)
```

### Risk 2 — Size variants scope (HIGH PRIORITY)

```
Description: Baseline §2 covers only S (40px). M3 §4 defines XS/S/M/L/XL.

Impact: If Button Full-Spec audit claims "complete M3 §4 coverage" while
        only S is implemented, the verdict is misleading.

Resolution: Phase 1 audit explicitly scopes "S size, 4 variants" and
            records other sizes as deferred. Matches baseline header
            statement (L125).

Phase 1 action: BUTTON-SPEC-AUDIT.md §exception-inventory records the
                size deferral with M3 spec reference.
```

### Risk 3 — `has-state-layer` opt-in vs default (MEDIUM PRIORITY)

```
Description: 5 of 25 specimens in style-guide.html omit has-state-layer.
             This may be intentional demo of state-layer-off rendering,
             OR an inconsistency that should be normalized.

Impact: Unclear what "default" Button rendering is. Pattern HTML for
        the module needs to settle this.

Resolution: Phase 1 decides: (a) state-layer is recommended-default
            and pattern HTML always includes it, recording the 5
            exceptions as documentation specimens; or (b) state-layer
            is opt-in and pattern HTML covers both with/without.

RECOMMENDATION: (a) recommended-default. M3 spec implies state-layer
                is part of the button's interactive feedback contract.
                The opt-in mechanism remains, but the default pattern
                HTML uses it.
```

### Risk 4 — Icon slot convention (LOW PRIORITY)

```
Description: Baseline §2 defines .ax-button > .ax-button-icon slot
             that sizes via --comp-icon-size-sm. Specimens use
             material-symbols-rounded as the actual glyph element.
             Convention: <button class="ax-button"><span class="material-symbols-rounded ax-button-icon">close</span>label</button>

Impact: Need to confirm this is the canonical pattern, and that other
        icon-bearing components (FAB, Chip) follow compatible slot
        conventions.

Resolution: Phase 1 records the canonical pattern in BUTTON-SPEC-AUDIT
            and references icon-system/ for the glyph element conventions.

Phase 1 action: Confirm canonical slot pattern; note for Wave 1
                consistency (Icon button + FAB use similar slots).
```

## §9 — Phase 1 readiness verdict

```
READY for Phase 1 entry, with 4 risks documented and resolved
either inside Button Phase 1 (Risk 2, 3, 4) or scheduled as a
v3.5.x charter amendment (Risk 1 — matrix correction).

Framework validation result:
  ✓ 4-category fits Button cleanly (Component Full-Spec, no dual)
  ✓ DISTINCT but COUPLED works for icon-system dependency
  ⚠ DISTINCT but COUPLED needs refinement on consumer-state axis —
    Button is a valid TARGET ripple/ consumer, not a "non-consumer";
    the matrix needs a consumer-state column to distinguish CURRENT-
    wired from TARGET-designed-but-not-wired (Risk 1 — framework
    amendment candidate, NOT framework failure)
  ✓ G1-G10 applicable; G11-G26 correctly NOT applicable
  ✓ G13 spirit caught Risk 1 (consumer-state ambiguity)
  ✓ Phase 0 accuracy approach surfaced 1 high-priority structural
    finding (Risk 1), 1 high-priority scope finding (Risk 2),
    and 2 minor pattern findings (Risk 3, 4)

The framework held up. The one finding it surfaced (Risk 1) is
a precision-improvement amendment, NOT a false-positive in the
matrix. Button IS a designed ripple/ consumer; the matrix correctly
records that intent.
```

### Phase 1 entry conditions

Phase 1 entry is approved with these constraints:

```
1. Phase 1 Button audit MUST distinguish dependency states explicitly:
     - components.css §0 state-layer foundation: CURRENT (wired today)
     - lab/modules/ripple/:                       TARGET  (designed
                                                  dependency; not yet
                                                  wired in baseline;
                                                  Phase 2 may add it
                                                  as enhanced interaction
                                                  with no-JS fallback
                                                  via §0)
     - icon-system/:                              CURRENT (conditional;
                                                  when icon slot is used)
   Do NOT remove Button from ripple/ consumer graph.
   Do NOT declare Button as "NOT ripple-dependent".

2. Phase 1 SPEC audit MUST scope to S size + 4 variants + bare,
   recording XS/M/L/XL deferral honestly.

3. Phase 1 pattern HTML MUST use has-state-layer as default,
   with an opt-out demo specimen for completeness.

4. Phase 1 MUST open a BACKLOG entry for the v3.5.x matrix
   amendment (Risk 1 resolution — adding consumer-state column).
   Button Phase 1 itself does NOT execute the matrix amendment.

5. Phase 1 G13 spirit application — extend the "Phase 0 inventory
   accuracy" practice across all categories, not just Interaction
   Runtime. This is a future charter amendment, NOT a Phase 1 blocker.
```

## §10 — What this Phase 0 does NOT do

```
NOT in v3.5.1 Phase 0:
  - Implement the Button module
  - Edit components.css §2 (baseline untouched per CHARTER §3.2)
  - Create new runtime behavior
  - Author the SPEC / MEASUREMENT / WP-MAPPING audit docs
  - Author lab-button.css / lab-button-pattern.html
  - Execute the Risk 1 matrix correction
  - Start naming sweep / data-theme auto / theme.json / pilot work

All of the above happens in:
  - Phase 1 (audit skeleton + dependency declaration + risk recording)
  - Phase 2 (implementation)
  - Phase 3+5 (verdict + mechanical close)
  - v3.5.x charter amendment release (Risk 1 matrix correction)
  - Wave 1 subsequent items (Icon button, FAB, etc.)
```

## §11 — One-line summary

```
v3.5.1 Phase 0 validates the v3.5.0 framework against Button #1
as the first Wave 1 case: 4-category fits (Component Full-Spec),
DISTINCT but COUPLED holds for icon-system, baseline §2 inventory
is 11 rule blocks across 2 distinct base selectors covering 5
variants in S size only. Material Web ripple spec alignment audit
(§3.5) shows the current lab/modules/ripple/ is a Beer-CSS-derived
delegated-listener model that captures the state-layer concept but
lacks bounded/unbounded split, --md-ripple-* tokens, and explicit
attach API; the actual HOST_SELECTOR allowlist is 7 components, not
the 13 listed in MODULE-STATUS-MATRIX row #36. Button is a valid
TARGET ripple/ consumer (in allowlist; baseline-not-wired); a future
Ripple v2 contract (§3.7) would align with Material Web's spec
while preserving Axismundi namespace + WordPress compatibility.
All this is scheduled as v3.5.x amendments — NOT v3.5.1 Phase 1
blockers.
```
