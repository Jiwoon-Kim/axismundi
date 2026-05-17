# v3.5.16 Styleguide Modernization + Module Workspace Framing — Phase 0 Plan

> Status: Phase 5 closed  
> Date: 2026-05-17  
> Cycle: v3.5.16 modernization  
> Scope: plan-first for post-publish styleguide/module framing alignment  
> Non-scope: GitHub repo setup, Pages activation, v4.0 directory restructure, WordPress pilot implementation
> Close: Approved as v1.1 after mobile-first Lane N scope amendment; closed by v3.5.16 Phase 5.

---

## 0. Phase Framing

v3.5.16 follows the first public GitHub Pages publish. The repository is now
public and root Pages serves the full repo, not only `/styleguide/`.

This changes the practical meaning of the old "lab is internal" wording:

```txt
Before Pages:
  lab/modules/* = validation workspace, not public publish surface

After Pages:
  lab/modules/* = publicly browsable module workspace and validation surface,
                  but still not the canonical UI demo or downstream API
```

v3.5.16 must align documentation, navigation, and pattern-page framing with that
reality without doing the v4.0 directory restructure.

Locked strategic choice:

```txt
Option B from user discussion:
  v4.0까지 directory restructure 보류
  v3.5.16에서 framing / UX / navigation 정렬
```

---

## 1. Authoritative Inputs

Primary:

1. `docs/v3.5.16/MODERNIZATION-AUDIT.md`.
2. `docs/v3.5.16/STALE-STATE-AUDIT.md`.
3. `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md`.
4. `products/reference-implementations/axismundi-lab/docs/ARCHITECTURE-BOUNDARIES.md`.
5. `products/reference-implementations/axismundi-lab/modules/README.md`.
6. `tools/generators/publish_styleguide.py`.
7. `products/reference-implementations/axismundi-lab/style-guide.html`.
8. `BACKLOG.md`.

Secondary:

1. `CURRENT-STATE.md`.
2. `NEXT-SESSION.md`.
3. `ROADMAP.md`.
4. `CHANGELOG.md`.
5. `docs/v3.5.14/TEMPLATES-PUBLISH-CATEGORY-NOTE.md`.

---

## 2. Lane Decisions

### Lane M — Modernization Framing

Status:

```txt
ADOPT
```

Scope:

- Amend `PUBLIC-SURFACE-CHARTER.md` §3.3.
- Update `ARCHITECTURE-BOUNDARIES.md` wording if it still implies lab is
  unreachable from public docs.
- Update `lab/modules/README.md` to say modules are canonical validated
  implementation workspaces, while `/styleguide/` remains the canonical public
  UI demo mirror.
- Update `publish_styleguide.py` comment so it no longer says module pattern
  HTML/audit docs are "lab-internal" in the sense of inaccessible; instead:

```txt
not mirrored into /styleguide/, but browsable from repo-root Pages as validation
specimens / source docs
```

Hard lock:

```txt
Do not rename directories.
Do not change publish_styleguide.py copy behavior in v3.5.16 unless Phase 0
discovers a hard blocker.
```

### Lane N — Styleguide → Module Navigation UX + Mobile-First Pages Layout

Status:

```txt
ADOPT
```

BACKLOG relationship:

```txt
Implements BACKLOG #34.
Absorbs BACKLOG #11's remaining UX portion.
```

Phase 0 must decide exact UX:

| Option | Description | Risk |
|---|---|---|
| N1 inline per-section link | Add `View lab pattern` / `View audit docs` link near each section header | Low |
| N2 mobile-first responsive shell | Make root index and styleguide navigation work first at 390px, then scale to tablet/desktop | Medium |
| N3 lab icon button + dialog | Add icon affordance that opens a module list/dialog | Medium |
| N4 global module index link only | Add one link from top nav to modules README | Low but too weak |
| N5 full Axismundi component dogfooding | Rebuild docs shell with App bar / Nav bar / Nav rail / Tabs patterns | High |

Recommendation:

```txt
N1 + N2 for v3.5.16.
N3 deferred unless Phase 0 report proves no runtime/focus risk.
N5 deferred until Wave 2 navigation components close.
```

Reason:

- N1 is accessible, no new runtime, no focus-trap/dialog behavior, lower risk.
- N2 aligns the first public Pages experience with mobile-first M3/WP reality.
- N3 matches user preference but introduces dialog UX, keyboard focus handling,
  and possibly `theme.js` publish questions.
- N5 would be the strongest showcase, but App bar / Nav bar / Nav rail / Tabs
  are Wave 2 components and should not be rushed into v3.5.16 as mini
  implementations.
- v3.5.16 can still visually style N1 with a small lab icon affordance and
  use Wave 1 components (Button / Card / List) for modest dogfooding.

Mobile-first lock:

```txt
index.html:
  - keep root entry lightweight;
  - ensure primary nav wraps cleanly at 390px;
  - public surface cards become a single-column stack first;
  - no horizontal overflow.

styleguide:
  - preserve existing monolithic source for now;
  - improve navigation affordances at mobile width;
  - no full multi-page rewrite;
  - no dependency on unclosed Wave 2 App bar/Nav bar/Nav rail primitives.
```

Dogfooding lock:

```txt
Allowed in v3.5.16:
  - modest use of already-closed Wave 1 components for links/cards/lists.

Deferred:
  - docs shell rebuilt around App bar / Nav bar / Nav rail / Tabs.
  - create BACKLOG #37 for this after Wave 2 navigation closes.
```

Minimum target set:

```txt
Wave 1 + legacy components with pattern HTML:
  Button
  Icon button
  FAB
  Extended FAB
  Button group
  Card
  Text field
  Search bar
  List
  Carousel
  Chip
  Snackbar
  Tooltip

PARTIAL:
  Date picker -> date-time module
  Time picker -> date-time module

Infrastructure:
  popover / ripple / icon-system handled via module index, not component
  section links unless a matching section exists.

Record-only:
  Avatar / Divider / Badge link to record audit only if low-friction.
```

Phase 0 report must verify the exact section ids and link targets before Phase
2 edits.

### Lane O — Lab Pattern Validation-Specimen Banner

Status:

```txt
ADOPT
```

Scope:

Add a standard banner to lab pattern HTML files:

```html
<aside class="lab-specimen-banner" role="note">
  <strong>Validation specimen.</strong>
  This page verifies the <Component> module. The canonical public demo is
  <a href="../../../../style-guide.html#components-...">styleguide section</a>
  and the published mirror is <a href="...">/styleguide/#components-...</a>.
</aside>
```

Final relative path pattern must be determined from actual file depth.

Candidate file set:

```txt
17 pattern HTML files under lab/modules/*/
```

Phase 0 report must inventory the exact list. If a pattern lacks an obvious
styleguide anchor, it gets a module-workspace banner without canonical section
link.

### Lane P — BACKLOG Hygiene / Legacy Marking

Status:

```txt
ADOPT
```

Scope:

- Resolve #11 / #34 overlap:
  - #11 = partially resolved by v3.5.0 framework work.
  - #11 remaining UX portion = superseded by #34 / v3.5.16.
- Decide #13 publish `theme.js`:
  - If N1 no-runtime links are chosen, no `theme.js` publish behavior is needed.
  - If N3 dialog is chosen, `theme.js` publish/copy policy must be revisited.
- Close-check #10 Ripple runtime verification against v3.5.6 Ripple v2.
- Close-check #17 Text Input Corpus / Ontology Audit against v3.5.7 Text field.
- Mark Snackbar / Tooltip as DONE legacy audit-shape rows rather than forcing
  3-doc/4-doc retrofits.
- Add BACKLOG #36 for v4.0 directory restructure.
- Add BACKLOG #37 for GitHub Pages dogfooding with Axismundi navigation
  components after Wave 2 navigation closure.

### Lane Q — Opportunistic Side-Fixes

Status:

```txt
CONDITIONAL
```

Candidates from `STALE-STATE-AUDIT.md`:

```txt
#1  Inline code font-size in helper text
#2  Avatar size token consistency
#3  Floating toolbar selected color
#28 Icon button public specimen SVG wording cleanup
```

Phase 0 default:

```txt
Only #1 and #28 are eligible for v3.5.16 if Phase 0 report confirms tiny,
styleguide-adjacent changes.
Defer #2 and #3 unless they are proven to be one-line/no-regression fixes.
```

Reason:

- v3.5.16 is about public surface framing.
- Avoid smuggling component token work into a navigation/framing cycle.

---

## 3. UX Lock Questions For Phase 0 Report

Phase 0 report must settle:

1. N1/N2/N3/N4/N5 navigation and mobile-first scope.
2. Exact link label:
   - `Open module`
   - `View lab pattern`
   - `Validation specimen`
   - Korean/English mixed label?
3. Whether component sections link to:
   - pattern HTML only,
   - module README if present,
   - docs directory,
   - record audit for record-only entries.
4. Whether Infrastructure modules appear in:
   - a global module index link,
   - an "Infrastructure" styleguide nav block,
   - no styleguide link in v3.5.16.
5. Whether Date picker and Time picker both link to `date-time`.
6. Whether Search bar links to `search-bar` only, with `search-expansion`
   discoverable through Search bar docs.

---

## 4. File Scope Preview

Allowed Phase 2 edit candidates:

```txt
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
products/reference-implementations/axismundi-lab/docs/ARCHITECTURE-BOUNDARIES.md
products/reference-implementations/axismundi-lab/modules/README.md
tools/generators/publish_styleguide.py
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/*/*pattern.html
BACKLOG.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md (only for legacy marking if needed)
```

Generated / validation:

```txt
styleguide/                  regenerated by publish_styleguide.py
binding_legitimacy_audit.json / pilot_validation_report.md regenerated by validator
```

Forbidden in v3.5.16:

```txt
Directory rename
axismundi-lab/ rename
lab-* prefix rename
WordPress pilot implementation
GitHub Pages source change
Component baseline CSS/token edits, unless #1 is explicitly scoped to prose/css
and confirmed tiny
```

---

## 5. Phase Shape

Recommended:

```txt
Phase 0  Plan-first
Phase 0R Report with exact UX and file inventory
Phase 1  Framing/backlog audit docs or amendment plan
Phase 2  Implementation plan
Phase 2E Edits
Phase 3  Browser/Playwright visual + link QA
Phase 5  Mechanical close + push
```

v3.5.16 touches public navigation, so Phase 3 must include actual Pages or
local browser link checks.

---

## 6. Validation Requirements

Minimum:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
python .\tools\generators\publish_styleguide.py
```

Navigation/link checks:

```txt
Local root index
Local styleguide/index.html
Generated /styleguide/ section links
Lab pattern HTML banner links
GitHub Pages after push
```

If N3 dialog is chosen, additional checks:

```txt
keyboard focus
Escape close
click outside behavior
reduced motion if animated
```

---

## 7. Non-Goals

v3.5.16 must not:

- implement v4.0 directory restructure,
- rename `axismundi-lab`,
- rename `lab-*` files,
- create `index.ko.html` or language toggle (#35),
- rebuild styleguide as a multi-page app,
- publish module pattern HTML into `/styleguide/`,
- start Ontology Theme Pilot,
- start Wave 2 component cycles,
- change Pages source away from main root.

---

## 8. Risks

| Risk | Severity | Disposition |
|---|---:|---|
| New navigation overclaims lab as canonical public API | P1 | Charter amendment + validation specimen wording. |
| Dialog UX creates runtime/focus bugs | P2 | Prefer N1/N2 unless Phase 0 proves N3 safe. |
| Mobile-first work becomes full docs-site rewrite | P2 | Limit N2 to responsive shell/nav fixes; defer N5. |
| Banner patch breaks 17 pattern pages | P2 | Inventory first; use consistent minimal markup; run link checks. |
| #11/#34 duplicate remains confusing | P2 | Lane P explicitly resolves overlap. |
| Side-fixes expand scope | P2 | Lane Q conditional; default defer #2/#3. |
| Pages links break after publish | P1 | Phase 3 local + public URL checks. |

---

## 9. Self-Check

```txt
MODERNIZATION-AUDIT read: yes
STALE-STATE-AUDIT read:  yes
Lane M adopted:          yes
Lane N adopted:          yes
Lane O adopted:          yes
Lane P adopted:          yes
Lane Q conditional:      yes
v4.0 restructure:        deferred
N1 recommended:          yes
N2 recommended:          yes
N5 dogfooding:           deferred to #37
Baseline edits:          forbidden by default
```

## 10. Verdict

Phase 0 plan v1.0 is ready for review.

Recommended route:

```txt
Review → approve/revise → Phase 0 report with exact UX inventory
```
