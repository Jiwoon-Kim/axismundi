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

## §4 — WP social-links size → M3 icon-button size (proposed mapping)

`core/social-links` ships four icon sizes via the `size` attribute. WP's raw px
(16/24/36/48) do not align with M3, so when Omphalos treats the cluster as an
icon-button it **owns** the sizing and maps onto the M3 matrix:

| WP `size` class | WP icon px | → M3 size | M3 container / icon |
|---|---|---|---|
| `has-small-icon-size` | 16 | **XS** | 32 / 20 |
| `has-normal-icon-size` *(default)* | 24 | **S** *(default)* | 40 / 24 |
| `has-large-icon-size` | 36 | **M** | 56 / 24 |
| `has-huge-icon-size` | 48 | **L** | 96 / 32 |
| — | — | *(XL reserved)* | 136 / 40 — standalone / FAB-ish, future |

The default lines up (both 24px icon → S). Realistically a social cluster lives
at XS/S; M/L are where it shades into standalone icon-button / FAB territory, so
the larger end of the map is a range demonstrator, not an endorsement to ship
96px social icons. **Open for review** — confirm or retune before consumption.

---

## §5 — Color policy (brand vs neutral)

```txt
brand color   = core/service responsibility  (logos-only / default service hue)
neutral surface = theme opt-in style          (M3 tonal tokens)
```

- **Standard / logos-only** → the service brand mark owns its colour; theme does
  not override (respects platform identity).
- **Neutral M3 surface** (theme opt-in) → already demonstrated in
  `vqa-widgets.php`: `iconColor: on-secondary-container` /
  `iconBackgroundColor: secondary-container` (M3 *tonal* icon-button colour).
- Full M3 colour matrix (filled `primary` / tonal `secondary-container` /
  outlined `outline` hairline / standard transparent) is **deferred** to the
  consumption step — it opens a colour × size × shape matrix.

**Policy (KO)**: 브랜드 색은 서비스 책임, neutral surface는 theme opt-in.
서비스 브랜드 의미가 있는 logos-only/default는 theme가 색을 덮지 않는다.

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

1. Confirm / retune the §4 size map and the §5 colour matrix scope.
2. Pick the consumption surface order: social-links cluster first (VQA), then
   reconcile theme-switcher segment + search icon submit onto the shared tokens.
3. Decide round-vs-square default and whether selected/toggle morph is in scope
   for Omphalos (vs. a lab / runtime concern, like ripple in BUTTON-ROUTE §5).
4. Connect to **Button v2 (M3 Expressive)**: the icon-button size matrix and the
   text-button `--comp-button-*` matrix open together (size / icon size / touch
   target / toggle selected shape). extended FAB / nav-rail expanded stay future.
