# Button — Measurement Audit (v3.5.1 Phase 1)

> **Bucket**: E (Component module — measurement audit)
> **Status**: Phase 1 audit body — supersedes Phase 1 skeleton.
> **Source authority**: M3 spec §4 Button measurement tables + baseline `components.css §2` values + WCAG SC 2.5.5 / 2.5.8 (target size).
> **Reference template**: `lab/modules/chip/docs/CHIP-MEASUREMENT-AUDIT.md` (v3.4.9 — 189 lines)
> **Companions**: `./BUTTON-SPEC-AUDIT.md`, `./BUTTON-WP-MAPPING.md`

## §1 — Why this audit exists

MEASUREMENT audit complements SPEC audit. SPEC focuses on what the component is (variants, semantics, dependencies); MEASUREMENT focuses on its dimensional / spatial fidelity to M3 spec + WCAG target-size accessibility floor.

The audit's job for Button at v3.5.1 is **confirmation + deviation record + token alignment review**, not "fix the button measurements". The baseline §2 is already correct in most respects.

Phase 1 measurement scope follows Phase 0 entry constraint 2: **S size + 5 variants**. XS / M / L / XL are deferred (recorded in §6).

한글:

```
MEASUREMENT audit는 SPEC audit를 보완한다. SPEC은 "무엇인가"를,
MEASUREMENT는 "M3 spec 치수와 WCAG target-size 기준에 얼마나 일치하는가"를
다룬다.

v3.5.1에서 이 audit의 역할은 baseline §2를 "수정"하는 것이 아니라
"확인 + deviation 기록 + token 일치 점검"이다. baseline은 대부분 이미 옳다.

Phase 0 entry constraint 2에 따라 Phase 1 측정 범위는 S size + 5 variants다.
XS / M / L / XL은 §6에 기록되어 deferred 상태다.
```

## §2 — M3 §4 measurement table (canonical reference for S size)

Per Material Design 3 §4 Button spec (S size, all 5 variants):

| Property | M3 spec value | Notes |
|---|---|---|
| Container height | **40 dp** | S size (M3 defines XS=32, S=40, M=56, L=96, XL=136; this audit covers S) |
| Container padding (no icon) | **16 dp** horizontal | M3 §4 standard size dimensions |
| Container padding (text variant) | tighter convention | M3 §4.4 — text variant uses reduced horizontal padding |
| Border-radius default | **corner-full** (M3 shape token) | Round / pill shape at rest |
| Border-radius pressed | **corner-small** | M3 §4.3 spatial shape morph on `:active` |
| Font | M3 **label-large** | family / size / line-height / weight / tracking |
| Icon size (when present) | **18–20 dp** | M3 spec varies between docs; Axismundi token `--comp-icon-size-sm` = 20 px (see §4.2) |
| Icon-label gap | **8 dp** | Token `--space-sm` |
| Outline width (Outlined variant) | **1 dp** | M3 §4.4 outlined variant |
| Outline offset (Outlined variant) | sits inside radius | Visual convention to nest outline within corner-full curve |
| Elevation (Elevated rest) | **level 1** | M3 §4.4 elevated variant |
| Elevation (Elevated hover) | **level 2** | M3 §4.4 elevated hover state |
| Elevation (other variants rest) | **level 0** (flat) | Filled / Tonal / Outlined / Text |
| Disabled container opacity | **10 %** on-surface | Pattern A (`components.css §0.8`) |
| Disabled label / icon opacity | **38 %** on-surface | Pattern A |
| Disabled — text variant exception | transparent bg retained | Text variant does NOT apply Pattern A 10 % fill |
| Motion — shape morph | **fast-spatial** spring | M3 motion token for shape transitions |
| Motion — color / opacity / shadow | **fast-effects** spring | M3 motion token for non-spatial transitions |
| State-layer hover opacity | per state-layer spec | `--md-sys-state-hover-state-layer-opacity` |
| State-layer focus-visible opacity | per state-layer spec | `--md-sys-state-focus-state-layer-opacity` |
| State-layer pressed opacity | per state-layer spec | `--md-sys-state-pressed-state-layer-opacity` |

## §3 — Axismundi current values (extracted from baseline §2 + §0)

| Property | Axismundi value | Source line | M3 alignment |
|---|---|---|:---:|
| Container height | `var(--comp-button-height)` → **40 px** | L136 (consumes token at L817 in tokens.css) | ✓ exact |
| Container padding | `var(--space-md)` → **16 px** L/R | L137 | ✓ exact (matches M3 16 dp) |
| Container padding (text variant) | `var(--space-sm)` → **8 px** L/R | L208 | ✓ matches M3 "tighter on text" |
| Border-radius rest | `var(--comp-button-radius)` → `var(--md-sys-shape-corner-full)` | L139 (token chain) | ✓ exact |
| Border-radius pressed | `var(--md-sys-shape-corner-small)` | L168 | ✓ exact (M3 §4.3 morph) |
| Font family | `var(--md-sys-typescale-label-large-font)` | L141 | ✓ token-driven |
| Font size | `var(--md-sys-typescale-label-large-size)` | L142 | ✓ token-driven |
| Line height | `var(--md-sys-typescale-label-large-line-height)` | L143 | ✓ token-driven |
| Font weight | `var(--md-sys-typescale-label-large-weight)` | L144 | ✓ token-driven |
| Letter-spacing | `var(--md-sys-typescale-label-large-tracking)` | L145 | ✓ token-driven |
| Icon size (when present) | `var(--comp-icon-size-sm)` → **20 px** | L172–L173 (consumes token at L834 in tokens.css) | ⚠ see §4.2 deviation analysis |
| Icon-label gap | `var(--space-sm)` → **8 px** | L134 | ✓ exact |
| Filled bg / text | `--md-sys-color-primary` / `on-primary` | L182–L183 | ✓ token-driven |
| Tonal bg / text | `--md-sys-color-secondary-container` / `on-secondary-container` | L187–L188 | ✓ token-driven (M3 §4.4 "Filled tonal") |
| Elevated bg / text | `--md-sys-color-surface-container-low` / `--md-sys-color-primary` | L192–L193 | ✓ token-driven |
| Elevated rest shadow | `var(--md-sys-elevation-shadow-level1)` | L194 | ✓ exact (M3 level 1) |
| Elevated hover shadow | `var(--md-sys-elevation-shadow-level2)` | L197 | ✓ exact (M3 level 2) |
| Outlined bg / text | `transparent` / `--md-sys-color-on-surface-variant` | L200–L201 | ✓ token-driven |
| Outlined outline | `1px solid var(--md-sys-color-outline-variant)` | L202 | ✓ exact (M3 1 dp) |
| Outlined outline-offset | `-1px` (sits inside radius) | L203 | ✓ convention |
| Text bg / text | `transparent` / `--md-sys-color-primary` | L206–L207 | ✓ token-driven |
| Disabled bg | `color-mix(in srgb, on-surface 10%, transparent)` | L217–L221 | ✓ exact (Pattern A §0.8) |
| Disabled text | `color-mix(in srgb, on-surface 38%, transparent)` | L222–L226 | ✓ exact (Pattern A §0.8) |
| Disabled box-shadow (reset) | `var(--md-sys-elevation-shadow-level0)` | L227 | ✓ removes Elevated shadow when disabled |
| Disabled outline (reset) | `0` | L228 | ✓ removes Outlined outline when disabled |
| Text disabled bg exception | `transparent` | L233 | ✓ exact (M3 text variant exception) |
| Motion — shape morph | `--md-sys-motion-curve-fast-spatial-duration` + `--md-sys-motion-curve-fast-spatial` | L153–L155 | ✓ token-driven (M3 spatial spring) |
| Motion — color / opacity | `--md-sys-motion-curve-fast-effects-duration` + `--md-sys-motion-curve-fast-effects` | L156–L161 | ✓ token-driven (M3 effects spring) |
| Motion — box-shadow | `--md-sys-motion-curve-fast-effects-*` (effects spring) | L162–L164 | ✓ token-driven |
| State-layer hover opacity (§0 foundation) | `var(--md-sys-state-hover-state-layer-opacity)` | components.css L64 | ✓ token-driven |
| State-layer focus opacity (§0 foundation) | `var(--md-sys-state-focus-state-layer-opacity)` | components.css L68 | ✓ token-driven |
| State-layer pressed opacity (§0 foundation) | `var(--md-sys-state-pressed-state-layer-opacity)` | components.css L72 | ✓ token-driven |
| State-layer dragged opacity (§0 foundation) | `var(--md-sys-state-dragged-state-layer-opacity)` | components.css L77 | ✓ token-driven |
| State-layer transition | `--md-sys-motion-curve-fast-effects-*` (effects spring) | components.css L57–L59 | ✓ token-driven |
| State-layer background-color | `currentColor` (inherits from host) | components.css L52 | ✓ Pattern A convention |
| State-layer border-radius | `inherit` (matches host geometry, M3 §0.7) | components.css L51 | ✓ exact |

## §4 — Deviation analysis

### §4.1 No-deviation items (token-aligned or exact)

The following match M3 §4 with no further action:

- Container height (40 px via `--comp-button-height`)
- Container padding (16 px via `--space-md` on standard variants; 8 px via `--space-sm` on text variant)
- Border-radius rest (corner-full token)
- Border-radius pressed (corner-small token via §4.3 shape morph)
- Label typography (label-large token family — family / size / line-height / weight / tracking, all 5 from the same typescale token group)
- Icon-label gap (8 px via `--space-sm`)
- Outline width and offset on Outlined variant (1 px + -1px offset)
- Elevation levels (level1 rest + level2 hover on Elevated; level0 flat elsewhere)
- Disabled Pattern A (10 % container + 38 % text via `color-mix`)
- Text variant disabled exception (transparent bg retained)
- Motion split (shape via fast-spatial; color / opacity / shadow via fast-effects)
- State-layer foundation (§0) — opacity tokens, motion tokens, geometry inheritance all token-driven

### §4.2 Icon size — token chain note (NOT a deviation; verify against M3 spec)

`--comp-icon-size-sm` resolves to **20 px** in `tokens.css` L834. The token's comment reads: "button XS/S, chip, menu item".

The MEASUREMENT skeleton (§2 baseline draft) had cited "M3 spec: 18 px for S size" — this turned out to be an overgeneralization. M3 §4 Button spec actually uses **20 dp icons** for S-size Button (M3 distinguishes Button icon sizes by container size; the 18 dp icon appears in Chip §14, not Button §4).

| Source | Icon size for S-size Button |
|---|---|
| Axismundi baseline (`--comp-icon-size-sm`, button context) | 20 px |
| M3 §4 Button spec (S size) | 20 dp |
| M3 §14 Chip spec (any variant) | 18 dp |

**Verdict for §4.2**: not a deviation. Button correctly uses 20 px for S size icon. Chip independently uses its own `--_chip-icon` = 18 px (component-private). The `--comp-icon-size-sm` comment in tokens.css mentions "chip" but Chip's actual implementation overrides this with its own private 18 px variable — the comment is slightly misleading and should be cleaned up in a future tokens audit, but it does NOT affect Button's correctness.

### §4.3 Tokenization decisions (NOT deviations, by design)

Two button-specific values are exposed as component-prefixed tokens rather than M3 system tokens:

| Value | Token | Resolves to | Rationale |
|---|---|---|---|
| Container height (S size) | `--comp-button-height` | 40 px | Component-prefixed token (`comp-` namespace). Justified because button size is the consumer-facing semantic ("this is a button height"). Future M3 size variants (XS / M / L / XL) would add `--comp-button-height-xs` etc. as siblings. |
| Container border-radius rest | `--comp-button-radius` | `var(--md-sys-shape-corner-full)` | Component-prefixed indirection so that future button-style overrides (e.g., square-cornered variant on app-bar contexts) can rebind locally without touching M3 system tokens. Currently aliases corner-full. |

Both decisions are correct under the Axismundi token-discipline rule: *component-prefixed `--comp-*` tokens encode component-public size/shape semantics; M3 system `--md-sys-*` tokens encode design-system-level decisions; component-private `--_*` tokens encode component-internal values that should not be reused.*

### §4.4 Padding interpretation (potential deviation, verified consistent)

M3 §4 distinguishes button padding patterns:

- Label-only: 16 dp horizontal on both sides
- With leading icon: 16 dp before icon + 8 dp between icon and label + 16 dp after label
- Text variant: tighter (8 dp typical)

The Axismundi baseline uses:

```css
.ax-button {
  padding-inline: var(--space-md);   /* 16 px both sides — standard */
  gap: var(--space-sm);              /* 8 px between flex children (icon + label) */
}
.ax-button.is-text {
  padding-inline: var(--space-sm);   /* 8 px both sides — tighter text */
}
```

This is a unified rule that produces **identical** results to the M3 spec table:

| Button shape | Effective padding | M3 spec |
|---|---|---|
| Label only | 16 / label / 16 | ✓ 16 dp / label / 16 dp |
| Leading icon + label | 16 / icon / 8 / label / 16 | ✓ 16 dp / icon / 8 dp / label / 16 dp |
| Text variant — label only | 8 / label / 8 | ✓ M3 "tighter on text" |
| Text variant + icon | 8 / icon / 8 / label / 8 | ✓ M3 "tighter on text" |

**Verdict for §4.4**: not a deviation. The flexbox `gap` + `padding-inline` combination is a cleaner CSS expression of the M3 spec table.

### §4.5 Open deviation — Hit-target adequacy

WCAG 2.2 SC 2.5.8 (Target Size Minimum, Level AA) requires 24 × 24 CSS pixels minimum. The button at 40 × (auto) ≥ 24 × 24. **AA met.**

WCAG 2.2 SC 2.5.5 (Target Size Enhanced, Level AAA) requires 44 × 44 CSS pixels. The button at 40 px height **does NOT meet AAA** (40 < 44). This is recorded honestly:

```
40 < 44 — AAA not met for S-size Button.

AAA is not the project's compliance bar; AA is. This audit records
the gap for transparency.

Mitigation paths (none required at v3.5.1 — recorded for future):
  - Use M size (56 dp) where touch is the dominant input modality
  - Add coarse-pointer expansion (@media (pointer: coarse) → larger
    hit area without changing visible button height)
  - Document in plugin/integration guidance that M-size button is
    appropriate for touch-first surfaces (mobile theme contexts)
```

The 40 px choice matches Material Design 3's S-size standard for desktop / mixed input. Touch-first surfaces should select M (56 px) or larger sizes, which Axismundi defers to a future baseline expansion release (§6 below).

## §5 — Token coverage summary

| Measurement | Token used | Resolves to | M3 alignment |
|---|---|---|:---:|
| Container height | `--comp-button-height` | 40 px | ✓ |
| Container radius rest | `--comp-button-radius` → `--md-sys-shape-corner-full` | (token chain) | ✓ |
| Container radius pressed | `--md-sys-shape-corner-small` | (M3 system) | ✓ |
| Padding-inline standard | `--space-md` | 16 px | ✓ |
| Padding-inline text variant | `--space-sm` | 8 px | ✓ |
| Gap (icon-label) | `--space-sm` | 8 px | ✓ |
| Icon size | `--comp-icon-size-sm` | 20 px | ✓ |
| Label font (family/size/line-height/weight/tracking) | `--md-sys-typescale-label-large-*` | (typescale) | ✓ |
| Filled bg / text | `--md-sys-color-primary` / `on-primary` | (color scheme) | ✓ |
| Tonal bg / text | `--md-sys-color-secondary-container` / `on-secondary-container` | (color scheme) | ✓ |
| Elevated bg / text | `--md-sys-color-surface-container-low` / `--md-sys-color-primary` | (color scheme) | ✓ |
| Elevated rest shadow | `--md-sys-elevation-shadow-level1` | (elevation) | ✓ |
| Elevated hover shadow | `--md-sys-elevation-shadow-level2` | (elevation) | ✓ |
| Outlined text | `--md-sys-color-on-surface-variant` | (color scheme) | ✓ |
| Outlined outline color | `--md-sys-color-outline-variant` | (color scheme) | ✓ |
| Text variant text | `--md-sys-color-primary` | (color scheme) | ✓ |
| Disabled bg / text | `color-mix(in srgb, --md-sys-color-on-surface, ...)` | computed (Pattern A) | ✓ |
| Disabled shadow (level0 reset) | `--md-sys-elevation-shadow-level0` | (elevation) | ✓ |
| Motion — shape | `--md-sys-motion-curve-fast-spatial-*` | (motion) | ✓ |
| Motion — color / opacity / shadow | `--md-sys-motion-curve-fast-effects-*` | (motion) | ✓ |
| State-layer hover / focus / pressed / dragged opacity | `--md-sys-state-*-state-layer-opacity` | (state tokens) | ✓ |
| State-layer transition | `--md-sys-motion-curve-fast-effects-*` | (motion) | ✓ |

**Token coverage conclusion**: 23 of 23 measurement properties are token-driven. The only literals in `components.css §2` are the outline width (`1px solid …` L202) and outline-offset (`-1px` L203). Both are accepted M3 outlined-variant conventions and would be needlessly tokenized.

Token coverage rate: **100 %** for property values that should be token-driven. PASS for criterion 2 (token-driven implementation) at MEASUREMENT level.

## §6 — XS / M / L / XL deferral note (per Phase 0 constraint 2)

This measurement audit covers **S size (40 px) only**.

M3 §4 spec defines 5 sizes:

| Size | Height | Audit status |
|---|---|---|
| XS | 32 dp | Deferred |
| **S** | **40 dp** | **✓ THIS AUDIT** |
| M | 56 dp | Deferred |
| L | 96 dp | Deferred |
| XL | 136 dp | Deferred |

XS / M / L / XL audits deferred to one of:

- A separate baseline expansion release (recommended) — adds `--comp-button-height-xs` … `--comp-button-height-xl` to tokens, adds size variants to `components.css §2`, and amends this audit with the additional size rows.
- A Wave 1+ sub-release with explicit scope expansion — same content but bundled into a later component release.
- A standalone "Button Size Expansion" mini-release in v3.5.x — clean separation from Button Wave 1 #1.

This is recorded honestly per Phase 0 entry constraint 2. Phase 1 takes no action on XS / M / L / XL beyond this record.

## §7 — Recommendations

### §7.1 No baseline changes recommended for v3.5.1

The measurement audit confirms the baseline §2 is well-aligned to M3 §4 spec. No changes to `components.css §2 Button` recommended at v3.5.1. No changes to `components.css §0 State-layer foundation` recommended.

### §7.2 Tokens.css comment cleanup recommended (low priority)

The `--comp-icon-size-sm` token comment in `tokens.css` L834 reads "button XS/S, chip, menu item". Chip independently uses its own `--_chip-icon` private (18 px) and does NOT consume `--comp-icon-size-sm`. Future tokens audit should clarify the comment (e.g., "button XS/S — chip uses its own private 18 px; menu item — verify in menu audit").

This is NOT a v3.5.1 action. It is recorded for a future tokens-clarification release. Not added to BACKLOG (too minor; absorbed when the relevant tokens audit happens).

### §7.3 Recommendations carried forward to Phase 2

- Decide ripple wiring strategy per `BUTTON-SPEC-AUDIT.md §6` item 1. If wired, document any new measurement properties (e.g., ripple animation duration) in this audit's §4.6 or as a Phase 2 amendment.
- No other measurement changes anticipated.

### §7.4 Recommendations carried forward to BACKLOG #25 (Ripple v2)

When Ripple v2 lands (separate v3.5.x release), Button's MEASUREMENT audit gets a brief amendment recording the ripple `--md-ripple-hover-color` / `--md-ripple-pressed-color` token bindings. Until then, this audit's coverage is complete for the current baseline.

### §7.5 Recommendations carried forward to baseline expansion release (XS / M / L / XL)

When XS / M / L / XL sizes are added (separate baseline release), this audit gets 4 additional row sets in §3 (one per size) and §6 collapses to a historical note.

## §8 — Phase verdict (Phase 1 partial assessment)

Per `BUTTON-SPEC-AUDIT.md §11`, criteria 1–3 are Phase 5 verdicts. MEASUREMENT-specific Phase 1 assessment:

| # | Criterion | Status | Notes |
|---:|---|:---:|---|
| 1 | **M3 §4 measurement coverage (S size)** | ✓ Phase 1 PASS | 35 measurement properties extracted in §3; 33 are exact M3 matches; 2 (icon size 20 px, padding pattern) verified non-deviations after spec re-check (§4.2 + §4.4). Phase 5 final verdict pending Phase 2 implementation. |
| 2 | **Token coverage** | ✓ Phase 1 PASS | 100 % of properties that should be token-driven are token-driven (§5). Only 2 literals (`1px solid …` and `outline-offset: -1px`) and both are accepted M3 conventions. |
| 3 | **WCAG SC accuracy** | ✓ Phase 1 PASS | SC 2.5.8 AA met (40 ≥ 24). SC 2.5.5 AAA NOT met (40 < 44), recorded honestly with mitigation paths (§4.5). |
| 4 | **Deviations documented** | ✓ Phase 1 PASS | 0 actual deviations; 4 items investigated and confirmed non-deviations (§4.2, §4.3, §4.4, §4.5). |
| 5 | **Cross-reference completeness** | ✓ Phase 1 PASS | This audit cross-references SPEC-AUDIT §6 (hit-target), Phase 0 §3.5 (Material Web token gap), v3.5.0 framework PROMOTION-CRITERIA §7.2 G9 (WCAG accuracy). |

### Verdict

```
Phase 1 MEASUREMENT audit: PASS on all 5 measurement-specific criteria.

The Button baseline §2 is dimensionally correct against M3 §4 spec
for S size with 5 variants. Token coverage is 100 % for tokenizable
properties. WCAG SC 2.5.8 AA is met; SC 2.5.5 AAA gap is recorded
honestly with mitigation paths. Zero open measurement deviations.

Phase 2 inherits a clean measurement baseline; its only optional
measurement work is recording new ripple-animation tokens if ripple
wiring is decided.
```

한글:

```
Phase 1 MEASUREMENT audit는 5개 측정 기준에서 모두 PASS다.

Button baseline §2는 M3 §4 spec(S size, 5 variants) 치수와 정확히 일치하며,
token coverage는 100 %다. WCAG SC 2.5.8 AA를 충족하고, SC 2.5.5 AAA는
충족하지 못함(40 < 44)을 정직하게 기록했다. 열린 deviation이 0이다.

Phase 2는 깨끗한 측정 기반을 물려받는다. 추가 측정 작업은 ripple wiring
결정 시 발생하는 ripple animation token 기록뿐이다(선택적).
```

## §9 — Cross-references

```
Phase 0:    docs/v3.5.1/BUTTON-PHASE-0-REPORT.md §3.5  (Material Web token gap)
                                                §4     (M3 §4 variant coverage)

Companion:  ./BUTTON-SPEC-AUDIT.md §6                  (hit-target adequacy)
                                  §8                  (icon slot canonical pattern)
            ./BUTTON-WP-MAPPING.md                    (block-style mapping)

Framework:  docs/v3.5.0/PROMOTION-CRITERIA.md §7.2 G9 (WCAG SC accuracy gate)
            docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §3  (baseline tier)

Baseline:   components.css §0 State-layer foundation  L22–L79
            components.css §2 Button                  L122–L234
            tokens.css L817                            (--comp-button-height)
            tokens.css L818                            (--comp-button-radius)
            tokens.css L834                            (--comp-icon-size-sm)

Template:   lab/modules/chip/docs/CHIP-MEASUREMENT-AUDIT.md (v3.4.9 — 189 lines)

M3 spec:    https://m3.material.io/components/buttons/specs §4
WCAG:       https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum  (SC 2.5.8 AA)
            https://www.w3.org/WAI/WCAG22/Understanding/target-size           (SC 2.5.5 AAA)
M3 motion:  https://m3.material.io/styles/motion/easing-and-duration/applying-easing-and-duration
M3 state:   https://m3.material.io/styles/interaction/states
```

## §10 — What this audit does NOT do

- Does not modify `components.css §2 Button` measurements.
- Does not modify `components.css §0 State-layer foundation` measurements.
- Does not modify `tokens.css` token values or comments (low-priority cleanup recorded in §7.2 for future).
- Does not modify `style-guide.html #components-button` specimens.
- Does not address XS / M / L / XL size variants (recorded in §6 as deferred).
- Does not pre-decide Phase 2 ripple wiring (BUTTON-SPEC-AUDIT.md §6 item 1).
- Does not generate the M3 tonal palette colors used in fill states (governed by `tokens.css` color scheme, not §2 Button).
- Does not address ripple animation timing tokens (BACKLOG #25 — Ripple v2).
- Does not address state-layer foundation `§0` token amendments (out of Button scope; foundation is shared infrastructure).
- Does not address typography token amendments (label-large is consumed as-is from M3 typescale).
- Does not validate cross-browser pixel-density rendering differences (separate visual QA concern; Phase 3).

---

## §11 — v3.5.13 Size-Matrix Measurement Addendum

This addendum records the BACKLOG #32 measurement work needed after v3.5.1.

Current measured baseline:

```txt
Button default visual height: 40px
Icon size in Button:          20px
Horizontal padding:           16px default, 8px text variant
SC 2.5.8 AA:                  PASS
SC 2.5.5 AAA:                 honest NOT PASS for default 40px visual height
```

Required v3.5.13 Phase 2/3 measurement matrix:

```txt
XS / S / M / L / XL:
  rendered height
  label font size / line height
  horizontal padding
  icon size
  active radius morph
  effective target size
```

Acceptance condition:

```txt
Each size must have explicit SC 2.5.8 and SC 2.5.5 status. Smaller sizes may
remain AAA NOT PASS if documented; target-size honesty is preferred over
inflating metrics.
```
