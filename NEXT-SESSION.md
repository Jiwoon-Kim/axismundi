# NEXT-SESSION.md — v3.5.18 Routing Handoff

> **Status**: v3.5.17 Styleguide shell rebuild + mobile reading polish closed.
> **Use**: read at the start of the next local Claude/Codex session.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
5. CHANGELOG.md latest entry (v3.5.17)
6. ROADMAP.md v3.5.18 NEXT entry
7. docs/v3.5.17/STYLEGUIDE-SHELL-REBUILD-PHASE-0-PLAN.md
8. docs/v3.5.17/STYLEGUIDE-SHELL-REBUILD-PHASE-0-REPORT.md
9. docs/v3.5.17/STYLEGUIDE-SHELL-REBUILD-PHASE-1-PLAN.md
10. docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

## 1) Current State

```txt
v3.5.13 — Wave 1 closure cleanup (#32/#33/Records)  ✓ CLOSED
v3.5.14 — Publish prep                               ✓ CLOSED
v3.5.15 — GitHub repository + Pages publish          ✓ CLOSED
v3.5.16 — Styleguide modernization/framing           ✓ CLOSED
v3.5.17 — Styleguide shell rebuild/mobile polish     ✓ CLOSED
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

## 2) v3.5.17 Closure Summary

Closed scope:

```txt
Styleguide-local shell:
  mobile top app bar
  menu icon button
  Sheet-style .sg-drawer
  desktop/tablet sidebar preserved
  no App bar / Nav drawer / Sheet completion claim

Theme:
  icon-button theme switcher
  light_mode / dark_mode / contrast glyphs
  data-theme-button contract preserved

Body mobile polish:
  palette chips compact/wrap on mobile
  long explanatory copy uses native details/summary disclosure
  visual specimens remain the primary content

Typography adjunct:
  Foundation > Typography links typography-axis.html
  typography-axis.html has mobile-friendly, collapsible sticky controls

Version:
  Axismundi Style Guide v0.3.0
  Monorepo cycle: v3.5.17
```

Deferred:

```txt
BACKLOG #34:
  residual N3 module picker/dialog UX remains open.

BACKLOG #37:
  full docs-site dogfooding remains deferred until Wave 2 navigation closure.
```

Phase 3 lesson:

```txt
Do not close on "plan executed" alone.
Close only after explicit user-request acceptance criteria pass.
For corrective UX cycles, preserve a User Request Log and verify it directly.
```

Verification:

```txt
Validator:       1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:        PASS
Playwright/user QA:
  mobile top bar visible at 390px
  drawer opens/closes from menu icon
  drawer includes canonical nav + icon theme switcher
  desktop/tablet sidebar remains
  nav/body order equality preserved
  lab links and validation banners preserved
  typography-axis collapsible controls accepted by user
```

## 3) v3.5.18 Route Candidates

No route is selected yet. User decision required.

```txt
Option A — BACKLOG #34 residual
  N3 module picker / dialog UX for lab module discovery.

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
