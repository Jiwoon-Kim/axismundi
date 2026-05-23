# v3.6.15 VS Code Diagnostics Sweep - Phase 0 Plan

Date: 2026-05-23

## Purpose

Run a post-component-modularization diagnostics sweep after v3.6.14 brought the
component matrix to DONE 31 / PARTIAL 0 / TODO 0 / RECORD 3.

This cycle is diagnostic-only unless Phase 1 finds a narrow, mechanical P1/P2
issue that can be fixed without touching protected architecture surfaces.

## Source Inputs

Reading order:

```txt
1. AGENTS.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. NEXT-SESSION.md §0 / §1 / §2
7. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-5-CLOSE.md
8. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-3-VISUAL-QA.md
```

Resume checklist input:

```txt
Docker-dependent validation retry:
  python tools/generators/build_pilot_specimen_wall.py
  npm run validate:specimen-wall
  npm run validate:computed
```

## Diagnostic Surface

Repository configuration inventory:

```txt
.vscode/                absent
tsconfig/jsconfig       absent
ESLint config           absent
Stylelint config        absent
pyproject.toml          absent
composer/phpcs config   absent
```

Because no VS Code workspace diagnostics configuration exists in-repo, the
sweep approximates VS Code Problems panel coverage with language parsers and
project validators:

```txt
JavaScript syntax:      node --check on repo JS files outside node_modules/styleguide
PHP syntax:             php -l on repo PHP files
Python syntax:          python -m compileall -q tools
JSON syntax:            Node JSON.parse on repo JSON files outside node_modules
Project validator:      npm test
Published mirror:       npm run publish:styleguide, then restore generated mirror
WP specimen validator:  retry only if Docker/wp-env is available
Whitespace:             git diff --check
Git state:              git status --short --branch
```

## Scope Clarification

User correction after the initial Phase 1 pass:

```txt
Original intent:
  Open lab module HTML/CSS/JS files in VS Code and inspect the Problems panel
  with Ctrl+Shift+M. The expected source is VS Code / extension diagnostics,
  including Microsoft Edge Tools, built-in CSS language service, webhint, and
  axe-style HTML diagnostics.

Initial Codex interpretation:
  Repo-level lint/config inventory plus reproducible parser/validator sweep.

Corrected scope:
  Primary evidence must be the VS Code Problems panel diagnostics supplied by
  the user. Parser/validator results remain supporting evidence only.
```

Primary target set:

```txt
products/reference-implementations/axismundi-lab/modules/**/*.html
products/reference-implementations/axismundi-lab/modules/**/*.css
products/reference-implementations/axismundi-lab/modules/**/*.js
```

Priority slice:

```txt
v3.6.14 Wave 3 new modules:
  modules/slider/lab-slider-pattern.html
  modules/slider/lab-slider.css
  modules/slider/lab-slider.js
  modules/loading/lab-loading-pattern.html
  modules/loading/lab-loading.css
  modules/progress/lab-progress-pattern.html
  modules/progress/lab-progress.css
```

Evidence model:

```txt
Primary Evidence:    VS Code Problems panel, user-captured
Supporting Evidence: parser/validator sweep, Codex-runnable
```

## Phase Cadence

```txt
Phase 0: scope / method definition
Phase 1: diagnostics inventory and findings
Phase 2: only if Phase 1 finds a narrow fix
Phase 3: only if Phase 2 changes user-visible surfaces
Phase 5: close docs if Phase 1 is clean or after any approved fix
```

Phase 4 is intentionally unused in this v3.6.x cadence.

## Fences

Expected write scope before review:

```txt
docs/v3.6.15/
```

Files not expected to change during the diagnostic sweep:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/scripts/
products/reference-implementations/axismundi-lab/modules/
products/reference-implementations/axismundi-pilot/
bindings/wordpress-material3/
tools/generators/
tools/validators/
styleguide/          generated mirror; may be regenerated then restored
```

3-tracked-copy impact:

```txt
None expected. No token CSS, Pilot bridge CSS, or styleguide token mirror edits
are planned.
```

## Lock Impact

```txt
Lock 1 Axis G: verify with npm test; no theme.json edits planned.
Lock 2 Axis E: verify with npm test; no md-sys token edits planned.
Lock 3 core/button: no WordPress bridge implementation planned.
Lock 4 semantic row routing: no component row changes planned.
Lock 5 diagnostic-first: active; this cycle is diagnostic-only by design.
```

## Non-Goals

```txt
No Pilot revision.
No BACKLOG #21 / #41 / #44 / #46 / #47 implementation.
No styleguide integration for Slider / Loading / Progress.
No baseline CSS mutation.
No provider mutation.
No generated mirror commit unless a separate approved cycle chooses it.
```

## Review Gate

Phase 1 should report:

```txt
P1/P2/P3 findings, if any.
GO / NO-GO / APPROVE WITH NOTES recommendation.
Whether a Phase 2 fix cycle is needed.
Whether Docker-dependent validation remains blocked.
```
