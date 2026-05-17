# Axismundi — M3 Component Specs

> Compressed M3 component specifications for Axismundi.
> Source: Material Design 3 (`m3.material.io`), translated into Axismundi token vocabulary.
> Catalog complete — covers all M3 components in §1–§34.

---

## 0. Conventions

These rules apply to every component section below.

### 0.1 M3 Expressive baseline
Axismundi targets **M3 Expressive**. When source spec offers both M3 baseline and Expressive variants, only Expressive is recorded. Baseline variants flagged "Not recommended" are dropped:
- App bar `medium`, `large` → use `medium-flexible`, `large-flexible`
- Extended FAB baseline → use `small extended FAB`
- `small FAB` deprecated
- Navigation bar (baseline) → use flexible navigation bar
- Navigation rail (baseline) → use collapsed navigation rail
- Search divided style → use contained style
- Indeterminate circular progress → consider replacing with loading indicator
- Bottom app bar → use docked toolbar
- List baseline → use expressive list
- **Surface tint color → deprecated**, rely on elevation level tokens directly
- **Easing & duration legacy system → migrating to motion physics (springs)**, see §0.9

### 0.2 Token name shorthand

| Spec writes | Resolves to |
|---|---|
| `surface`, `on-surface`, `error`, `primary`, `tertiary`, `scrim`, etc. | `--md-sys-color-{name}` |
| `surface-container`, `surface-container-high`, etc. | `--md-sys-color-{name}` |
| `outline`, `outline-variant` | `--md-sys-color-{name}` |
| `inverse-surface`, `inverse-on-surface`, `inverse-primary` | `--md-sys-color-{name}` |
| `on-error-container` | `--md-sys-color-{name}` (used by text field error hover) |
| `title-large`, `body-medium`, etc. | `--md-sys-typescale-{name}-{font|size|weight|line-height|tracking}` |
| `corner-none`, `corner-extra-small`, `corner-small`, `corner-medium`, `corner-large`, `corner-large-increased`, `corner-extra-large`, `corner-extra-large-increased`, `corner-extra-extra-large`, `corner-full` | `--md-sys-shape-{name}` |
| `level0` … `level5` | `--md-sys-elevation-{name}` |

### 0.3 Units
All measurements in **px**. Source `dp`/`pt` values converted 1:1.

### 0.4 Light/dark not split
Color references are token names only. Light/dark resolution handled by `tokens.css`.

### 0.5 Typography normalized to typescale
Source spec literal font values converted to typescale token references. Mapping is exact — Axismundi typescale follows M3 type-scale-tokens 1:1.

### 0.6 Excluded from spec
- Anatomy diagrams
- Color-role illustrations
- Tracking values when already implied by typescale token
- M3 baseline variants superseded by Expressive
- Light/dark hex values (resolved at tokens.css)

### 0.7 State layer rule (interactive components)

State layers overlay the surface using **the variant's foreground (label / icon / content) color** at these opacities:

| State | Opacity |
|---|---|
| hover | 0.08 |
| focus | 0.1 |
| pressed | 0.1 |
| dragged | 0.16 |

**Single rule:** `state-layer-color = the foreground content color of the variant`.

> ⚠️ **Discrepancy with `comment.md`:** earlier note set focus/pressed at 0.12. M3 Expressive specs use **0.1**. Update `tokens.css` (see PROMPT-QUEUE A-1.5).

**State-layer geometry** matches container by default. For form controls (checkbox, radio, switch), the state layer is a fixed 40×40px circle larger than the container.

### 0.8 Disabled rule

**Pattern A — interactive content (button / icon-button / FAB / split-button / chip / list-item / menu-item / nav-item / toolbar-item):**

| Element | Token | Opacity |
|---|---|---|
| Container | `on-surface` | 0.1 (chip 0.12) |
| Label / icon | `on-surface` | 0.38 |
| Elevation | `level0` | — |

**Pattern B — surface containers (card):**
- Container color unchanged, opacity 0.38, elevation unchanged.

**Pattern C — selection controls (checkbox / radio / switch / slider):**
- 0.38 opacity on icon + container/handle.
- Track-based controls (switch track, slider track): track 0.12 opacity.

**Pattern D — input surfaces (text field):**
- Filled container: `on-surface` @ 0.04 (very faint)
- Outlined container outline: `on-surface` @ 0.12
- Label / icons / input text / supporting text: `on-surface` @ 0.38
- Active indicator (filled): `on-surface` @ 0.38

### 0.9 Motion physics (M3 Expressive spring system)

Axismundi adopts M3 Expressive **motion physics**, replacing the legacy duration/easing model from `prompt.md` §5.

**Two token families:**
- **Spatial** — for animations that move, resize, or morph shape. Spring overshoots and settles (slight bounce).
- **Effects** — for color and opacity changes. No overshoot.

**Three speeds each:** fast, default, slow.

#### Spring tokens (raw physics — for native API integration / Compose)

| Token | Damping | Stiffness |
|---|---|---|
| `motion-spring-fast-spatial` | 0.6 | 800 |
| `motion-spring-default-spatial` | 0.8 | 380 |
| `motion-spring-slow-spatial` | 0.8 | 200 |
| `motion-spring-fast-effects` | 1 | 3800 |
| `motion-spring-default-effects` | 1 | 1600 |
| `motion-spring-slow-effects` | 1 | 800 |

#### CSS cubic-bezier tokens (for `transition-timing-function`)

| Token | cubic-bezier | Duration |
|---|---|---|
| `motion-curve-fast-spatial` | `cubic-bezier(0.42, 1.67, 0.21, 0.90)` | 350ms |
| `motion-curve-default-spatial` | `cubic-bezier(0.38, 1.21, 0.22, 1.00)` | 500ms |
| `motion-curve-slow-spatial` | `cubic-bezier(0.39, 1.29, 0.35, 0.98)` | 650ms |
| `motion-curve-fast-effects` | `cubic-bezier(0.31, 0.94, 0.34, 1.00)` | 150ms |
| `motion-curve-default-effects` | `cubic-bezier(0.34, 0.80, 0.34, 1.00)` | 200ms |
| `motion-curve-slow-effects` | `cubic-bezier(0.34, 0.88, 0.34, 1.00)` | 300ms |

**CSS usage:**

```css
.button {
  transition:
    transform var(--md-sys-motion-curve-fast-spatial-duration) var(--md-sys-motion-curve-fast-spatial),
    background-color var(--md-sys-motion-curve-fast-effects-duration) var(--md-sys-motion-curve-fast-effects);
}
```

#### Speed selection guide

| Speed | Use for |
|---|---|
| Fast | Small components — buttons, switches, chips |
| Default | Mid-size — bottom sheet, expanded nav rail, dialog |
| Slow | Full-screen — page transitions, hero animations |

#### Expressive components extend morph beyond pressed (5 patterns)

1. **Single-state shape morph (button §4):** rest → pressed only.
2. **Per-state shape morph (list-item §19, vertical menu-item §22):** every state has its own corner.
3. **Position-aware shape morph (vertical menu §22):** first-child / last-child of a group get different outer corners.
4. **Per-state size morph (switch §30, slider §27, text field active indicator §32, toolbar floating FAB §33):** width / height / thickness changes per state.
5. **Per-state typography morph (text field label §32):** font-size and line-height shrink when label transitions empty → populated (16/24 → 12/16). Floating label pattern.

All five use **spatial spring** for transition. Color/opacity changes use **effects spring**.

### 0.10 Shape scale + directional corners

**Full shape scale (10 tiers + literals):**

| Token | Value | Notable use |
|---|---|---|
| `corner-none` | 0px | App bar, full-screen dialog |
| `corner-extra-small` | 4px | Snackbar, menu item base, text field outlined |
| `corner-small` | 8px | Chip, menu container, video thumbnail |
| `corner-medium` | 12px | Card, button XS/S square |
| `corner-large` | 16px | List container, button L/XL pressed, side sheet detached |
| `corner-large-increased` | 20px | Medium FAB §7, Toolbar floating FAB collapsed §33 |
| `corner-extra-large` | 28px | Card carousel item, dialog basic, button L/XL square, modal date picker |
| `corner-extra-large-increased` | 32px | Hero cards, large dialogs (Axismundi WP theme extensions) |
| `corner-extra-extra-large` | 48px | Hero cards, large dialogs (Axismundi WP theme extensions) |
| `corner-full` | 50% / 9999px | Button round, FAB, search bar, badge, switch track |

Sub-`xs` literals (e.g. checkbox 2px corner) — kept as component literals.

**Directional shape modifiers:**

| Modifier | Meaning | Used by |
|---|---|---|
| `.top` | Top corners only (bottom = 0) | Bottom sheet (§26), Filled text field (§32) |
| `.start` | Start corners only (end = 0) | Modal side sheet (§26) |

In CSS: `border-radius: 28px 28px 0 0` (top), `border-radius: 16px 0 0 16px` (start, LTR — flips in RTL).

### 0.11 Focus indicator

| Element | Token / value |
|---|---|
| Color | `secondary` |
| Thickness | 3px |
| Offset | per-component: outer +2px (cards, chips, checkbox, search bar, sheets, switch) OR inner -3px (list-item, menu-item, tabs) |

Focus indicator and focus state layer coexist.

**Note:** Text fields do NOT use the standard focus indicator. They use their own active-indicator (filled) or container outline (outlined) thickness change instead.

### 0.12 Scrim rule (modal / overlay components)

| Element | Token | Opacity |
|---|---|---|
| Scrim | `scrim` | 0.32 |

Modal surfaces (basic dialog, modal sheet, modal date/time picker, modal nav rail expanded) render scrim. Click on scrim → dismiss (unless required-action).

Full-screen surfaces and standard sheet variants do **not** use scrim.

### 0.13 Elevation system

**Six levels with dp values:**

| Token | dp value | Resting | Used by |
|---|---|---|---|
| `level0` | 0dp | ✓ | App bar (rest), filled/tonal/outlined buttons, button groups, filled/outlined cards, carousel, chips (default), full-screen dialog, FAB menu list items, icon buttons, list, navigation rail, segmented button, side sheet (docked), slider, split button, tabs |
| `level1` | 1dp | ✓ | Banner, bottom sheet (modal), elevated button, elevated card, elevated chips, navigation drawer (modal), side sheet (modal) |
| `level2` | 3dp | ✓ | App bar (scrolled), menu, navigation bar, rich tooltip, toolbar |
| `level3` | 6dp | ✓ | Date pickers, modal dialog, extended FAB, FAB, FAB menu close button, search, time pickers |
| `level4` | 8dp | interaction only | Hover state for FAB, dragged state for list-item, dragged for cards |
| `level5` | 12dp | interaction only | (rarely used) |

> ⚠️ **Surface tint color is deprecated.** Use elevation level tokens (0–5) directly. Surfaces show elevation via tonal containers (surface-container-low → -highest) plus shadow if needed.

**Cap on resting elevation:** levels 0-3 only. Levels 4-5 reserved for interaction states (hover, focus, pressed, dragged).

### 0.14 Active indicator pattern (navigation components)

| Element | Token |
|---|---|
| Indicator container | `secondary-container` |
| Active icon | `on-secondary-container` |
| Active label | `secondary` |
| Inactive icon | `on-surface-variant` |
| Inactive label | `on-surface-variant` |
| Indicator shape | `corner-full` |

State layer over active indicator: `on-secondary-container`.

**Note:** Tabs (§31) and Text fields (§32) use *different* active-indicator patterns. Don't unify.

### 0.15 Bucket marker

Components are tagged with a bucket per `BLOCK-COMPONENT-MAP.md §0`:

- **A** Block Style on a core block
- **B** Block Pattern (composition of core blocks)
- **C** Custom block (plugin)
- **D** Template-part / FSE-level
- **E** Drop / replace (form plugin territory)
- **F** Theme JS only
- **`(deferred)`** suffix — recorded but not implemented in v1

---

## 1. App Bar `D`

### 1.1 Variants
- `small`, `medium-flexible`, `large-flexible`, `search`

### 1.2 Common tokens (all variants)

| Element | Token |
|---|---|
| Container color | `surface` |
| Container color (scrolled) | `surface-container` |
| Container elevation | `level0` |
| Container elevation (scrolled) | `level2` |
| Container shape | `corner-none` |
| Title color | `on-surface` |
| Subtitle color | `on-surface-variant` |
| Leading icon color | `on-surface` |
| Trailing icon color | `on-surface-variant` |
| Left/right padding | 4px |
| Icon spacing | 0px |
| Icon size | 24px |
| Avatar size | 32px |

### 1.3 Per-variant

| Variant | Height | Height (with subtitle) | Title | Subtitle |
|---|---|---|---|---|
| `small` | 64px | — | `title-large` | `label-medium` |
| `medium-flexible` | 112px | 136px | `headline-medium` | `title-small` |
| `large-flexible` | 120px | 152px | `display-small` | `title-medium` |

### 1.4 Search variant
See §29.2 — same tokens as standalone search bar.

### 1.5 Configurations
- Text alignment: leading (default) or centered
- Trailing icon button can be replaced with single filled icon button
- Image/logo can replace label text on `small` variant only
- Subtitle supported on `medium-flexible` and `large-flexible` only

---

## 2. Badge `C`

### 2.1 Variants
- `small` — dot only
- `large` — circular container with numeric label

### 2.2 Tokens

| Element | Token |
|---|---|
| Container color | `error` |
| Label color | `on-error` |
| Shape | `corner-full` |
| Label typography | `label-small` |

### 2.3 Sizes

| Variant | Size |
|---|---|
| `small` | 6 × 6px |
| `large` (1 digit) | 16 × 16px |
| `large` (max chars) | 16 × 34px |

### 2.4 Positioning (anchor: top-trailing of host)

| Variant | Offset (H × W) |
|---|---|
| `small` | 6 × 6px |
| `large` | 14 × 12px |

Gap between large badge and adjacent text: 4px.

### 2.5 Host surfaces
Nav bar items, nav rail items, icon buttons, menu items, tab labels.

---

## 3. Button — Decision guide

| Emphasis | Component | Use for |
|---|---|---|
| Highest | FAB / Extended FAB / FAB menu | Primary screen action |
| High | Button (filled) | Final / unblocking actions |
| High | Split button | Action with related options |
| High | Standard button group | Multiple key actions |
| Medium | Button (tonal) | Secondary final actions |
| Medium | Button (elevated) | Visual separation from patterned bg |
| Medium | Button (outlined) | Important non-primary |
| Low | Connected button group | Toggling visible content |
| Low | Button (text) | Optional |
| Low | Icon button | Bookmark, Star, Print |

**Hierarchy rule:** one high-emphasis button per screen.

---

## 4. Button (common) `A`

### 4.1 Variants
- **Color:** `elevated`, `filled`, `tonal`, `outlined`, `text` (text doesn't support toggle)
- **Size:** XS, S, M, L, XL — default S
- **Shape:** round (default), square
- **Mode:** default, toggle

### 4.2 Sizes

| Size | Height | Outline | Icon | Label typography | Leading | Between | Trailing |
|---|---|---|---|---|---|---|---|
| XS | 32px | 1px | 20px | `label-large` | 12px | 8px | 12px |
| S | 40px | 1px | 20px | `label-large` | 16px | 8px | 16px |
| M | 56px | 1px | 24px | `title-medium` ¹ | 24px | 8px | 24px |
| L | 96px | 2px | 32px | `headline-small` (brand) | 48px | 12px | 48px |
| XL | 136px | 3px | 40px | `headline-medium` (brand) ² | 64px | 16px | 64px |

¹ Source spec shows only "Aa" for M label; M3 type docs specify 16/24/500 = `title-medium`.
² Source spec shows only "Aa md.ref.typeface.brand"; safest match by scaling = `headline-medium`. Confirm before shipping XL.

### 4.3 Shape

| Size | Round | Square (default rest) | Pressed morph |
|---|---|---|---|
| XS | `corner-full` | `corner-medium` (12px) | `corner-small` (8px) |
| S | `corner-full` | `corner-medium` (12px) | `corner-small` (8px) |
| M | `corner-full` | (16px) | (12px) |
| L | `corner-full` | `corner-extra-large` (28px) | `corner-large` (16px) |
| XL | `corner-full` | `corner-extra-large` (28px) | `corner-large` (16px) |

**Toggle shape rule:** resting round → selected square; resting square → selected round.

Shape morph motion: §0.9 (fast spatial spring).

### 4.4 Color — default mode

| Color variant | Container | Label / icon | Elevation |
|---|---|---|---|
| Elevated | `surface-container-low` | `primary` | `level1` |
| Filled | `primary` | `on-primary` | `level0` |
| Tonal | `secondary-container` | `on-secondary-container` | `level0` |
| Outlined | transparent (outline = `outline-variant`) | `on-surface-variant` | `level0` |
| Text | none | `primary` | — |

### 4.5 Color — toggle mode

| Color variant | Unselected (container / label) | Selected (container / label) |
|---|---|---|
| Elevated | `surface-container-low` / `primary` | `primary` / `on-primary` |
| Filled | `surface-container` / `on-surface-variant` | `primary` / `on-primary` |
| Tonal | `secondary-container` / `on-secondary-container` | `secondary` / `on-secondary` |
| Outlined | (outline = `outline-variant`) / `on-surface-variant` | `inverse-surface` / `inverse-on-surface` |
| Text | — (toggle not supported) | — |

State layers per §0.7. Disabled per §0.8 Pattern A. Only `elevated` shifts elevation between rest/disabled.

---

## 5. Button group `C`

### 5.1 Variants
- **Standard** — modifies width/shape of pressed/selected button + adjacent
- **Connected** — only modifies pressed/selected button

### 5.2 Sizes
Inherits Button heights and color tokens.

### 5.3 Standard group — inner padding

| Size | Padding |
|---|---|
| XS | 18px |
| S | 12px |
| M / L / XL | 8px |

### 5.4 Connected group

- Inner padding: 2px
- Outer shape: `corner-full` (round) OR per-size square
- Inner corner: XS 4px, S/M 8px, L 16px, XL 20px
- XS/S require minimum width 48px

### 5.5 Selection modes
Single-select / Multi-select / Selection-required

### 5.6 Standard group — pressed motion
Spring per §0.9 (fast spatial). Pressed button widens ~15%; adjacent buttons compress.

---

## 6. Icon button `C`

### 6.1 Variants
- **Color:** `filled` (default), `tonal`, `outlined`, `standard`
- **Size:** XS, S, M, L, XL
- **Shape:** round (default), square
- **Width:** narrow, default, wide
- **Mode:** default, toggle

### 6.2 Sizes & shapes
Reuses Button §4.2 / §4.3. XS/S require 48×48px target area.

### 6.3 Color — default mode

| Color | Container | Icon |
|---|---|---|
| Filled | `primary` | `on-primary` |
| Tonal | `secondary-container` | `on-secondary-container` |
| Outlined | transparent (outline = `outline-variant`) | `on-surface-variant` |
| Standard | none | `on-surface-variant` |

### 6.4 Color — toggle mode

| Color | Unselected (container / icon) | Selected (container / icon) |
|---|---|---|
| Filled | `surface-container` / `on-surface-variant` | `primary` / `on-primary` |
| Tonal | `secondary-container` / `on-secondary-container` | `secondary` / `on-secondary` |
| Outlined | (outline = `outline-variant`) / `on-surface-variant` | `inverse-surface` / `inverse-on-surface` |
| Standard | none / `on-surface-variant` | none / `primary` |

**Glyph rule:** outlined glyph for unselected, filled glyph for selected.

---

## 7. FAB `D`

### 7.1 Variants
`fab` (default), `medium`, `large`. Small FAB deprecated.

### 7.2 Sizes

| Variant | Container | Icon | Shape |
|---|---|---|---|
| `fab` | 56 × 56px | 24px | `corner-large` (16px) |
| `medium` | 80 × 80px | 28px | `corner-large-increased` (20px) |
| `large` | 96 × 96px | 36px | `corner-extra-large` (28px) |

### 7.3 Color (6 styles, all visually equivalent)

| Style | Container | Icon |
|---|---|---|
| Tonal primary (default) | `primary-container` | `on-primary-container` |
| Tonal secondary | `secondary-container` | `on-secondary-container` |
| Tonal tertiary | `tertiary-container` | `on-tertiary-container` |
| Primary | `primary` | `on-primary` |
| Secondary | `secondary` | `on-secondary` |
| Tertiary | `tertiary` | `on-tertiary` |

### 7.4 Elevation

| State | Elevation |
|---|---|
| Rest | `level3` |
| Hover | `level4` |
| Focus / pressed | `level3` |
| Disabled | `level0` |

---

## 8. Extended FAB `D`

### 8.1 Variants
`small` (default), `medium`, `large`.

### 8.2 Sizes (small only)

| Element | Value / token |
|---|---|
| Container height | 56px |
| Min width | 80px (dynamic) |
| Icon size | 24px |
| Container shape | `corner-large` (16px) |
| Padding | 16px |
| Label typography | `title-medium` |

### 8.3 Color
Same 6 styles as FAB §7.3.

---

## 9. FAB menu `E (deferred)` (drop for v1)

### 9.1 Anatomy
Close button + 2–6 list items.

### 9.2 Close button

| Element | Value / token |
|---|---|
| Size | 56 × 56px |
| Icon size | 20px |
| Shape | `corner-full` |
| Elevation (rest) | `level3` |
| Gap to first item | 8px |

### 9.3 List item

| Element | Value / token |
|---|---|
| Height | 56px |
| Shape | `corner-full` |
| Elevation | `level0` |
| Icon size | 24px |
| Label typography | `title-medium` |
| Leading / between / trailing | 24px / 8px / 24px |
| Gap between items | 4px |

### 9.4 Color (3 sets)

| Set | Close button (container / icon) | List item (container / icon + label) |
|---|---|---|
| Primary | `primary` / `on-primary` | `primary-container` / `on-primary-container` |
| Secondary | `secondary` / `on-secondary` | `secondary-container` / `on-secondary-container` |
| Tertiary | `tertiary` / `on-tertiary` | `tertiary-container` / `on-tertiary-container` |

---

## 10. Split button `C (deferred)` (Tier 3)

### 10.1 Anatomy
Leading button + trailing menu button.

### 10.2 Sizes

| Size | Height | Between | Inner corner (rest) | Trailing icon |
|---|---|---|---|---|
| XS | 32px | 2px | 4px | 22px |
| S | 40px | 2px | 4px | 24px ³ |
| M | 56px | 2px | 4px | 28px ³ |
| L | 96px | 2px | 8px | 36px ³ |
| XL | 136px | 2px | 12px | 40px ³ |

³ Source data only verifies XS. Other sizes extrapolated.

Container shape: `corner-full`. Inner corner morphs to 8px on hover/pressed.

### 10.3 Trailing icon visual centering offset

| Size | Offset |
|---|---|
| XS / S | -1px |
| M | -2px |
| L | -3px |
| XL | -6px |

When trailing button selected: inner corner = 50% (full round).

### 10.4 Color
Reuses Button color tokens. Selected state does NOT change color — only state layer applies.

---

## 11. Card `A`

### 11.1 Variants
- `elevated`, `filled`, `outlined`

### 11.2 Common tokens

| Element | Token / value |
|---|---|
| Shape | `corner-medium` (12px) |
| Left/right padding | 16px |
| Padding between cards | 8px (max) |
| Label text alignment | start |
| Icon color | `primary` |
| Icon size | 24px |
| Body text color | `on-surface` |

### 11.3 Per-variant container

| Variant | Container color | Outline | Elevation (rest) |
|---|---|---|---|
| Elevated | `surface-container-low` | — | `level1` |
| Filled | `surface-container-highest` | — | `level0` |
| Outlined | `surface` | `outline-variant`, 1px | `level0` |

### 11.4 Elevation per state

| Variant | Rest | Hover | Focus | Pressed | Dragged |
|---|---|---|---|---|---|
| Elevated | `level1` | `level2` | `level1` | `level1` | `level4` |
| Filled | `level0` | `level1` | `level0` | `level0` | `level3` |
| Outlined | `level0` | `level1` | `level0` | `level0` | (unchanged) |

### 11.5 State layer
Per §0.7. Color = `on-surface`. Outlined card focus also changes outline color → `on-surface`.

### 11.6 Focus indicator
Per §0.11. Outer offset +2px.

### 11.7 Disabled
Per §0.8 Pattern B.
- Elevated: container = `surface`
- Filled: container = `surface-variant`
- Outlined: outline = `outline` @ 0.12

---

## 12. Carousel `C (deferred)` (Tier 3)

### 12.1 Layouts

| Layout | Items shown | Use for |
|---|---|---|
| Multi-browse | ≥1 large + medium + small | Mixed-density browsing |
| Uncontained | Items bleed past edges | Content-first scroll |
| Uncontained multi-aspect-ratio | Items vary | Mixed media |
| Hero | ≥1 large + 1 small | Featured + queue |
| Center-aligned hero | ≥1 large + 2 small | Symmetric featured |
| Full-screen | 1 edge-to-edge large | Single-focus (stories) |

### 12.2 Common tokens

| Element | Token / value |
|---|---|
| Item corner radius | `corner-extra-large` (28px) |
| Container background | `surface` |

### 12.3 Per-layout padding

| Layout | Leading | Trailing | Top/bottom | Between |
|---|---|---|---|---|
| Multi-browse | 16px | 16px | 8px | 8px |
| Uncontained | 16px | 0 (bleed) | 8px | 8px |
| Uncontained multi-aspect | 16px | 0 (bleed) | 8px | 8px |
| Hero | 16px | 16px | 8px | 8px |
| Center-aligned hero | 16px | 16px | 8px | 8px |
| Full-screen | 0 | 0 | 0 | 16px |

### 12.4 Item width rules

| Item size | Width |
|---|---|
| Large | dynamic (or user-set max) |
| Medium | dynamic |
| Small | 40–56px |

---

## 13. Checkbox `E` (form plugin styling target)

### 13.1 Dimensions

| Element | Value |
|---|---|
| Container size | 18 × 18px |
| Container corner | 2px (sub-xs literal) |
| Icon size | 18px |
| State layer size | 40 × 40px circle |
| State layer shape | `corner-full` |
| Touch target size | 48 × 48px |

### 13.2 Color — by state

| State | Container | Outline | Outline width | Icon |
|---|---|---|---|---|
| Unselected | transparent | `on-surface-variant` | 2px | — |
| Selected | `primary` | — | 0 | `on-primary` |
| Unselected, error | transparent | `error` | 2px | — |
| Selected, error | `error` | — | 0 | `on-error` |

### 13.3 State layers
Per §0.7. Unselected = `on-surface`, selected = `primary`, error = `error`. Pressed quirk: unselected pressed uses `primary` (preview), selected pressed uses `on-surface`.

### 13.4 Disabled (Pattern C)

| Element | Token | Opacity |
|---|---|---|
| Unselected outline | `on-surface` | 0.38 |
| Selected container | `on-surface` | 0.38 |
| Selected icon | `surface` | 1 |

---

## 14. Chip `C`

### 14.1 Variants

| Variant | Purpose | Anatomy |
|---|---|---|
| Assist | Single action | Container + label + leading icon (optional) |
| Filter | Toggleable filter | Container + label + leading icon (selected = checkmark) + trailing icon (optional) |
| Input | User input token | Container + label + leading icon/avatar + trailing close (×) |
| Suggestion | AI/dynamic suggestion | Container + label only |

Each variant supports optional **elevated** style.

### 14.2 Common tokens

| Element | Token / value |
|---|---|
| Height | 32px |
| Shape | `corner-small` (8px) |
| Icon size | 18px |
| Padding (no leading icon) | 16px L/R |
| Padding (with leading icon) | 8px L/R |
| Padding between elements | 8px |
| Label typography | `label-large` |
| Default elevation (rest) | `level0` |
| Elevated style elevation (rest) | `level1` |

### 14.3 Color — Assist

| Element | Token |
|---|---|
| Container outline (default) | `outline-variant`, 1px |
| Container fill (elevated) | `surface-container-low` |
| Label text | `on-surface` |
| Icon | `primary` |

### 14.4 Color — Filter

| State | Outline | Fill (elevated) | Label | Leading icon | Trailing icon |
|---|---|---|---|---|---|
| Unselected | `outline-variant`, 1px | `surface-container-low` | `on-surface-variant` | `primary` | `on-surface-variant` |
| Selected | none | `secondary-container` | `on-secondary-container` | `on-secondary-container` | `on-secondary-container` |

### 14.5 Color — Input

| State | Outline | Fill (selected) | Label | Leading icon | Trailing icon |
|---|---|---|---|---|---|
| Unselected | `outline-variant`, 1px | — | `on-surface-variant` | `on-surface-variant` | `on-surface-variant` |
| Selected | none | `secondary-container` | `on-secondary-container` | `primary` | `on-secondary-container` |

Avatar option: `corner-full`, 24px, 4px L padding, 8px R padding, 48px close target.

### 14.6 Color — Suggestion

| Element | Token |
|---|---|
| Container outline (default) | `outline`, 1px |
| Container fill (elevated) | `surface-container-low` |
| Label text | `on-surface-variant` |

### 14.7 Elevation — elevated style

| State | Elevation |
|---|---|
| Rest | `level1` |
| Hover | `level2` |
| Focus / pressed | `level1` |
| Dragged | `level4` |

### 14.8 Disabled
Per §0.8 Pattern A. Chip uses **0.12** for container/outline:

| Element | Token | Opacity |
|---|---|---|
| Outline | `on-surface` | 0.12 |
| Container fill (elevated, or selected) | `on-surface` | 0.12 |
| Label / icon | `on-surface` | 0.38 |
| Avatar | (unchanged) | 0.38 |

---

## 15. Date picker `C (deferred)` (low priority — plugin if usecase arises)

### 15.1 Variants
- `docked`, `modal`, `modal-input`

### 15.2 Container tokens

| Element | Token / value |
|---|---|
| Container color | `surface-container-high` |
| Container elevation | `level3` |
| Modal container shape | `corner-large` (docked) or `corner-extra-large` (modal/modal-input) |
| Docked container | 360 × 456px |
| Modal container | 360 × 524px |
| Modal-input container | 328 × 512px |

Modal variants render scrim per §0.12.

### 15.3 Date cell tokens

| Element | Token |
|---|---|
| Date container shape | `corner-full` |
| Date container size | 40-48px |
| Today outline | `primary`, 1px |
| Selected container | `primary` |
| Selected label | `on-primary` |
| Unselected label | `on-surface` |
| Outside-month label opacity | 0.38 |
| In-range active indicator | `secondary-container` |
| In-range date label | `on-secondary-container` |
| State layer | 40×40px circle |
| Date label typography | `body-large` |

### 15.4 Header (modal variants)

| Element | Token |
|---|---|
| Header height | 64px (docked) / 120px (modal) / 128px (range modal) |
| Headline color | `on-surface-variant` |
| Headline typography (modal) | `headline-large` |
| Headline typography (range modal) | `title-large` |
| Supporting text typography | `label-large` |

### 15.5 Year selection

| Element | Token |
|---|---|
| Year container | 72 × 36px |
| Year selected container | `primary` |
| Year selected label | `on-primary` |
| Year unselected label | `on-surface-variant` |
| Year typography | `body-large` |

---

## 16. Time picker `C (deferred)` (low priority — plugin if usecase arises)

### 16.1 Variants
- `dial` — vertical or horizontal layout, 12h or 24h
- `input` — two text inputs

### 16.2 Container tokens

| Element | Token |
|---|---|
| Container color | `surface-container-high` |
| Container elevation | `level3` |
| Container shape | `corner-extra-large` |
| Padding | 24px all sides |

Renders scrim per §0.12.

### 16.3 Time selector

| Element | Token |
|---|---|
| Selected container | `primary-container` |
| Selected label | `on-primary-container` |
| Unselected container | `surface-container-highest` |
| Unselected label | `on-surface` |
| Container shape | `corner-small` |
| Container size (dial) | 96 × 80px |
| Container size (input) | 96 × 72px |
| Label typography | `display-large` |

### 16.4 Period selector (AM/PM)

| Element | Token |
|---|---|
| Selected container | `tertiary-container` |
| Selected label | `on-tertiary-container` |
| Unselected container | transparent |
| Unselected label | `on-surface-variant` |
| Outline | `outline`, 1px |
| Container shape | `corner-small` |
| Vertical layout | 52 × 80px |
| Horizontal layout | 216 × 38px |
| Label typography | `title-medium` |

### 16.5 Clock dial

| Element | Token |
|---|---|
| Dial container | `surface-container-highest`, `corner-full`, 256px |
| Selector handle | `primary`, `corner-full`, 48px |
| Selector center | `primary`, `corner-full`, 8px |
| Selector track | `primary`, 2px width |
| Dial label — selected | `on-primary` |
| Dial label — unselected | `on-surface` |
| Dial label typography | `body-large` |

---

## 17. Dialog `C`

### 17.1 Variants
- `basic` — modal with scrim, sized to content
- `full-screen` — replaces full screen, no scrim

### 17.2 Basic dialog

| Element | Token / value |
|---|---|
| Container color | `surface-container-high` |
| Container elevation | `level3` |
| Container shape | `corner-extra-large` (28px) |
| Container width | min 280px, max 560px |
| Padding (all sides) | 24px |
| Padding between buttons | 8px |
| Padding icon ↔ title | 16px |
| Padding title ↔ body | 16px |
| Padding body ↔ actions | 24px |
| Divider thickness | 1px (`outline-variant`) |
| Icon size | 24px |
| Icon color | `secondary` |
| Headline color | `on-surface` |
| Headline typography | `headline-small` |
| Supporting text color | `on-surface-variant` |
| Supporting text typography | `body-medium` |
| Action button label color | `primary` |
| Action button typography | `label-large` |
| Alignment (with icon) | center-aligned |
| Alignment (without icon) | start-aligned |

Renders scrim per §0.12.

### 17.3 Full-screen dialog

| Element | Token / value |
|---|---|
| Container color | `surface` |
| Container color (on scroll) | `surface-container` |
| Container elevation | `level0` |
| Container shape | `corner-none` |
| Header height | 56px |
| Header elevation (rest / scroll) | `level0` / `level2` |
| Headline color | `on-surface` |
| Headline typography | `title-large` |
| Headline alignment | start |
| Close icon color | `on-surface` |
| Close icon size | 24px |
| Bottom action bar height | 56px |
| Top/left/right padding | 24px |
| Padding between elements | 8px |

**No scrim** — content is replaced.

---

## 18. Divider `A`

| Element | Token |
|---|---|
| Color | `outline-variant` |
| Thickness | 1px |

Layout variants: full-width / inset (16px L margin) / middle-inset (16px L+R margins). Spacing: 4px to supporting text; 8px right margin (adjacent), 8px bottom margin (stacked).

---

## 19. List `A` (Block Style on `core/list` for simple; pattern for full list-item)

### 19.1 Variants
- **Style:** standard, segmented (segmented adds 2px gaps and rounds each item)
- **Selection:** single-action, multi-action, single-select, multi-select
- **Visual:** baseline, **expressive** (default — supports state-by-state shape morph)

### 19.2 Container

| Element | Token / value |
|---|---|
| List container shape | `corner-large` (16px) |
| List container background | `surface` |
| List container elevation | `level0` |
| Segmented gap between items | 2px |

### 19.3 List item dimensions

| Element | Value |
|---|---|
| Single-line height | 56px |
| Two-line height | 72px |
| Three-line height | 88px |
| Leading / trailing space | 16px |
| Top/bottom space | 10px |
| Between content | 12px |
| Divider leading/trailing space | 16px |

### 19.4 Color — default (unselected)

| Element | Token |
|---|---|
| Container | `surface` |
| Label | `on-surface` |
| Supporting text | `on-surface-variant` |
| Overline | `on-surface-variant` |
| Leading icon | `on-surface-variant` |
| Trailing icon | `on-surface-variant` |
| Trailing supporting text | `on-surface-variant` |

### 19.5 Color — selected

| Element | Token |
|---|---|
| Container | `secondary-container` |
| All text + icons | `on-secondary-container` |

### 19.6 Expressive shape state machine (canonical example for §0.9 pattern 2)

| State | Item shape |
|---|---|
| Rest (unselected) | `corner-extra-small` (4px) |
| Hover | `corner-medium` |
| Focus / Pressed / Dragged / Selected | `corner-large` |
| Disabled | `corner-extra-small` |

Baseline list item: `corner-none` for all states.

### 19.7 Slots & elements

**Leading slot:**
- Avatar — 40px, `corner-full`, container `primary-container`, label `on-primary-container`, typography `title-medium`
- Icon — 24px (baseline) or 20px (expressive)
- Image — 56 × 56px, `corner-none` (baseline) or `corner-small` (expressive)
- Video — 100×56px (small) or 114×64px (large), `corner-small`
- Selection control

**Content slot:**
- Label text (required) — `body-large`
- Supporting text — `body-medium`
- Overline — `label-small`

**Trailing slot:**
- Icon — 24px (baseline) or 20px (expressive), color `on-surface-variant`
- Trailing supporting text — `label-small`, color `on-surface-variant`
- Selection control

### 19.8 Focus indicator
Per §0.11. Inner offset -3px.

### 19.9 Disabled
Per §0.8 Pattern A. Container 0.1, label/icon 0.38.

### 19.10 Slot a11y
- Target ≥ 48 × 48px
- Only one selection interaction per item

---

## 20. Loading indicator `F`

Replaces indeterminate circular progress for short waits and pull-to-refresh.

### 20.1 Variants
- `default` — uncontained
- `contained` — filled container

### 20.2 Tokens

| Element | Token / value |
|---|---|
| Active indicator size | 48 × 48px |
| Container width × height | 38 × 48px |
| Container shape | `corner-full` |
| Active indicator color (default) | `primary` |
| Container fill (contained) | `primary-container` |
| Active indicator color (contained) | `on-primary-container` |

---

## 21. Progress indicator `C`

### 21.1 Variants
- `linear`, `circular`
- **Behavior:** determinate, indeterminate
- **Shape:** flat (default), wavy
- **Thickness:** fixed 4px (default), configurable

### 21.2 Common tokens

| Element | Token |
|---|---|
| Active indicator color | `primary` |
| Track color | `secondary-container` |
| Stop indicator color | `primary` |
| All shapes | `corner-full` |

### 21.3 Linear

| Element | Value |
|---|---|
| Height (flat) | 4px |
| Height (wavy) | 10px |
| Track ↔ active gap | 4px |
| Stop indicator size | 4px |
| Wave amplitude | 3px |
| Wave wavelength (determinate) | 40px |
| Wave wavelength (indeterminate) | 20px |
| Inset from screen edge | 4px |

### 21.4 Circular

| Element | Value |
|---|---|
| Size (flat) | 40px |
| Size (wavy) | 48px |
| Active indicator thickness | 4px |
| Track ↔ active gap | 4px |
| Wave amplitude | 1.6px |
| Wave wavelength | 15px |

---

## 22. Menu `C`

### 22.1 Variants
- `vertical` (Expressive default), `horizontal`
- Color: **Standard** (surface-based) or **Vibrant** (tertiary-based, sparingly)

### 22.2 Container tokens

| Element | Standard | Vibrant |
|---|---|---|
| Container color | `surface-container-low` | `tertiary-container` |
| Container elevation | `level2` | `level2` |
| Vertical container shape | `corner-large` | `corner-large` |
| Horizontal container shape | `corner-full` | `corner-full` |
| Group shape (within menu) | `corner-small` | `corner-small` |
| Gap between items | 2px | 2px |

### 22.3 Item dimensions (vertical)

| Element | Value |
|---|---|
| Item height | 44px |
| Top/bottom space | 8px |
| Leading/trailing space | 16px |
| Between space (icon ↔ label) | 12px |
| Leading / trailing icon size | 20px |

### 22.4 Item dimensions (horizontal)

| Element | Value |
|---|---|
| Container top/bottom space | 8px |
| Item leading/trailing space | 12px |
| Item top/bottom space | 6px |
| Item between space | 12px |
| Icon-only item leading/trailing/top/bottom | 16px |
| Icon-only gap | 4px |

### 22.5 Item shape — vertical (per §0.9 patterns 2 + 3)

Position-aware: first/last child have larger outer corners.

| State | Middle item | First-child outer | Last-child outer |
|---|---|---|---|
| Rest | `corner-small` | `corner-medium` | `corner-medium` |
| Active (parent of open submenu) | `corner-large` | (inherits) | (inherits) |
| Selected | `corner-medium` | (inherits) | (inherits) |
| Default base | `corner-extra-small` | — | — |

First/last-child have smaller **inner** corner (`corner-extra-small`) opposite the rounded outer.

### 22.6 Item shape — horizontal

| State | Item shape |
|---|---|
| Rest | (inherits container) |
| Hover / focus / pressed | `corner-medium` |
| Selected hover/focus/pressed | `corner-full` |
| Selected (icon-only) | `corner-full` |

### 22.7 Color — Standard

| Element | Token |
|---|---|
| Item container | `surface-container-low` |
| Section label | `on-surface-variant` |
| Item label | `on-surface` |
| Supporting / trailing supporting | `on-surface-variant` |
| Leading / trailing icon | `on-surface-variant` |
| Selected container | `tertiary-container` |
| Selected label / icon | `on-tertiary-container` |

### 22.8 Color — Vibrant

| Element | Token |
|---|---|
| Item container | `tertiary-container` |
| Section label / item label | `on-tertiary-container` |
| Leading icon (rest) | `on-tertiary-container` |
| Leading icon (hover/focus/pressed) | `tertiary` |
| Selected container | `tertiary` |
| Selected label / icon | `on-tertiary` |

### 22.9 Typography

| Element | Token |
|---|---|
| Label text | `label-large` (14/20/500) |
| Supporting text | `body-small` (12/16/400) |
| Trailing supporting text | `label-large` |

### 22.10 Focus indicator
Per §0.11. Inner offset -3px.

---

## 23. Navigation bar `D`

### 23.1 Variants
- `flexible` (default — Expressive)
- baseline navigation bar still available, not recommended

### 23.2 Container

| Element | Token |
|---|---|
| Container color | `surface-container` |
| Container elevation | `level2` |
| Container shape | `corner-none` |
| Container height | 64px |
| Width | full viewport |
| Item gap | 0 |
| Item shape | `corner-full` |
| Icon size | 24px |
| Icon ↔ label space | 4px |

### 23.3 Color
Per §0.14.

### 23.4 Item — vertical layout (compact, default)

| Element | Token / value |
|---|---|
| Active indicator | 56 × 32px |
| Container between space | 6px |
| Indicator ↔ icon-label space | 4px |
| Label typography | `label-medium` (12/16/500, tracking 0.5) |

### 23.5 Item — horizontal layout (medium windows)

| Element | Token / value |
|---|---|
| Active indicator height | 40px |
| Indicator leading/trailing space | 16px |
| Indicator ↔ icon-label space | 4px |
| Label typography | `label-medium` |

Vertical items dynamically share width. Horizontal items fixed width — extra space at ends.

---

## 24. Navigation rail `D`

> **Important:** Navigation drawer merged into rail. Use **expanded navigation rail (modal layout)**. Update `BLOCK-COMPONENT-MAP §1.4`.

### 24.1 Variants
- `collapsed`, `expanded`
- baseline rail still available, not recommended

### 24.2 Collapsed container

| Element | Token / value |
|---|---|
| Container color | `surface` |
| Container elevation | `level0` |
| Container shape | `corner-none` |
| Container width (default) | 96px |
| Container width (narrow) | 80px |
| Item top space | 44px |
| Item vertical space | 4px |

### 24.3 Expanded container

| Element | Standard | Modal |
|---|---|---|
| Container color | `surface` | `surface-container` |
| Container elevation | `level0` | `level2` |
| Container shape | `corner-none` | `corner-large` |
| Container width | 220–360px | 220–360px |
| Top space | 44px | 44px |

Modal layout renders scrim per §0.12.

### 24.4 Item — common

| Element | Token / value |
|---|---|
| Icon size | 24px |
| Container height | 64px |
| Short container height | 56px |
| Container shape | `corner-none` |
| Container vertical space | 6px |
| Active indicator shape | `corner-full` |
| Active indicator leading/trailing | 16px |
| Indicator ↔ icon/label | 8px |
| Header space minimum | 40px |

### 24.5 Item — vertical (collapsed rail)

| Element | Token / value |
|---|---|
| Active indicator | 56 × 32px |
| Icon ↔ label | 4px |
| Leading/trailing | 16px |
| Label typography | `label-medium` |

### 24.6 Item — horizontal (expanded rail)

| Element | Token / value |
|---|---|
| Active indicator height | 56px |
| Full-width leading/trailing | 16px |
| Icon ↔ label | 8px |
| Label typography | `label-large` (14/20/500) |

### 24.7 Color
Per §0.14.

### 24.8 Configurations
- Hide-when-collapsed (Expressive only)
- Standard expanded (default) vs modal expanded

---

## 25. Radio button `E` (form plugin styling target)

### 25.1 Dimensions

| Element | Value |
|---|---|
| Icon size | 20px |
| State layer size | 40 × 40px circle |
| State layer shape | `corner-full` |
| Touch target size | 48 × 48px |

### 25.2 Color — by state

| State | Icon color |
|---|---|
| Selected | `primary` |
| Unselected | `on-surface-variant` |

### 25.3 State layers

| Combination | State layer color |
|---|---|
| Selected (hover/focus) | `primary` |
| Unselected (hover/focus) | `on-surface` |
| Selected pressed | `on-surface` |
| Unselected pressed | `primary` |

Same cross-color quirk as checkbox §13.3.

### 25.4 Disabled (Pattern C)

| Element | Token | Opacity |
|---|---|---|
| Selected icon | `on-surface` | 0.38 |
| Unselected icon | `on-surface` | 0.38 |

Adjacent label color: `on-surface` regardless of state.

---

## 26. Sheet `C`

### 26.1 Variants
- `bottom-standard` — bottom-anchored, no scrim
- `bottom-modal` — bottom-anchored, with scrim
- `side-standard` — side-anchored, docked, no scrim
- `side-modal` — side-anchored, docked, with scrim
- `side-detached` — side-anchored, floating with margins

### 26.2 Bottom sheet (both variants)

| Element | Token / value |
|---|---|
| Container color | `surface-container-low` |
| Container elevation | `level1` |
| Container shape | `corner-extra-large.top` (28px top, 0 bottom) |
| Container shape (minimized) | `corner-none` |
| Width | full viewport, max 640px |
| Top margin | 72px |
| Top margin (window > 640px) | 56px |
| Side margins (window > 640px) | 56px |
| Drag handle width × height | 32 × 4px |
| Drag handle color | `on-surface-variant` |
| Drag handle padding (top/bottom) | 22px |

Bottom-modal renders scrim per §0.12. Bottom-standard does NOT.

### 26.3 Side sheet — Standard

| Element | Token / value |
|---|---|
| Container color | `surface` |
| Container elevation | `level0` |
| Container shape | `corner-none` |
| Container height | 100% |
| Container width | 256px |

### 26.4 Side sheet — Modal

| Element | Token / value |
|---|---|
| Container color | `surface-container-low` |
| Container elevation | `level1` |
| Container shape | `corner-large.start` (16px start corners, 0 end) |
| Container width | 256px |

Renders scrim per §0.12.

### 26.5 Side sheet — Detached

| Element | Token / value |
|---|---|
| Container shape | `corner-large` (all sides) |
| Margins | 16px |

### 26.6 Side sheet content

| Element | Token / value |
|---|---|
| Start/end padding | 24px |
| Start padding (with icon) | 16px |
| Padding between top elements | 12px |
| Bottom actions height | 72px |
| Bottom actions top padding | 16px |
| Bottom actions bottom padding | 24px |
| Bottom actions alignment | left (start) |
| Max-width | 400px |
| Headline color | `on-surface-variant` |
| Headline typography | `title-large` (22/28/400) |
| Divider color | `outline` |
| Action button label color | `primary` |
| Close icon color | `on-surface-variant` |

### 26.7 Focus indicator
Per §0.11. Outer offset +2px.

---

## 27. Slider `E` (form plugin styling target)

### 27.1 Variants
- `standard`, `centered`, `range`
- Stops configuration (was "discrete")

### 27.2 Configurations
- Orientation: horizontal (default), vertical
- Size: XS (default), S, M, L, XL
- Inset icon, stop indicators, value indicator

### 27.3 Sizes

| Size | Track height | Track shape | Handle height | Inset icon |
|---|---|---|---|---|
| XS (default) | 16px | 8px | 44px | — |
| S | 24px | 8px | 44px | — |
| M | 40px | 12px | 52px | 24px |
| L | 56px | 16px | 68px | 24px |
| XL | 96px | 28px | 108px | 32px |

Handle width: 4px (rest/hover), 2px (focus/pressed). Per §0.9 pattern 4 — size morph.

Stop indicator: 4px size, `corner-full`, 4px trailing space.

### 27.4 Color

| Element | Token |
|---|---|
| Active track | `primary` |
| Inactive track | `secondary-container` |
| Handle | `primary` |
| Stop indicator (active) | `on-primary` |
| Stop indicator (inactive) | `on-secondary-container` |
| Value indicator container | `inverse-surface` |
| Value indicator label color | `inverse-on-surface` |
| Value indicator typography | `label-large` |
| Value indicator bottom space | 12px |

### 27.5 Disabled (Pattern C)

| Element | Token | Opacity |
|---|---|---|
| Active track | `on-surface` | 0.38 |
| Inactive track | `on-surface` | 0.12 |
| Handle | `on-surface` | 0.38 |

---

## 28. Snackbar `F`

### 28.1 Container

| Element | Token / value |
|---|---|
| Container color | `inverse-surface` |
| Container elevation | `level3` |
| Container shape | `corner-extra-small` |
| Single-line height | 48px |
| Two-line height | 68px |

### 28.2 Content

| Element | Token / value |
|---|---|
| Supporting text color | `inverse-on-surface` |
| Supporting text typography | `body-medium` |
| Action label color | `inverse-primary` |
| Action label typography | `label-large` |
| Icon color | `inverse-on-surface` |
| Icon size | 24px |

### 28.3 State layers

| Region | State layer color |
|---|---|
| Action label | `inverse-primary` |
| Icon | `inverse-on-surface` |

### 28.4 Configurations
Single line / Single line with action / Two lines / Two lines with action / Two lines with longer action

---

## 29. Search `C` (also embedded in App Bar §1)

### 29.1 Variants
- **Style:** contained (Expressive default), divided (baseline — not recommended)
- **Layout:** docked, full-screen
- **Surface:** standalone OR embedded in App Bar

### 29.2 Search bar (rest)

| Element | Token / value |
|---|---|
| Container color | `surface-container-high` |
| Container elevation | `level3` |
| Container height | 56px |
| Container shape | `corner-full` |
| Leading icon color | `on-surface` |
| Trailing icon color | `on-surface-variant` |
| Supporting text color | `on-surface-variant` |
| Input text color | `on-surface` |
| Supporting / input typography | `body-large` |
| Icon size | 24px |
| Avatar size | 30px |
| Avatar shape | `corner-full` |

### 29.3 Search bar layout — Contained (Expressive)

| Element | Value |
|---|---|
| Pane leading/trailing margin | 24px |
| Leading/trailing space | 4px |
| No-actions leading/trailing space | 16px |
| Icon ↔ label gap | 4px |
| Avatar target size | 48px |
| Trailing actions gap | 0 |
| Trailing actions leading/trailing space | 4px |

### 29.4 Search view (active state)

| Element | Token / value |
|---|---|
| View container color | `surface-container-high` |
| Contained background color | `surface` |
| Container elevation | `level3` |
| Header supporting text color | `on-surface-variant` |
| Header input text color | `on-surface` |
| Header leading icon color | `on-surface` |
| Header trailing icon color | `on-surface-variant` |
| Divider color | `outline` |
| Header text typography | `body-large` |

### 29.5 Search view layout — Contained

| Element | Value |
|---|---|
| Leading / trailing margin | 12px |
| Docked bar ↔ results gap | 2px |
| Docked results shape | `corner-medium` |
| Docked bar shape | `corner-full` |
| Full-screen bar height | 56px |

### 29.6 Common
Full-screen container shape: `corner-none` (no scrim).

### 29.7 Focus indicator
Per §0.11. Outer offset +2px.

---

## 30. Switch `E` (form plugin styling target)

### 30.1 Track

| Element | Token / value |
|---|---|
| Track height | 32px |
| Track width | 52px |
| Track shape | `corner-full` |
| Outline color (unselected) | `outline` |
| Outline width | 2px |
| Selected track color | `primary` |
| Unselected track color | `surface-container-highest` |

### 30.2 Handle (per-state size morph — §0.9 pattern 4)

| State | Width × height |
|---|---|
| Unselected | 16 × 16px |
| With icon (always present) | 24 × 24px |
| Selected | 24 × 24px |
| Pressed | 28 × 28px |

| Element | Token |
|---|---|
| Handle shape | `corner-full` |
| Selected handle color | `on-primary` |
| Unselected handle color | `outline` |
| Selected hover/focus/pressed handle color | `primary-container` |
| Unselected hover/focus/pressed handle color | `on-surface-variant` |

### 30.3 Icon (optional)

| Element | Token / value |
|---|---|
| Icon size | 16px |
| Selected icon color | `primary` |
| Unselected icon color | `surface-container-highest` |

### 30.4 State layer

| Element | Value |
|---|---|
| State layer size | 40 × 40px |
| State layer shape | `corner-full` |

State layer color: selected = `primary`, unselected = `on-surface`.

### 30.5 Focus indicator
Per §0.11. Outer offset +2px.

### 30.6 Disabled (Pattern C)

| Element | Token | Opacity |
|---|---|---|
| Track | varies | 0.12 |
| Selected disabled track | `on-surface` | 0.12 |
| Unselected disabled track | `surface-container-highest` | 0.12 |
| Unselected disabled track outline | `on-surface` | 0.12 |
| Unselected handle | `on-surface` | 0.38 |
| Selected handle | `surface` | 1 |
| Selected/unselected icon | `on-surface` / `surface-container-highest` | 0.38 |

---

## 31. Tabs `C`

### 31.1 Variants
- `primary` — top-level navigation, indicator below icon (3px tall, rounded top)
- `secondary` — within-section navigation, indicator below label (2px tall, flat)

### 31.2 Container (both variants)

| Element | Token / value |
|---|---|
| Container color | `surface` |
| Container elevation | `level0` |
| Container shape | `corner-none` |
| Container height (label only) | 48px |
| Container height (icon + label) | 64px |
| Divider thickness | 1px (`outline-variant`) |
| Icon size | 24px |
| Padding inline icon ↔ text | 8px |
| Padding inline text ↔ badge | 4px |
| Badge overlap on stacked icon | 6px |

### 31.3 Active indicator

| Element | Primary | Secondary |
|---|---|---|
| Color | `primary` | `primary` |
| Height | 3px | 2px |
| Shape | `3px 3px 0 0` (rounded top) | rectangle |
| Inset (each side) | 2px | — |
| Minimum length | 24px | — |

### 31.4 Color — Primary tabs

| Element | Token |
|---|---|
| Active label | `primary` |
| Inactive label | `on-surface-variant` |
| Active icon | `primary` |
| Inactive icon | `on-surface-variant` |
| Label typography | `title-small` (14/20/500) |

### 31.5 Color — Secondary tabs

| Element | Token |
|---|---|
| Active label | `on-surface` |
| Inactive label | `on-surface-variant` |
| Active icon | `on-surface` |
| Inactive icon | `on-surface-variant` |
| Label typography | `title-small` (14/20/500) |

### 31.6 State layer

| Variant | Active | Inactive |
|---|---|---|
| Primary | `primary` | `on-surface` |
| Secondary | `on-surface` | `on-surface` |

### 31.7 Focus indicator
Per §0.11. Inner offset -3px.

### 31.8 Constraints
- Tabs scroll horizontally (no upper limit)
- Place tabs as peers, not nested

---

## 32. Text field `E` (form plugin styling target)

Theme CSS styles output of CF7 / WPForms / Gravity.

### 32.1 Variants
- `filled` — surface-container-highest fill, bottom active indicator
- `outlined` — transparent fill, full container outline

### 32.2 Common dimensions (both variants)

| Element | Value |
|---|---|
| Container height | 56px |
| Target size | 56px |
| L/R padding (no icons) | 16px |
| L/R padding (with icons) | 12px |
| Padding icon ↔ text | 16px |
| Icon size (leading / trailing) | 24px |
| Top/bottom padding (filled) | 8px |
| Supporting text top padding | 4px |
| Padding supporting text ↔ char counter | 16px |

### 32.3 Common typography

| Element | Token |
|---|---|
| Input text | `body-large` (16/24/400, tracking 0.5) |
| Label text (empty) | `body-large` (16/24/400) |
| Label text (populated) | 12/16 — see §0.9 pattern 5 |
| Supporting text | `body-small` (12/16/400, tracking 0.4) |

### 32.4 Filled — container & active indicator

| Element | Token / value |
|---|---|
| Container color | `surface-container-highest` |
| Container shape | `corner-extra-small.top` (4px top, 0 bottom) |
| Active indicator height (rest) | 1px |
| Active indicator height (focus) | 2px ⁴ |
| Active indicator color (rest) | `on-surface-variant` |
| Active indicator color (focus) | `primary` |

⁴ Source spec lists both "focus active indicator height: 2dp" and "focus active indicator thickness: 3dp" — likely 2px is rendered height and 3px counts under-state-layer overlap. Use 2px.

### 32.5 Outlined — outline

| Element | Token / value |
|---|---|
| Container shape | `corner-extra-small` (all 4 corners) |
| Outline width (rest) | 1px |
| Outline width (focus) | 3px |
| Outline color (rest) | `outline` |
| Outline color (hover) | `on-surface` |
| Outline color (focus) | `primary` |

Per §0.9 pattern 4 — width morphs per state.

### 32.6 Color — common across both variants

| Element | Token |
|---|---|
| Label text (rest) | `on-surface-variant` |
| Label text (focus) | `primary` |
| Label text (hover, outlined) | `on-surface` |
| Input text | `on-surface` |
| Prefix / suffix / placeholder | `on-surface-variant` |
| Caret | `primary` |
| Leading icon | `on-surface-variant` |
| Trailing icon | `on-surface-variant` |
| Supporting text | `on-surface-variant` |

### 32.7 State layer (filled only)

| State | Color | Opacity |
|---|---|---|
| Hover | `on-surface` | 0.08 |

Outlined doesn't render state layer; outline color change conveys state.

### 32.8 Error state

| Element | Token |
|---|---|
| Active indicator (filled) / outline (outlined) | `error` |
| Label text | `error` |
| Supporting text | `error` |
| Trailing icon | `error` |
| Caret (focused error) | `error` |
| Leading icon | `on-surface-variant` (unchanged) |
| Input text | `on-surface` (unchanged) |

Error hover: indicator/outline/label/trailing-icon = `on-error-container`. Supporting text stays `error`.

### 32.9 Disabled (Pattern D)

| Element | Token | Opacity |
|---|---|---|
| Filled container | `on-surface` | 0.04 |
| Outlined outline | `on-surface` | 0.12 |
| Label / icons / input text / supporting text | `on-surface` | 0.38 |
| Active indicator (filled) | `on-surface` | 0.38 |

### 32.10 Configurations
- Empty / populated
- With supporting text
- With leading icon, trailing icon, both
- With prefix, suffix
- Multi-line (textarea)

### 32.11 Floating label morph (§0.9 pattern 5)
- Empty: label at input position, `body-large` (16/24)
- Populated or focused: label moves up, shrinks to 12/16
- Filled: label sits inside container above input
- Outlined: label crosses outline at top, padding 4px L/R for outline gap

---

## 33. Toolbar `D`

### 33.1 Variants
- `docked` — anchored to top or bottom edge, full-width, replaces baseline bottom app bar
- `floating` — detached, has margins, can be horizontal or vertical
- Color: **standard** (default) or **vibrant** (greater emphasis)

### 33.2 Docked toolbar

| Element | Token / value |
|---|---|
| Container height | 64px |
| Container shape | `corner-none` |
| Leading padding | 16px |
| Trailing padding | 16px |
| Max space between actions | 32px |
| Min space between actions | 4px |

### 33.3 Floating toolbar

| Element | Token / value |
|---|---|
| Container height (horizontal & vertical) | 64px |
| Container shape | `corner-full` |
| Container elevation | `level3` |
| Leading space (inside) | 8px |
| Trailing space (inside) | 8px |
| Margin from screen edge (horizontal) | 16px |
| Margin from screen edge (vertical) | 24px |
| Space between actions | 4px |

### 33.4 Color — Standard

| Element | Token |
|---|---|
| Container | `surface-container` |
| Button container | `surface-container` |
| Selected button container | `secondary-container` |
| Icon | `on-surface-variant` |
| Selected icon | `on-secondary-container` |
| Label | `on-surface-variant` |
| Selected label | `on-secondary-container` |

State layer: selected = `on-secondary-container`, unselected = `on-surface-variant`.

### 33.5 Color — Vibrant

| Element | Token |
|---|---|
| Container | `primary-container` |
| Button container | `primary-container` |
| Selected button container | `surface-container` |
| Icon | `on-primary-container` |
| Selected icon | `on-surface` |
| Label | `on-primary-container` |
| Selected label | `on-surface` |

State layer: selected = `on-surface`, unselected = `on-primary-container`.

### 33.6 Floating toolbar with FAB

When toolbar pairs with FAB (only floating variant):

| Element | Standard | Vibrant |
|---|---|---|
| FAB container | `secondary-container` | `tertiary-container` |
| FAB icon | `on-secondary-container` | `on-tertiary-container` |

| Element | Expanded | Collapsed |
|---|---|---|
| FAB size | 56 × 56px | 80 × 80px |
| FAB icon size | 24px | 28px |
| FAB shape | `corner-large` (16px) | `corner-large-increased` (20px) |
| FAB elevation | `level1` | `level2` |

Space between toolbar and FAB: 8px.

> **Note:** This FAB is *toolbar-bound* and uses different sizes/elevation than standalone FAB §7. Don't reuse §7 tokens here.

### 33.7 Disabled
Per §0.8 Pattern A.

### 33.8 Constraints
- Don't show alongside navigation bar
- Slot-based: icon buttons / buttons / text fields can each occupy a slot

---

## 34. Tooltip `F`

### 34.1 Variants
- `plain` — short label only, attached to triggering element
- `rich` — subhead + body + up to 2 buttons

### 34.2 Plain tooltip

| Element | Token / value |
|---|---|
| Container color | `inverse-surface` |
| Container shape | `corner-extra-small` |
| Container height | 24px |
| Padding | 8px |
| Supporting text color | `inverse-on-surface` |
| Supporting text typography | `body-small` (12/16/400, tracking 0.4) |

### 34.3 Rich tooltip

| Element | Token / value |
|---|---|
| Container color | `surface-container` |
| Container elevation | `level2` |
| Container shape | `corner-medium` |
| Top padding | 12px |
| Bottom padding | 8px |
| L/R padding | 16px |

| Region | Color | Typography |
|---|---|---|
| Subhead | `on-surface-variant` | `title-small` (14/20/500) |
| Supporting text | `on-surface-variant` | `body-medium` (14/20/400) |
| Action label | `primary` | `label-large` |

### 34.4 Action button state layers
Per §0.7. Color = `primary`.

### 34.5 Configurations (rich only)
- Subhead + supporting text + 2 buttons
- Subhead + supporting text + 1 button
- Subhead + supporting text only
- Supporting text + 1 or 2 buttons (no subhead)

### 34.6 Constraints
- Plain tooltip: attached to single trigger, no interaction
- Rich tooltip: dismissible, supports keyboard navigation

---

## Template — adding a new component

```markdown
## N. <Component name> `<bucket>`

### N.1 Variants
- list each variant with one-line role

### N.2 Common tokens
| Element | Token |
|---|---|

### N.3 Per-variant
| Variant | <attr1> | <attr2> | ... |
|---|---|---|---|

### N.4 Configurations (optional)
- enumerate variant-orthogonal options

### N.5 States to support (if interactive)
- enumerate
```

Drop sections that don't apply. Reference §0 conventions instead of restating. Tag bucket per §0.15.

---

## Component index — COMPLETE

All M3 components covered:
1 App bar · 2 Badge · 3-10 Button family (button, button group, icon button, FAB, extended FAB, FAB menu, split button) · 11 Card · 12 Carousel · 13 Checkbox · 14 Chip · 15 Date picker · 16 Time picker · 17 Dialog · 18 Divider · 19 List · 20 Loading indicator · 21 Progress indicator · 22 Menu · 23 Navigation bar · 24 Navigation rail · 25 Radio button · 26 Sheet · 27 Slider · 28 Snackbar · 29 Search · 30 Switch · 31 Tabs · 32 Text field · 33 Toolbar · 34 Tooltip
