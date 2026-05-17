# STALE-STATE-AUDIT.md — Known issues, deferred work, items needing update

> **Status**: research only, while Codex was on cooldown.  
> **Scope**: comprehensive sweep of stale markers, deferred items, normalization gaps across the project.  
> **Companion**: `MODERNIZATION-AUDIT.md` (styleguide/lab framing tension).  
> **Use**: input for v3.5.16+ planning, BACKLOG hygiene, legacy normalization decisions.

---

## §1 — Methodology

Greps performed:

```
1. TODO / FIXME / XXX / HACK across all project code (CSS / JS / Python / HTML)
   → 0 matches in project files (node_modules excluded)
2. [NEXT SESSION:] / deferred / pending / 보류 / 중지 / 미루 across .md files
   → 50+ matches, mostly intentional (BACKLOG entries, audit "deferred" markers)
3. BACKLOG open items inventory
4. docs/v3.5.x/ directory coverage (orphan phase docs check)
5. Legacy module audit shape (pre-Wave-1 modules vs 3/4-doc framework)
6. publish_styleguide.py policy vs reality
```

Findings categorized by priority: BLOCKER / HIGH / MEDIUM / LOW.

---

## §2 — Project code health: clean

```
CSS / JS / Python / HTML files (excluding node_modules):
  TODO    0
  FIXME   0
  XXX     0
  HACK    0
```

No abandoned code-side markers. All "deferred" language is intentional, recorded in docs.

This is unusually clean — credit to the plan-first discipline that routes everything through BACKLOG before any code marker would be needed.

---

## §3 — Phase doc coverage (no orphans)

All `docs/v3.5.x/` directories correspond to **closed** releases:

```
v3.5.0   framework canonical docs (CONSTITUTION-aligned, not cycle-based)  CLOSED
v3.5.1   Wave 1 Button #1                                                  CLOSED
v3.5.2   Wave 1 Icon button #2                                             CLOSED
v3.5.3   Wave 1 Card #9                                                    CLOSED
v3.5.4   Matrix consumer-state amendment                                   CLOSED
v3.5.5   Wave 1 FAB family #3+#4                                           CLOSED
v3.5.6   Ripple v2 contract                                                CLOSED
v3.5.7   Wave 1 Text field #16                                             CLOSED
v3.5.8   Wave 1 Search bar #17                                             CLOSED
v3.5.9   Pill radius correction (#31)                                      CLOSED
v3.5.10  Wave 1 Button group #6                                            CLOSED
v3.5.11  Wave 1 List #33                                                   CLOSED
v3.5.12  Wave 1 Carousel #34                                               CLOSED
v3.5.13  Wave 1 closure cleanup (#32/#33/Records)                          CLOSED
v3.5.14  Publish prep                                                      CLOSED
v3.5.16  Modernization + stale-state research                              PRE-CYCLE (this doc)

(v3.5.15 GitHub repo + Pages — pending, no docs yet)
```

No incomplete cycles. No phase doc orphans. Pattern discipline holds.

---

## §4 — BACKLOG open items inventory

26 numbered items across BACKLOG.md. Status breakdown:

### §4.1 — Resolved (do not re-open)

```
#4   Chip Measurement Audit                           CLOSED v3.4.9
#5   WordPress logo styleguide specimen               CLOSED v3.4.4
#7   Search bar leading icon (v3.4.3 delta)           CLOSED v3.4.4
#8   Module pattern role="group" → "radiogroup"       CLOSED v3.4.3.1
#9   Theme switcher data-theme-button → -set          CLOSED v3.4.5
#12  Theme switcher syncSwitchers() selector          CLOSED v3.4.5.1
#15  Snackbar Runtime Module                          CLOSED v3.4.10
#18  Snackbar .snackbar → .ax-snackbar rename         CLOSED earlier
#24  Matrix consumer-state column                     CLOSED v3.5.4
#25  Ripple v2 contract                               CLOSED v3.5.6
#26  Matrix row #36 allowlist correction              CLOSED v3.5.4
#27  data-ax-ripple opt-in                            CLOSED v3.5.6
#31  Pill radius interpolation                        CLOSED v3.5.9
#32  Button family size variants                      CLOSED v3.5.13
#33  List M3 full token coverage                      CLOSED v3.5.13
```

### §4.2 — Open items (active)

```
#1   Inline code font-size in helper text             OPEN, LOW
     Bucket D theme typography
     Small CSS fix, candidate for any styleguide-touching release
     
#2   Avatar size token consistency                    OPEN, MEDIUM
     Bucket B/D, token system + composition
     Could merge with Avatar Record (already done v3.5.13) or future cycle
     
#3   Floating toolbar "is-selected" color             OPEN, LOW
     Bucket B/D, App bar Large-flexible / Floating vibrant
     Component state token audit candidate
     
#6   Monotone SVG theming plugin concept              OPEN, FUTURE
     Bucket F plugin, multi-release vision
     Post-pilot, post-v3.4.x
     Architectural decision preserved; no immediate action
     
#10  Lab ripple module runtime verification           UNCLEAR
     Probably superseded by v3.5.6 Ripple v2 cycle
     Need explicit close marking — see §6.3
     
#11  Public surface reframe styleguide ⇄ lab UX       PARTIALLY CLOSED — see §6.1
     Bucket E documentation UX
     v3.5.0 closed the FRAMEWORK part; the actual styleguide ⇄ lab UX
     reframe was never executed
     OVERLAPS with #34 (just added in v3.5.15)
     
#13  publish_styleguide.py does not copy theme.js     OPEN, MEDIUM
     Bucket D / build pipeline
     Decision criteria tied to #11
     Three options recorded; awaits #11/#34 decision
     
#14  Material Symbols ligature layout shift          DEFERRED, LOW
     Bucket D theme runtime / icon
     FOUT/FOIT-class first-load shift
     Patch ready; awaits decision on global icon box contract
     
#16  Tooltip touch long-press refinement              OPEN, LOW
     Bucket D theme interaction
     Touch + rich tooltip behavior
     
#17  Text Input Corpus / Ontology Audit               OPEN, MEDIUM
     Bucket B/E
     Pre-dates Wave 1; possibly absorbed by Text field v3.5.7
     Need explicit close check — see §6.3
     
#19  Date Picker Grid Navigation A11y                 OPEN, MEDIUM
     Bucket D theme interaction
     Date picker is PARTIAL in MODULE-STATUS-MATRIX
     Pending date-time Full-Spec promotion
     
#20  Theme-only color customization policy            OPEN, MEDIUM
     Bucket E charter content
     Phase 1B carry-forward from v3.5.0
     
#21  M3 Interpreter Plugin separation                 OPEN, FUTURE
     Bucket F plugin
     Post-pilot architectural direction
     
#22  data-theme="auto" 3-state model                  OPEN, MEDIUM
     Bucket E theme runtime
     Phase 1B carry-forward
     
#23  Elevated Chip Variants                           OPEN, LOW
     Bucket B/D component variant
     Chip module future enhancement
     
#28  Icon button public specimen SVG wording          OPEN, LOW
     Bucket E documentation
     v3.5.2 Phase 0 Risk 4 follow-up
     
#29  Card behavior patterns                           OPEN, MEDIUM
     Bucket B/E
     v3.5.3 M3 guideline cross-check
     
#30  Extended FAB behavior patterns                   OPEN, MEDIUM
     Bucket B/E
     v3.5.5 FAB family close finding
     
#34  Styleguide modernization + lab nav UX            OPEN, HIGH
     v3.5.16 cycle scope
     OVERLAPS with #11 — see §6.1
     
#35  Root index Korean version + language toggle     OPEN, LOW
     v3.5.17+ candidate
```

### §4.3 — Status visualization

```
Total numbered BACKLOG items :  26
Resolved (closed)            :  15
Open active                  :   9 (#1, #2, #3, #13, #16, #19, #20, #22, #23, #28, #29, #30, #34)
Open future / multi-release  :   3 (#6, #21, #14 deferred)
Unclear / needs close check  :   2 (#10, #17)
Overlap                      :   1 (#11 vs #34)
```

---

## §5 — Legacy / pre-framework normalization gaps

`MODULE-STATUS-MATRIX` lists Wave 1 closed components AND legacy DONE components. Audit doc shapes differ.

### §5.1 — Wave 1 Component Full-Spec (9, all 3-doc trio)

```
button            3 docs    BUTTON-SPEC / -MEASUREMENT / -WP-MAPPING
icon-button       3 docs    same shape
card              3 docs    same shape
fab               3 docs    same shape (family fold-in for #4 Extended FAB)
button-group      3 docs    same shape
text-field        3 docs    same shape
list              3 docs    same shape
search-bar        4 docs    + SEARCH-BAR-RUNTIME-AUDIT (extracted JS runtime)
carousel          4 docs    + CAROUSEL-RUNTIME-AUDIT (extracted JS runtime)
```

Shape consistency: 7 × 3-doc + 2 × 4-doc. Aligned with framework decisions.

### §5.2 — Legacy DONE (pre-v3.5.0 framework)

```
chip              3 docs    CHIP-SPEC / -MEASUREMENT / -WP-MAPPING  ✓ NORMALIZED
                            v3.4.9 happened to author the same 3-doc shape
                            before the framework codified it

snackbar          1 doc     SNACKBAR-RUNTIME-AUDIT only             ✗ GAP
                            No SPEC / MEASUREMENT / WP-MAPPING docs
                            Pre-framework legacy

tooltip           1 doc     TOOLTIP-AUDIT (combined umbrella)       ✗ GAP
                            Single audit doc with mixed content
                            Pre-framework legacy

search-expansion  1 doc     SEARCH-EXPANSION-AUDIT (historical)     INTENTIONAL
                            v3.3.4 historical evidence
                            Search bar v3.5.8 absorbed this; preserve as-is
```

**Snackbar and Tooltip have audit shape gaps relative to Wave 1 standard.** Two options:

```
(A) Promote to Wave 1 audit shape:
    - SNACKBAR-SPEC-AUDIT.md (new)
    - SNACKBAR-MEASUREMENT-AUDIT.md (new)
    - SNACKBAR-WP-MAPPING.md (new)
    - SNACKBAR-RUNTIME-AUDIT.md (existing, keep)
    Same for Tooltip.

(B) Keep legacy single-doc shape, mark as "pre-framework legacy" in MATRIX.
```

Recommended: **(B) keep legacy + mark explicitly**. Rationale: these components are functionally DONE and produce no v3.5.x findings. Forcing them through the framework would be busywork. Add MATRIX note: "DONE (v3.4.6 legacy) — audit shape predates v3.5.0 framework."

### §5.3 — Infrastructure modules

```
popover           1 doc     POPOVER-AUDIT  (single umbrella)
ripple            2 docs    RIPPLE-AUDIT (legacy v3.3.3) + RIPPLE-V2-AUDIT (v3.5.6)
icon-system       8 docs    multiple specialized audits
```

Infrastructure provider rows in MATRIX use different audit shape from Component Full-Spec. No normalization expected; their category (Interaction Runtime Infrastructure) doesn't require 3-doc trio.

### §5.4 — Record-only (Avatar / Divider / Badge)

```
_records/         3 docs    AVATAR / DIVIDER / BADGE -RECORD-AUDIT
                            Closed v3.5.13, RECORD status preserved (no pattern HTML)
```

Already normalized at v3.5.13. Clean.

---

## §6 — Specific BACKLOG hygiene actions needed

### §6.1 — #11 and #34 overlap resolution

**Finding**: BACKLOG #11 "Public surface reframe — styleguide ⇄ module lab UX" (opened v3.4.5) is essentially the same scope as #34 "Styleguide modernization + lab module navigation UX" (just added v3.5.15).

`#11` was originally targeted at v3.5.0. v3.5.0 closed the FRAMEWORK part (5 canonical docs: CONSTITUTION-aligned MATRIX, COVERAGE-MAP, PROMOTION-CRITERIA, PUBLIC-SURFACE-CHARTER, COMPONENT-INVENTORY). But the actual styleguide ⇄ lab UX REFRAMING described in #11's user vision was never executed.

#11 user vision (verbatim):
> 스타일가이드에는 승격된 컴포넌트 모듈의 결과만을 보여주고, 설명은 lab 모듈 페이지로 링크. 승격되지 않은 모듈은 실험실 아이콘 팝업 메뉴로 리스트를 보여주면 될 것 같음.

This is exactly #34's scope.

**Recommended action**:

```
Option 1 (preferred):
  - Mark #11 as PARTIALLY RESOLVED v3.5.0 (framework portion only).
  - #11 styleguide ⇄ lab UX portion is now tracked under #34.
  - Add cross-reference between #11 and #34 in BACKLOG.

Option 2:
  - Merge #34 into #11 (since #11 came first).
  - Mark #34 as duplicate of #11.

Option 1 is cleaner because #11 has long history and rationale; #34 has v3.5.16
cycle scope. Cross-reference preserves both.
```

### §6.2 — #13 (publish theme.js) and #11/#34 decision

`#13` decision criteria explicitly tied to `#11`. Since #11 was never fully resolved, #13 has been waiting since v3.4.5.1. Three options remain:

```
A. Copy theme.js to publish (small patch)
B. Remove theme.js reference from style-guide.html (tighten contract)
C. Document asymmetry, leave as-is
```

**Recommended action**: lock #13 decision during v3.5.16 (alongside #34 styleguide modernization). Tying it to the same cycle gives the publish surface a coherent reframe.

### §6.3 — Items needing explicit close check

#10 (Lab ripple module runtime verification) — pre-dated Ripple v2 v3.5.6. Probably absorbed but never explicitly closed. **Action**: verify and mark as RESOLVED v3.5.6 if applicable.

#17 (Text Input Corpus / Ontology Audit) — pre-dated Text field v3.5.7. Need to check what scope #17 had and whether v3.5.7 closure absorbed it. **Action**: read #17 in detail, compare to TEXT-FIELD audit trio, mark RESOLVED if absorbed.

### §6.4 — Items deferrable beyond Wave 1 publish

```
#6   Monotone SVG plugin                              post-pilot
#21  M3 Interpreter plugin                            post-pilot
#22  data-theme="auto" 3-state model                  post-publish
#20  Theme-only color policy                          Phase 1B charter
#14  Material Symbols ligature layout shift           low-priority polish
#16  Tooltip touch long-press                         low-priority polish
#23  Elevated Chip variants                           low-priority polish
```

These do not block v3.5.15 publish or v3.6.0 pilot.

### §6.5 — Items potentially relevant to v3.5.16

```
#1   Inline code font-size in helper text             pickable as side-fix
#2   Avatar size token consistency                    pickable as side-fix
#3   Floating toolbar selected color                  pickable as side-fix
#28  Icon button SVG wording cleanup                  pickable as side-fix
#29  Card behavior patterns                           larger scope, defer to v3.5.17+
#30  Extended FAB behavior patterns                   larger scope, defer
```

Side-fixes (#1, #2, #3, #28) could opportunistically close during v3.5.16 if the styleguide is being modified anyway. Larger scope items (#29, #30) should stay separate.

---

## §7 — `index.html` framing tension carry-forward

This is the same finding as `MODERNIZATION-AUDIT.md` §6 (cross-reference):

```
Charter §3.3                : lab/modules/* is validation surface, not public API
index.html (v3.5.14 entry)  : already links directly to lab README + modules README
publish_styleguide.py       : refuses to mirror lab pattern HTML
GitHub Pages on repo root   : will serve all lab files regardless

Result: implementation already broke the strict-lab-boundary framing.
v3.5.16 needs to amend Charter §3.3 to acknowledge this.
```

---

## §8 — Items that are NOT stale (intentional)

These look like deferrals but are actually correct decisions:

```
search-expansion module                  intentionally preserved as v3.3.4 historical
                                          evidence, never normalized to Wave 1 shape
                                          
Chip / Snackbar / Tooltip M3 cross-check  not deferred — they are pre-framework
                                          DONE and adequate as-is

popover audit shape                      single umbrella audit appropriate for
                                          infrastructure category

Date picker / Time picker PARTIAL status  intentional — date-time module fold-in
                                          decision; full-spec layer is future work

publish_styleguide.py CSS-only mirror     intentional asymmetry per modules/README
                                          (preserve lab-internal artifacts on
                                          source side; mirror is generated)
```

---

## §9 — Recommended priority for v3.5.16 hygiene actions

```
HIGH (do in v3.5.16):
  1. Resolve #11 vs #34 overlap (mark #11 partially closed, cross-ref #34)
  2. Lock #13 publish theme.js decision (alongside #34 work)
  3. Close-check #10 (likely RESOLVED v3.5.6)
  4. Close-check #17 (likely RESOLVED v3.5.7)
  5. Charter §3.3 amendment (lab publishing reality)
  6. Mark Snackbar / Tooltip MATRIX rows as "pre-framework legacy"

MEDIUM (opportunistic in v3.5.16 if scope allows):
  7. Side-fix #1 (helper text inline code)
  8. Side-fix #2 (avatar size tokens)
  9. Side-fix #3 (floating toolbar selected color)
  10. Side-fix #28 (icon button SVG wording)

LOW (post-v3.5.16):
  #14, #16, #19, #20, #22, #23, #29, #30 all stay open
  #35 Korean toggle stays open

FUTURE / POST-PILOT:
  #6, #21 plugin-level work
```

---

## §10 — Phase 0 deliverable expectations for v3.5.16

When Codex starts v3.5.16:

```
1. Read MODERNIZATION-AUDIT.md (companion doc) for styleguide ⇄ lab framing.
2. Read this STALE-STATE-AUDIT.md for BACKLOG hygiene scope.
3. Decide whether v3.5.16 absorbs ONLY the modernization cycle or ALSO the
   BACKLOG hygiene items above.
4. Author STYLEGUIDE-MODERNIZATION-PHASE-0-PLAN.md (or similar name) with
   four-or-five-lane structure:
      Lane M  Modernization framing (Charter §3.3 amendment)
      Lane N  Lab navigation UX (#34, supersedes #11 UX portion)
      Lane O  Lab pattern HTML validation-specimen banner
      Lane P  BACKLOG hygiene (#10, #11, #13, #17 status updates)
      Lane Q  Opportunistic side-fixes (#1, #2, #3, #28) if scope allows
5. Phase 0 report follows with explicit scope/lane locks.
```

---

## §11 — One-line summary

```
Project code is clean (zero TODO/FIXME). All stale state lives in docs as
intentional deferrals. The two real gaps are: (a) BACKLOG #11 was never
fully closed, and now #34 has been opened with overlapping scope, and
(b) Snackbar/Tooltip pre-framework audit shape gaps remain. Both are
addressable in v3.5.16 alongside the styleguide modernization cycle.
```
