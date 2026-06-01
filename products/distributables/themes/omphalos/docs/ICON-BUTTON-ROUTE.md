# Omphalos — Icon-button semantic route (decision, pre-implementation)

> **Purpose**: cut the route for the *icon-button* family BEFORE any CSS /
> `register_block_style()` lands, per the diagnostic-first lock. This is the
> sibling of `BUTTON-ROUTE.md` (text button) — the icon-button was explicitly
> split out there (§2 search-icon row, §5.4, §6) as a separate route.
> **Spec authority**: M3 Expressive — `m3.material.io/components/icon-buttons/specs`.
> **Status**: route + token storage only. No block styles registered, no
> blocks.css rule, no theme.json change, no social-links rendering change.
> Consumption is the NEXT step, after review.
> **Date**: 2026-06-01 · WP 7.0 · M3 Expressive.

---

## §1 — Why this lane (the convergence)

Several Omphalos surfaces are the *same shape problem* — a square-ish tappable
container holding one icon — and should share one contract instead of each
re-deriving sizing/shape:

| Surface | Element | Notes |
|---|---|---|
| `core/social-links` icon cluster | `<a class="wp-social-link-anchor">` + `<svg>` in `<li>` | The VQA observation surface. Treated as an **icon link-button cluster**. |
| `omphalos/theme-switcher` segment | `<button class="wp-element-button">` + icon | Already has an icon + button contract (segmented control). |
| `core/search` icon submit | `<button class="…has-icon wp-element-button">` + `<svg>` | Interactivity-driven; the icon submit is an icon-button, not the text button (per BUTTON-ROUTE §2). |
| future standalone icon button | — | No core block yet; reserved (XL / FAB-ish). |

**Decision (KO)**: Social Links는 단순 위젯 스타일이 아니라 **icon link-button
cluster**로 본다. icon size / show-labels / style 조합이 icon-button 계열
(icon-button · extended FAB · nav-rail expanded) 계약을 밀어내는 관찰 표면.

`core/social-link` is **dynamic** (render_callback) and **skips links with no
`url`** — so every VQA fixture social-link must carry `url="#"` or it renders
nothing on the front end. (Fixed in `patterns/vqa-widgets.php`.)

---

## §2 — M3 Expressive icon-button spec (grounding table)

Per-size tokens (dp). `Circular` = `--md-sys-shape-corner-full`. Spring is
constant across sizes and equals the existing sys `fast-spatial` spring.

| Token | XS | S (default) | M | L | XL |
|---|---|---|---|---|---|
| container height | 32 | 40 | 56 | 96 | 136 |
| icon size | 20 | 24 | 24 | 32 | 40 |
| space — narrow | 4 | 4 | 12 | 16 | 32 |
| space — default | 6 | 8 | 16 | 32 | 48 |
| space — wide | 10 | 14 | 24 | 48 | 72 |
| shape round (resting) | Circular | Circular | Circular | Circular | Circular |
| shape square | 12 | 12 | 16 | 28 | 28 |
| outline width | 1 | 1 | 1 | 2 | 3 |
| pressed-morph | 8 | 8 | 12 | 16 | 16 |
| selected round | 12 | 12 | 16 | 28 | 28 |
| selected square | Circular | Circular | Circular | Circular | Circular |
| spring damping | 0.6 | 0.6 | 0.6 | 0.6 | 0.6 |
| spring stiffness | 800 | 800 | 800 | 800 | 800 |

Config axes (M3 / M3 Expressive availability):

| Category | Option | M3 | M3 Expressive |
|---|---|---|---|
| Size | Small (default) | ✓ | ✓ |
| Size | XS, M, L, XL | — | ✓ |
| Shape | Round (default) | ✓ | ✓ |
| Shape | Square | — | ✓ |
| Color | Filled (default) / tonal / outlined / standard | ✓ | ✓ |
| Width | Default | ✓ | ✓ |
| Width | Narrow, wide | — | ✓ |

---

## §3 — Tokenization (stored now)

All of the above is stored in `assets/styles/tokens.comp.css` as
`--comp-icon-button-*`, mirroring the existing `--comp-button-*` matrix. Raw
dimensions are literal dp; **every shape value references the sys corner scale**
and the morph spring references the sys motion layer — nothing shape/motion is a
fresh literal:

```txt
height-{xs,s,m,l,xl}              32 / 40 / 56 / 96 / 136
icon-size-{xs,s,m,l,xl}           20 / 24 / 24 / 32 / 40
space-{size}[-narrow|-wide]       per table (symmetric leading/trailing)
outline-width-{xs,s,m,l,xl}       1 / 1 / 1 / 2 / 3
shape-round                       → --md-sys-shape-corner-full
shape-square-{size}               → corner medium(12)/medium/large(16)/x-large(28)/x-large
shape-pressed-{size}              → corner small(8)/small/medium(12)/large(16)/large
shape-selected-round-{size}       → corner medium(12)/medium/large(16)/x-large(28)/x-large
shape-selected-square             → --md-sys-shape-corner-full (Circular)
morph-spring-damping/stiffness    → --md-sys-motion-spring-fast-spatial-* (0.6 / 800)
```

These are **isolation candidates** (defined, not yet consumed by Omphalos CSS) —
tracked in `TOKENS-COMP-AUDIT.md`, promoted to KEEP when the contract below lands.

---

## §4 — WP social-links size → M3 icon-button size (CONFIRMED)

**Principle — WP size names are NOT preserved; they are re-interpreted as M3
*container* sizes.** `core/social-links` ships four sizes via the `size`
attribute whose raw icon px (16/24/36/48) do not align with M3. Omphalos
**discards the WP icon-px** and keeps only the *ordinal*
(small < normal < large < huge → XS < S < M < L). Concretely, "large" / "huge"
mean **more container affordance, not a larger glyph** — the icon size follows
the M3 column, not WP's. Treating the cluster as an icon-button, Omphalos
**owns** the sizing and maps onto the M3 matrix:

| WP `size` class | WP icon px | → M3 size | M3 container / icon |
|---|---|---|---|
| `has-small-icon-size` | 16 | **XS** | 32 / 20 |
| `has-normal-icon-size` *(default)* | 24 | **S** *(default)* | 40 / 24 |
| `has-large-icon-size` | 36 | **M** | 56 / 24 |
| `has-huge-icon-size` | 48 | **L** | 96 / 32 |
| — | — | *(XL reserved)* | 136 / 40 — standalone / FAB-ish, future |

Per-size rationale (confirmed 2026-06-01):

- **small → XS** — WP's 16px glyph is too small; XS (32 / 20) is the a11y-safer
  floor. *Touch target*: XS container 32 < the 48px minimum, so the anchor's tap
  area must be guaranteed separately (padding / min-size on the link, or
  `--comp-touch-target`) — tracked for the consumption step.
- **normal → S** — the natural anchor: WP default icon 24 = M3 S icon 24,
  container 40.
- **large → M** — normalize to M3 M (container 56 / icon 24); do **not** carry
  WP's 36px glyph (oversized for a social cluster). "large" = container
  affordance increase, not icon enlargement.
- **huge → L** — L (96 / 32) is rarely used in a social cluster, but the name
  "huge" is itself an extreme, so the ordinal maps cleanly. **XL is not mapped
  from WP social-links** — reserved for a future standalone / FAB / hero control.

Status: **confirmed** — consumption may proceed on this map (size / shape / gap
first; see §7).

---

## §5 — Color policy (brand vs neutral) + per-style treatment

Core principle — **the theme normalizes geometry (size / shape / gap) but does
NOT seize colour.** Social icons carry strong brand meaning, so neutral colour is
**opt-in**, never the default.

```txt
brand color     = core/service responsibility   (default + logos-only)
neutral surface = theme opt-in style            (M3 tonal tokens)
```

Per social-links style, at consumption:

| Style | Container | Icon colour | Theme normalizes |
|---|---|---|---|
| **default** | service brand hue (untouched) | service brand (untouched) | size / shape / gap only |
| **neutral** *(opt-in, VQA custom)* | `secondary-container` | `on-secondary-container` | size / shape / gap + M3 tonal colour |
| **logos-only** | none | `currentColor` / inherited | size (match icon to the icon-button icon token); no container |
| **pill-shape** | reads as a pill / round icon-button cluster | per default / neutral | size / shape (pill) / gap |

The neutral set is already seeded in `vqa-widgets.php` (`iconColor:
on-secondary-container` / `iconBackgroundColor: secondary-container`). The full
M3 colour matrix (filled `primary` / tonal / outlined hairline / standard
transparent) stays **deferred** — it opens a colour × size × shape matrix.

**Policy (KO)**: theme는 size/shape/gap만 정규화하고 색은 빼앗지 않는다.
default·logos-only는 서비스 브랜드 색 유지, neutral(tonal)은 opt-in.

---

## §6 — Explicitly NOT in this step

- No `register_block_style()`, no blocks.css icon-button rule, no theme.json.
- No change to `core/social-links` rendering (sizes still render WP defaults).
- No toggle/selected shape morph (needs a JS spring runtime — the damping/
  stiffness tokens are stored, not wired).
- No square-shape or narrow/wide width variants applied.
- Search icon submit + theme-switcher remain on their current contracts; folding
  them onto `--comp-icon-button-*` is part of consumption, not this route.

---

## §7 — Next (consumption, after review)

§4 size map is **confirmed**; the §5 colour policy is set (theme normalizes
geometry, neutral colour is opt-in). The consumption step is deliberately narrow:

1. **social-links size / shape / gap ONLY, first.** Map the four WP `size`
   classes to the `--comp-icon-button-*` container / icon / space tokens and
   normalize the cluster gap; do **not** touch colour (default keeps the service
   brand). Verify computed values front + editor, both schemes; mind the XS
   touch-target floor (§4).
2. **Colour is separate + opt-in** — verified via the existing neutral VQA
   specimen (tonal `secondary-container`), not applied to the default style.
3. Then reconcile the theme-switcher segment + `core/search` icon submit onto the
   shared `--comp-icon-button-*` tokens.
4. Decide round-vs-square default and whether selected / toggle shape morph is in
   Omphalos scope (vs. a lab / runtime concern, like ripple in BUTTON-ROUTE §5);
   the morph-spring tokens are stored but unwired.
5. Connect to **Button v2 (M3 Expressive)**: the icon-button matrix and the
   text-button `--comp-button-*` matrix open together (size / icon size / touch
   target / toggle selected shape). extended FAB / nav-rail expanded stay future.
