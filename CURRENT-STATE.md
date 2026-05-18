# CURRENT-STATE.md — Release / Phase Status Board

> **Purpose**: volatile release state. Update only at real phase or release
> boundaries.
> **Stable architecture**: see `PROJECT-CONTEXT.md`.
> **Session handoff**: see `NEXT-SESSION.md`.
> **Last updated**: 2026-05-18 (v3.5.18 RELEASE CLOSED — Pre-Pilot cleanup)

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
v3.5.18  Pre-Pilot cleanup + Carousel reroute                         ✓ DONE
v3.6.0   Ontology Theme Pilot                                         ☐ NEXT
```

## Current Phase

```txt
Current release:   v3.5.18 Pre-Pilot cleanup + Carousel reroute
Current phase:     CLOSED
Closed by:         Phase 3 PASS + Phase 5 bookkeeping
Next allowed work: v3.6.0 Ontology Theme Pilot plan-first
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

v3.5.18 does not change component row status. Carousel #34 remains historically
DONE from v3.5.12, but is Pilot-excluded / plugin-routed for v3.6.0.

## v3.5.18 Closure Notes

```txt
Carousel:
  v3.5.12 DONE history preserved
  Pilot-excluded / plugin-routed amendment added
  lab/modules/carousel/ retained as BACKLOG #38 extraction seed

Process:
  User Request Log discipline added to AGENTS.md / CLAUDE.md
  portal/overlay smoke-test rule added
  v3.5.16 / v3.5.17 lessons recorded in grounding doc

Pilot inputs:
  blocks.html verified as WP core block extension spec
  prose.html verified as post body rendering spec
  prose 390px overflow fixed in-cycle
  sg-sidebar shell inconsistency routed to BACKLOG #39

Handoff:
  docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md created
```

## v3.6.0 Next Route

```txt
Route: Ontology Theme Pilot
Scope: theme-only WordPress block theme proof
Consumes:
  Wave 1 minus Carousel
  popover/ + ripple/ + icon-system/
  tokens.css + components.css + blocks.css + prose.css
  styleguide/index.html + blocks.html + prose.html as spec references
Excludes:
  Carousel plugin/block
  ActivityPub runtime
  M3 Interpreter plugin
  v4.0 directory restructure
```

Repository:

```txt
https://github.com/Jiwoon-Kim/axismundi
https://jiwoon-kim.github.io/axismundi/
C:\Users\thaum\dev\axismundi
```

## Validation State

```txt
Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:  PASS
Publish:   /styleguide/ regenerated
Smoke:
  styleguide/index.html PASS
  styleguide/blocks.html PASS
  styleguide/prose.html PASS
  typography-axis.html PASS
  lab pattern pages PASS
Baseline:
  components.css / tokens.css / blocks.css / theme.json untouched.
```

## Discipline

```txt
CURRENT-STATE.md updates only at phase/release boundaries.
NEXT-SESSION.md updates only at session boundary or explicit handoff requests.
Phase plans and audit docs carry the detailed working state.
```
