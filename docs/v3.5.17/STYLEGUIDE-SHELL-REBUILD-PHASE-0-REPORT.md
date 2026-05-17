# Axismundi v3.5.17 — Styleguide Shell Rebuild Phase 0 Report

Status: Phase 0 report v1.0  
Date: 2026-05-18  
Cycle: v3.5.17 styleguide shell rebuild + mobile reading polish  
Input: `STYLEGUIDE-SHELL-REBUILD-PHASE-0-PLAN.md`

## §0. Verdict

Phase 0 confirms v3.5.17 as a **styleguide-local shell rebuild**, not a Wave 2
component completion cycle.

Core decision:

```txt
Do NOT complete App bar / Nav drawer / Sheet before v3.5.17.
Use styleguide-local chrome only.
No promotion claim for App bar / Nav drawer / Sheet.
Full dogfooding remains deferred to BACKLOG #37 after Wave 2 closure.
```

## §1. User Acceptance Criteria — Locked

These criteria are binding. Phase 5 close is blocked unless they pass or the
user explicitly waives a criterion.

```txt
Shell / navigation:
  □ 390px viewport: desktop aside is not visible as the primary shell.
  □ 390px viewport: top app bar is visible.
  □ Top app bar includes a real icon button menu trigger.
  □ Menu trigger opens and closes a Sheet-style side drawer.
  □ Drawer contains canonical section nav.
  □ Drawer contains icon theme switcher.
  □ Desktop / tablet preserve an efficient sidebar/aside experience.
  □ Body section order equals canonical nav order.

Theme switcher:
  □ Theme switcher is icon-based (light / dark / auto icons).
  □ Existing data-theme-button contract remains valid.
  □ Existing localStorage persistence still works.
  □ Pattern is reusable for index/module pages later.

Body mobile polish:
  □ Color palette grids fit 390px without oversized cards.
  □ Visual demo content appears before long explanatory text where practical.
  □ Long explanation copy uses native read-more disclosure.
  □ Disclosure uses accessible native <details><summary>.
  □ Existing semantic content is preserved.
  □ Existing 18 styleguide lab/record links are preserved.
  □ Existing 16 lab pattern validation banners are preserved.
```

## §2. Styleguide-Local Chrome Decision

### §2.1 Definition

`styleguide-local chrome` means:

- markup and CSS are scoped with `.sg-*`,
- the chrome exists only to make the documentation site usable,
- it may visually borrow M3 ideas from app bars and sheets,
- it does not claim to implement Axismundi App bar, Navigation drawer, Sheet,
  Nav bar, Nav rail, or Tabs components,
- it does not create a new `lab/modules/*` component module,
- it does not require a Full-Spec audit doc.

### §2.2 Naming Lock

Allowed:

```txt
.sg-top-bar
.sg-top-bar__menu
.sg-top-bar__title
.sg-drawer
.sg-drawer__surface
.sg-drawer__header
.sg-theme--icons
.sg-readmore
```

Forbidden:

```txt
.ax-app-bar
.ax-nav-drawer
.ax-navigation-drawer
.ax-nav-bar
.ax-nav-rail
.ax-tabs
```

Conditional:

- Existing `.ax-icon-button` may be used for the menu trigger and theme buttons
  because Icon button #2 is already DONE.
- Existing Sheet visual language may be borrowed, but do not name the drawer
  `.ax-sheet` unless Phase 1 proves this does not imply component completion.
  Recommended default: `.sg-drawer`, with local CSS.

## §3. Navigation Shell Decision

### §3.1 Adopted Structure

Mobile:

```html
<header class="sg-top-bar">
  <button class="ax-icon-button is-standard has-state-layer sg-menu-toggle"
          type="button"
          aria-label="Open navigation"
          aria-controls="sg-drawer"
          aria-expanded="false"
          data-toggle-nav>
    <span class="material-symbols-rounded notranslate"
          translate="no"
          aria-hidden="true"
          draggable="false">menu</span>
  </button>
  <span class="sg-top-bar__title">Axismundi</span>
  <div class="sg-top-bar__theme">...</div>
</header>

<dialog id="sg-drawer" class="sg-drawer" aria-label="Style guide navigation">
  <div class="sg-drawer__surface">
    <header class="sg-drawer__header">...</header>
    ...icon theme switcher...
    ...canonical nav...
  </div>
</dialog>
```

Desktop/tablet:

```txt
existing .sg-sidebar remains visible and efficient
```

### §3.2 Runtime Decision

Use the existing `theme.js §1` drawer skeleton where possible:

- `[data-toggle-nav]`
- `[data-close-modal]`
- `aria-controls`
- `aria-expanded`
- `<dialog>.showModal()`
- Escape/backdrop close
- viewport guard

Phase 1 must verify whether `theme.js §1` currently requires
`dialog.tagName === "DIALOG"` only or also class names. Current evidence:
it checks tag name and `aria-controls`, not `.ax-sheet`, so `.sg-drawer` is
compatible.

If a styleguide-specific gap appears, prefer adding a narrow branch to
`theme.js §1` over creating a second drawer protocol in `style-guide.js`.

### §3.3 Breakpoint Decision

Adopt:

```txt
<= 720px:
  show .sg-top-bar
  hide .sg-sidebar
  enable .sg-drawer

> 720px:
  hide .sg-top-bar
  show .sg-sidebar
  close drawer if open
```

Note: `theme.js §1` currently guards at `min-width: 1024px`. Phase 1 must
either adjust that guard to match 721px or make it configurable without
breaking actual pages.

## §4. Icon Theme Switcher Decision

### §4.1 Contract

Keep the existing theme contract:

```txt
data-theme-button="light|dark|auto"
role="radio"
aria-checked
aria-pressed
.is-selected
localStorage key: axismundi.theme
```

This preserves the v3.4.5 cohort fix and current theme persistence behavior.

### §4.2 Visual Pattern

Adopt an icon button radiogroup:

```html
<div class="sg-theme sg-theme--icons" role="radiogroup" aria-label="Theme">
  <button class="ax-icon-button is-standard has-state-layer"
          type="button"
          role="radio"
          aria-label="Light theme"
          aria-checked="false"
          data-theme-button="light">
    <span class="material-symbols-rounded notranslate">light_mode</span>
  </button>
  ...
</div>
```

Glyph lock:

| Mode | Glyph |
| --- | --- |
| light | `light_mode` |
| dark | `dark_mode` |
| auto | `contrast` |

Rationale:

- All three are Material Symbols.
- `contrast` communicates system/auto better than a generic settings glyph.
- No new icon provider is introduced.

### §4.3 Placement

Mobile:

- top app bar may include a compact current-theme affordance only if it does
  not crowd the title.
- drawer must include the full icon theme switcher.

Desktop:

- sidebar keeps the icon theme switcher where the chip switcher currently is.

## §5. Body Mobile Polish Decision

### §5.1 Color Palette

Adopt a mobile swatch grid reduction:

```css
@media (max-width: 640px) {
  .sg-swatch-grid {
    grid-template-columns: repeat(auto-fill, minmax(104px, 1fr));
    gap: var(--space-sm);
  }
  .sg-swatch__chip {
    aspect-ratio: 1 / 0.72;
  }
}
```

Phase 3 must visually verify that swatches still communicate color and do not
become too small to scan.

### §5.2 Read-More Disclosure

Use native:

```html
<details class="sg-readmore">
  <summary>Read more</summary>
  ...
</details>
```

Do not invent a Disclosure component. This is documentation chrome.

### §5.3 Disclosure Targeting

Phase 1 must inventory exact targets. Initial candidates:

- long section intro paragraphs in `sg-section__head`,
- long `sg-helper` paragraphs that explain implementation caveats,
- Date picker explanatory paragraphs,
- Time picker explanatory paragraphs,
- Text field caveats,
- Carousel explanatory helper,
- any section whose intro pushes the visual specimen too far below the fold at
  390px.

Rule:

```txt
If a paragraph explains implementation constraints more than the visible demo,
move it into disclosure on mobile-first pass.
```

Content is preserved; only presentation changes.

## §6. Canonical Order Decision

v3.5.16 Phase 3 follow-up clarified the direction:

```txt
sidebar nav is canonical
body sections follow nav order
```

v3.5.17 must preserve:

- 37 nav anchors,
- 37 body sections,
- exact equality between nav order and body section order.

Any shell rebuild must treat this as a hard invariant.

## §7. Files In Scope

Expected edit targets:

- `products/reference-implementations/axismundi-lab/style-guide.html`
- `products/reference-implementations/axismundi-lab/scripts/theme.js`
- possibly `products/reference-implementations/axismundi-lab/scripts/style-guide.js`
- `styleguide/index.html` after publish regeneration
- `styleguide/scripts/theme.js` after publish regeneration
- v3.5.17 phase docs

Expected generated target:

- `/styleguide/` mirror via `publish_styleguide.py`

Out of scope:

- `components.css`
- `tokens.css`
- `blocks.css`
- `theme.json`
- lab module pattern HTMLs
- module audit docs
- `lab/modules/*` new directories

## §8. Verification Plan

### §8.1 Automated Playwright

Required scenarios:

```txt
Viewport 390:
  - .sg-top-bar visible
  - .sg-sidebar hidden
  - [data-toggle-nav] visible
  - click menu -> dialog open
  - drawer nav visible
  - drawer theme switcher visible
  - Escape closes drawer
  - overflow = 0

Viewport 768:
  - selected behavior according to breakpoint decision
  - overflow = 0

Viewport 1280:
  - .sg-sidebar visible
  - .sg-top-bar hidden
  - nav/body order equality true
  - overflow = 0

Theme:
  - click light/dark/auto icon buttons
  - html[data-theme] changes or clears
  - selected marker syncs

Body:
  - color swatch width below desktop size at 390
  - details/summary exists for selected long descriptions
  - lab links remain 18
```

### §8.2 User Visual QA

User must explicitly review:

- mobile top app bar feel,
- drawer feel,
- icon theme switcher feel,
- color palette scan density,
- whether read-more hides enough prose without hiding essential visual demos.

## §9. Fallback Triggers

| Trigger | Fallback |
| --- | --- |
| `<dialog>` breaks on GitHub Pages / file URL | Use non-modal fixed `.sg-drawer` with `hidden` + focus-light fallback; record deviation |
| `theme.js §1` change risks production pages | Keep production behavior and add styleguide-only drawer wiring in `style-guide.js` |
| Duplicated nav drifts | Generate mobile drawer nav from desktop nav at runtime or add equality check before close |
| Icon theme switcher regresses persistence | Revert to text labels visually hidden + icon visible while preserving old chip contract |
| Disclosure hides essential guidance | Reduce disclosure targets; keep only long caveats collapsed |

## §10. Phase 1 Entry Conditions

Phase 1 plan may proceed with:

1. exact markup diff for top bar + drawer,
2. exact CSS diff for breakpoints and swatches,
3. exact JS diff in `theme.js` or `style-guide.js`,
4. exact disclosure target inventory,
5. exact icon theme switcher markup,
6. exact Playwright acceptance script.

## §11. Non-Goals Reconfirmed

- No Wave 2 component completion.
- No `.ax-app-bar` / `.ax-nav-drawer` / `.ax-sheet` promotion.
- No new lab module.
- No baseline CSS token edits.
- No full demo rewrite.
- No v4.0 directory restructure.

## §12. Phase 0 Close

Phase 0 settles the ontology:

```txt
v3.5.17 = styleguide-local shell rebuild
not Wave 2 shell component completion
not full styleguide rebuild
not dogfooding claim
```

Next: Phase 1 implementation plan.

## §13. Phase 5 Close

Status: **CLOSED at v3.5.17 Phase 5** (2026-05-18).

The styleguide-local shell decision held. Implementation used `.sg-*` chrome,
preserved Wave 1 component semantics, kept the `data-theme-button` contract,
and deferred rich motion / full dogfooding to BACKLOG #37. Phase 3 user QA
passed, including the follow-up collapsible control panel for
`typography-axis.html`.
