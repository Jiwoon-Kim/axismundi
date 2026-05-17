# v3.5.16 Styleguide Modernization + Module Workspace Framing — Phase 0 Report

> Status: Phase 5 closed  
> Date: 2026-05-18  
> Cycle: v3.5.16 modernization  
> Scope: UX/framing inventory and lock decisions  
> Non-scope: implementation edits, v4.0 directory restructure, Wave 2 component work
> Close: Phase 0 report approved; closed by v3.5.16 Phase 5.

---

## 0. Framing

v3.5.16 aligns the public GitHub Pages surface with the module-first reality
created by Wave 1.

The core decision is:

```txt
Keep current directory structure through v3.x.
Modernize framing and navigation now.
Defer structural rename / lab naming retirement to v4.0.
```

This report locks the exact v3.5.16 lanes, UX scope, styleguide section mapping,
lab pattern banner direction, and BACKLOG hygiene decisions before Phase 1/2
edits begin.

---

## 1. Inputs Read

Primary:

| Input | Use |
|---|---|
| `MODERNIZATION-AUDIT.md` | styleguide/lab framing tension and Option C recommendation |
| `STALE-STATE-AUDIT.md` | backlog hygiene, #11/#34 overlap, #10/#17 close-check candidates |
| `STYLEGUIDE-MODERNIZATION-PHASE-0-PLAN.md` | v1.1 lane structure |
| `style-guide.html` | section id inventory |
| `lab/modules/*` | pattern/audit inventory |
| `MODULE-STATUS-MATRIX.md` | legacy DONE and RECORD status |
| `BACKLOG.md` | #10/#11/#13/#17/#34/#36/#37 context |

Validation:

```txt
Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## 2. Lane Scope Lock

### Lane M — Modernization Framing

Decision:

```txt
ADOPT
```

Phase 1/2 must amend wording so that:

```txt
lab/modules/* = module workspace + validation specimen surface
/styleguide/  = canonical public visual demo mirror
repo-root Pages = public browser access to both
```

This preserves the old boundary:

```txt
lab modules are not the downstream API and not a replacement for styleguide
```

while removing the false implication that lab files are not publicly browsable.

### Lane N — Navigation + Mobile-First Shell

Decision:

```txt
ADOPT N1 + N2
DEFER N3
DEFER N5 to BACKLOG #37
```

Definitions:

```txt
N1 = per-section module / validation specimen links
N2 = mobile-first responsive shell improvements for root index + styleguide nav
N3 = lab icon button + dialog UX
N5 = full Axismundi docs-shell dogfooding with App bar / Nav bar / Nav rail / Tabs
```

Rationale:

- N1 directly closes the navigation gap.
- N2 improves the public Pages first impression without needing unclosed Wave 2
  navigation components.
- N3 introduces dialog/focus/runtime surface; defer unless later proven useful.
- N5 depends on App bar / Nav bar / Nav rail / Tabs, all still Wave 2 TODO.

### Lane O — Lab Pattern Validation-Specimen Banner

Decision:

```txt
ADOPT
```

All lab pattern HTML pages get a small standard banner that says the page is a
validation specimen / module workspace, not the canonical public demo.

### Lane P — BACKLOG Hygiene / Legacy Marking

Decision:

```txt
ADOPT
```

Actions:

- #11 partially resolved by v3.5.0 framework work; remaining UX portion
  superseded by #34 / v3.5.16.
- #10 close-check against Ripple v2 v3.5.6.
- #17 close-check against Text field v3.5.7.
- #13 publish `theme.js` decision tied to N1/N2 no-dialog path.
- Snackbar / Tooltip marked as legacy DONE audit shape; no forced retrofit.
- #36 and #37 stay open future items, already added.

### Lane Q — Opportunistic Side-Fixes

Decision:

```txt
CONDITIONAL / LIMITED
```

Allowed for Phase 1 evaluation:

- #1 inline code font-size in helper text.
- #28 Icon button public specimen SVG wording cleanup.

Default defer:

- #2 Avatar size token consistency.
- #3 Floating toolbar selected color.

Reason:

```txt
#2 and #3 are component/token issues, not modernization framing issues.
```

---

## 3. Styleguide Section Inventory

`style-guide.html` contains 38 `sg-section` entries: 2 foundation sections and 36
component-ish sections.

Relevant component section ids:

```txt
components-button
components-icon-button
components-fab
components-fab-extended
components-fab-menu
components-button-group
components-split-button
components-toolbar
components-avatar
components-list
components-carousel
components-date-picker
components-time-picker
components-card
components-chip
components-badge
components-divider
components-app-bar
components-nav-bar
components-nav-rail
components-nav-rail-expanded
components-tabs
components-menu
components-text-field
components-search-bar
components-checkbox
components-radio
components-switch
components-slider
components-dialog
components-sheet
components-snackbar
components-tooltip
components-loading
components-progress
```

Foundation:

```txt
color
typography
```

---

## 4. Lab Pattern Inventory

17 pattern HTML pages:

```txt
button/lab-button-pattern.html
button-group/lab-button-group-pattern.html
card/lab-card-pattern.html
carousel/lab-carousel-pattern.html
chip/lab-chip-pattern.html
date-time/lab-date-time-pattern.html
fab/lab-fab-pattern.html
icon-button/lab-icon-button-pattern.html
icon-system/icon-system-pattern.html
list/lab-list-pattern.html
popover/lab-popover-pattern.html
ripple/lab-ripple-pattern.html
search-bar/lab-search-bar-pattern.html
search-expansion/lab-search-expansion-pattern.html
snackbar/lab-snackbar-pattern.html
text-field/lab-text-field-pattern.html
tooltip/lab-tooltip-pattern.html
```

Record-only audits:

```txt
_records/AVATAR-RECORD-AUDIT.md
_records/DIVIDER-RECORD-AUDIT.md
_records/BADGE-RECORD-AUDIT.md
```

---

## 5. N1 Section → Module Link Map

### 5.1 Direct pattern links

| Styleguide section | Link target | Label |
|---|---|---|
| `components-button` | `modules/button/lab-button-pattern.html` | Validation specimen |
| `components-icon-button` | `modules/icon-button/lab-icon-button-pattern.html` | Validation specimen |
| `components-fab` | `modules/fab/lab-fab-pattern.html` | Validation specimen |
| `components-fab-extended` | `modules/fab/lab-fab-pattern.html` | Validation specimen |
| `components-button-group` | `modules/button-group/lab-button-group-pattern.html` | Validation specimen |
| `components-card` | `modules/card/lab-card-pattern.html` | Validation specimen |
| `components-text-field` | `modules/text-field/lab-text-field-pattern.html` | Validation specimen |
| `components-search-bar` | `modules/search-bar/lab-search-bar-pattern.html` | Validation specimen |
| `components-list` | `modules/list/lab-list-pattern.html` | Validation specimen |
| `components-carousel` | `modules/carousel/lab-carousel-pattern.html` | Validation specimen |
| `components-chip` | `modules/chip/lab-chip-pattern.html` | Validation specimen |
| `components-snackbar` | `modules/snackbar/lab-snackbar-pattern.html` | Validation specimen |
| `components-tooltip` | `modules/tooltip/lab-tooltip-pattern.html` | Validation specimen |
| `components-date-picker` | `modules/date-time/lab-date-time-pattern.html` | Validation specimen |
| `components-time-picker` | `modules/date-time/lab-date-time-pattern.html` | Validation specimen |

### 5.2 Record audit links

| Styleguide section | Link target | Label |
|---|---|---|
| `components-avatar` | `modules/_records/AVATAR-RECORD-AUDIT.md` | Record audit |
| `components-divider` | `modules/_records/DIVIDER-RECORD-AUDIT.md` | Record audit |
| `components-badge` | `modules/_records/BADGE-RECORD-AUDIT.md` | Record audit |

### 5.3 No direct v3.5.16 link

The following sections have no component module yet and should not get a fake
module link:

```txt
FAB menu
Split button
Toolbar
App bar
Nav bar
Nav rail
Nav rail expanded
Tabs
Menu
Checkbox
Radio
Switch
Slider
Dialog
Sheet
Loading
Progress
```

They may receive a muted "module pending" note only if Phase 2 can do it without
cluttering the page. Default: omit.

### 5.4 Infrastructure modules

Infrastructure modules have no styleguide component section except through
consumers:

```txt
icon-system
popover
ripple
search-expansion
```

Decision:

```txt
Do not add per-section infrastructure links in v3.5.16.
Expose them through lab modules README / future module index UX.
```

---

## 6. N1 Markup Standard

Recommended section-header pattern:

```html
<div class="sg-section-head">
  <h2 class="t-headline-medium">Button</h2>
  <a class="sg-lab-link t-label-large"
     href="modules/button/lab-button-pattern.html">
    Validation specimen
  </a>
</div>
```

For record-only:

```html
<a class="sg-lab-link t-label-large"
   href="modules/_records/AVATAR-RECORD-AUDIT.md">
  Record audit
</a>
```

Important path note:

```txt
Source style-guide.html sits at axismundi-lab/style-guide.html.
Link targets from source are modules/<name>/...

publish_styleguide.py rewrites relative paths for /styleguide/index.html.
Phase 2 must verify the generated hrefs resolve from /styleguide/.
```

If the generator does not rewrite these new hrefs correctly, Phase 2 must patch
the generator instead of hardcoding publish-only paths into source.

---

## 7. N2 Mobile-First Shell Lock

### 7.1 Root index

Scope:

```txt
index.html only
```

Goals:

- mobile-first layout at 390px,
- nav buttons wrap cleanly,
- public surface cards are single-column first,
- no horizontal overflow,
- keep no-JS static page,
- use existing tokens/style, not a new framework.

Allowed:

- CSS grid/flex refinements,
- spacing/type scale tuning,
- better mobile link sizing,
- minor copy updates if needed.

Forbidden:

- App bar/Nav bar/Nav rail/Tabs dogfooding,
- client-side route switching,
- language toggle (#35),
- custom domain work.

### 7.2 Styleguide

Scope:

```txt
products/reference-implementations/axismundi-lab/style-guide.html
styleguide/ regenerated mirror
```

Goals:

- mobile-first section navigation does not overflow at 390px,
- module links are reachable on mobile,
- no new runtime dependency,
- no full multi-page rewrite.

Allowed:

- CSS adjustments inside existing styleguide CSS/source,
- responsive section header layout,
- mobile-friendly `.sg-lab-link`.

Forbidden:

- rebuild docs shell around unclosed Wave 2 navigation primitives,
- add dialog/focus-trap runtime in v3.5.16 by default,
- publish module pattern HTML into `/styleguide/`.

### 7.3 Breakpoint QA matrix

Required:

```txt
390px  mobile
768px  tablet
1280px desktop
```

Checks:

- root index overflowX = 0,
- styleguide overflowX = 0,
- lab links visible and tappable,
- no nav overlap with content,
- theme switcher still works,
- generated `/styleguide/` mirror matches source intent.

---

## 8. Lane O Banner Standard

Recommended banner:

```html
<aside class="lab-specimen-banner" role="note">
  <strong>Validation specimen.</strong>
  This page verifies the <Component> module. The canonical public demo is the
  styleguide section.
</aside>
```

Link policy:

- Direct link to local source styleguide if reliable.
- Direct link to published `/styleguide/#components-...` if path can be made
  stable from Pages and local file contexts.
- If link reliability is messy, use text-only "canonical public demo is the
  styleguide section" and rely on N1 reverse navigation.

Phase 2 plan must decide exact href strategy after testing relative paths.

Banner style:

```txt
CSS in each lab module?      no
Shared CSS?                 preferred if existing shared pattern CSS can reach
                            all pattern pages
Inline style?               avoid unless no shared route exists
```

Phase 0 report recommendation:

```txt
Use minimal markup + a small shared CSS rule if an existing module-wide CSS path
can carry it. Otherwise add unstyled semantic banner first.
```

---

## 9. BACKLOG Hygiene Locks

### 9.1 #11 / #34

Decision:

```txt
#11:
  Mark as partially resolved by v3.5.0 framework docs.
  Remaining styleguide ⇄ lab UX portion superseded by #34 / v3.5.16.

#34:
  Active implementation item for v3.5.16.
```

### 9.2 #10 Ripple runtime verification

Decision:

```txt
Likely RESOLVED by v3.5.6 Ripple v2.
```

Phase 1 must confirm:

- v3.5.6 `RIPPLE-V2-AUDIT.md` records implementation,
- `lab-ripple-pattern.html` uses v2 runtime,
- Phase 3 QA for Ripple v2 passed.

If confirmed, mark #10 closed with v3.5.6 resolution note.

### 9.3 #17 Text Input Corpus / Ontology Audit

Decision:

```txt
Likely RESOLVED by v3.5.7 Text field + v3.5.8 Search bar.
```

Phase 1 must confirm:

- Text field owns generic input shell,
- Search bar distinct from Text field per Phase 0B,
- Date/Time boundary is documented,
- WP mapping theme-can/plugin-should is recorded.

If confirmed, mark #17 closed with v3.5.7/v3.5.8 resolution note.

### 9.4 #13 publish theme.js

Decision:

```txt
For N1 + N2, do not publish theme.js solely to support dialog UX.
```

Phase 1 must verify whether current `/styleguide/` still 404s `scripts/theme.js`.

If it 404s:

- Option B from BACKLOG #13 is likely best:
  remove stale `theme.js` reference if `style-guide.js` owns the theme switcher.

If no 404:

- Mark #13 resolved by current publish mirror behavior.

### 9.5 Legacy Snackbar / Tooltip

Decision:

```txt
Do not retrofit 3-doc/4-doc audit shape.
Mark matrix notes as pre-v3.5.0 legacy DONE audit shape.
```

Exact wording:

```txt
DONE (v3.4.x legacy). Audit shape predates v3.5.0 framework; legacy single-doc
audit retained, no retrofit required.
```

---

## 10. Phase 1 Entry Conditions

Phase 1 should produce:

1. exact edit plan for Charter / architecture / modules README wording,
2. exact N1 styleguide section-link patch plan,
3. exact N2 mobile-first CSS/source patch plan,
4. exact Lane O pattern banner inventory,
5. BACKLOG #10/#11/#13/#17 close-check evidence,
6. MATRIX legacy wording patch plan,
7. Lane Q side-fix decision (#1/#28 only unless tiny evidence supports more).

---

## 11. Non-Goals Reconfirmed

v3.5.16 does not:

- rename `axismundi-lab`,
- rename `lab-*` files,
- create `index.ko.html`,
- implement language toggle,
- implement dialog UX unless separately approved,
- rebuild docs shell with App bar/Nav bar/Nav rail/Tabs,
- start WordPress pilot,
- start Wave 2 components,
- change GitHub Pages source.

---

## 12. Verdict

Phase 0 report locks v3.5.16 as:

```txt
Lane M  framing amendment                         ADOPT
Lane N  N1 per-section links + N2 mobile-first    ADOPT
Lane O  validation specimen banners               ADOPT
Lane P  backlog hygiene + legacy marking          ADOPT
Lane Q  small side-fixes                          CONDITIONAL
N3 dialog UX                                      DEFER
N5 dogfooding                                     DEFER to #37
v4.0 directory restructure                        DEFER to #36
```

Recommended route:

```txt
Review → approve/revise → Phase 1 implementation plan
```
