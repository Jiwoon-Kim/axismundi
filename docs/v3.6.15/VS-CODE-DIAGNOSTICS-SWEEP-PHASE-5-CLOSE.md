# v3.6.15 VS Code Diagnostics Sweep - Phase 5 Close

Date: 2026-05-23

## Release

```txt
Release: v3.6.15 VS Code Diagnostics Sweep
Status:  CLOSED
Route:   Diagnostic-only cycle with corrected VS Code Problems panel scope
Cadence: Phase 0 / Phase 1 / Phase 5
```

v3.6.15 closes as a diagnostic-only cycle. Phase 2, Phase 3, and Phase 4 were
intentionally unused because the corrected Phase 1 review chose forward-routing
instead of in-cycle implementation.

This cadence is a deliberate scope contraction: Phase 1 found actionable
diagnostics, but those diagnostics span existing lab module semantics and need
a fresh plan-first fix cycle.

## Scope Correction

The initial Codex pass treated "VS Code diagnostics" as repo-level parser and
validator checks. User correction clarified the intended surface:

```txt
Open lab module HTML/CSS/JS files in VS Code and inspect Ctrl+Shift+M Problems.
```

The Phase 0 / Phase 1 docs were amended in-place:

```txt
Primary Evidence:    user-captured VS Code Problems panel diagnostics
Supporting Evidence: Codex-runnable parser / validator sweep
```

## Primary Evidence

User-captured VS Code Problems panel diagnostics covered the lab module surface.

Wave 3 priority slice:

```txt
slider/loading/progress source errors: 0
slider/loading/progress warnings:      9 no-inline-styles warnings
```

The Wave 3 warnings are from the shared module-page inline critical style
pattern. They are not v3.6.14 regressions.

Severity 8 diagnostics surfaced outside the Wave 3 priority slice:

```txt
P2 forward-routed:
  date-time/lab-date-time.css:22-31
    CSS nested comment marker confuses VS Code CSS parser.

  menu/lab-menu-pattern.html:77
    aria-selected is invalid on role=menuitem.

  nav-bar/lab-nav-bar-pattern.html:81
    aria-selected is invalid on a plain button nav item.

  ripple/lab-ripple-pattern.html:190
    standalone role=menuitem lacks required menu/menubar/group parent.

P3 routed:
  button-group inline-size:fit-content Samsung Internet fallback hint.
  button user-select Safari prefix hint.
  broad Microsoft Edge Tools / webhint compat and no-inline-styles warnings.
```

## Supporting Evidence

Codex-runnable parser / validator sweep:

```txt
JavaScript syntax: 25 / 25 PASS
PHP syntax:         8 / 8 PASS
Python compile:     PASS
JSON parse:        50 / 50 PASS
npm test:           PASS - Axis A/B/C/D/E/F/G all 1.000
npm run publish:styleguide: PASS, generated mirror restored
git diff --check:  PASS
```

Docker-dependent validation debt from v3.6.14 was resolved after desktop reboot
and Docker Desktop launch:

```txt
npx wp-env start: PASS
python tools/generators/build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
npm run validate:computed: PASS
```

Local wp-env fixture setup performed during diagnostics:

```txt
wp core install
wp theme activate axismundi-pilot
Pattern QA fixture restored at page_id=10
Specimen wall pages created at page 13 and page 14
```

The WordPress database fixture is local runtime state only and is not committed.

## Fences

No source implementation files were changed.

Generated artifacts were restored / removed:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
products/reference-implementations/axismundi-pilot/assets/
styleguide/
tools/**/__pycache__/
```

Write scope retained:

```txt
docs/v3.6.15/
CHANGELOG.md
CURRENT-STATE.md
ROADMAP.md
NEXT-SESSION.md
BACKLOG.md
```

3-tracked-copy impact:

```txt
None. Token CSS, Pilot bridge CSS, and styleguide mirror token copies were not
committed.
```

## Routed Forward

Primary next candidate:

```txt
v3.6.16 Lab Module A11y Diagnostics Fix Sweep
```

Forward-routed P2 items:

```txt
1. date-time/lab-date-time.css:22-31
   CSS nested comment hygiene. Mechanical fix candidate.

2. menu/lab-menu-pattern.html:77
   Replace invalid aria-selected on role=menuitem with an appropriate selected
   menu semantics decision, likely aria-checked or visual-only state.

3. nav-bar/lab-nav-bar-pattern.html:81
   Replace invalid aria-selected on a plain button with an explicit navigation
   semantics decision, likely aria-current or a tab model.

4. ripple/lab-ripple-pattern.html:190
   Fix standalone role=menuitem by adding a valid parent context or changing
   the specimen role.
```

Additional routed policy questions:

```txt
VS Code workspace diagnostics configuration: low-priority separate cycle.
Microsoft Edge Tools / webhint normative policy for lab module pages.
no-inline-styles policy for self-contained pattern-page critical styles.
compat-api/css broad signal policy for color-mix, scrollbar-width, and prefix hints.
```

## Lock 5

v3.6.15 counts as Lock 5 fifth clean self-application - diagnostic-only
variant:

```txt
v3.6.11  Dialog / Sheet       clean implementation cycle
v3.6.12  DateTime             clean implementation cycle
v3.6.13  Actions Consumers    clean implementation cycle
v3.6.14  Wave 3 Closure       clean implementation cycle
v3.6.15  Diagnostics Sweep    clean diagnostic-only cycle
```

No safe-shortcut exception was used. Scope mismatch was halted, amended
in-place, reviewed, and then forward-routed before close.
