# CURRENT-STATE.md — Release / Phase Status Board

> **Purpose**: volatile release state. Update only at real phase or release
> boundaries.
> **Stable architecture**: see `PROJECT-CONTEXT.md`.
> **Session handoff**: see `NEXT-SESSION.md`.
> **Last updated**: 2026-05-17 (v3.5.14 RELEASE CLOSED — Publish prep DONE)

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
v3.5.15  GitHub repository + Pages publish                           ☐ NEXT
```

## Current Phase

```txt
Current release:   v3.5.14 Publish prep
Current phase:     CLOSED
Closed by:         Phase 3 visual QA follow-up + Phase 5 mechanical close
Next allowed work: v3.5.15 GitHub repository + Pages publish plan-first
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

v3.5.14 does not change component row status. The matrix remains
13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.

## v3.5.14 Closure Notes

```txt
Publish prep closed.
No component baseline CSS/tokens were changed.
No GitHub repository was created.
No GitHub Pages settings were changed.
No local directory rename was executed.
```

Lane results:

```txt
Lane H — License / NOTICE / package metadata
  - Root LICENSE added (GPL-3.0 text).
  - LICENSE-CC-BY-SA-4.0.md added.
  - LICENSE-MATRIX.md now defines code / docs / ontology-data boundaries.
  - NOTICE.md asset paths corrected to core/design-systems/material3/assets.
  - package.json / package-lock.json aligned to axismundi + GPL-3.0-or-later.

Lane E — Hardcoded path audit
  - Public-facing active stale command fixed.
  - Historical / provenance paths intentionally preserved.
  - Directory rename remains deferred pending final user GO.

Lane G — Publish mirror check
  - publish_styleguide.py regenerated 30 files and 16 module CSS files.
  - Publish generator wording now references Axismundi lab source.

Lane C — Ignore and artifact policy
  - .gitignore covers Node, Playwright, temporary, editor, and OS artifacts.
  - Validator evidence remains tracked.

Lane D — GitHub Actions
  - Validator-only workflow added.
  - Playwright CI deferred.

Lane A/B — README surfaces
  - README.md rewritten.
  - README.ko.md created.
  - Authorship aligned to KIM JIWOON (designbusan.ai.kr) — Busan, Korea.

Lane F — Templates category note
  - docs/v3.5.14/TEMPLATES-PUBLISH-CATEGORY-NOTE.md added.
  - Future route: /templates/.
  - Actual template implementation deferred.

Phase 3 follow-up — Root index
  - index.html refreshed as the public entry point.
  - Links now route to styleguide, README, README.ko, lab overview, lab module
    index, templates note, license matrix, and NOTICE.
```

## v3.5.15 Next Route

```txt
GitHub repository + Pages publish:
  - final path grep
  - user GO for local directory rename
  - rename local directory to C:\Users\thaum\dev\axismundi if approved
  - create GitHub repository `axismundi`
  - push initial public repo
  - enable GitHub Pages
  - verify /index.html -> /styleguide/ -> lab/module documentation navigation
```

Repository naming / path lock:

```txt
Repo name: axismundi
Preferred target path:
  C:\Users\thaum\dev\axismundi

Current directory:
  C:\Users\thaum\dev\axismundi
```

## Validation State

```txt
Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:  PASS
Index QA:  overflowX 0; 10 links rendered; local link targets exist
Baseline:  components.css / tokens.css / style-guide.html / blocks.css /
           theme.json preserved in v3.5.14.
```

## Discipline

```txt
CURRENT-STATE.md updates only at phase/release boundaries.
NEXT-SESSION.md updates only at session boundary or explicit handoff requests.
Phase plans and audit docs carry the detailed working state.
```
