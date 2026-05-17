# Axismundi v3.5.16 — Styleguide Modernization Phase 2 Report

Status: Phase 5 closed  
Date: 2026-05-18  
Cycle: v3.5.16 Styleguide modernization + lab/module framing alignment
Close: Phase 3 visual QA PASS; Phase 5 bookkeeping complete.

## §0. Verdict

Phase 2 executed the approved lanes:

```txt
M -> P -> Q -> N1 -> N2 -> O -> publish mirror -> QA pre-check
```

The cycle remains within modernization scope:

- no baseline component CSS edits,
- no token edits,
- no Wave 2 component dogfooding,
- no directory restructure,
- no language-toggle implementation.

## §1. Lane M — Framing Amendment

Updated:

- `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md`
- `products/reference-implementations/axismundi-lab/modules/README.md`
- `tools/generators/publish_styleguide.py`

Lock implemented:

- `/styleguide/` remains the canonical public visual demo mirror.
- `lab/modules/*` is now explicitly a module workspace + validation specimen surface.
- Repository-root GitHub Pages may browse lab pattern HTML and audit docs, but lab-only selectors/runtime hooks are not public API.
- `lab` naming is retained as legacy naming until the v4.0 directory restructure tracked by BACKLOG #36.

## §2. Lane P — Backlog And Legacy Hygiene

Updated:

- `BACKLOG.md`
- `docs/v3.5.0/MODULE-STATUS-MATRIX.md`

Changes:

- #10 marked resolved by v3.5.6 Ripple v2.
- #11 marked partially resolved by v3.5.0 framework; remaining UX superseded by #34 / v3.5.16.
- #13 marked resolved by v3.5.16 `theme.js` publish copy.
- #17 marked resolved by v3.5.7 Text field + v3.5.8 Search bar.
- #28 marked resolved by v3.5.16 Icon button public snippet wording cleanup.
- Snackbar and Tooltip matrix rows now record their pre-v3.5.0 legacy audit shape without reopening them.

## §3. Lane Q — Side Fixes

Updated:

- `products/reference-implementations/axismundi-lab/style-guide.html`

Implemented:

- #1 helper inline code inheritance:

```css
.sg-helper code,
.t-body-small code {
  font-size: inherit;
}
```

- #28 Icon button snippet/helper wording now uses Material Symbols markup instead of SVG-era examples.

Deferred:

- #2 Avatar size tokens.
- #3 Floating toolbar selected color.

## §4. Lane N1 — Styleguide Module Links

Updated:

- `products/reference-implementations/axismundi-lab/style-guide.html`
- generated `styleguide/index.html`

Implemented:

- 15 component validation specimen links.
- 3 record audit links.
- Total `.sg-lab-link` count:

```txt
source style-guide.html: 18
generated styleguide/index.html: 18
```

Source links stay short:

```txt
modules/button/lab-button-pattern.html
```

Generated publish links are rewritten:

```txt
../products/reference-implementations/axismundi-lab/modules/button/lab-button-pattern.html
```

All generated module links resolve on disk.

## §5. Lane N2 — Mobile-First Public Shell

Updated:

- `index.html`
- `products/reference-implementations/axismundi-lab/style-guide.html`
- generated `styleguide/index.html`

Implemented:

- Root index mobile-first button stacking below 560px.
- Root index repository link: `https://github.com/Jiwoon-Kim/axismundi`.
- Root index state updated from planned GitHub Pages to completed public GitHub Pages.
- Styleguide responsive shell guardrails:
  - reduced mobile padding,
  - safe horizontal guide navigation,
  - full-width mobile validation links,
  - code snippets remain scroll-contained.

No Wave 2 nav/app-bar/tabs dogfooding was introduced.

## §6. Lane O — Lab Pattern Validation Banners

Updated 16 current lab pattern HTML files:

- `button/lab-button-pattern.html`
- `button-group/lab-button-group-pattern.html`
- `card/lab-card-pattern.html`
- `carousel/lab-carousel-pattern.html`
- `chip/lab-chip-pattern.html`
- `date-time/lab-date-time-pattern.html`
- `fab/lab-fab-pattern.html`
- `icon-button/lab-icon-button-pattern.html`
- `list/lab-list-pattern.html`
- `popover/lab-popover-pattern.html`
- `ripple/lab-ripple-pattern.html`
- `search-bar/lab-search-bar-pattern.html`
- `search-expansion/lab-search-expansion-pattern.html`
- `snackbar/lab-snackbar-pattern.html`
- `text-field/lab-text-field-pattern.html`
- `tooltip/lab-tooltip-pattern.html`

Each now includes one `lab-specimen-banner`.

Note: Phase 0 report used 17 as the broader lab-surface count. The filesystem-authoritative Phase 2 count is 16 `lab-*-pattern.html` files plus record-only audits.

## §7. Publish Generator

Updated:

- `tools/generators/publish_styleguide.py`

Implemented:

- `scripts/theme.js` is copied to `/styleguide/scripts/theme.js` when present.
- generated HTML rewrites `href="modules/` to `href="../products/reference-implementations/axismundi-lab/modules/`.
- comments now distinguish `/styleguide/` mirror from browsable validation specimens.

Publish run:

```txt
stylesheets/: 23 files
scripts/style-guide.js: copied
scripts/theme.js: copied
style-guide.html -> index.html
style-guide-blocks.html -> blocks.html
style-guide-prose.html -> prose.html
publish surface total: 31 files
```

## §8. Validation

Command validation:

```txt
validate_theme_pilot.py: 1.000 / 1.000 / 1.000 / 1.000 PASS
npm test: PASS
publish_styleguide.py: PASS
generated module links: all resolve
```

Playwright pre-check:

```txt
root 390: overflow=0
root 768: overflow=0
root 1280: overflow=0
styleguide 390: overflow=0, labLinks=18
styleguide 768: overflow=0, labLinks=18
styleguide 1280: overflow=0, labLinks=18
button-pattern 390: overflow=0, banners=1
button-pattern 768: overflow=0, banners=1
button-pattern 1280: overflow=0, banners=1
```

## §9. Phase 3 Entry Criteria

Phase 3 visual QA may proceed.

Recommended checks:

1. Root Pages landing at mobile/desktop widths.
2. Styleguide mobile/desktop widths.
3. At least one `Validation specimen` link from generated `/styleguide/`.
4. At least one `Record audit` link from generated `/styleguide/`.
5. One lab pattern page banner.
6. Confirm no visible regression from copied `theme.js`.

## §10. Phase 3 Follow-Up — Nav Is Canonical

User QA clarified the intended direction:

```txt
canonical source of order = sidebar navigation
body section order        = follows sidebar navigation
```

Initial follow-up briefly changed the navigation order to match the existing
body order. That was incorrect. The correction restores the canonical category
navigation order and moves the body `<section>` blocks to match it.

Canonical order now verified:

```txt
Foundation:
  Color / Typography
Actions:
  Button / Icon button / FAB / Extended FAB / FAB menu /
  Button group / Split button / Toolbar
Containers:
  Card / Divider
Navigation:
  App bar / Nav bar / Nav rail / Nav rail expanded / Tabs / Menu
Inputs:
  Text field / Search bar / Checkbox / Radio / Switch / Slider /
  Date picker / Time picker
Selection:
  Chip / Badge
Feedback:
  Dialog / Sheet / Snackbar / Tooltip / Loading / Progress
Display:
  Avatar / List / Carousel
```

Automated verification:

```txt
nav anchors:      37
body sections:    37
nav/body equality true
mobile overflow:  0
lab links:        18
icon links:       18
validator:        1.000 / 1.000 / 1.000 / 1.000 PASS
```
