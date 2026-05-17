# Axismundi v3.5.17 — Styleguide Shell Rebuild Phase 1 Implementation Plan

Status: Phase 1 plan v1.0  
Date: 2026-05-18  
Cycle: v3.5.17 styleguide shell rebuild + mobile reading polish  
Inputs:

- `STYLEGUIDE-SHELL-REBUILD-PHASE-0-PLAN.md`
- `STYLEGUIDE-SHELL-REBUILD-PHASE-0-REPORT.md`
- v3.5.16 user visual QA correction: the previous modernization did not satisfy
  the requested mobile shell, Sheet-style drawer, icon theme switcher, or body
  mobile polish.

## §0. Verdict

Phase 1 is **READY FOR IMPLEMENTATION** after user approval.

The implementation must rebuild the styleguide shell locally while preserving
the validated component sections. It is not a Wave 2 component cycle.

```txt
Implement:
  - mobile top app bar
  - menu icon button
  - Sheet-style side drawer
  - icon-based theme switcher
  - mobile color palette sizing
  - read-more disclosure for long explanation copy

Preserve:
  - 38 existing styleguide sections
  - 37 canonical nav anchors + body order equality
  - 18 lab/record links
  - 16 lab validation banners
  - Wave 1 visual demo semantics
```

Phase 5 close is blocked until the acceptance checklist in §10 passes.

## §1. User Request Log — Non-Negotiable

This section is intentionally not abstracted into lane names.

User explicitly requested:

1. Mobile must not keep the desktop aside as the primary navigation shell.
2. Mobile needs a top app bar.
3. Mobile nav must be a Sheet-style static side modal/drawer.
4. The menu icon must toggle that drawer.
5. The theme switcher must become icon-based and reusable across styleguide,
   module pages, and index later.
6. Body sections also need mobile polish.
7. Color palettes must shrink or wrap better on mobile.
8. Visual demos should be more prominent than explanation copy.
9. Long explanation copy should move into a read-more pattern.
10. Existing section content and validated demo surface must not be destroyed.
11. `typography-axis.html` must be linked from Foundation > Typography as a
    typography adjunct, per BACKLOG #11.
12. Styleguide version should move to `v0.3.0`, with monorepo cycle
    `v3.5.17` shown as metadata.

These twelve items are implementation requirements, not inspiration.

## §2. Implementation Framing

### §2.1 Styleguide-Local Chrome

The shell is documentation chrome:

- namespace: `.sg-*`
- location: `style-guide.html` and styleguide-specific scripts/styles
- claim: GitHub Pages/styleguide UX improvement
- non-claim: App bar, Navigation drawer, Sheet, Nav bar, Nav rail, Tabs, or
  any Wave 2 component completion

Allowed examples:

```txt
.sg-top-bar
.sg-menu-toggle
.sg-drawer
.sg-drawer__surface
.sg-drawer__header
.sg-theme--icons
.sg-readmore
```

Forbidden examples:

```txt
.ax-app-bar
.ax-nav-drawer
.ax-navigation-drawer
.ax-sheet
.ax-nav-bar
.ax-nav-rail
.ax-tabs
```

Existing `.ax-icon-button` is allowed for the menu trigger and theme buttons
because Icon button #2 is DONE.

### §2.2 Files In Scope

Primary editable files:

```txt
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
```

Generated files after publish:

```txt
styleguide/index.html
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js
```

Documentation:

```txt
docs/v3.5.17/STYLEGUIDE-SHELL-REBUILD-PHASE-1-PLAN.md
```

Out of scope:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/theme.json
products/reference-implementations/axismundi-lab/modules/*/lab-*-pattern.html
products/reference-implementations/axismundi-lab/modules/*/docs/*.md
```

No new `lab/modules/*` directory is created.

## §3. Lane A — Navigation Shell Rebuild

### §3.1 Markup Placement

Implementation adds the mobile shell after `<body>` and before
`<div class="sg-layout">`.

Pattern:

```html
<header class="sg-top-bar" data-screen-label="Mobile top app bar">
  <button
    class="ax-icon-button is-standard has-state-layer sg-menu-toggle"
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
  <div class="sg-top-bar__brand" aria-label="Axismundi Style Guide">
    <span class="t-title-medium sg-top-bar__title">AXISMUNDI</span>
    <span class="t-label-medium sg-top-bar__subtitle">Style Guide</span>
  </div>
</header>

<dialog
  id="sg-drawer"
  class="sg-drawer"
  aria-label="Style guide navigation"
  data-close-at="(min-width: 721px)">
  <div class="sg-drawer__surface">
    <header class="sg-drawer__header">
      <div>
        <div class="t-title-medium sg-brand">AXISMUNDI</div>
        <div class="t-label-medium sg-brand-meta">Style Guide</div>
      </div>
      <button
        class="ax-icon-button is-standard has-state-layer"
        type="button"
        aria-label="Close navigation"
        data-close-modal>
        <span class="material-symbols-rounded notranslate"
              translate="no"
              aria-hidden="true"
              draggable="false">close</span>
      </button>
    </header>
    ...icon theme switcher...
    ...canonical guide nav...
    ...canonical section nav...
  </div>
</dialog>
```

The drawer uses `.sg-drawer`, not `.ax-sheet`.

### §3.2 Desktop Sidebar

The existing `.sg-sidebar` remains the desktop/tablet shell.

At mobile width it is hidden, not moved above content:

```css
@media (max-width: 720px) {
  .sg-sidebar {
    display: none;
  }
}
```

This directly fixes the v3.5.16 issue where the desktop aside still behaved as
the mobile primary shell.

### §3.3 Breakpoints

Implementation breakpoints:

| Viewport | Top bar | Drawer | Sidebar |
| --- | --- | --- | --- |
| 390px | visible | enabled | hidden |
| 768px | hidden | closed if open | visible |
| 1280px | hidden | closed if open | visible |

CSS lock:

```css
.sg-top-bar {
  display: none;
}

@media (max-width: 720px) {
  .sg-top-bar {
    display: flex;
  }
}
```

### §3.4 Drawer CSS

The drawer is a local Sheet-style side modal:

```css
.sg-drawer {
  inline-size: min(360px, 88vw);
  max-inline-size: min(360px, 88vw);
  block-size: 100dvh;
  max-block-size: 100dvh;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
}

.sg-drawer::backdrop {
  background: color-mix(in srgb, var(--md-sys-color-scrim) 32%, transparent);
}

.sg-drawer__surface {
  min-block-size: 100dvh;
  overflow: auto;
  background: var(--md-sys-color-surface);
  border-inline-end: 1px solid var(--md-sys-color-outline-variant);
}
```

The exact spacing may be tuned in Phase 2, but the namespace and side-modal
behavior are locked.

### §3.5 `theme.js §1` Change

Current evidence:

```txt
theme.js §1:
  - wires [data-toggle-nav]
  - requires aria-controls
  - requires target tagName DIALOG
  - closes drawer at min-width: 1024px
```

Implementation changes only the viewport guard to be configurable:

```js
const closeAt = drawer.getAttribute('data-close-at') || '(min-width: 1024px)';
const desktopMq = window.matchMedia(closeAt);
```

Styleguide drawer sets:

```html
data-close-at="(min-width: 721px)"
```

This preserves existing pages that rely on the 1024px default.

If this change conflicts with current production pages, fallback is a
styleguide-only drawer handler in `style-guide.js`; Phase 2 must stop and report
before taking that fallback.

## §4. Lane B — Icon Theme Switcher

### §4.1 Contract

The current contract remains:

```txt
data-theme-button="light|dark|auto"
role="radio"
aria-checked
aria-pressed
.is-selected
localStorage key: axismundi.theme
```

No new theme storage key is introduced.

### §4.2 Markup Pattern

Replace the existing text chip switcher with icon buttons:

```html
<div class="sg-theme sg-theme--icons" role="group" aria-label="Theme switcher">
  <div class="t-label-medium sg-theme__label">Theme</div>
  <div class="sg-theme__row" role="radiogroup" aria-label="Theme">
    <button
      class="ax-icon-button is-standard has-state-layer"
      type="button"
      role="radio"
      aria-label="Light theme"
      aria-checked="false"
      aria-pressed="false"
      data-theme-button="light">
      <span class="material-symbols-rounded notranslate"
            translate="no"
            aria-hidden="true"
            draggable="false">light_mode</span>
    </button>
    <button ... data-theme-button="dark">dark_mode</button>
    <button ... data-theme-button="auto">contrast</button>
  </div>
</div>
```

Glyph lock:

| Theme | Glyph |
| --- | --- |
| light | `light_mode` |
| dark | `dark_mode` |
| auto | `contrast` |

### §4.3 Placement

Desktop:

- sidebar keeps the theme switcher position,
- text chips are replaced with icon buttons.

Mobile:

- full icon switcher appears inside the drawer,
- top app bar does not need a compact theme control in v3.5.17 unless Phase 2
  proves there is enough space without crowding the title.

This matches the user request: drawer must contain the switcher; top bar must
contain the menu icon.

### §4.4 Sync Risk

`style-guide.js` currently owns `[data-theme-button]`. `theme.js §8` has a
guard for styleguide switchers.

Phase 2 must verify:

```txt
click sidebar light/dark/auto -> all icon buttons sync
click drawer light/dark/auto -> all icon buttons sync
reload -> localStorage mode restored
```

If duplicate script ownership causes drift, keep `style-guide.js` as the owner
and avoid moving theme logic into `theme.js`.

## §5. Lane C — Body Section Mobile Polish

### §5.1 Color Palette Sizing

Current swatches use:

```css
.sg-swatch-grid {
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
}

.sg-swatch__chip {
  aspect-ratio: 1 / 1;
}
```

Mobile update:

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

Phase 3 must confirm swatches are scan-friendly at 390px and no horizontal
overflow appears.

### §5.2 Read-More Pattern

Use native disclosure:

```html
<details class="sg-readmore">
  <summary class="t-label-large">Read more</summary>
  <div class="sg-readmore__body">
    ...existing explanatory content...
  </div>
</details>
```

CSS:

```css
.sg-readmore {
  border-block-start: 1px solid var(--md-sys-color-outline-variant);
  padding-block-start: var(--space-sm);
}

.sg-readmore > summary {
  cursor: pointer;
  color: var(--md-sys-color-primary);
}
```

No custom disclosure component or JS runtime is created.

### §5.3 Disclosure Target Rule

Apply disclosure when:

```txt
paragraph/helper content primarily explains implementation constraints
and pushes the visual specimen below the mobile fold.
```

Do not collapse:

- headings,
- controls,
- demo specimens,
- accessibility labels,
- essential state examples,
- validation specimen links.

### §5.4 Initial Disclosure Target Inventory

Phase 2 targets:

1. Long implementation notes in `Color`.
2. Long helper/caveat copy in `Button`.
3. Long helper/caveat copy in `Icon button`.
4. Long helper/caveat copy in `Button group`.
5. Long helper/caveat copy in `Text field`.
6. Long helper/caveat copy in `Date picker`.
7. Long helper/caveat copy in `Time picker`.
8. Long helper/caveat copy in `Carousel`.

Phase 2 may add more targets only if the content matches §5.3. It may remove a
target if wrapping it would hide essential visual guidance.

### §5.5 Visual-First Rule

Where a section has both a long explanation and a demo specimen, Phase 2 should
prefer:

```txt
header + validation link
primary visual/demo
read-more explanation
secondary implementation details
```

This is a layout adjustment, not a semantic rewrite. Existing content remains.

### §5.6 Typography Axis Adjunct Link

BACKLOG #11 already decided:

```txt
typography-axis.html is a typography adjunct, not a module.
It belongs as a sub-link from the Typography section.
```

Phase 2 must add a Foundation > Typography link:

```html
<a class="sg-foundation-link t-label-large"
   href="typography-axis.html"
   aria-label="Open typography axis specimen">
  <span class="material-symbols-rounded notranslate"
        translate="no"
        aria-hidden="true"
        draggable="false">text_fields</span>
  <span>Typography axis specimen</span>
</a>
```

Rules:

- keep this under the `#typography` section header,
- do not create a separate module entry,
- do not create a new Guides top-level IA group,
- existing 18 lab/record links remain preserved,
- typography adjunct link is counted separately as one additional foundation
  resource link via `.sg-foundation-link`, not `.sg-lab-link`.

Phase 3 must verify:

```txt
existing lab/record links == 18
typography-axis adjunct links == 1
total styleguide resource links == 19
```

## §6. Canonical Nav / Body Order Invariant

v3.5.16 Phase 3 corrected the direction:

```txt
nav is the single source of truth
body sections follow nav order
```

v3.5.17 must preserve:

```txt
37 nav anchors
37 body sections
nav href order == body section id order
```

The mobile drawer nav must also match the desktop nav.

Implementation preference:

- duplicate the current canonical nav markup into the drawer in Phase 2,
- verify equality with Playwright,
- if drift is detected, switch to runtime cloning from the desktop nav rather
  than manually maintaining divergent orders.

## §7. Publish Mirror

Phase 2 must run:

```powershell
python .\tools\generators\publish_styleguide.py
```

Expected mirror updates:

```txt
styleguide/index.html
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js (only if source changed)
```

Existing link rewriting from v3.5.16 must remain intact:

- source styleguide links: `modules/...`
- publish mirror links: `../products/reference-implementations/axismundi-lab/modules/...`

New v3.5.17 link rewriting:

- source typography adjunct link: `typography-axis.html`
- publish mirror link:
  `../products/reference-implementations/axismundi-lab/typography-axis.html`

`typography-axis.html` is not copied into `/styleguide/` as a top-level guide.
It remains a source-tree adjunct reachable from the generated mirror.

If publish rewriting regresses any of the 18 lab/record links or the typography
axis adjunct link, Phase 2 fails.

## §7a. Version Display

Styleguide display version:

```txt
Axismundi Style Guide v0.3.0
Monorepo cycle: v3.5.17
```

Rules:

- `v0.3.0` is the styleguide preview stream after the v3.5.16/v3.5.17 public
  surface modernization work.
- `v3.5.17` remains the monorepo cycle version.
- Do not relabel the styleguide as monorepo `v3.5.17` in the page title.
- Do not claim public `v1.0.0`; that remains reserved for the v4.0 public
  release graduation.

Motion note:

```txt
No custom choreography in v3.5.17.
The shell may use native dialog behavior and existing state-layer feedback only.
Richer docs-site dogfooding motion is deferred to BACKLOG #37.
```

## §8. Validator And Test Sequence

Required sequence:

```txt
Pre-edit:
  - validator 4x1.000 PASS
  - npm test PASS

After Lane A:
  - readback style-guide.html + theme.js
  - validator 4x1.000 PASS

After Lane B:
  - readback theme switcher markup
  - validator 4x1.000 PASS

After Lane C:
  - readback disclosure + swatch CSS
  - validator 4x1.000 PASS

After publish mirror:
  - publish_styleguide.py PASS
  - validator 4x1.000 PASS
  - npm test PASS

Before Phase 3:
  - Playwright acceptance PASS
```

Use `apply_patch` for manual edits. No fresh full-file rewrite unless file
corruption is detected and reported.

## §9. Playwright Acceptance Plan

Phase 2 must create or run a targeted Playwright script that verifies the local
source and/or published mirror.

### §9.1 Mobile 390px

Checks:

```txt
viewport 390x844
.sg-top-bar visible
.sg-sidebar hidden
[data-toggle-nav] visible
click menu -> #sg-drawer.open true
drawer has section nav
drawer has 3 data-theme-button controls
Escape closes drawer
document horizontal overflow == 0
```

### §9.2 Tablet 768px

Checks:

```txt
viewport 768x1024
.sg-top-bar hidden
.sg-sidebar visible
#sg-drawer not open
document horizontal overflow == 0
```

### §9.3 Desktop 1280px

Checks:

```txt
viewport 1280x900
.sg-top-bar hidden
.sg-sidebar visible
desktop nav order == body section order
drawer nav order == desktop nav order, if drawer markup exists
document horizontal overflow == 0
```

### §9.4 Theme Switcher

Checks:

```txt
click dark icon -> html[data-theme="dark"] or equivalent selected state
click light icon -> html[data-theme="light"] or equivalent selected state
click auto icon -> auto state restored
all visible data-theme-button groups sync aria-checked / aria-pressed / is-selected
reload preserves localStorage mode
```

### §9.5 Body Polish

Checks:

```txt
390px swatch chip width < desktop swatch chip width
390px swatch grid horizontal overflow == 0
details.sg-readmore count >= 1
summary toggles details open/closed
sg-lab-link count == 18
typography axis adjunct link count == 1
typography axis adjunct href resolves from source and publish mirror
lab-specimen-banner count == 16 in lab pattern HTML inventory
```

## §10. Phase 3 / Phase 5 Acceptance Gate

Phase 5 close is blocked unless these are PASS or explicitly waived by user.

### §10.1 Shell / Navigation

```txt
□ 390px viewport: desktop aside is not visible as the primary shell.
□ 390px viewport: top app bar is visible.
□ Top app bar includes a real icon button menu trigger.
□ Menu trigger opens and closes a Sheet-style side drawer.
□ Drawer contains canonical section nav.
□ Drawer contains icon theme switcher.
□ Desktop / tablet preserve an efficient sidebar/aside experience.
□ Body section order equals canonical nav order.
```

### §10.2 Theme Switcher

```txt
□ Theme switcher is icon-based (light / dark / auto icons).
□ Existing data-theme-button contract remains valid.
□ Existing localStorage persistence still works.
□ Pattern is reusable for index/module pages later.
```

### §10.3 Body Mobile Polish

```txt
□ Color palette grids fit 390px without oversized cards.
□ Visual demo content appears before long explanatory text where practical.
□ Long explanation copy uses native read-more disclosure.
□ Disclosure uses accessible native <details><summary>.
□ Existing semantic content is preserved.
□ Existing 18 styleguide lab/record links are preserved.
□ Foundation > Typography includes one typography-axis adjunct link.
□ Existing 16 lab pattern validation banners are preserved.
```

## §11. Fallback Triggers

| Trigger | Action |
| --- | --- |
| Dialog fails in GitHub Pages/file contexts | Stop and report; consider non-modal fixed drawer fallback |
| Configurable drawer breakpoint regresses other pages | Stop and move styleguide drawer handling to `style-guide.js` |
| Drawer nav order drifts from desktop nav | Use runtime clone or abort close |
| Theme switcher desynchronizes between drawer/sidebar | Keep `style-guide.js` as owner; avoid duplicate theme logic |
| Read-more hides required demo content | Reduce disclosure targets; preserve visual-first target |
| Mobile swatches become too small to identify | Increase minmax floor before Phase 3 |

## §12. Explicit Non-Goals

- Do not complete App bar.
- Do not complete Navigation drawer.
- Do not complete Sheet.
- Do not use `.ax-*` names for shell chrome except existing `.ax-icon-button`.
- Do not create a new component module.
- Do not edit `components.css`, `tokens.css`, `blocks.css`, or `theme.json`.
- Do not rewrite 38 sections from scratch.
- Do not remove existing lab/record links.
- Do not remove existing validation banners.
- Do not implement N3 dialog UX from BACKLOG #34.
- Do not implement N5 full dogfooding from BACKLOG #37.

## §13. Phase 2 Entry Checklist

Phase 2 may begin after user approval of this plan.

```txt
□ User approves styleguide-local shell plan.
□ User confirms icon theme switcher direction.
□ User accepts <details>/<summary> disclosure approach.
□ User accepts drawer nav duplication with equality verification.
□ User accepts no Wave 2 component completion in v3.5.17.
```

## §14. Self-Check Summary

```txt
self-check:
  user request log: 10 explicit items
  mobile top app bar: locked
  Sheet-style drawer: locked as .sg-drawer, not .ax-sheet
  menu icon trigger: locked with ax-icon-button + menu glyph
  icon theme switcher: locked with light_mode / dark_mode / contrast
  data-theme-button contract: preserved
  body mobile polish: swatches + details disclosure
  typography-axis adjunct: locked under Foundation > Typography
  version display: Style Guide v0.3.0 + Monorepo v3.5.17
  nav/body invariant: nav remains source of truth
  baseline CSS/tokens: out of scope
  Phase 5 close gate: acceptance checklist required
```

## §15. Verdict

Phase 1 plan locks v3.5.17 as a concrete UX correction cycle, not a nominal
modernization close.

Next, after user approval:

```txt
Phase 2 execution:
  1. pre-validator + npm test
  2. navigation shell patch
  3. icon theme switcher patch
  4. body mobile polish patch
  5. publish mirror regenerate
  6. Playwright acceptance
  7. final validator + npm test
```

## §16. Phase 5 Close

Status: **CLOSED at v3.5.17 Phase 5** (2026-05-18).

Phase 2/3 delivered the locked implementation:

```txt
PASS  mobile top app bar
PASS  menu icon button
PASS  Sheet-style .sg-drawer
PASS  desktop/tablet sidebar preservation
PASS  icon theme switcher with data-theme-button preserved
PASS  compact mobile palettes
PASS  native details/summary read-more
PASS  Foundation > Typography typography-axis adjunct link
PASS  styleguide version v0.3.0 + monorepo cycle v3.5.17
PASS  typography-axis mobile polish + collapsible sticky controls
PASS  no Wave 2 App bar / Nav drawer / Sheet completion claim
```

Phase 5 bookkeeping is recorded in `CHANGELOG.md`, `ROADMAP.md`,
`CURRENT-STATE.md`, and `NEXT-SESSION.md`.
