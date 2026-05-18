# CURRENT-STATE.md — Release / Phase Status Board

> **Purpose**: volatile release state. Update only at real phase or release
> boundaries.
> **Stable architecture**: see `PROJECT-CONTEXT.md`.
> **Session handoff**: see `NEXT-SESSION.md`.
> **Last updated**: 2026-05-19 (v3.6.0 Phase 3 paused — computed QA gate added)

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
v3.6.0   Ontology Theme Pilot                                         ◐ ACTIVE
```

## Current Phase

```txt
Current release:   v3.6.0 Ontology Theme Pilot
Current phase:     Phase 3 QA / Phase 2E bridge verification
Current state:     PAUSED FOR SESSION END
Next allowed work: User visual QA confirmation → Phase 5 mechanical close
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

v3.6.0 does not change component row status yet. Carousel #34 remains
historically DONE from v3.5.12, but is Pilot-excluded / plugin-routed for the
theme Pilot.

## v3.6.0 Active Notes

```txt
Phase 2A:
  axismundi-pilot scaffold created and activated in wp-env.

Phase 2B:
  asset bridge generator created; lab CSS/fonts/icons copied into Pilot.

Phase 2C:
  block templates created; Korean prose sample render verified.

Phase 2D:
  Pilot patterns and block styles registered.

Phase 2E:
  WordPress block -> M3 reverse mapping bridge added.
  No forced .prose wrapper; block-level customization preserved.
  Button native fill/outline map to M3 Filled/Outlined.
  Pilot registers only tonal/elevated/text Button styles.
  Ripple + finite-radius morph verified for core/button links.
  Table default/stripes reset verified.

Phase 3:
  computed-style Playwright gate added:
    npm run validate:computed
  Technical validation passes.
  User visual QA is not yet final-close approved.
```

## v3.6.0 Close Route

```txt
Next:
  1. Reopen wp-env if needed.
  2. Run final validation:
       npm test
       npm run validate:computed
       php -l products/reference-implementations/axismundi-pilot/functions.php
  3. User visual QA:
       Button styles
       prose code/quote/table/separator
       table default/stripes
       mobile 390px
  4. If user approves: Phase 5 mechanical close.

Do not:
  close v3.6.0 before user visual QA approval.
  commit Phase 5 bookkeeping before approval.
  reintroduce forced .prose wrapper on core/post-content.
```

Repository:

```txt
https://github.com/Jiwoon-Kim/axismundi
https://jiwoon-kim.github.io/axismundi/
C:\Users\thaum\dev\axismundi
```

## Validation State

```txt
Validator:         1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:          PASS
PHP lint:          PASS
Computed QA:       npm run validate:computed PASS
wp-env:            active during verification
Published mirror:  styleguide regenerated
Generated tmp:     tmp/ ignored; validator script is tracked
```

## Discipline

```txt
CURRENT-STATE.md updates only at phase/release boundaries.
NEXT-SESSION.md updates only at session boundary or explicit handoff requests.
Phase plans and audit docs carry the detailed working state.
```
