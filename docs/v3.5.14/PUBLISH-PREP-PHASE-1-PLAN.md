# v3.5.14 Publish Prep — Phase 1 Plan

> Status: CLOSED — v3.5.14 Phase 5  
> Date: 2026-05-17  
> Cycle: v3.5.14 publish prep  
> Scope: implementation-ready plan for public repository readiness  
> Non-scope: GitHub repository creation, Pages activation, directory rename execution

---

## 0. Phase Framing

Phase 1 converts the Phase 0 report into lane-level deliverables and execution
order. It still does not implement the README rewrite, license edits, Actions
workflow, path remediation, or template scaffolding.

This is a plan-first checkpoint before v3.5.14 starts editing public-facing
files.

Phase 1 lock:

```txt
Eight lanes stay in one v3.5.14 release.
Implementation order is publish-blocking first, narrative last.
```

Recommended lane order:

```txt
H → E → G → C → D → A → B → F
```

Reason:

- License and path drift can invalidate public release regardless of README
  quality.
- Publish mirror and artifact policy inform README instructions.
- README.ko should follow the approved README.md structure.
- Templates/page-layout can be documented after source/publish route decisions
  are stable.

---

## 1. Lane Dependency Map

| Lane | Name | Depends On | Blocks |
| --- | --- | --- | --- |
| H | License / NOTICE / root LICENSE | User license decision | Public push |
| E | Hardcoded path remediation | Lane H metadata decisions partly | Directory rename |
| G | Publish mirror check | Lane E path safety | README publish instructions |
| C | `.gitignore` / artifact policy | Lane H validator evidence policy | Git hygiene |
| D | GitHub Actions | Lane C artifact policy | Public CI |
| A | README.md | H/E/G/C/D decisions | Public repo narrative |
| B | README.ko.md | A outline | Korean public narrative |
| F | Templates/page-layout category | G publish route decision + A narrative | Future publish surface |

Lane F may remain documentation-only if v3.5.14 chooses not to scaffold
`templates/`.

---

## 2. Lane H — License / NOTICE / Root LICENSE

### 2.1 Deliverables

Phase 2 should edit or create:

| File | Action |
| --- | --- |
| `LICENSE-MATRIX.md` | Fix asset paths; resolve or explicitly mark remaining TBDs; record package metadata policy. |
| `NOTICE.md` | Fix asset paths from old `products/.../assets` to `core/design-systems/material3/assets`. |
| `LICENSE` | Create if user confirms root license. Recommended: GPL-3.0-or-later. |
| `package.json` | Update `name`; update `license` away from stale `ISC`. |
| `package-lock.json` | Sync package name/license metadata. |

### 2.2 Lock Decisions

Phase 2 must not guess silently. It must lock:

1. Root project license:
   - Recommended: `GPL-3.0-or-later`.
   - Reason: current `LICENSE-MATRIX.md` intent; Apache-2.0 Material Symbols
     compatibility; WordPress theme direction.
2. Tools license:
   - Option A: inherit GPL-3.0-or-later for simplicity.
   - Option B: MIT for tools only, as currently proposed.
   - Recommended for v3.5.14: leave matrix explicit if dual-license is intended;
     otherwise use single-license GPL to avoid public ambiguity.
3. Ontology / bindings / governance docs license:
   - Current proposed: CC-BY-4.0.
   - Phase 2 can preserve this as proposed if not finalizing every subdomain.
4. `package.json` license:
   - If root `LICENSE` is created as GPL-3.0-or-later, set package metadata to
     `GPL-3.0-or-later`.
   - If root license remains undecided, set package metadata to `UNLICENSED` and
     mark publish blocked.

### 2.3 Known P1 Findings

1. Stale asset paths:

```txt
OLD:
products/reference-implementations/axismundi-lab/assets/...

CURRENT:
core/design-systems/material3/assets/...
```

2. Missing root `LICENSE`.

3. `package.json` license is currently `ISC`, conflicting with the license
   matrix intent.

4. Beer CSS provenance note still says code audit pending.

### 2.4 Beer CSS Provenance Check

Phase 2 should add a concise status note rather than opening a large audit:

- v3.5.6 Ripple v2 rewrote the ripple contract around M3/Data API.
- v3.5.12 Carousel runtime is documented in 4-doc audit form.
- Popover/search/snackbar runtime contracts are documented in module audits.
- No public release should claim "no code copied" without either:
  - preserving the pending-audit caveat, or
  - completing a targeted provenance note.

Recommended v3.5.14 lock:

```txt
Keep the Beer CSS attribution and replace "pending before public release" only
if Phase 2 performs a targeted provenance review. Otherwise keep it as a known
pre-v3.5.15 gate.
```

---

## 3. Lane E — Hardcoded Path Remediation

### 3.1 Deliverables

Phase 2 should edit:

| File | Action |
| --- | --- |
| `package.json` | `name: "axismundi"` |
| `package-lock.json` | root package name metadata sync |
| `products/reference-implementations/ontology-theme-pilot/README.md` | Replace old `/home/claude/...validate...` command with current validator command. |
| `CURRENT-STATE.md` / `NEXT-SESSION.md` | Phase 5 only, after actual v3.5.14 close. |

Phase 2 should classify but probably not edit:

| File family | Reason |
| --- | --- |
| `core/wordpress/pilots/*.json` | Source provenance snapshots, not active executable paths. |
| `core/design-systems/material3/token_ontology.jsonld` old source paths | Provenance metadata; changing may alter data semantics. |
| `bindings/wordpress-material3/block_component_rules.json` old source path | Provenance metadata; classify before edit. |
| historical `docs/v3.5.x` / `CHANGELOG.md` path mentions | Historical record. |

### 3.2 Search Patterns

Phase 2 must run:

```powershell
rg -n "axismundi-v3\.5\.1-phase0-handoff|phase0-handoff|C:\\Users\\thaum\\dev|/home/claude|/mnt/c" -S . --glob "!node_modules/**" --glob "!styleguide/**"
```

Then classify hits as:

- active executable,
- public-facing stale instruction,
- package metadata,
- source provenance,
- historical release record.

### 3.3 Directory Rename Gate

Directory rename is not automatic in Phase 2.

Phase 5 may recommend:

```txt
C:\Users\thaum\dev\axismundi-v3.5.1-phase0-handoff
→ C:\Users\thaum\dev\axismundi
```

Only if:

- package metadata is clean,
- public README no longer mentions the temporary directory,
- active tool commands are repo-relative,
- user approves the actual filesystem move.

---

## 4. Lane G — Publish Mirror Check

### 4.1 Deliverables

Phase 2 should run:

```powershell
python .\tools\generators\publish_styleguide.py
```

And verify:

- `styleguide/index.html` exists.
- `styleguide/blocks.html` exists.
- `styleguide/prose.html` exists.
- `styleguide/stylesheets/` includes design-system CSS.
- `styleguide/stylesheets/` includes `lab-*.css` module CSS.
- No module JS is copied.
- No module pattern HTML is copied.
- `styleguide/README.md` explains mirror status.

### 4.2 Possible Text Fix

`publish_styleguide.py` currently says:

```txt
Edit the source in the RC theme
```

Recommended Phase 2 edit:

```txt
Edit the source in the Axismundi lab
```

Reason: public repo language should not call the current lab source an RC theme.

### 4.3 Templates Route

Do not extend `publish_styleguide.py` for `/templates/` in Phase 2 unless Lane F
is explicitly approved for scaffolding.

---

## 5. Lane C — `.gitignore` / Artifact Policy

### 5.1 Deliverables

Phase 2 should update `.gitignore`.

Recommended entries:

```gitignore
node_modules/
test-results/
playwright-report/
.playwright/
docs/**/*-qa.png
docs/**/qa-*.png
*.tmp
.DS_Store
Thumbs.db
```

Optional editor entries:

```gitignore
.vscode/
.idea/
```

Phase 2 should decide whether to include editor ignores. Recommended: include
them because public repo hygiene is the goal and no project settings are
currently intended to be shared.

### 5.2 Validator Outputs

Do not ignore:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

Lock:

```txt
Tracked validation evidence.
```

---

## 6. Lane D — GitHub Actions

### 6.1 Deliverable

Phase 2 should create:

```txt
.github/workflows/validate.yml
```

Workflow scope:

- validator-only,
- no Playwright browser install,
- no Pages deploy,
- no scheduled run.

Recommended triggers:

```yaml
on:
  pull_request:
  push:
    branches: [main]
  workflow_dispatch:
```

Exact action versions should be verified during Phase 2 before writing the
workflow. If version verification is skipped, use conservative maintained
versions and record the choice.

### 6.2 Workflow Command

The workflow should run from repo root:

```bash
python tools/validators/validate_theme_pilot.py
```

Phase 3 should compare CI command with local command:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

---

## 7. Lane A — README.md

### 7.1 Deliverable

Phase 2 should rewrite root `README.md`.

Required outline:

```txt
1. Axismundi
2. What this repository is
3. Current status
   - Wave 1 complete
   - v3.5.14 publish prep
4. Architecture
   - 6 layers
   - 4 tiers
   - publishing surfaces are mirrors
5. Public surfaces
   - index.html
   - styleguide/
   - future templates/
6. Quick start
   - install npm dependencies if needed
   - validator
   - publish styleguide
7. Repository map
8. WordPress binding and pilot
9. Stability notes
10. License and attribution
```

### 7.2 Tone Locks

README must:

- avoid overclaiming production readiness,
- state that Wave 1 component public surface is complete,
- state that GitHub repo/Pages publish is in prep,
- name WordPress as the current binding, not the only possible future binding,
- explain `styleguide/` as derived publish mirror,
- explain `templates/` as planned composition/template preview category.

---

## 8. Lane B — README.ko.md

### 8.1 Deliverable

Phase 2 should create:

```txt
README.ko.md
```

### 8.2 Authorship Lock

README.ko.md is a Korean-native companion, not a strict translation.

Required sections:

```txt
1. Axismundi란 무엇인가
2. 현재 상태
3. 구조
4. 실행 방법
5. 공개 표면
6. WordPress / 블록 테마 / 플러그인 경계
7. 라이선스
```

It should be concise enough for a Korean developer to orient quickly.

---

## 9. Lane F — Templates / Page-Layout Category

### 9.1 Deliverable

Phase 2 should not implement high-fidelity templates by default.

It may create a short planning note if needed:

```txt
docs/v3.5.14/TEMPLATES-PUBLISH-CATEGORY-NOTE.md
```

Or append the decision to the Phase 5 close if no standalone note is needed.

### 9.2 Locked Direction

Preferred source:

```txt
products/reference-implementations/axismundi-lab/templates/
```

Preferred future publish route:

```txt
/templates/
```

Meaning:

- page layout / template / composition preview layer,
- consumes Wave 1 components,
- not a component module,
- not the canonical styleguide,
- not the WordPress pilot theme.

### 9.3 Implementation Deferred

No `templates/` directory creation unless Phase 2 explicitly needs a placeholder
for README links. If created, it must contain documentation only, not a hifi UI.

---

## 10. Phase 2 Execution Order

Recommended Phase 2 order:

1. Lane H: license/NOTICE/package metadata.
2. Lane E: active path remediation.
3. Lane G: publish mirror check and generator wording fix.
4. Lane C: `.gitignore`.
5. Lane D: GitHub Actions validator workflow.
6. Lane A: README.md.
7. Lane B: README.ko.md.
8. Lane F: templates/category note if needed.

Reason:

- README should reflect decisions already made in H/E/G/C/D.
- README.ko follows README.
- Templates remain last because they are the least blocking and most likely to
  expand scope.

---

## 11. Phase 3 QA Plan

Phase 3 should run:

```powershell
python .\tools\validators\validate_theme_pilot.py
python .\tools\generators\publish_styleguide.py
```

Then verify:

- `README.md` has no stale v3.3 source path.
- `README.ko.md` exists and mirrors key facts.
- `.gitignore` still does not ignore validator evidence.
- `.github/workflows/validate.yml` command matches local validator command.
- `LICENSE-MATRIX.md` and `NOTICE.md` point to current asset paths.
- root `LICENSE` exists or publish is explicitly blocked pending user license
  confirmation.
- `package.json` name is `axismundi`.
- `package.json` license is not stale `ISC`.
- path grep has no active executable/public-facing temporary path hits.
- publish mirror generator still succeeds.

If GitHub Actions cannot be run locally, Phase 3 should at least validate YAML
syntax by inspection and ensure command paths are repo-relative.

---

## 12. Phase 5 Close Gates

v3.5.14 can close only if:

- README.md refreshed.
- README.ko.md created.
- `.gitignore` updated.
- GitHub Actions validator workflow created or explicitly deferred.
- path audit remediation completed or remaining hits classified.
- license/NOTICE path drift fixed.
- root LICENSE either created or explicitly marked as v3.5.15 blocker.
- package metadata no longer says `axismundi-v3.5.1-phase0-handoff`.
- package metadata no longer says `ISC` unless user intentionally chooses ISC.
- publish generator passes.
- validator passes.
- GitHub repo creation remains untouched.
- Pages activation remains untouched.

---

## 13. Non-Goals

Phase 1 and Phase 2 must not:

- create the GitHub repo,
- push any remote branch,
- enable GitHub Pages,
- move the local directory without a final user go,
- change component baseline CSS/tokens,
- reorganize `docs/v3.5.x/` into `docs/releases/`,
- implement actual page templates,
- implement the WordPress pilot theme,
- hide validator evidence in `.gitignore`,
- publish lab module pattern HTML by default.

---

## 14. Self-Check

```txt
Lane H license:        defined
Lane E path audit:     defined
Lane G publish mirror: defined
Lane C gitignore:      defined
Lane D Actions:        defined
Lane A README:         defined
Lane B README.ko:      defined
Lane F templates:      defined
Phase 2 order:         H → E → G → C → D → A → B → F
Repo creation:         non-scope
Pages activation:      non-scope
Directory rename:      gated
Baseline edits:        forbidden
```

## 15. Verdict

Phase 1 plan v1.0 is ready for review.

Recommended route:

```txt
Review → revise if needed → approve → Phase 2 implementation
```
