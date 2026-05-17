# CURRENT-STATE.md — Release / Phase Status Board

> **Purpose**: volatile release state. Update only at real phase or release
> boundaries.
> **Stable architecture**: see `PROJECT-CONTEXT.md`.
> **Session handoff**: see `NEXT-SESSION.md`.
> **Last updated**: 2026-05-18 (v3.5.17 RELEASE CLOSED — Styleguide shell rebuild)

---

## Current Release Status

```txt
v3.5.0   Public Surface Reframe / policy framework                  ✓ DONE
v3.5.1   Wave 1 — Button #1                                          ✓ DONE
v3.5.2   Wave 1 — Icon button #2                                     ✓ DONE
v3.5.3   Wave 1 — Card #9                                            ✓ DONE
v3.5.4   Matrix consumer-state amendment (#24 + #26)                 ✓ DONE
v3.5.5   Wave 1 — FAB family #3 + #4                                 ✓ DONE
v3.5.6   Ripple v2 + data-ax-ripple (#25 + #27)                      ✓ DONE
v3.5.7   Wave 1 — Text field #16                                     ✓ DONE
v3.5.8   Wave 1 — Search bar #17                                     ✓ DONE
v3.5.9   Baseline correction — Pill radius interpolation (#31)        ✓ DONE
v3.5.10  Wave 1 — Button group #6                                    ✓ DONE
v3.5.11  Wave 1 — List #33                                           ✓ DONE
v3.5.12  Wave 1 — Carousel #34                                       ✓ DONE
v3.5.13  Wave 1 closure cleanup (#32 / #33 / Records sweep)          ✓ DONE
v3.5.14  Publish prep                                                ✓ DONE
v3.5.15  GitHub repository + Pages publish                           ✓ DONE
v3.5.16  Styleguide modernization + module workspace framing          ✓ DONE
v3.5.17  Styleguide shell rebuild + mobile reading polish             ✓ DONE
v3.5.18  Post-shell routing decision                                  ☐ NEXT
```

## Current Phase

```txt
Current release:   v3.5.17 Styleguide shell rebuild + mobile reading polish
Current phase:     CLOSED
Closed by:         Phase 3 visual QA PASS + Phase 5 bookkeeping
Next allowed work: v3.5.18 route selection
```

## Wave 1 Progress

```txt
DONE      9 / 9
  - Button #1
  - Icon button #2
  - FAB family #3 + #4
  - Button group #6
  - Card #9
  - Text field #16
  - Search bar #17
  - List #33
  - Carousel #34

PARTIAL   0 / 9
TODO      0 / 9
```

## Matrix Snapshot

```txt
34 TOC component rows:
  DONE       13
  PARTIAL     2
  TODO       16
  RECORD      3

3 infrastructure provider rows:
  popover/      DONE
  ripple/       DONE
  icon-system/  DONE

37 canonical entries total.
```

v3.5.17 does not change component row status. The matrix remains
13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.

## v3.5.17 Closure Notes

```txt
Styleguide shell rebuild + mobile reading polish closed.
No component matrix rows changed.
No baseline component CSS/tokens were changed.
Public Pages remains live:
  https://jiwoon-kim.github.io/axismundi/
```

Closed scope:

```txt
Styleguide:
  mobile top app bar added
  menu icon button opens a Sheet-style .sg-drawer
  desktop/tablet sidebar preserved
  theme switcher is now icon-button based
  data-theme-button contract preserved
  body/nav order equality preserved
  color palettes compact/wrap on mobile
  long explanation copy uses native details/summary disclosure

Typography:
  Foundation > Typography now links typography-axis.html as an adjunct
  typography-axis.html has mobile-friendly, collapsible sticky controls

Versioning:
  Axismundi Style Guide v0.3.0
  Monorepo cycle: v3.5.17

Backlog:
  #34 partially resolved; N3 module picker/dialog UX remains
  #37 remains full dogfooding after Wave 2 navigation closure
```

## v3.5.18 Next Route Candidates

```txt
Option A  BACKLOG #34 residual: N3 dialog/module picker UX
Option B  Side-fix cleanup: BACKLOG #2 / #3
Option C  Date picker grid a11y: BACKLOG #19
Option D  Behavior patterns: BACKLOG #29 Card / #30 Extended FAB
Option E  v3.6.0 Ontology Theme Pilot entry planning
```

Repository naming / path lock:

```txt
Repo name: axismundi
Current directory:
  C:\Users\thaum\dev\axismundi
```

## Validation State

```txt
Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:  PASS
Playwright:
  styleguide shell acceptance passed at 390 / 768 / 1280
  mobile drawer opens/closes from the menu icon button
  nav/body order equality = true
  lab/record links and validation banners preserved
  typography-axis mobile overflow = 0
Baseline:
  components.css / tokens.css / blocks.css / theme.json untouched.
```

## Discipline

```txt
CURRENT-STATE.md updates only at phase/release boundaries.
NEXT-SESSION.md updates only at session boundary or explicit handoff requests.
Phase plans and audit docs carry the detailed working state.
```
