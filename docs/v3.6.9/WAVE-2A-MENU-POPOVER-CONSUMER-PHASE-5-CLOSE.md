# v3.6.9 - Wave 2A-2 Menu / Popover Consumer - Phase 5 Close

Status: CLOSED.

Verdict:

```txt
v3.6.9 CLOSED.
BACKLOG #45 closed.
Wave 2A is complete.
```

v3.6.9 implemented Route A: Menu Consumer Closure, Provider Unchanged. The
cycle added a Menu lab module that consumes the existing `popover/` and
`ripple/` providers without adding `lab-menu.js` and without editing provider,
baseline, WordPress, Pilot, validator, or prior Wave 2A module files.

## Cycle Summary

Phase 0 selected BACKLOG #45 as the next cycle:

```txt
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-0-PLAN.md
```

Phase 1 selected Route A:

```txt
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-1-REPORT.md
```

Route A meant:

```txt
Menu Consumer Closure, Provider Unchanged
```

Phase 2 implemented:

```txt
products/reference-implementations/axismundi-lab/modules/menu/lab-menu.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-RUNTIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-WP-MAPPING.md
```

Phase 3 verified:

```txt
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-3-VISUAL-QA.md
```

## Close Evidence

Visual matrix:

| Cell | Console errors | Overflow X | Body background | Body color | Triggers / wired / static / total |
|---|---:|---:|---|---|---|
| desktop/light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | 3 / 3 / 1 / 4 |
| desktop/dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | 3 / 3 / 1 / 4 |
| mobile/light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | 3 / 3 / 1 / 4 |
| mobile/dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | 3 / 3 / 1 / 4 |

Interaction evidence:

```txt
trigger click -> aria-expanded=true, .is-open=true
first enabled item focus -> New document
ArrowDown -> Open
ArrowDown -> Autosave on
ArrowDown skips disabled -> Delete draft
ArrowUp -> Autosave on
End -> Delete draft
Home -> New document
Escape -> close + focus restore
outside pointerdown -> close
item click -> close + focus restore
```

Forbidden-ancestor evidence:

```txt
trigger inside .prose
aria-expanded: false
.is-open:      false
visibility:    hidden
opacity:       0
console:       0
```

Ripple evidence:

```txt
enabled data-ax-ripple hosts:    10
disabled item hosts:              2
disabled data-ax-ripple hosts:    0
.ax-ripple nodes after click:     1
```

Static / live separation:

```txt
popover-wired surfaces: 3
static open surfaces:   1
```

Submenu:

```txt
interactive submenu triggers: 0
submenu note visible:         true
```

## BACKLOG Changes

BACKLOG #45 is closed by v3.6.9.

The close summary records:

```txt
Menu lab module added
popover/ provider unchanged
ripple/ provider unchanged
icon-system/ provider unchanged
components.css unchanged
lab-menu.js not added
enabled-only bounded ripple
forbidden-ancestor negative probe PASS
keyboard interaction matrix PASS
```

BACKLOG #47 is added for future hygiene:

```txt
popover provider menu-item-class logic extraction hygiene
```

Scope:

```txt
lab-popover.js menu-item selectors and keyboard behavior
lab-popover.css §3 .ax-menu__item:focus-visible outline override
```

This is a low-priority future hygiene item. It does not reopen v3.6.9.

BACKLOG #41, #44, and #46 remain open and unchanged in status.

## Locks

Lock 1:

```txt
Preserved. Axis G remains 1.000. No wp-custom or WordPress source change.
```

Lock 2:

```txt
Preserved. Axis E remains 1.000. No md-sys/md-ref token mapping change.
```

Lock 3:

```txt
Preserved. core/button semantic routing was not reopened.
```

Lock 4:

```txt
Preserved. Menu/popover was handled as DISTINCT but COUPLED. The provider was
not changed and Menu did not reimplement anchored positioning or dismissal.
```

Lock 5:

```txt
Do not promote diagnostic-first to Lock 5 in v3.6.9.
```

Reason:

Diagnostic-first has now succeeded across five cycles, including two
component-lab cycles and this direct Menu/popover provider-consumer boundary.
That is strong evidence. It is still a methodology finding rather than a lock:
Wave 2B Form or BACKLOG #21 plugin strategy should prove the pattern in a
distinct input/plugin domain before AGENTS.md / CLAUDE.md gain a new lock.

AGENTS.md and CLAUDE.md remain unchanged.

## Validation

```txt
wp-env run cli wp core version                         PASS - 7.0
python tools/generators/build_pilot_specimen_wall.py   PASS - pages 29 and 41 updated
npm run validate:specimen-wall                         PASS
php -l products/reference-implementations/axismundi-pilot/functions.php
                                                        PASS
npm test                                                PASS - overall 1.000
  Axis A schema                                         1.000
  Axis B theme                                          1.000
  Axis C css                                            1.000
  Axis D runtime                                        1.000
  Axis E tokens                                         1.000
  Axis F bridge                                         1.000
  Axis G custom                                         1.000
npm run validate:computed                              PASS
npm run publish:styleguide                             PASS
git diff --check                                       PASS
```

Generated validator reports and publish mirror files were restored after
validation in Phase 2 and Phase 3. Phase 5 is state-docs only.

## Test Target Convention

Phase 3 used a repository-root localhost server:

```txt
http://127.0.0.1:<port>/products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
```

This avoids `file://` automation policy blocks and preserves repository-root
self-hosted font and Material Symbols paths. Future module QA should prefer
this repo-root server convention unless the cycle has a more specific test
target.

## Files Changed In Phase 5

Phase 5 changes only:

```txt
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

Implementation files remain unchanged in Phase 5.

## Next

Recommended next cycle:

```txt
Wave 2B Form plan-first
```

Alternatives:

```txt
BACKLOG #21 Interpreter Plugin strategy
BACKLOG #41 shared WordPress ripple runtime packaging decision
BACKLOG #44 remaining specimen coverage follow-ons
BACKLOG #46 disabled ripple host authoring hygiene
BACKLOG #47 popover provider menu-item-class logic extraction hygiene
```
