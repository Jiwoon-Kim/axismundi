# NEXT-SESSION.md — v3.6.0 Pilot QA Handoff

> **Status**: v3.6.0 Ontology Theme Pilot is active.
> **Use**: read at the start of the next Codex/Claude session.
> **Last updated**: 2026-05-19.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-0-PLAN.md
5. docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-1-PLAN.md
6. docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-2E-REPORT.md
7. docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
8. docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md
9. docs/v3.5.0/MODULE-STATUS-MATRIX.md
10. BACKLOG.md latest #38 / #39 / #40 / #41 entries
```

## 1) Current State

```txt
v3.5.18  Pre-Pilot cleanup + Carousel reroute       ✓ CLOSED
v3.6.0   Ontology Theme Pilot                       ◐ ACTIVE
Phase    Phase 3 QA / Phase 2E bridge verification
Status   paused for session end after computed-style gate PASS
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

## 2) v3.6.0 Pilot Progress

Completed:

```txt
Phase 0    Plan + report
Phase 1    Implementation plan
Phase 2A   Theme scaffold + wp-env activation
Phase 2B   Asset bridge generator
Phase 2C   Templates + Korean prose sample
Phase 2D   Patterns + block styles
Phase 2E   WP block -> M3 reverse mapping bridge
Phase 3    automated smoke + computed-style audit
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

The next session should not immediately close v3.6.0.

Required before Phase 5:

```txt
□ User visual QA final approval
□ Button style picker / Button visual states acceptable
□ Table default and stripes visually acceptable
□ Prose inline code / code block / quote / separator acceptable
□ 390px mobile visual QA acceptable
□ Computed-style audit still PASS
```

If approved:

```txt
Proceed to v3.6.0 Phase 5 mechanical close.
Update CHANGELOG / ROADMAP / CURRENT-STATE / NEXT-SESSION.
Close or route BACKLOG #20 / #40 / #41 as appropriate.
Commit Phase 5 bookkeeping separately if possible.
```

If more issues are found:

```txt
Treat as Phase 3 finding.
Patch source authority first.
Regenerate assets/mirror.
Run npm test + npm run validate:computed.
Do not close until user confirms.
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
