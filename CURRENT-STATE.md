# CURRENT-STATE.md — Release / Phase Status Board

> **Purpose**: volatile release state. Update only at real phase or release
> boundaries.
> **Stable architecture**: see `PROJECT-CONTEXT.md`.
> **Session handoff**: see `NEXT-SESSION.md`.
> **Last updated**: 2026-05-21 (v3.6.7 closed)

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
v3.6.0   Ontology Theme Pilot v0                                      ✓ DONE
v3.6.1   Token Architecture Refactor                                  ✓ DONE
v3.6.2   WP Core Block Specimen Wall                                  ✓ DONE
v3.6.3   WP Block Bridge Expansion                                     ✓ DONE
v3.6.4   WP Block Bridge Residual Cleanup                              ✓ DONE
v3.6.5   WP Block Bridge Editor Token Parity                           ✓ DONE
v3.6.6   WP Block Bridge Ripple / Editor State Parity                   ✓ DONE
v3.6.7   WP Specimen Follow-On Editor Compatibility                     ✓ DONE
```

## Current Phase

```txt
Current release:   v3.6.7 WP Specimen Follow-On Editor Compatibility
Current phase:     CLOSED
Current state:     BACKLOG #44 narrowed to remaining coverage follow-ons and validator polish
Next allowed work: Next cycle plan-first (Wave 2 / #21 / narrowed #41 packaging / residual #44 coverage)
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

## v3.6.0 Closed Notes

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
  User visual QA PASS after follow-up fixes.

Phase 5:
  v3.6.0 closed as:
    Pilot v0 — scaffold + Wave 1 reverse mapping + block bridge MVP

Architectural insights captured (post-Phase 2E):
  docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md (622 lines)
  Three lessons locked:
    1. Build direction reversal (forward design system vs reverse theme integration)
    2. WP core style reset first (five-step block bridge order + computed audit gate)
    3. Token layering architecture
       md-ref → md-sys → wp-preset + wp-custom + comp
       Dark mode = sys layer swap only (ref unchanged)
       All bridges M3 → WP (Strict M3 mode)
  This doc is authoritative input for v3.6.1 plan-first.
```

## v3.6.0 Close Outcome

```txt
Closed with:
  - CHANGELOG v3.6.0 entry
  - ROADMAP v3.6.0 DONE + v3.6.1 NEXT
  - Phase 5 close doc
  - lesson locks in AGENTS.md / CLAUDE.md / PRE-ENTRY / FEEDBACK
  - BACKLOG #42 Token Architecture Refactor
  - BACKLOG #43 WP core block specimen wall / full variation audit

Routed:
  #20 final close -> v3.6.1
  #21 Interpreter Plugin -> after v3.6.1 architecture lock
  #41 full block bridge expansion -> v3.6.x/v3.7.x
  #42 Token Architecture Refactor -> v3.6.1
  #43 WP core specimen wall -> v3.6.x
```

## v3.6.1 Close Outcome

```txt
v3.6.1   Token Architecture Refactor                   ✓ DONE

Scope (per PILOT-LESSONS-AND-TOKEN-ARCHITECTURE §5.1):
  1. tokens.css split (ref / sys.light / sys.dark / comp)
  2. wp-preset / wp-custom bridge files
  3. theme.json settings.custom.axismundi.* shape/state-layer/motion
  4. Dark mode infrastructure (data-theme + theme switcher)
  5. Cross-cutting: axismundi-lab + Pilot 양쪽 영향
  6. Light + dark computed validation matrix
  7. BACKLOG #20 final close
  8. FEEDBACK-AND-STRATEGY.md §1 refinement

Phase 1 implementation complete:
  - Axis E md-sys -> md-ref token layering guard
  - Axis F wp-preset/wp-custom bridge guard
  - Axis G theme.json settings.custom.axismundi guard
  - Pilot real Light / Dark / Auto switcher click path validated
  - BACKLOG #20 closed

Phase 3 visual QA complete:
  - Light/dark visual surfaces PASS
  - Two non-blocking core block findings routed to BACKLOG #43 / #41
  - Evidence doc:
    docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-3-VISUAL-QA.md

Phase 5 complete:
  - CHANGELOG v3.6.1 entry
  - ROADMAP v3.6.1 DONE + v3.6.x/v3.7.x NEXT
  - BACKLOG #42 closed
  - Lesson locks applied to AGENTS / CLAUDE / PRE-ENTRY / FEEDBACK / NEXT-SESSION
```

Repository:

```txt
https://github.com/Jiwoon-Kim/axismundi
https://jiwoon-kim.github.io/axismundi/
C:\Users\thaum\dev\axismundi
```

## Validation State

```txt
Validator:         7-axis 1.000 PASS
npm test:          PASS
PHP lint:          PASS
Computed QA:       npm run validate:computed PASS (light/dark matrix + click path)
wp-env:            active during final verification
Published mirror:  styleguide regenerated
Generated tmp:     tmp/ ignored; validator script is tracked
```

## v3.6.2 Close Outcome

```txt
Mode:
  Evidence collection / classification, not implementation

Primary backlog:
  #43 WP core block specimen wall / full variation audit — CLOSED

Feeds:
  #41 full block bridge expansion
  #44 specimen follow-on coverage + editor compatibility

Phase 0 plan:
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-0-PLAN.md

Phase 1 report:
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-1-REPORT.md

Phase 2 classification:
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md

Phase 3 visual QA:
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md

Phase 5 close:
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md

Specimen page:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Coverage:
  Tier 1 block families represented: 11 / 11
  Tier 1 entries classified: 26 / 26
  Unclassified entries: 0
  Stable anchors: PASS
  Render gate: PASS
  Phase 3 findings routed: PASS

Routed to #41:
  table-footer reset
  search/code/separator bridge inputs
  button/quote semantic-decision inputs

Routed to #44:
  editor-invalid-content
  mark/highlight coverage
  Material Symbols font constraint
```

## v3.6.3 Close Outcome

```txt
Mode:
  BACKLOG #41 implementation/routing slice

Closed by v3.6.3:
  table-footer-contrast reset
  search-styleguide-delta bridge
  code-long-line-overflow bridge
  separator-variant-visibility bridge
  button-anchor-semantics route
  quote-pullquote-semantics route

Phase 0 plan:
  docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md

Phase 1 report:
  docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md

Phase 2 report:
  docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md

Phase 3 decisions:
  docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
  docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md

Phase 5 close:
  docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md

Validation:
  python tools/generators/build_pilot_specimen_wall.py PASS
  npm run validate:specimen-wall                       PASS
  php -l products/reference-implementations/axismundi-pilot/functions.php PASS
  npm test                                             PASS
  npm run validate:computed                            PASS

Routed forward:
  BACKLOG #41 residual button mechanical cleanup
  BACKLOG #41 quote/pullquote distinct-surface implementation
  BACKLOG #41 original ripple/editor parity questions
  BACKLOG #44 specimen/editor follow-on coverage
```

## v3.6.4 Close Outcome

```txt
Mode:
  BACKLOG #41 residual mechanical cleanup after v3.6.3 semantic routing

Closed by v3.6.4:
  button mechanical cleanup after route
  quote/pullquote selector narrowing
  pullquote distinct-surface bridge implementation
  light/dark visual QA for button + quote/pullquote
  editor canvas smoke
  front-end drag console smoke

Phase 0 plan:
  docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md

Phase 1 report:
  docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md

Phase 2 report:
  docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md

Phase 3 visual QA:
  docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md

Phase 5 close:
  docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md

Validation:
  python tools/generators/build_pilot_specimen_wall.py PASS
  npm run validate:specimen-wall                       PASS
  php -l products/reference-implementations/axismundi-pilot/functions.php PASS
  npm test                                             PASS
  npm run validate:computed                            PASS
  git diff --check                                     PASS

Routed forward:
  BACKLOG #41 ripple bridge graduation
  BACKLOG #41 editor-canvas parity questions
  BACKLOG #41 editor md-sys color token enqueue parity
  BACKLOG #44 editor-invalid-content / editor compatibility
```

## v3.6.5 Close Outcome

```txt
Mode:
  BACKLOG #41 editor token enqueue parity slice

Closed by v3.6.5:
  editor md-sys color token enqueue parity
  WordPress 7.0 editor iframe tokens.sys.light landing
  editor pullquote divider/color token resolution

Phase 0 plan:
  docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md

Phase 1 report:
  docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md

Phase 2 report:
  docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md

Phase 3 visual QA:
  docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md

Phase 5 close:
  docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md

Validation:
  wp-env run cli wp core version                      7.0
  python tools/generators/build_pilot_specimen_wall.py PASS
  npm run validate:specimen-wall                       PASS
  php -l products/reference-implementations/axismundi-pilot/functions.php PASS
  npm test                                             PASS
  npm run validate:computed                            PASS
  git diff --check                                     PASS

Routed forward:
  BACKLOG #41 ripple bridge graduation
  BACKLOG #41 broader editor-canvas state parity
  BACKLOG #44 editor-invalid-content / editor compatibility
  TT5 as future selector/theme.json structure reference only
```

## v3.6.6 Close Outcome

```txt
Mode:
  BACKLOG #41 ripple / editor state diagnostic and routing cycle

Closed by v3.6.6:
  Pilot ripple bridge does not graduate in v3.6.6
  Pilot front-end core/button ripple remains Pilot-only
  editor-canvas state parity for current core/button theme bridge:
    focus-visible PASS
    disabled PASS
    hover not exposed / no theme target
    pressed not exposed / no theme target
    selected not exposed / no theme target

Phase 0 plan:
  docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md

Phase 1 report:
  docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md

Phase 2 report:
  docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md

Phase 3 visual QA:
  docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md

Phase 5 close:
  docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md

Validation:
  wp-env run cli wp core version                      7.0
  python tools/generators/build_pilot_specimen_wall.py PASS
  npm run validate:specimen-wall                       PASS
  php -l products/reference-implementations/axismundi-pilot/functions.php PASS
  npm test                                             PASS (Axis A-G all 1.000)
  npm run validate:computed                            PASS
  git diff --check                                     PASS

Routed forward:
  BACKLOG #41 shared WordPress ripple runtime packaging decision
  BACKLOG #44 editor-valid fixture / editor compatibility
  diagnostic-first remains methodology finding, not Lock 5
```

## v3.6.7 Close Outcome

```txt
Mode:
  BACKLOG #44 specimen editor compatibility / split fixture cycle

Closed by v3.6.7:
  Route C implemented
  original front-end specimen wall preserved for stable data-ax computed evidence
  new editor-valid smoke fixture added:
    products/reference-implementations/axismundi-pilot/fixtures/core-block-editor-smoke.html
  importer extended to import both fixture pages
  validate:specimen-wall extended to validate both surfaces

Phase 0 plan:
  docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md

Phase 1 report:
  docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md

Phase 2 report:
  docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md

Phase 3 visual QA:
  docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md

Phase 5 close:
  docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md

Validation:
  wp-env run cli wp core version                      7.0
  python tools/generators/build_pilot_specimen_wall.py PASS
  npm run validate:specimen-wall                       PASS
  php -l products/reference-implementations/axismundi-pilot/functions.php PASS
  npm test                                             PASS (Axis A-G all 1.000)
  npm run validate:computed                            PASS
  git diff --check                                     PASS

Routed forward:
  BACKLOG #44 mark/highlight, long-line code, deep pullquote, Material Symbols coverage
  BACKLOG #44 validator hardening polish
  BACKLOG #41 shared WordPress ripple runtime packaging decision unchanged
  diagnostic-first remains methodology finding, not Lock 5
```

## Discipline

```txt
CURRENT-STATE.md updates only at phase/release boundaries.
NEXT-SESSION.md updates only at session boundary or explicit handoff requests.
Phase plans and audit docs carry the detailed working state.
```
