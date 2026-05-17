# v3.5.15 GitHub Repository + Pages Publish — Phase 1 Report

> Status: CLOSED — v3.5.15 Phase 5  
> Date: 2026-05-17  
> Cycle: v3.5.15 GitHub repository + Pages publish  
> Scope: local preflight + final hardcoded path audit  
> Non-scope: git init, directory rename, GitHub repo creation, Pages activation

---

## 0. Framing

Phase 1 checks whether the repository is locally ready for the first GitHub
publish cycle.

Result:

```txt
READY WITH GATES
```

The local tree is valid and publish-prepared. The remaining gates are external
or user-approved actions:

1. directory rename approval,
2. GitHub repository creation route,
3. GitHub Pages source selection.

---

## 1. Validation Preflight

Commands run:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
python .\tools\generators\publish_styleguide.py
```

Results:

```txt
Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:  PASS
Publish:   PASS
```

Publish mirror result:

```txt
Source: products\reference-implementations\axismundi-lab
Target: styleguide

stylesheets/: 23 files, paths rewritten
scripts/style-guide.js copied
style-guide.html -> index.html
style-guide-blocks.html -> blocks.html
style-guide-prose.html -> prose.html
README.md publish-mirror notice copied

Total in publish surface: 30 files
```

---

## 2. Baseline Snapshot

Current baseline / pilot file snapshot:

| File | Last write | Size |
|---|---:|---:|
| `products/reference-implementations/axismundi-lab/stylesheets/components.css` | 2026-05-17 20:00:38 | 216465 |
| `products/reference-implementations/axismundi-lab/stylesheets/tokens.css` | 2026-05-17 18:00:59 | 41222 |
| `products/reference-implementations/axismundi-lab/style-guide.html` | 2026-05-16 14:40:02 | 182947 |
| `products/reference-implementations/axismundi-lab/stylesheets/blocks.css` | 2026-05-16 14:40:03 | 18635 |
| `products/reference-implementations/ontology-theme-pilot/theme.json` | 2026-05-16 14:40:02 | 12370 |

Note:

```txt
There is no theme.json under axismundi-lab/. The active pilot theme.json is
products/reference-implementations/ontology-theme-pilot/theme.json.
```

---

## 3. Publish File Presence

Required publish-prep files exist:

| File | Exists |
|---|---:|
| `index.html` | yes |
| `README.md` | yes |
| `README.ko.md` | yes |
| `LICENSE` | yes |
| `LICENSE-CC-BY-SA-4.0.md` | yes |
| `LICENSE-MATRIX.md` | yes |
| `NOTICE.md` | yes |
| `package.json` | yes |
| `.gitignore` | yes |
| `.github/workflows/validator.yml` | yes |
| `styleguide/index.html` | yes |

Author metadata remains aligned:

```txt
KIM JIWOON (designbusan.ai.kr) — Busan, Korea
```

No `Seoul`, `서울`, or `KIM Ji-woon` public metadata string remains in:

```txt
README.md
README.ko.md
AUTHORSHIP.md
package.json
index.html
```

---

## 4. Git State

Current git state:

```txt
Test-Path .git -> False
```

v3.5.15 remains the first git initialization cycle.

Recommended commit strategy remains:

```txt
git init
git add .
git status --short
git commit -m "Initial Axismundi public release"
```

No synthetic historical commit reconstruction.

---

## 5. Hardcoded Path Audit

Search patterns:

```txt
phase0-handoff
axismundi-v3.5.1-phase0-handoff
/home/claude/
C:\Users\thaum\dev\
file:///
computer://
```

Executed with:

```powershell
rg -n "phase0-handoff|axismundi-v3\.5\.1-phase0-handoff|/home/claude/|C:\\Users\\thaum\\dev\\|file:///|computer://" . --glob "!node_modules/**" --glob "!styleguide/**"
```

### 5.1 Active Public Surface

No active stale path was found in:

```txt
README.md
README.ko.md
index.html
package.json
LICENSE-MATRIX.md
NOTICE.md
.github/workflows/validator.yml
tools/generators/publish_styleguide.py
products/reference-implementations/ontology-theme-pilot/README.md
```

These are publish-facing or command-facing surfaces. They are clean for v3.5.15.

### 5.2 Allowed Current-Path Handoff Mentions

The current working path appears in volatile handoff/state docs:

```txt
CURRENT-STATE.md
NEXT-SESSION.md
docs/v3.5.15/GITHUB-PUBLISH-PHASE-0-PLAN.md
```

Disposition:

```txt
Allowed before directory rename.
Update naturally if the user approves rename and the rename executes.
```

These are not public installation instructions; they are session-local state
records.

### 5.3 Allowed Phase-Plan / Historical Mentions

The path grep terms appear in v3.5.14 / v3.5.15 planning docs because those docs
recorded the path audit itself:

```txt
docs/v3.5.14/PUBLISH-PREP-PHASE-0-PLAN.md
docs/v3.5.14/PUBLISH-PREP-PHASE-0-REPORT.md
docs/v3.5.14/PUBLISH-PREP-PHASE-1-PLAN.md
docs/v3.5.14/PUBLISH-PREP-PHASE-2-PLAN.md
docs/v3.5.15/GITHUB-PUBLISH-PHASE-0-PLAN.md
```

Disposition:

```txt
Allowed historical/audit record.
Do not rewrite because these docs explain the transition.
```

Older release notes also mention `/home/claude` as historical cleanup context:

```txt
CHANGELOG.md
docs/v3.5.1/BUTTON-PHASE-2-PLAN.md
```

Disposition:

```txt
Allowed historical record.
```

### 5.4 Allowed Provenance Data

Remaining `/home/claude/...` references exist in provenance / ontology data:

```txt
bindings/wordpress-material3/legitimacy_audit.json
bindings/wordpress-material3/gap_report.md
bindings/wordpress-material3/block_component_rules.json
core/wordpress/validation_sources.json
core/wordpress/pilots/*.json
core/design-systems/material3/token_ontology.jsonld
```

Disposition:

```txt
Allowed provenance paths.
They identify source workspaces or frozen upstream extraction contexts.
Do not normalize during v3.5.15 because changing them would alter data
provenance semantics.
```

### 5.5 Ignored Dependency Mentions

`node_modules/` contains `file:///` examples inside Playwright internals. The
audit excludes `node_modules/**`, and `.gitignore` excludes `node_modules/`.

Disposition:

```txt
Ignored dependency implementation detail.
Not committed if .gitignore is respected.
```

---

## 6. Pages Source Recommendation Reconfirmed

Recommended Pages source:

```txt
main branch root
```

Reason:

- root `index.html` is current and links to `/styleguide/`,
- `/styleguide/` is a generated mirror, not the entire public story,
- README, LICENSE, NOTICE, and lab/module source docs should remain browsable,
- BACKLOG #34 / v3.5.16 modernization will refine the lab/module navigation
  story without requiring a different Pages source.

---

## 7. Go / No-Go

```txt
Local publish preflight: PASS
Path audit:              PASS with allowed provenance/handoff residues
Git state:               ready for first git init
Directory rename:        blocked on user GO
GitHub repo creation:    blocked on user route/auth choice
GitHub Pages:            blocked on repo creation + user source confirmation
```

Verdict:

```txt
READY WITH GATES
```

---

## 8. Phase 2 Entry Conditions

Before Phase 2 execution, user must decide:

1. Execute local directory rename now, or keep current folder for first push?
2. Create GitHub repo via web UI, `gh` CLI, or connector?
3. Confirm Pages source: `main` branch root.

Recommended route:

```txt
1. Rename local directory to C:\Users\thaum\dev\axismundi.
2. Create GitHub repo `axismundi`.
3. Use HTTPS remote URL supplied from GitHub UI unless SSH is already configured.
4. Use Pages source: main branch root.
```
