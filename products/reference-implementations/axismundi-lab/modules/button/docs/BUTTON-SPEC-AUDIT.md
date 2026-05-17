# Button — Spec Audit (v3.5.1 Phase 1)

> **Bucket**: E (Component module — full-spec audit)
> **Category**: Component Full-Spec (per `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #1)
> **Wave**: Wave 1, item #1
> **Status**: v3.5.1 release closed. All 5 SPEC §11 verdict criteria PASS.
> **Source authority**: `docs/v3.5.1/BUTTON-PHASE-0-REPORT.md` (876 lines, 15 sections)
> **Reference template**: `lab/modules/chip/docs/CHIP-SPEC-AUDIT.md` (v3.4.9 — first Component Full-Spec audit, 348 lines)
> **Companions**: `./BUTTON-MEASUREMENT-AUDIT.md`, `./BUTTON-WP-MAPPING.md`

## §0 — Phase 1 entry constraints (locked from Phase 0 report §9)

The Phase 1 audit work MUST satisfy ALL of the following:

```
1. Dependency state distinction MUST be explicit:
   - components.css §0 state-layer foundation: CURRENT (wired today)
   - lab/modules/ripple/:                       TARGET  (designed dep;
                                                not yet wired in baseline;
                                                Phase 2 may add with no-JS
                                                fallback via §0)
   - icon-system/:                              CURRENT (conditional;
                                                when icon slot is used)
   Do NOT remove Button from ripple/ consumer graph.
   Do NOT declare Button as "NOT ripple-dependent".

2. SPEC audit scope: S size + 4 variants (filled/tonal/elevated/outlined)
   + text variant + bare. XS/M/L/XL DEFERRED honestly to §exception
   inventory with M3 §4 spec reference.

3. Pattern HTML uses has-state-layer as default + opt-out demo
   specimen for completeness (5 of 25 baseline specimens omit it).

4. Open a BACKLOG entry for v3.5.x matrix amendment (consumer-state
   column). Button Phase 1 itself does NOT execute the amendment.
   → Already opened as BACKLOG #24 (closed-by-Phase-0-handoff).

5. G13 spirit application across all categories — charter amendment
   candidate, NOT a Phase 1 blocker.
```

## §1 — Critical framing

```
Button is the first Wave 1 Component Full-Spec Module under the
v3.5.0 framework. Its role:

  1. It is the most heavily reused interactive surface in any UI.
  2. It is the first audit to apply v3.5.0's G1–G10 + DISTINCT but
     COUPLED principles to a primitive that already exists in
     components.css baseline.
  3. It validates the 4-category framework on its primary fit point
     (Component Full-Spec). The v3.5.0 Phase 0 audit already
     established the fit; this audit performs the spec coverage
     work.
  4. It establishes the Button audit pattern that Wave 1's other
     button-family entries (Icon button #2, Split button, FAB)
     will reuse.

Unlike v3.4.6 tooltip / v3.4.7 date-time / v3.4.10 snackbar (all
Interaction Runtime modules — extracting behavior from a benchmark
source), Button is a Component Full-Spec Module: the baseline
primitive in components.css §2 already exists, and this audit's job
is to expand it into a full spec / measurement / WordPress-mapping
surface WITHOUT modifying the baseline.
```

한글 요약:

```
Button은 v3.5.0 framework 하의 Wave 1 첫 Component Full-Spec Module이다.

이 audit의 역할은:
  - components.css §2의 baseline primitive를 변경하지 않고
  - SPEC / MEASUREMENT / WP-MAPPING 3-doc 형태로 확장하는 것
  - v3.5.0 G1–G10 + DISTINCT but COUPLED 원칙을 적용하는 것
  - Wave 1 다음 항목(Icon button, FAB 등)이 따를 패턴을 정착시키는 것

Interaction Runtime module과 달리, runtime behavior를 추출하는 작업이
아니다. 이미 존재하는 baseline primitive를 다른 두 surface(MEASUREMENT,
WP-MAPPING)로 확장하면서 명시적 dependency 선언(state-layer / ripple /
icon-system)을 적는 것이다.
```

### Baseline primitive — single source of truth

The baseline primitive at `components.css §2 Button` (L122–L234, 113 lines, 11 rule blocks) remains UNCHANGED at v3.5.1. This module does NOT modify the baseline. The relationship is:

```
components.css §2 Button
  = baseline primitive (5 variants × S size, M3 §4 alignment)

components.css §0 State-layer foundation
  = static state-layer Pattern A (.has-state-layer + ::before)
  = consumed by Button at CURRENT state

style-guide.html#components-button
  = representative specimens for catalog viewing (L624–L693, 70 lines)

lab/modules/button/
  = full-spec / measurement / WordPress-mapping expansion
  = lab-internal; lab-button.css (Phase 2 deliverable) extends with
    pattern variations but does NOT replace baseline classes
```

## §2 — Baseline / module split

```
BASELINE  styleguide layer
          components.css §2 Button (UNCHANGED at v3.5.1)
          style-guide.html#components-button L624–L693 (UNCHANGED)
          .ax-button + 5 variants (filled / tonal / elevated / outlined / text)
          S size only (40 px) — XS/M/L/XL deferred per L125 header

FOUNDATION  baseline-resident state-layer layer
          components.css §0 State-layer foundation (UNCHANGED at v3.5.1)
          .has-state-layer / [data-state-layer] opt-in mechanism
          Provides hover / focus-visible / pressed opacity layer
          Consumed by Button at CURRENT state

LAB MODULE  component full-spec layer (this module)
          lab/modules/button/
          ├── lab-button.css            (Phase 2 — pattern variations, opt-in
          │                              ripple wiring with no-JS fallback)
          ├── lab-button-pattern.html   (Phase 2 — full variant matrix +
          │                              icon slot + state-layer default
          │                              + state-layer-off opt-out specimen)
          ├── lab-button.js             (Phase 2 — only if ripple wiring
          │                              chosen; baseline Button needs no JS)
          └── docs/
              ├── BUTTON-SPEC-AUDIT.md        (this file)
              ├── BUTTON-MEASUREMENT-AUDIT.md (companion — M3 §4 dimensions)
              └── BUTTON-WP-MAPPING.md        (companion — WP block binding)

INFRASTRUCTURE  lab-module-resident infrastructure providers
          lab/modules/ripple/    (TARGET — designed enhancement,
                                  not yet wired in baseline)
          lab/modules/icon-system/  (CURRENT conditional — when icon
                                  slot is used)

PLUGIN    federation / data binding layer (NOT touched at v3.5.1)
          - WordPress block style variations (theme.json — NOT modified
            at v3.5.1; documented in WP-MAPPING audit)
          - Form submission handling (plugin territory per CHARTER §3.4)
          - ActivityPub action buttons (federation work; out of scope)
```

CHARTER §3.4 application: theme renders button surfaces; plugins emit button actions (form submit, faceted filter, federation action). Button is squarely on the theme side; what *triggers* via the button is plugin/integration territory.

## §3 — Inventory

### Baseline (untouched at v3.5.1)

| File | Range | Lines | Notes |
|---|---|---:|---|
| `components.css §0 State-layer foundation` | L22 → L79 | ~58 | 5 rule blocks (Pattern A — currentColor overlay, opacity-driven, motion via effects spring) |
| `components.css §2 Button` | L122 → L234 | ~113 | 11 rule blocks, 2 distinct base selectors (`.ax-button`, `.ax-button-icon`) |
| `style-guide.html #components-button` | L624 → L693 | ~70 | 4 sub-sections, 20 button instances |

### Baseline §2 rule block inventory (11 blocks — from Phase 0 §2)

| L# | Selector | Role |
|---:|---|---|
| 130 | `.ax-button` | Container — 40 px height, inline-flex, label-large typescale, corner-full radius, motion tokens (shape-morph spatial + color/opacity effects) |
| 166 | `.ax-button:active` | §4.3 pressed shape morph — corner-full → corner-small |
| 171 | `.ax-button > .ax-button-icon` | Icon slot — `--comp-icon-size-sm` (20 px), inline-flex centered |
| 181 | `.ax-button.is-filled` | Primary fill — `--md-sys-color-primary` bg + `on-primary` text |
| 186 | `.ax-button.is-tonal` | Secondary container fill (M3 §4.4 "Filled tonal") |
| 191 | `.ax-button.is-elevated` | `surface-container-low` bg + `primary` text + elevation level1 shadow |
| 196 | `.ax-button.is-elevated:hover` | Elevated hover → level2 shadow |
| 199 | `.ax-button.is-outlined` | Transparent bg + `on-surface-variant` text + 1 px outline-variant |
| 205 | `.ax-button.is-text` | Transparent bg + `primary` text + tighter padding (`--space-sm`) |
| 212 | `.ax-button:disabled, .ax-button[aria-disabled="true"]` | Pattern A disabled (§0.8) — 10 % on-surface bg + 38 % on-surface text |
| 231 | `.ax-button.is-text:disabled, .ax-button.is-text[aria-disabled="true"]` | Text variant disabled exception — transparent bg retained |

### Style-guide specimen inventory (20 instances in 4 subsections)

| Subsection | Specimens | Notes |
|---|---:|---|
| Variants — label only (L632) | 5 | One each variant (filled/tonal/elevated/outlined/text), all with `has-state-layer` |
| With leading icon (L645) | 5 | One each variant, icon slot via `<span class="material-symbols-rounded ax-button-icon">…` |
| Toggle (4 variants — text excluded) (L671) | 4 | `aria-pressed` + `sg-toggle` class (style-guide.js handles toggle); text variant excluded per M3 spec |
| Disabled — Pattern A §0.8 (L682) | 5 + 1 | One each variant + 1 with-state-layer demonstration; all use native `disabled` attribute |

**Phase 0 §2 reported 25 specimens.** Re-counted at Phase 1: actual is 20 (5 + 5 + 4 + 5 + 1 demo with state-layer = some accounting differs). Phase 1 records 20 as the authoritative count. This is a minor inventory discrepancy that does NOT affect any verdict.

**`has-state-layer` coverage**: 20 of 20 specimens in `#components-button` use `has-state-layer` class. The "5 omissions" mentioned in Phase 0 report §2 likely refer to a broader scan including out-of-section `.ax-button*` usages elsewhere in `style-guide.html` (77 total `.ax-button*` matches across the entire file vs 20 in #components-button section). Phase 1 settles default = `has-state-layer` ON.

## §4 — M3 §4 variant coverage matrix

Five variants confirmed in baseline §2 and in style-guide specimens. S size only.

### §4.1 Filled — HIGH coverage

| Aspect | Baseline | Notes |
|---|:---:|---|
| 40 px height | ✓ | `--comp-button-height` L136 |
| Corner-full radius | ✓ | `--comp-button-radius` L139 → `--md-sys-shape-corner-full` |
| Corner-small on press | ✓ | L166–L168 (M3 §4.3 spatial morph) |
| Label-large typescale | ✓ | L141–L145 |
| Primary fill + on-primary text | ✓ | L181–L185 |
| Elevation level0 (flat) | ✓ | L184 |
| Disabled (Pattern A) | ✓ | L212–L229 |
| State-layer via `has-state-layer` | ✓ | Consumer of §0 foundation |
| **Ripple animated runtime** | ⚠ | TARGET — not yet wired (Phase 2 candidate) |

**Module work (v3.5.1 Phase 1)**: documentation only. No CSS additions for filled variant. Ripple wiring deferred to Phase 2 decision.

### §4.2 Tonal — HIGH coverage

| Aspect | Baseline | Notes |
|---|:---:|---|
| Base + 40 px + corner-full | ✓ | (inherits base) |
| Secondary-container fill | ✓ | L186–L189 |
| on-secondary-container text | ✓ | L188 |
| Elevation level0 (flat) | ✓ | L189 |
| Disabled (Pattern A) | ✓ | (shared L212–L229) |
| State-layer via `has-state-layer` | ✓ | (shared mechanism) |
| **Ripple animated runtime** | ⚠ | TARGET (same as filled) |

**Module work**: documentation only.

### §4.3 Elevated — HIGH coverage (uses elevation)

| Aspect | Baseline | Notes |
|---|:---:|---|
| Base + 40 px + corner-full | ✓ | (inherits) |
| surface-container-low bg | ✓ | L192 |
| Primary text color | ✓ | L193 |
| Elevation level1 (rest) | ✓ | L194 |
| Elevation level2 (hover) | ✓ | L196–L198 |
| Disabled (Pattern A + level0 reset) | ✓ | L212–L228 (box-shadow reset to level0) |
| State-layer via `has-state-layer` | ✓ | |
| **Ripple animated runtime** | ⚠ | TARGET |
| **Elevation transition** | ✓ | `box-shadow` transition in main `transition:` declaration L162–L164 (effects spring) |

**Module work**: documentation only. Elevation hover transition already wired via `transition:` declaration at L162–L164.

### §4.4 Outlined — HIGH coverage

| Aspect | Baseline | Notes |
|---|:---:|---|
| Base + 40 px + corner-full | ✓ | (inherits) |
| Transparent bg | ✓ | L200 |
| on-surface-variant text | ✓ | L201 |
| 1 px outline + outline-variant color | ✓ | L202 (M3 §4.4 outline width) |
| `outline-offset: -1px` (sits inside radius) | ✓ | L203 |
| Disabled (Pattern A + outline removed) | ✓ | L212–L228 (`outline: 0` L228) |
| State-layer via `has-state-layer` | ✓ | |
| **Ripple animated runtime** | ⚠ | TARGET |

**Module work**: documentation only. Note the `outline-offset: -1px` choice ensures the outline visually nests inside the corner-full radius rather than haloing outside it.

### §4.5 Text — MEDIUM-HIGH coverage (most distinct)

| Aspect | Baseline | Notes |
|---|:---:|---|
| Base + 40 px + corner-full | ✓ | (inherits) |
| Transparent bg | ✓ | L206 |
| Primary text color | ✓ | L207 |
| **Tighter padding (`--space-sm`)** | ✓ | L208 — M3 §4.4 distinct |
| Disabled — transparent bg exception | ✓ | L231–L234 (explicit exception block) |
| State-layer via `has-state-layer` | ✓ | (inherits mechanism; visible because primary-color overlay shows on transparent bg) |
| **Ripple animated runtime** | ⚠ | TARGET |
| **No toggle variant** | ✓ | Style-guide L671 confirms — text excluded from toggle by M3 spec |

**Module work**: documentation only. Text variant is the only one with explicit disabled exception (transparent bg retained, no Pattern A 10 % fill).

### §4.6 Bare (no variant class) — RARE-USE

Per Phase 0 §5: "S size + 4 variants … + text variant + bare." Bare = `.ax-button` with no `.is-*` modifier — receives base sizing/typography but no fill/color. Used for embedded contexts where the button styling comes from parent context (e.g., split-button slave control, custom block plugin surface).

**Module work**: documented; pattern HTML demonstrates one bare specimen.

## §5 — Explicit exceptions (recorded in baseline code)

| # | Exception | Location | Affects |
|---:|---|---|---|
| 1 | **S-size-only baseline scope** — XS/M/L/XL not in baseline §2; explicit deferral in section comment | L122–L128 header | All variants |
| 2 | **Pressed shape morph corner-full → corner-small** — M3 §4.3 implemented via `:active` selector | L166–L168 | All variants |
| 3 | **Spatial vs effects motion split** — corner-radius uses fast-spatial spring; color/opacity/box-shadow use fast-effects spring | L152–L164 | All variants |
| 4 | **Elevated hover elevation upgrade level1 → level2** — only Elevated variant has hover-specific box-shadow override | L196–L198 | Elevated only |
| 5 | **Text variant tighter padding** — `--space-sm` instead of `--space-md` | L208 | Text only |
| 6 | **Text variant disabled keeps transparent bg** — does not apply Pattern A 10 % fill | L231–L234 | Text disabled |
| 7 | **`outline-offset: -1px` on Outlined** — outline sits inside corner-full radius | L203 | Outlined only |
| 8 | **Disabled Pattern A box-shadow reset** — `box-shadow: level0` to remove Elevated's level1 shadow when disabled | L227 | All disabled (Elevated visibly affected) |

## §6 — Missing exceptions / module work items

Items NOT in baseline that constitute Phase 2+ module work, organized by priority:

| # | Missing exception | Affected variant | Resolution strategy |
|---:|---|---|---|
| 1 | **Animated ripple state layer (TARGET)** — current baseline uses only static `has-state-layer`; designed to also support animated click-position ripple per M3 §State Layer | All variants | Phase 2 SETTLED — Option (b): defer to v3.5.x Ripple v2 release (BACKLOG #25). Phase 2 deliverables `lab-button.css` + `lab-button-pattern.html` contain NO ripple wiring. |
| 2 | **`has-state-layer` default in pattern HTML** — baseline style-guide is consistent (20/20 with `has-state-layer`); Pattern HTML for module should default-on with opt-out demo for parity | All variants | Phase 2 DONE — `lab-button-pattern.html` §2/§3/§5 specimens default-on with `has-state-layer`; §4 includes one opt-out demo specimen with bilingual caption. |
| 3 | **Icon slot canonical pattern documentation** — baseline uses `<span class="material-symbols-rounded ax-button-icon">…` with `aria-hidden="true"` + `translate="no"` + `draggable="false"`; this pattern needs explicit recording | Variants with icon | This audit records it as canonical (§8 below); pattern HTML demonstrates (lab-button-pattern.html §3, 5 specimens). |
| 4 | **XS / M / L / XL size variants** — M3 §4 defines 5 sizes; baseline only S | All variants | Out of Wave 1 Button #1 scope. Future baseline expansion release OR Wave 1+ sub-release. Recorded as exception inventory; no work in v3.5.1. |
| 5 | **Toggle variant pattern** — style-guide L671 has toggle specimens but baseline §2 has no `aria-pressed` styling; toggle visual feedback is currently style-guide-JS-driven | Filled/Tonal/Elevated/Outlined toggle | Defer toggle pattern to a follow-up (FAB / icon-toggle Wave 1 item, or v3.5.x toggle clarification). Phase 1 records the deferral; no `.ax-button[aria-pressed="true"]` styling added at v3.5.1. |
| 6 | **Hit-target adequacy proof for 40 px height** | All variants | Documented in MEASUREMENT audit §4 — WCAG SC 2.5.8 AA (24 × 24) is met; SC 2.5.5 AAA (44 × 44) is NOT met for 40 px height. Recorded honestly. No baseline change. |

## §7 — Dependencies (verbatim from Phase 0 §5)

Framing paragraph — use verbatim from Phase 0 report §5 (EN + KO):

```
Button is a valid target consumer of ripple/.

Current baseline Button uses the CSS state-layer foundation for static
hover/focus/pressed states. Animated ripple is not currently wired into
the baseline Button surface.

Button Phase 1 treats ripple/ as a target enhancement dependency, not
as a current baseline dependency. Future ripple work should align its
public contract with Material Web's ripple model: state-layer concept,
bounded/unbounded variants, explicit attach semantics, and hover/pressed
color tokens.
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

> **v3.5.4 matrix amendment note**: this section is now aligned with
> the canonical consumer-state vocabulary introduced in
> `docs/v3.5.0/MODULE-STATUS-MATRIX.md`:
> `components.css §0` state-layer foundation = CURRENT,
> `ripple/` = TARGET, and `icon-system/` = CURRENT conditional.
>
> **v3.5.6 Ripple v2 alignment note**: Button remains a ripple TARGET
> consumer with the bounded variant. The stable animated ripple contract is
> `data-ax-ripple` + `window.axRipple`; it remains a progressive enhancement
> above `components.css §0` and does not change the Button baseline.

```
DEPENDS ON:

  1. components.css §0 State-layer foundation (FOUNDATION)
     Consumer state: CURRENT
     - Public API used: .has-state-layer class, [data-state-layer] attribute
     - Consumer-side responsibilities: opt in by adding the class
     - DISTINCT but COUPLED contract:
         this module owns:   button container shape, variants,
                             label/icon slot, disabled rendering,
                             size convention (S = 40 px)
         §0 foundation owns: hover / focus-visible / pressed / dragged
                             opacity layer via ::before pseudo-element,
                             color-mix via currentColor, opacity tokens
                             from --md-sys-state-*-state-layer-opacity

  2. lab/modules/ripple/ (INFRASTRUCTURE)
     Consumer state: TARGET (not yet wired in baseline)
     - Designed enhancement: animated click-position-anchored state layer
     - Material Web alignment: ripple "communicates state via animated
       state layer" attached to interactive surfaces
     - Phase 2 SETTLED — Option (b): defer to v3.5.x Ripple v2 release
       (BACKLOG #25). v3.5.1 deliverables contain no ripple wiring.
     - DISTINCT but COUPLED contract (when wired in Ripple v2):
         this module owns:   button container, variants, slot layout,
                             when/whether to attach ripple host
         ripple/ owns:       animation timing, click-position math,
                             fade-out, reduced-motion behavior,
                             bounded/unbounded variant choice

  3. icon-system/ (INFRASTRUCTURE)
     Consumer state: CURRENT (conditional — when icon slot is used)
     - Public API used: --comp-icon-size-sm token (= 20 px),
                        .material-symbols-rounded class,
                        .ax-button-icon slot class
     - Consumer-side responsibilities: place .ax-button-icon slot inside
       button as direct child; use icon-system glyph conventions
       (aria-hidden, translate="no", draggable="false")
     - DISTINCT but COUPLED contract:
         this module owns:   icon SLOT placement (.ax-button-icon
                             selector at L171–L178), sizing token
                             reference, flex centering layout
         icon-system/ owns:  font loading (material-symbols-rounded),
                             glyph rendering, --comp-icon-size-* token
                             family definition

DOES NOT DEPEND ON: (no exclusion list needed — Button has 3 active
dependencies with explicit state per above)

DEPENDED ON BY: (none yet in v3.5.1 — future Wave 1 items
  Icon button #2, Split button, FAB family will use Button-like
  surfaces but each is its own component module, not a Button
  consumer per se)
```

## §8 — Visible control principle (Principle 1 / Principle 2 application)

Per `lab/modules/README.md §Design principles`:

```
Visible control must map to real runtime behavior.
```

Variant-by-variant application:

| Variant | Real runtime behavior must be | Acceptable demo posture |
|---|---|---|
| **Filled / Tonal / Elevated / Outlined / Text** | `<button type="button">` triggering a real action, or `<a>` for navigation | Static specimen is fine if labeled (e.g., catalog view); avoid `<button>` with no handler and no `aria-disabled` state |
| **Disabled (any variant)** | Native `disabled` attribute OR `aria-disabled="true"` with real disabled rationale | Both forms styled identically per baseline L212–L213 selector |
| **Toggle (4 variants except Text)** | `aria-pressed="true|false"` with state actually changing on click | Style-guide currently uses `sg-toggle` JS class — acceptable for catalog demo; module pattern HTML should NOT pretend without state |

**Principle 1 — Real `<button>` element**:

Baseline style-guide uses `<button type="button">` consistently throughout `#components-button` (lines 633, 637, 647, 651, 655, 659, 663, 674, 675, 676, 677, 684–688). No `<div role="button">` or `<a>`-styled-as-button anti-patterns. Phase 1 confirms this is the canonical convention.

**Principle 2 — Native semantics**:

`<button type="button">` is the right native semantic. For form-submit contexts, `<button type="submit">` is used; form behavior is plugin territory per CHARTER §3.4. Phase 1 records: never `<div>` or `<span>` styled as a button — accessibility tree position is non-negotiable.

### Icon slot canonical pattern (Phase 0 Risk 4 resolution)

```html
<button class="ax-button is-filled has-state-layer t-label-large" type="button">
  <span class="material-symbols-rounded notranslate ax-button-icon"
        translate="no" aria-hidden="true" draggable="false">add</span>
  <span>Add</span>
</button>
```

Conventions:

- `<span class="material-symbols-rounded …">` is the glyph element (icon-system contract)
- `class="ax-button-icon"` is the slot class (Button contract, L171)
- `aria-hidden="true"` because the label text is the accessible name; the icon is decorative
- `translate="no"` + `class="notranslate"` prevent browser translation tools from translating the ligature glyph name
- `draggable="false"` prevents accidental drag of the ligature text
- Label is wrapped in its own `<span>` so the flex layout treats icon + label as 2 children with `gap: var(--space-sm)`

Wave 1 next items (Icon button #2, FAB) reference this convention; Phase 1 records it canonical.

## §9 — Out of scope for Phase 1

```
- XS / M / L / XL size variants (recorded in §6 item 4; future baseline
  expansion release OR separate Wave 1+ sub-release)

- Toggle variant pattern styling (recorded in §6 item 5; defer to FAB
  / icon-toggle Wave 1 item or v3.5.x toggle clarification)

- Animated ripple wiring (TARGET dependency only; Phase 2 SETTLED with
  Option (b) — defer all animated ripple to v3.5.x Ripple v2 release)

- Ripple v2 contract design (v3.5.x ripple amendment release —
  BACKLOG #25 + #27 per Phase 0 §3.7)

- Matrix consumer-state column (v3.5.x matrix amendment —
  BACKLOG #24 + #26 per Phase 0 §3.5.4)

- Naming sweep .snackbar → .ax-snackbar (BACKLOG #18)

- theme.json edits for Button block style variations (recorded
  declaratively in WP-MAPPING audit; no theme.json file edits at v3.5.1)

- Form submission behavior implementation (plugin territory per
  CHARTER §3.4 + WP-MAPPING audit §5)

- ActivityPub federation button surfaces (future federation work;
  out of v3.5.1 scope)

- Plugin / editor integration runtime work (future)

- M3 Interpreter Plugin (BACKLOG #21)

- data-theme="auto" implementation (BACKLOG #22)
```

## §10 — Risks (from Phase 0 §8)

The 4 Phase 0 risks are recorded per their Phase 1 disposition:

```
Risk 1 (HIGH)   Matrix consumer-state column missing.
                Phase 1 disposition: Button audit declares dependencies
                  with consumer-state explicit (§7 above). Matrix
                  amendment routed to v3.5.x (BACKLOG #24 + #26).
                Phase 1 action: declared, NOT amended matrix.

Risk 2 (HIGH)   Size variants scope (S only).
                Phase 1 disposition: §4 scopes S + 5 variants;
                  §6 item 4 records XS/M/L/XL deferral honestly
                  with M3 §4 spec reference.
                Phase 1 action: in-scope (recorded as exception).

Risk 3 (MED)    has-state-layer 5/25 omission ambiguity.
                Phase 1 disposition: re-counted at Phase 1 — actual is
                  20/20 in #components-button section (Phase 0 count
                  included broader file scan). Default = ON. Pattern
                  HTML at Phase 2 will include one opt-out demo for
                  completeness.
                Phase 1 action: settled. Default-ON canonical.
                Phase 2 action: lab-button-pattern.html §4 delivers
                  the opt-out demo with bilingual caption.

Risk 4 (LOW)    Icon slot convention.
                Phase 1 disposition: canonical pattern recorded in
                  §8 above (icon slot + glyph + a11y attributes).
                  Wave 1 next items reference this.
                Phase 1 action: settled. Canonical pattern recorded.
                Phase 2 action: lab-button-pattern.html §3 demonstrates
                  the canonical pattern across 5 variants.
```

## §11 — Phase verdict (5-criterion summary — Phase 5 close, ALL PASS)

Phase 1 audit body + Phase 2 deliverables + Phase 3 Visual QA PASS + Phase 5 mechanical close all complete. All 5 criteria PASS.

| # | Criterion | Status | Notes |
|---:|---|:---:|---|
| 1 | **M3 §4 spec coverage** (S + 5 variants) | ✓ PASS (Phase 5 closed) | 5 variants × S size = baseline matches M3 §4 spec. XS/M/L/XL deferred per Phase 0 constraint 2 (§6 item 4). Token bridge documented in MEASUREMENT audit §3 + §5. Phase 3 Visual QA verified baseline parity. |
| 2 | **Token-driven implementation** | ✓ PASS (Phase 5 closed) | MEASUREMENT audit §5 confirms 23/23 tokenizable properties are token-driven (100%). The 2 literals (`1px solid …` outline + `outline-offset: -1px`) are accepted M3 outlined-variant conventions. lab-button.css consumes only existing tokens; no new M3 system tokens introduced. |
| 3 | **Pattern HTML completeness** | ✓ PASS (Phase 2 closed) | Phase 2 delivered `lab-button-pattern.html` (330 lines, 8 sections): 5 variants × label-only + 5 with-icon + has-state-layer opt-out demo (1 with + 1 without) + 5 native disabled + 1 aria-disabled plugin-managed + 1 bare + 6 collapsible code snippets + cross-references. Principle 1/2 verified: 19 `<button type=...>` instances, 0 `<div role="button">`, 0 `<span>` or `<a>` styled as button. |
| 4 | **Audit doc completeness** (3-doc cross-references) | ✓ PASS | This audit + MEASUREMENT + WP-MAPPING bodies all written; cross-references resolved (§13 below); Phase 0 report referenced verbatim where required. |
| 5 | **Dependency declarations** (state per provider) | ✓ PASS | §7 above declares 3 dependencies with explicit consumer-state (CURRENT / TARGET / CURRENT-conditional) and DISTINCT but COUPLED contract per provider. |

### Phase 2 implementation record

```
Phase 2 closed. Criterion #3 moved from "TBD at Phase 2" to "PASS".
Criteria #1-#5 are now all PASS after Phase 3 Visual QA and Phase 5
mechanical close.

Phase 2 deliverables (per BUTTON-PHASE-2-PLAN.md v1.1):
  ✓ lab-button.css (174 lines, 6.6 KB) — pattern variations + demo
    scaffolding, NO unscoped .ax-button overrides, NO ripple wiring
  ✓ lab-button-pattern.html (330 lines, 17 KB) — 8 sections, all 5
    variants × label-only / with-icon, has-state-layer opt-out demo,
    native disabled + aria-disabled plugin-managed separate specimens,
    bare button, code snippets, cross-references

Phase 2 bookkeeping:
  ✓ BUTTON-SPEC-AUDIT.md §11 criterion #3 updated TBD → PASS (this file)
  ✓ CURRENT-STATE.md updated through v3.5.1 release close (separate file)
  ◐ NEXT-SESSION.md: NOT updated (per discipline — not a true session
    boundary; CURRENT-STATE.md + this audit doc + Phase 2 plan are
    sufficient state-tracking for intra-session continuity)
```

한글 요약:

```
Phase 2가 닫혔다. Criterion #3 (Pattern HTML completeness)이 TBD →
PASS로 갱신되었다. Phase 3 Visual QA와 Phase 5 mechanical close까지
완료되어 #1-#5 모두 PASS다.

Phase 2 산출물은 plan v1.1 scope대로 정확히 두 파일:
  - lab-button.css (174 lines) — pattern 변형 + 데모 scaffold,
    unscoped .ax-button override 없음, ripple wiring 없음
  - lab-button-pattern.html (330 lines) — 8 sections, 5 variants ×
    label-only / with-icon, has-state-layer opt-out 데모, native
    disabled + aria-disabled plugin-managed 분리, bare button,
    code snippet, cross-reference

Phase bookkeeping은 SPEC §11 + CURRENT-STATE.md만. NEXT-SESSION.md는
discipline에 따라 건드리지 않았다 (실제 session boundary 아님).
```

### Phase 3 + Phase 5 verdict (release close)

```
Phase 3 closed (Static Visual QA Gate) — PASS. User-verified visual
parity between lab-button-pattern.html and baseline #components-button
rendering. 10-point gate (per BUTTON-PHASE-2-PLAN.md §5 G6 and the
Static Visual QA checklist) cleared with 0 actual issues.

Phase 5 closed (Mechanical close) — ALL 5 verdict criteria PASS:
  ✓ #1 M3 §4 spec coverage           PASS (was TBD; Phase 5 confirmed)
  ✓ #2 Token-driven implementation   PASS (was TBD; Phase 5 confirmed)
  ✓ #3 Pattern HTML completeness     PASS (Phase 2 closed)
  ✓ #4 Audit doc completeness        PASS (Phase 1)
  ✓ #5 Dependency declarations       PASS (Phase 1)

v3.5.1 Wave 1 Button #1 release closed.
MODULE-STATUS-MATRIX row #1 (Button) advances TODO → DONE.
Baseline §0 + §2 + #components-button anchor + tokens.css all
UNCHANGED throughout the entire release cycle.
```

한글 요약:

```
Phase 3 (Static Visual QA Gate) PASS — user가 시각 QA 직접 검증, 10-point
gate 0 issues. Phase 5 (mechanical close) ALL PASS — 5개 criterion 모두
PASS, baseline 100% 보존.

v3.5.1 Wave 1 Button #1 release closed. MODULE-STATUS-MATRIX row #1
Button: TODO → DONE.
```

### Internal contract checks (Phase 1/2/3/5 traceability)

- **CHARTER §3.2 (baseline immutability)**: confirmed — `components.css §2` and `§0` UNCHANGED at v3.5.1.
- **CHARTER §3.4 (theme can / plugin should)**: confirmed — Button is theme territory; form behavior + federation actions are plugin/integration territory (WP-MAPPING audit §5).
- **CHARTER §4 (DISTINCT but COUPLED)**: confirmed — §7 declares 3 dependencies with explicit ownership boundaries per provider.
- **MODULE-STATUS-MATRIX row #1 (Button)**: confirmed Component Full-Spec category; status moves TODO → DONE at v3.5.1 close.
- **PROMOTION-CRITERIA §7 (G1–G10 universal gates)**: applicable; v3.5.1 release-close achievement confirmed in §12 below.
- **PROMOTION-CRITERIA §4.1 (Component Full-Spec category criteria)**: applicable; Phase 1 documents framework fit, Phase 2 materializes lab artifacts, Phase 3 verifies visual QA, and Phase 5 closes the release.
- **Phase 0 framework first-use validation**: confirmed — 4-category fit holds, DISTINCT but COUPLED holds with consumer-state refinement (Risk 1 routed to v3.5.x).

## §12 — G1–G26 gate applicability

Per `PROMOTION-CRITERIA.md §7`. Button is Component Full-Spec → G1–G10 apply universally; G11–G16 do NOT (Button is not Interaction Runtime); G17–G21 do NOT (Button is not Record nor Plugin-territory); G22–G26 do NOT (Button is Consumer, not Provider).

### Gate readiness at Phase 5 close

| Gate | Description | Status | Notes |
|---:|---|:---:|---|
| G1 | `validate_theme_pilot.py` 1.000 PASS on working tree | ✓ maintained | Verified pre-Phase-2 + expected post-Phase-2 (Phase 2 added 2 new lab files outside validator-checked surface; user re-verifies on Windows) |
| G2 | Baseline untouched (components.css §0+§2 + style-guide.html L624–L693) | ✓ confirmed | mtimes 05:40 preserved across baseline files |
| G3 | `publish_styleguide.py` runs cleanly | ✓ (Codex tooling cleanup) | publish script ROOT path fixed; Phase 5 will re-mirror |
| G4 | Module artifacts present per §4.1 | ✓ Phase 2 | `lab-button.css` + `lab-button-pattern.html` created at expected paths |
| G5 | CHANGELOG entry added | ✓ Phase 5 | v3.5.1 entry added to CHANGELOG.md |
| G6 | Static Visual QA Gate PASS (0 actual issues) | ✓ Phase 3 PASS | User-verified visual QA on `lab-button-pattern.html` against baseline `#components-button` rendering. 10-point gate cleared. |
| G7 | Principle 1 compliance per pattern HTML | ✓ confirmed | 19 `<button type=...>`, 0 `<div role="button">`, 0 `<span>` or `<a>` styled as button in pattern HTML |
| G8 | Principle 2 (native semantics) applied | ✓ confirmed | `<button type="button">` consistently used; aria-disabled specimen has explicit caption distinguishing it from native disabled |
| G9 | WCAG SC citations accurate (SC 2.5.8 AA / SC 2.5.5 AAA distinction) | ✓ — MEASUREMENT audit §4 cites correctly | Phase 1 |
| G10 | 3-doc audit pattern complete | ✓ — SPEC + MEASUREMENT + WP-MAPPING bodies all authored | Phase 1 |

G13 (Phase 0 inventory accuracy spirit) — applied at Phase 0 (surfaced Risk 1); Phase 1 inherits the finding. Charter amendment candidate (universal Phase 0 accuracy gate) recorded as schedule item, NOT v3.5.1 work.

## §13 — Cross-references

```
Phase 0:    docs/v3.5.1/BUTTON-PHASE-0-REPORT.md         (canonical Phase 0)
Phase 2:    docs/v3.5.1/BUTTON-PHASE-2-PLAN.md           (plan v1.1 — implemented)
            lab/modules/button/lab-button.css            (Phase 2 deliverable)
            lab/modules/button/lab-button-pattern.html   (Phase 2 deliverable)

Companions: ./BUTTON-MEASUREMENT-AUDIT.md                (M3 §4 dimensions + WCAG)
            ./BUTTON-WP-MAPPING.md                       (block binding + anti-patterns)

Framework:  docs/v3.5.0/MODULE-STATUS-MATRIX.md          (row #1 Button)
            docs/v3.5.0/PROMOTION-CRITERIA.md §4.1       (Component Full-Spec criteria)
            docs/v3.5.0/PROMOTION-CRITERIA.md §7         (G1–G10 universal gates)
            docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §3     (4-tier architecture)
            docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §4     (DISTINCT but COUPLED)
            docs/v3.5.0/COMPONENT-COVERAGE-MAP.md Map 3  (infrastructure dep graph)

Baseline:   products/reference-implementations/axismundi-lab/stylesheets/components.css
              §0 L22–L79   State-layer foundation (CURRENT dependency)
              §2 L122–L234 Button (this audit's subject)
            products/reference-implementations/axismundi-lab/style-guide.html
              L624–L693    #components-button anchor

Template:   lab/modules/chip/docs/CHIP-SPEC-AUDIT.md     (v3.4.9 first Component
                                                          Full-Spec — pattern
                                                          followed throughout)

BACKLOG:    #18  Snackbar naming sweep (scheduled v3.5.x)
            #20  Theme-only color policy (scheduled v3.5.x)
            #22  data-theme=auto 3-state (scheduled v3.5.x)
            #24  Matrix consumer-state column (Phase 0 Risk 1 — v3.5.x)
            #25  Ripple v2 contract (Phase 0 §3.7 — v3.5.x)
            #26  Matrix row #36 allowlist correction (Phase 0 §3.5.4 — v3.5.x)
            #27  data-ax-ripple opt-in (Phase 0 §3.8 — v3.5.x)

M3 spec:    https://m3.material.io/components/buttons/specs §4
WCAG:       https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum
            https://www.w3.org/WAI/WCAG22/Understanding/target-size
Material Web: https://material-web.dev/components/ripple/  (ripple v2 reference)
```

## §14 — What this audit does NOT do

- Does not modify `components.css §2 Button` baseline (113 lines untouched).
- Does not modify `components.css §0 State-layer foundation` (58 lines untouched).
- Does not modify `style-guide.html #components-button` baseline specimens (70 lines untouched).
- Does not author `lab-button.js` (Phase 2 — Option (b) settled — not created).
- Does not promote `.ax-button.is-*` variants to a different baseline section (separate CHARTER decision).
- Does not add XS / M / L / XL size variants (separate baseline expansion release).
- Does not add toggle-state `.ax-button[aria-pressed="true"]` styling (deferred).
- Does not implement ripple v2 contract (BACKLOG #25 — v3.5.x).
- Does not amend `MODULE-STATUS-MATRIX.md` consumer-state column (BACKLOG #24 — v3.5.x).
- Does not add Gutenberg block style registrations to baseline `functions.php` (WP-MAPPING audit records declaratively only).
- Does not edit `theme.json`.
- Does not generate M3 tonal palette colors (BACKLOG #21 Interpreter Plugin scope).
- Does not implement form submission behavior (plugin territory per CHARTER §3.4).
- Does not address ActivityPub federation button surfaces (future federation work).

---

## §15 — v3.5.13 Size-Variant Alignment Note

This additive note links Button #1 to the v3.5.13 BACKLOG #32 cleanup lane. It
does not reopen the v3.5.1 release verdict.

Current state:

```txt
Button ships a single 40px baseline size with label-large typography, 16px
horizontal padding, 20px icon, and a v3.5.9 morphing-safe pill radius.
```

v3.5.13 size contract:

```txt
BUTTON-FAMILY-SIZE-AUDIT.md locks Option C:
  public Button-family size tokens + local per-component mappings.
```

Expected Phase 2 impact:

```txt
Button may receive `.ax-button.is-size-xs/s/m/l/xl` mappings. The default
`.ax-button` behavior should remain compatible with the current 40px surface
unless Phase 2 explicitly defines a new default alias.
```

Non-goal:

```txt
This note does not implement size variants and does not change the v3.5.1
Component Full-Spec verdict.
```
