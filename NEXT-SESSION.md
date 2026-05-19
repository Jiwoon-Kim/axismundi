# NEXT-SESSION.md — v3.6.1 Token Architecture Refactor Handoff

> **Status**: v3.6.0 Ontology Theme Pilot is closed. v3.6.1 Phase 3 visual QA is complete.
> **Use**: read at the start of the next Codex/Claude session.
> **Last updated**: 2026-05-19.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md  ← HIGH PRIORITY
   (authoritative architectural input for v3.6.1)
5. docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-5-CLOSE.md
6. docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
7. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
8. BACKLOG.md #20 / #21 / #41 / #42 / #43
9. docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

Active v3.6.1 plan / relay note:

```txt
10. docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-0-PLAN.md
11. docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-1-PLAN.md
12. docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-1-CLOSE.md
13. docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-3-VISUAL-QA.md

Repo docs remain authority. Chat is relay, not source of truth.
Codex writes implementation + phase plan/report docs.
Opus/Claude writes review findings only, preferably via user relay or
docs/v3.6.1/*-review.md if repo-based handoff is requested.
```

## 1) Current State

```txt
v3.5.18  Pre-Pilot cleanup + Carousel reroute       ✓ CLOSED
v3.6.0   Ontology Theme Pilot v0                    ✓ CLOSED
v3.6.1   Token Architecture Refactor                ◐ IN PROGRESS
Phase    Phase 3 visual QA complete
Status   Phase 1A-1F complete; Phase 3 visual QA PASS; next is Phase 5 close
```

Public repository:

```txt
https://github.com/Jiwoon-Kim/axismundi
https://jiwoon-kim.github.io/axismundi/
```

Local workspace:

```txt
C:\Users\thaum\dev\axismundi
```

## 2) v3.6.0 Pilot Close Summary

Completed:

```txt
Phase 0    Plan + report
Phase 1    Implementation plan
Phase 2A   Theme scaffold + wp-env activation
Phase 2B   Asset bridge generator
Phase 2C   Templates + Korean prose sample
Phase 2D   Patterns + block styles
Phase 2E   WP block -> M3 reverse mapping bridge
Phase 3    automated smoke + computed-style audit + visual QA follow-up
Phase 5    mechanical close
```

Current architectural lock:

```txt
Pilot directory: products/reference-implementations/axismundi-pilot/
Theme slug:      axismundi-pilot
Display name:    Axismundi Pilot
wp-env URL:      http://localhost:8888/

Scope:
  Wave 1 minus Carousel
  core blocks only
  no custom block registration
  no Carousel plugin/block/runtime
  no HCT Interpreter Plugin

Bridge:
  WordPress block selectors map back to M3 contracts.
  Do not force .prose onto core/post-content.
```

## 3) Latest Technical State

Phase 2E added:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
tools/validators/validate_pilot_computed_styles.js
```

Mapped/fixed in this session:

```txt
Button:
  native WP fill      -> M3 Filled
  native WP outline   -> M3 Outlined
  custom tonal        -> M3 Tonal
  custom elevated     -> M3 Elevated
  custom text         -> M3 Text
  ripple + finite-radius morph verified on front-end links

Table:
  default table cells use M3 horizontal separators
  stripes odd rows/cells reset to transparent
  stripes even rows/cells use surface-container-high

Prose:
  core/post-content remains block-first
  code / quote / list / separator / table are mapped by block selectors
```

Computed-style audit:

```txt
Command: npm run validate:computed
Status:  PASS

Checks:
  pattern QA page
  single prose page
  front page
  styleguide blocks table
  button computed styles
  search computed styles
  prose computed styles
  table default/stripes computed styles
  horizontal overflow
  console/page errors
```

## 4) Resume Checklist

Start by running:

```powershell
cd C:\Users\thaum\dev\axismundi
git status --short
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
```

If wp-env is not running:

```powershell
wp-env start
```

Then open/check:

```txt
http://localhost:8888/
http://localhost:8888/?page_id=10
http://localhost:8888/?p=1
file:///C:/Users/thaum/dev/axismundi/styleguide/blocks.html#blocks-table
```

## 5) Next Decision

Start v3.6.1 Phase 0 plan-first:

```txt
Cycle: Token Architecture Refactor (cross-cutting: lab + Pilot)
Input: docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
       (authoritative, do not invent — verbatim quote in User Request Log)
Scope: per PILOT-LESSONS §5.1 (9 items)
Lane assignment unchanged: Codex implementation / Opus ontology review
```

Do not start by editing tokens. First produce a Phase 0 plan with a User Request
Log that quotes the insight directly:

```txt
□ md-ref is primitive source
□ md-sys is runtime semantic source
□ wp-preset is editor-facing projection
□ wp-custom is theme-managed internal bridge
□ dark mode swaps sys mappings, not ref values
□ lab + Pilot are both affected
```

## 6) Important Lessons

```txt
Smoke tests were insufficient for this Pilot.
Selector presence was insufficient.
Computed style is now a Phase 3 gate for WP block -> M3 mapping.

User had to catch several visual defects manually:
  Button native fill/outline mapping
  outlined Button border conflict
  table default/stripes leakage

Future agents must not call this done without computed values.
```

Architectural lessons captured during v3.6.0 close:

```txt
Reference: docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md (622 lines)

Lesson 1 — Build direction reversal:
  Design system construction is FORWARD (M3 spec → component → mapping).
  Theme integration is REVERSE (CMS native surface → M3 overlay).
  Forward direction CANNOT be reused as-is for reverse case.
  axismundi-lab = forward, axismundi-pilot = reverse.

Lesson 2 — WP core style reset first:
  WordPress core blocks carry non-neutral defaults
  (fill / outline / stripes / table borders / inline patterns).
  Reset is explicit step before M3 mapping.
  Five-step order: inventory → reset → M3 mapping → interaction → computed audit.

Lesson 3 — Token layering architecture:
  md-ref (primitive) → md-sys (semantic) → wp-preset + wp-custom → ax-comp
  Dark mode = sys layer swap only (ref unchanged, preset/custom follow).
  All bridges M3 → WP (Strict M3 mode).
  wp-preset = editor-facing projection.
  wp-custom = theme-managed internal bridge (NOT in picker).
  md-sys is source of truth, NOT theme.json hex values.

These lessons drive v3.6.1 Token Architecture Refactor scope.
plan-first must include User Request Log with verbatim quotes from
PILOT-LESSONS doc §1.4, §3.5, §3.6. Do not abstract these into lane labels.
```

## 7) v3.6.1 Preview — Token Architecture Refactor

Next cycle entry:

```txt
Cycle name:  v3.6.1 Token Architecture Refactor
Bucket:      A — Architecture / Constitution-level
Cross-cutting: axismundi-lab + axismundi-pilot 양쪽 영향
Input:       docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
Scope:       per §5.1 (9 items)

Phase 0 plan-first must:
  □ User Request Log at top with verbatim insight quotes
  □ Lock token layer 5-model (ref / sys.light / sys.dark / wp-preset / wp-custom / ax-comp)
  □ Lock bridge direction (M3 → WP only)
  □ Lock dark mode mechanism (data-theme + sys layer swap)
  □ Lock cross-cutting scope (lab tokens.css + Pilot 양쪽)
  □ Phase 3 acceptance criteria: light + dark 양쪽 visual QA
  □ Korean prose render in both modes
```
