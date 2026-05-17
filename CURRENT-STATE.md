# CURRENT-STATE.md — Release / Phase Status Board

> **Purpose**: volatile release state. Update only at real phase or release
> boundaries.
> **Stable architecture**: see `PROJECT-CONTEXT.md`.
> **Session handoff**: see `NEXT-SESSION.md`.
> **Last updated**: 2026-05-18 (v3.5.16 RELEASE CLOSED — Styleguide modernization)

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
v3.5.17  Post-modernization routing decision                          ☐ NEXT
```

## Current Phase

```txt
Current release:   v3.5.16 Styleguide modernization + module workspace framing
Current phase:     CLOSED
Closed by:         Phase 3 visual QA PASS + Phase 5 bookkeeping
Next allowed work: v3.5.17 route selection
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

v3.5.16 does not change component row status. The matrix remains
13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.

## v3.5.16 Closure Notes

```txt
Styleguide modernization closed.
No component matrix rows changed.
No baseline component CSS/tokens were changed.
Public Pages remains live:
  https://jiwoon-kim.github.io/axismundi/
```

Closed scope:

```txt
Charter §3.3:
  lab/modules/* = module workspace + validation specimen surface

Styleguide:
  18 module/record actions added
  nav/body order equality locked (nav is canonical)
  mobile-first shell guardrails added

Publish mirror:
  theme.js copied
  module links rewritten for repository-root Pages

Lab patterns:
  16 validation-specimen banners added

Backlog hygiene:
  #1 / #10 / #13 / #17 / #28 resolved
  #11 framework portion resolved; UX superseded by #34
```

## v3.5.17 Next Route Candidates

```txt
Option A  BACKLOG #34 follow-up: N3 dialog/module picker UX
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
  root/styleguide/lab pattern overflow 0 at 390 / 768 / 1280
  styleguide nav anchors = 37
  styleguide body sections = 37
  nav/body order equality = true
Baseline:
  components.css / tokens.css / blocks.css / theme.json untouched.
```

## Discipline

```txt
CURRENT-STATE.md updates only at phase/release boundaries.
NEXT-SESSION.md updates only at session boundary or explicit handoff requests.
Phase plans and audit docs carry the detailed working state.
```
