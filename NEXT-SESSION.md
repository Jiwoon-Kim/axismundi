# NEXT-SESSION.md — v3.5.17 Routing Handoff

> **Status**: v3.5.16 Styleguide modernization closed.
> **Use**: read at the start of the next local Claude/Codex session.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
5. CHANGELOG.md latest entry (v3.5.16)
6. ROADMAP.md v3.5.17 NEXT entry
7. docs/v3.5.16/STYLEGUIDE-MODERNIZATION-PHASE-2-REPORT.md
8. docs/v3.5.16/MODERNIZATION-AUDIT.md
9. docs/v3.5.16/STALE-STATE-AUDIT.md
10. docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

## 1) Current State

```txt
v3.5.13 — Wave 1 closure cleanup (#32/#33/Records)  ✓ CLOSED
v3.5.14 — Publish prep                               ✓ CLOSED
v3.5.15 — GitHub repository + Pages publish          ✓ CLOSED
v3.5.16 — Styleguide modernization                   ✓ CLOSED
Validator                                            1.000 / 1.000 / 1.000 / 1.000 PASS
npm test                                             PASS
GitHub repository                                    LIVE
GitHub Pages                                         LIVE
```

Public URLs:

```txt
Repository:
  https://github.com/Jiwoon-Kim/axismundi

Pages:
  https://jiwoon-kim.github.io/axismundi/
```

Overall matrix:

```txt
13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD + 3 infrastructure = 37 entries
```

## 2) v3.5.16 Closure Summary

Closed scope:

```txt
Charter §3.3:
  lab/modules/* = module workspace + validation specimen surface
  /styleguide/ = canonical public visual demo

Styleguide:
  18 icon+label actions added
  15 Validation specimen links
  3 Record audit links
  nav/body order equality locked

Publish mirror:
  theme.js copied to /styleguide/scripts/
  source-local modules/... links rewritten for generated /styleguide/

Lab patterns:
  16 lab-*-pattern.html files now include validation-specimen banners

Backlog hygiene:
  #1 / #10 / #13 / #17 / #28 resolved
  #11 framework portion resolved; UX superseded by #34
  Snackbar / Tooltip marked as pre-v3.5.0 legacy audit shape
```

Phase 3 lesson:

```txt
Sidebar nav is the canonical source of styleguide section order.
Body sections follow nav order.
Do not reorder nav to match body; reorder body to match nav.
```

Verification:

```txt
Validator:       1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:        PASS
Playwright:
  root / styleguide / lab pattern overflow = 0 at 390 / 768 / 1280
  styleguide nav anchors = 37
  styleguide body sections = 37
  nav/body order equality = true
  lab links = 18
  icon links = 18
```

## 3) v3.5.17 Route Candidates

No route is selected yet. User decision required.

```txt
Option A — BACKLOG #34 follow-up
  N3 dialog / module picker UX for lab module discovery.

Option B — Side-fix cleanup
  BACKLOG #2 Avatar size tokens and/or #3 floating toolbar selected color.

Option C — Date picker grid a11y
  BACKLOG #19.

Option D — Behavior pattern modules
  BACKLOG #29 Card behavior patterns and/or #30 Extended FAB behavior.

Option E — v3.6.0 Ontology Theme Pilot entry planning
  Move from public surface prep into actual WordPress block theme pilot.
```

## 4) Locked Later Route

```txt
v3.6.0   Ontology Theme Pilot
v3.7.x   Wave 2 / Wave 3 component coverage
v4.0     Public release + directory restructure
```

v4.0 candidate:

```txt
Retire "lab" naming structurally:
  - axismundi-lab/ -> module-first name
  - lab-* prefixes retired
  - publish_styleguide.py redesigned
  - audit doc cross-refs updated
```

## 5) Operational Commands

```powershell
cd C:\Users\thaum\dev\axismundi
python .\tools\validators\validate_theme_pilot.py
npm test
python .\tools\generators\publish_styleguide.py
git status --short
```
