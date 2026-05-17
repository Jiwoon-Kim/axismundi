# MODERNIZATION-AUDIT.md — Pre-Phase-0 Research

> **Status**: Pre-Phase-0 research input for BACKLOG #34 (Styleguide modernization + Lab navigation UX).  
> **Authored**: while Codex was on cooldown.  
> **Use**: Codex Phase 0 plan-first reads this as authoritative finding.  
> **Scope**: research only. No baseline edits, no audit doc edits.

---

## §1 — Why this audit

User raised two intertwined concerns:

1. **Styleguide is outdated relative to modular lab/modules/\* structure.** Components were migrated to per-module folders across v3.4.0 → v3.5.12, but `style-guide.html` remained monolithic.
2. **Charter §3.3 framing (lab = validation surface, not public API) conflicts with GitHub Pages publishing reality.** GitHub Pages will serve every file in the repo root by default; lab pattern HTMLs become publicly accessible whether the Charter labels them "internal" or not.

These two concerns share a single root cause: the publish surface narrative was authored before GitHub Pages was the actual deployment target.

---

## §2 — style-guide.html inventory

`products/reference-implementations/axismundi-lab/style-guide.html` is the canonical demo source. `publish_styleguide.py` mirrors it to `/styleguide/index.html`.

Components present (34 sections, including some Wave 2/3 still TODO in MODULE-STATUS-MATRIX):

```
Foundation:    Color / Typography
Actions:       Button / Icon button / FAB / Extended FAB / FAB menu /
               Button group / Split button / Toolbar
Containers:    Card / Divider
Selection:     Chip / Badge
Display:       Avatar / List / Carousel
Navigation:    App bar / Nav bar / Nav rail / Nav rail expanded /
               Tabs / Menu
Inputs:        Text field / Search bar / Date picker / Time picker /
               Checkbox / Radio / Switch / Slider
Feedback:      Dialog / Sheet / Snackbar / Tooltip /
               Loading / Progress
```

All 34 component sections live inline (~3000 lines). Each section uses pattern:

```
<section class="sg-section" id="components-<name>" data-screen-label="<Label>">
  <h2 class="t-headline-medium"><Label></h2>
  ...inline demo markup...
</section>
```

No section currently contains a link to a lab/modules/\* pattern HTML or audit doc.

---

## §3 — lab/modules/\* inventory

```
18 module directories (17 with lab-*-pattern.html, 1 _records without):

Wave 1 Component Full-Spec (9, all DONE):
  button            3 audit docs + pattern
  button-group      3 audit docs + pattern
  card              3 audit docs + pattern
  carousel          7 audit docs + pattern (4-doc + 3 v3.3.2 legacy)
  fab               3 audit docs + pattern
  icon-button       3 audit docs + pattern
  list              3 audit docs + pattern
  search-bar        4 audit docs + pattern
  text-field        3 audit docs + pattern

Inputs PARTIAL (1):
  date-time         1 audit doc + pattern  (Date picker + Time picker fold-in)

Legacy / pre-v3.5.0 (4):
  chip              3 audit docs + pattern  (v3.4.9, DONE before framework)
  snackbar          1 audit doc + pattern   (v3.4.10, DONE before framework)
  tooltip           1 audit doc + pattern   (v3.4.6, DONE before framework)
  search-expansion  1 audit doc + pattern   (v3.3.4 historical evidence)

Infrastructure (3):
  popover           1 audit doc + pattern   (v3.4.5)
  ripple            2 audit docs + pattern  (v3.5.6 v2 contract)
  icon-system       8 audit docs + pattern  (v3.4.2-v3.4.4)

Record-only (1):
  _records          3 RECORD audits (Avatar / Divider / Badge)
                    NO pattern HTML — Baseline-only Record category
```

Total publicly browsable lab artifacts under GitHub Pages on repo root:

```
17 pattern HTML pages
~50 audit Markdown docs
~17 module CSS files (also mirrored to /styleguide/stylesheets/)
~3 module JS files
3 record-only audits
```

---

## §4 — publish_styleguide.py behavior (verbatim)

Source: `tools/generators/publish_styleguide.py`.

```
SOURCE  = products/reference-implementations/axismundi-lab
PUBLISH = styleguide/

Copies:
  1a. lab/stylesheets/*.css → publish/stylesheets/*.css
  1b. lab/modules/*/lab-*.css → publish/stylesheets/*.css (flattened)
  2.  lab/style-guide.html → publish/index.html
  3.  lab/style-guide-blocks.html → publish/blocks.html
  4.  lab/style-guide-prose.html → publish/prose.html

INTENTIONALLY NOT COPIED:
  - Module JS
  - Pattern HTML
  - Per-module audit docs
```

Script comment (line 18-22):

> "Module JS, pattern HTML, and per-module audit docs are intentionally NOT copied to the publish surface — they are lab-internal artifacts. The flattened module CSS appears as an orphan on the publish surface (no HTML on the publish side links to it), which is the same asymmetry that existed at v3.3.2/v3.3.3/v3.3.4 when modules lived in flat lab/."

Constitution Article 12: "publishing surfaces are mirrors, not authorities."

**This explicit script policy assumes /styleguide/ is the only public-facing surface.** GitHub Pages serving the repo root breaks that assumption.

---

## §5 — Charter §3.3 framing

`docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md` §3.3 (line 171):

> ### §3.3 — `lab/modules/*` is a **validation surface**
> ```
> WHAT IT IS:
>   - Module authoring + audit + validation territory
>   - Pattern pages for visual + runtime verification
>   - Audit doc workspace
>   - Module contract proving ground
>
> WHAT IT IS NOT:
>   - A public API directly
>   - Consumed by downstream consumers without graduation
>   - A replacement for the baseline
> ```

This framing was written when "public" meant "consumed by other code." It does not address browser-level publishing via GitHub Pages.

---

## §6 — Reality check: current index.html (v3.5.14 entry point)

Root `index.html` already contains direct links into `products/reference-implementations/axismundi-lab/`:

```
Line 184: <a href="styleguide/">Open styleguide</a>
Line 189: <a href="products/reference-implementations/axismundi-lab/README.md">
Line 194: <a href="products/reference-implementations/axismundi-lab/modules/README.md">
```

**So as of v3.5.14, the published landing page already links to lab files.**

This means:

```
Charter §3.3 says:   "lab is not a public API, not consumed by downstream"
Index.html does:      directly links users into lab README + modules README
Pages will serve:     every file in the repo, including lab/*/lab-*-pattern.html
publish_styleguide:   refuses to mirror lab pattern HTML, but Pages serves it anyway
```

The framing and the implementation no longer match.

---

## §7 — Gap matrix

```
Component         Wave 1 module        Linked from styleguide?  Lab pattern accessible on Pages?
─────────────────────────────────────────────────────────────────────────────────────────────
Button            yes (v3.5.1)         no                       yes (will be)
Icon button       yes (v3.5.2)         no                       yes
FAB family        yes (v3.5.5)         no                       yes
Button group      yes (v3.5.10)        no                       yes
Card              yes (v3.5.3)         no                       yes
Text field        yes (v3.5.7)         no                       yes
Search bar        yes (v3.5.8)         no                       yes
List              yes (v3.5.11)        no                       yes
Carousel          yes (v3.5.12)        no                       yes
Chip              yes (v3.4.9 legacy)  no                       yes
Snackbar          yes (v3.4.10 legacy) no                       yes
Tooltip           yes (v3.4.6 legacy)  no                       yes
Avatar            RECORD only          n/a                      audit only
Divider           RECORD only          n/a                      audit only
Badge             RECORD only          n/a                      audit only
FAB menu          TODO                 (only inline)            no
Split button      TODO                 (only inline)            no
Toolbar           TODO                 (only inline)            no
App bar           TODO                 (only inline)            no
Nav bar           TODO                 (only inline)            no
Nav rail          TODO                 (only inline)            no
Tabs              TODO                 (only inline)            no
Menu              TODO                 (uses popover/)          partial
Checkbox/Radio/
Switch/Slider     TODO                 (only inline)            no
Dialog / Sheet    TODO                 (only inline)            no
Loading/Progress  TODO                 (only inline)            no
Date picker       PARTIAL (date-time)  no                       yes (date-time)
Time picker       PARTIAL (date-time)  no                       yes (date-time)
```

12 Wave 1 / legacy components have lab pattern HTMLs that are zero-linked from styleguide today.

---

## §8 — Framing tension: three options

### Option A — Strict lab boundary (Charter intent)

Configure GitHub Pages to publish only the `/styleguide/` subdirectory (not repo root).

```
Pro:   Charter §3.3 framing remains intact.
Con:   index.html, README, LICENSE, lab modules all unreachable from Pages.
       Users see only the legacy monolithic styleguide.
       BACKLOG #34 navigation UX impossible.
       Loses the "transparent, reviewable" property of GitHub-hosted open source.
```

### Option B — Open lab as deep-dive surface (reality-aligned)

GitHub Pages serves repo root. Lab files publicly browsable but **framed as validation evidence, not canonical demo**.

```
Pro:   BACKLOG #34 navigation UX possible.
       Full transparency: anyone can inspect audit history.
       Matches what GitHub Pages will actually do.
Con:   Charter §3.3 needs an amendment clarifying browsable ≠ canonical.
       Per-pattern "Lab pattern — validation specimen" banner needed to
       avoid users mistaking lab pages for the canonical demo.
```

### Option C — Hybrid: explicit publish + validation-banner

Same as B but with explicit policy:

```
1. /styleguide/ is the canonical UI demo (publish_styleguide.py mirror).
2. lab/modules/*/lab-*-pattern.html is the canonical *validation specimen*.
   Both are public but serve different audiences.
3. Every lab pattern HTML gets a standard banner:
     "Lab pattern — validation specimen for <Component>.
      Canonical demo: /styleguide/#components-<name>"
4. publish_styleguide.py script comment is updated to acknowledge that
   lab files are publicly browsable through Pages even though they are
   not actively mirrored.
5. styleguide/ component sections gain a "View lab pattern →" link
   (BACKLOG #34 navigation UX).
```

**Recommended: Option C.** It preserves Charter §3.3 intent (lab is not the canonical API/demo) while telling the truth about Pages behavior.

---

## §9 — Recommended v3.5.16 cycle scope

Three lanes, executed via plan-first / report / Phase 1 / Phase 2 / Phase 3 / Phase 5 standard cycle:

### Lane M (Modernization framing)

- Author Charter §3.3 amendment (or new §3.5) clarifying:
  - lab/modules/* is a validation surface and an authoring workspace
  - lab files ARE publicly browsable on the Pages publish surface
  - lab files are NOT the canonical UI demo (that is /styleguide/)
  - lab pattern HTMLs carry a "validation specimen" banner
- Update `publish_styleguide.py` script comment to reflect this duality
- Update `lab/modules/README.md` to match
- No baseline edits

### Lane N (Lab navigation UX — BACKLOG #34)

- Decide UX pattern (per Phase 0 plan):
  - Inline "View lab pattern →" link at each styleguide component section header
  - Or lab icon button → dialog showing module list
  - Or sidebar/index page that lists every lab module
  - User preference: lab icon button + dialog (per BACKLOG #34 framing)
- Pattern: `<a class="sg-lab-link" href="../products/reference-implementations/axismundi-lab/modules/<name>/lab-<name>-pattern.html">Open lab pattern</a>`
- Apply to all 12 Wave 1 / legacy components that have lab pattern HTMLs
- Phase 2 baseline edit: `style-guide.html` only (per-section augmentation)
- Republish via `publish_styleguide.py`

### Lane O (Lab pattern "validation specimen" banner)

- Add standardized header banner to every `lab-*-pattern.html`:
  ```
  <div class="lab-validation-banner" role="note">
    Lab pattern — validation specimen for <Component>.
    Canonical demo: <a href="...styleguide path...">/styleguide/#components-<name></a>
  </div>
  ```
- Pattern HTMLs to update: 17 files (Wave 1 + legacy + infrastructure)
- Optional: also extend to record-only audits in `_records/` (linked from somewhere)
- Phase 2 edit: per-module pattern HTML only (no CSS / no JS / no baseline)

### Out of scope for v3.5.16

```
- Rewriting style-guide.html section markup (defer to v3.5.17+)
- Implementing language toggle (BACKLOG #35, separate cycle)
- Adding new components (Wave 2 cycles)
- Changing publish_styleguide.py copy policy beyond the comment update
- Theme color decisions / Expressive adoption rollout
```

---

## §10 — Edge cases to lock in Phase 0

1. **Record-only entries (Avatar / Divider / Badge)**:
   No pattern HTML, only audit docs in `_records/`. Styleguide already has inline sections. Should "View lab pattern →" be replaced with "View record audit →"? Lock in Phase 0.

2. **Infrastructure modules (popover / ripple / icon-system)**:
   These don't have a styleguide section per se. Lab pattern HTMLs exist as standalone validation surfaces. Lock in Phase 0: are they reachable from a separate "Infrastructure" navigation entry?

3. **PARTIAL components (Date picker / Time picker → date-time fold)**:
   Two styleguide sections, one lab module. Both styleguide sections link to the same date-time pattern. Lock in Phase 0.

4. **search-expansion (historical evidence)**:
   v3.3.4 module preserved as historical context. Should it be reachable from public navigation? Likely no — link only from Search bar lab audit, not from styleguide.

5. **Wave 2/3 TODO components in styleguide**:
   No lab module yet. The styleguide section is the only demo. Should "View lab pattern →" be omitted or show "Lab module pending Wave 2"? Lock in Phase 0.

6. **Legacy DONE components (Chip / Snackbar / Tooltip)**:
   Predate the v3.5.0 framework. Audit doc structure differs from Wave 1 3-doc trio. Should they be promoted to the same audit shape now, or stay legacy? Out of scope for v3.5.16 (defer to a separate legacy-normalization cycle).

---

## §11 — Phase 0 deliverable expectations

When Codex starts the v3.5.16 cycle:

```
1. Read this audit doc as authoritative input.
2. Produce STYLEGUIDE-MODERNIZATION-PHASE-0-PLAN.md with:
   - Three-lane structure (M / N / O) confirmed
   - Per-lane locks (which files, which selectors, which markup pattern)
   - Edge case decisions (record-only / infrastructure / PARTIAL / TODO / legacy)
   - Phase 2 edit scope (style-guide.html + lab pattern HTMLs only;
     no baseline CSS / tokens / blocks / theme.json)
   - Edit protocol: edit-first / readback / abort-on-mismatch / no auto fresh Write
   - Validator sequence + publish_styleguide.py run before/after
   - Out-of-scope locks
3. Phase 0 report follows with Charter §3.3 amendment text drafted.
```

---

## §12 — Validation expectation at Phase 5 close

```
Validator                                  1.000 / 1.000 / 1.000 / 1.000 PASS
npm test                                   PASS
publish_styleguide.py                      run successfully, /styleguide/ regenerated
Wave 1 / legacy 12 styleguide sections    each gets a lab pattern link
17 lab pattern HTMLs                       each gets validation-specimen banner
Charter §3.3                               amended with publish-reality clarification
publish_styleguide.py comment              updated to acknowledge lab-publishing duality
```

---

## §13 — One-line summary

```
v3.5.16 closes the framing gap created when GitHub Pages publishing was
chosen over a /styleguide/-only mirror, and adds the lab-navigation UX
that the user has been asking for since the v3.4.0 module restructure.
```
