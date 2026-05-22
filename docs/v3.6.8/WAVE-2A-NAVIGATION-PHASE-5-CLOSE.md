# v3.6.8 - Wave 2A Navigation Core - Phase 5 Close

Date: 2026-05-22

Phase: 5 - Close

## Verdict

v3.6.8 is closed as the Wave 2A Navigation Core cycle.

The cycle implements Route B:

```txt
App bar:  lab-scoped static module added
Nav bar:  lab-scoped module added with bounded ripple consumers
Nav rail: lab-scoped module added with bounded ripple consumers
Tabs:     lab-scoped module added with local keyboard runtime
Menu:     deferred to Wave 2A-2
```

No baseline `components.css` edits were made. No provider modules were changed.

## Commits

```txt
af07725  Add v3.6.8 Wave 2A navigation plan
e6347f7  Document v3.6.8 Wave 2A navigation inventory
b8ee38b  Implement v3.6.8 Wave 2A navigation core
dc7a4b1  Document v3.6.8 Wave 2A navigation QA
```

## Documents

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-0-PLAN.md
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
```

## Closed In Wave 2A

The following Component Coverage Map rows now have lab module coverage:

```txt
Component-Coverage #11 App bar
Component-Coverage #12 Nav bar
Component-Coverage #13 Nav rail
Component-Coverage #14 Tabs
```

Index note: `#N` here means the Component Coverage Map TOC index from
`docs/v3.5.0/COMPONENT-COVERAGE-MAP.md`, not BACKLOG.md item numbers.

## Added Lab Modules

```txt
products/reference-implementations/axismundi-lab/modules/app-bar/
products/reference-implementations/axismundi-lab/modules/nav-bar/
products/reference-implementations/axismundi-lab/modules/nav-rail/
products/reference-implementations/axismundi-lab/modules/tabs/
```

Each module contains a pattern page and audit docs. Tabs additionally contains
`lab-tabs.js` for component-local interaction.

## Phase 3 Evidence

Visual matrix:

```txt
4 modules x 2 viewports x 2 themes = 16 cells
console/page errors: 0 in all cells
horizontal overflow: 0 in all cells
```

Focus evidence:

```txt
light focus outline: 2px solid rgb(98, 91, 113)
dark focus outline:  2px solid rgb(204, 194, 220)
```

Theme token smoke:

```txt
light body background/color: rgb(254, 247, 255) / rgb(29, 27, 32)
dark body background/color:  rgb(20, 18, 24) / rgb(230, 224, 233)
```

Ripple evidence:

```txt
App bar:  window.axRipple undefined / 0 hosts / 0 ripples created
Nav bar:  window.axRipple object / 7 DOM hosts / 1 bounded ripple created
Nav rail: window.axRipple object / 6 DOM hosts / 1 bounded ripple created
Tabs:     window.axRipple object / 8 DOM hosts / 1 bounded ripple created
```

Nav bar source contains eight `data-ax-ripple` text occurrences because one is
an explanatory `<code>` sample in the validation banner. Actual DOM hosts: 7.

Tabs interaction evidence:

```txt
click enabled tab:             PASS
ArrowLeft / ArrowRight:        PASS
Home / End:                    PASS
disabled tab skip:             PASS
panel hidden/show toggling:    PASS
per-tabset roving state:       PASS
```

## Routed Forward

Wave 2A-2 Menu is now tracked as BACKLOG #45.

Reason:

```txt
Menu owns role=menu/menuitem, density, icon, shortcut, selected, disabled,
divider, and submenu semantics.

popover/ owns anchor, position, dismiss, outside-click, Escape, focus restore,
and viewport collision mechanics.
```

Disabled ripple host authoring hygiene is tracked as BACKLOG #46. v3.6.8
verified that disabled hosts do not create ripples, but a future hygiene cycle
should decide whether disabled hosts should omit `data-ax-ripple` or whether
the provider's disabled-host tolerance should be documented as an explicit
contract.

BACKLOG #41 remains unchanged:

```txt
Open - narrowed by v3.6.6 to shared WordPress ripple runtime packaging decision
```

BACKLOG #44 remains unchanged:

```txt
Open - narrowed by v3.6.7 to mark/highlight, long-line code, deep pullquote,
Material Symbols follow-on coverage, and validator hardening polish
```

## Lock Compliance

```txt
Lock 1 - wp-custom downstream-only:
  preserved; Axis G remains 1.000.

Lock 2 - md-sys color maps to md-ref:
  preserved; Axis E remains 1.000.

Lock 3 - core/button semantic route before visual cleanup:
  preserved; WordPress core/button routing was not reopened.

Lock 4 - semantic mismatch handling rule:
  preserved; Menu/popover coupling was routed to Wave 2A-2, not collapsed.
```

## Methodology Finding

Diagnostic-first Phase 1 worked again in v3.6.8, now outside the WP block
bridge / specimen wall domain.

Decision:

```txt
Do not promote this to Lock 5 in v3.6.8.
```

Reason:

```txt
v3.6.8 is the first component-lab domain proof after three WP bridge/specimen
cycles. Keep the methodology available, but reconsider Lock 5 only after it
also proves itself in Wave 2A-2 Menu, Wave 2B Form, BACKLOG #21 plugin
strategy, or another distinct domain.
```

`AGENTS.md` and `CLAUDE.md` remain unchanged.

## Non-Goals Confirmed

```txt
AGENTS.md / CLAUDE.md edit:       no
theme.json edit:                  no
functions.php edit:               no
pilot bridge edit:                no
Pilot fixture edit:               no
components.css edit:              no
blocks.css edit:                  no
popover provider edit:            no
ripple provider edit:             no
icon-system provider edit:        no
styleguide manual edit:           no
validate_theme_pilot.py edit:     no
Menu module implementation:       no
```

## Validation

Final close validation:

```txt
wp-env run cli wp core version: 7.0
python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS (Axis A/B/C/D/E/F/G all 1.000)
npm run validate:computed: PASS
npm run publish:styleguide: PASS, then generated mirror restored
git diff --check: PASS
```

## Next Route

Recommended primary next route:

```txt
Wave 2A-2 Menu
```

Other viable routes:

```txt
Wave 2B Form
BACKLOG #21 Interpreter Plugin strategy
BACKLOG #41 shared WordPress ripple runtime packaging decision
BACKLOG #44 remaining specimen coverage / validator polish
BACKLOG #46 disabled ripple host authoring hygiene
```

The next cycle must remain plan-first.
