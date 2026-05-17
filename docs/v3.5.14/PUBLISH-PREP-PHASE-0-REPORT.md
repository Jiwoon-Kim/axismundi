# v3.5.14 Publish Prep — Phase 0 Report

> Status: CLOSED — v3.5.14 Phase 5  
> Date: 2026-05-17  
> Cycle: v3.5.14 publish prep  
> Scope: repository publishing readiness  
> Result: publish-prep scope locked; implementation deferred to Phase 1/2

---

## 0. Framing

v3.5.14 prepares Axismundi for public GitHub and GitHub Pages after Wave 1 and
Wave 1 cleanup have closed.

This is not a component cycle. It is not a baseline correction cycle. It is not
the WordPress pilot theme cycle. It is a public-repository readiness cycle.

Phase 0 confirms:

- the publish-prep lanes,
- hifi/template/page-layout ontology,
- path audit scope,
- GitHub Actions scope,
- README outline,
- publish mirror scope,
- license review gate.

Phase 0 is documentation-only. No README, `.gitignore`, workflow, baseline,
repository path, or publish surface edits happen in this phase.

---

## 1. Authoritative Inputs Read

| Input | Phase 0 finding |
| --- | --- |
| `NEXT-SESSION.md` | v3.5.14 scope already names README, README.ko, `.gitignore`, Actions, path grep, hifi-prototype Phase 0. |
| `CURRENT-STATE.md` | v3.5.13 closed; v3.5.14 is publish prep. |
| `ROADMAP.md` | v3.5.14 NEXT is publish prep; v3.5.15 is repo + Pages. |
| `CONSTITUTION.md` Article 12 | Publishing surfaces are mirrors, not source authorities. |
| `PUBLIC-SURFACE-CHARTER.md` §2-§3 | Public/lab/baseline/plugin tier meanings remain authoritative. |
| `ARCHITECTURE-BOUNDARIES.md` §3-§4 | Template parts and layout slots are theme territory; generation/parsing behavior is plugin territory. |
| `publish_styleguide.py` | Already uses script-relative `ROOT`; copies baseline styleguide and flattens module CSS only. |
| `validate_theme_pilot.py` | Runs from repo root with relative `Path(...)` inputs and writes validation evidence. |
| `LICENSE-MATRIX.md` | Final root LICENSE not yet finalized; compatibility review pending. |
| `NOTICE.md` | Attribution exists, but some asset paths are stale after asset relocation. |
| root `README.md` | Stale v3.3-era public narrative and old source-path references. |
| root `.gitignore` | Currently minimal: `node_modules/`, QA PNG patterns only. |

---

## 2. Scope Lanes Locked

v3.5.14 uses eight lanes. The Phase 0 plan had seven; Phase 0 report adds
license review as Lane H because public release is blocked without it.

| Lane | Name | Phase 0 lock |
| --- | --- | --- |
| A | README.md refresh | In scope. |
| B | README.ko.md creation | In scope. |
| C | `.gitignore` / artifact policy | In scope. |
| D | GitHub Actions | Validator-only first, unless Phase 1 rejects. |
| E | Hardcoded path audit | In scope before directory rename. |
| F | Template / page-layout / hifi prototype ontology | In scope as category decision, not implementation. |
| G | Publish mirror check | In scope. |
| H | License / NOTICE / root LICENSE readiness | In scope and publish-blocking. |

Non-scope remains:

- GitHub repository creation.
- GitHub Pages activation.
- Directory rename before path audit.
- Component baseline edits.
- WordPress pilot theme implementation.
- Hifi/template implementation.

---

## 3. Hifi / Template / Page-Layout Ontology

The user's correction is accepted:

```txt
hifi-prototype is better framed as a template/page-layout/composition layer
than as another component module.
```

Reason:

- Wave 1 validates component blocks and interaction modules.
- The next publish preview layer should validate how those pieces compose into
  page layouts, templates, and high-fidelity surfaces.
- This sits between component catalog and production WordPress pilot.

### 3.1 Six-Layer Placement

Constitution Article 1 places reference implementations, distributables, and
prototypes under `products/`. Therefore the source authority should remain
under `products/`, not root `/styleguide/`.

Recommended source location:

```txt
products/reference-implementations/axismundi-lab/templates/
```

Alternative considered:

```txt
products/reference-implementations/axismundi-lab/hifi-prototype/
```

Disposition:

```txt
Prefer templates/ as the source category name.
```

Rationale:

- `templates` matches WordPress/FSE language.
- It scales from page layouts to template parts.
- It is less decorative than `hifi-prototype`.
- It reflects the user's framing: component block verification is followed by
  a template layer/bridge.

### 3.2 Four-Tier Placement

Template/page-layout prototypes belong to the Lab tier until promoted.

```txt
LAB tier:
  products/reference-implementations/axismundi-lab/templates/

PUBLIC publish mirror candidate:
  /templates/ or /preview/

BASELINE tier:
  no change

PLUGIN tier:
  no change
```

They are not canonical component styleguide entries and not the WordPress pilot
theme.

### 3.3 Publish Route

Recommended publish route:

```txt
/templates/
```

Alternative:

```txt
/preview/
```

Disposition:

```txt
Use `/templates/` if the surface shows page/template compositions.
Reserve `/preview/` for a broader future app-like showcase.
```

Do not nest template pages under `/styleguide/` unless the user intentionally
wants the styleguide to become the umbrella for all visual surfaces. Article 12
already treats `/styleguide/` as a mirror of the canonical component styleguide,
so adding templates under it would blur the current meaning.

### 3.4 Implementation Timing

v3.5.14 may define the category and README route language. It should not build
the template gallery unless Phase 1/2 explicitly approves a minimal skeleton.

Default Phase 2 scope:

- no template implementation,
- maybe create a placeholder note only if README needs a link target,
- no publish generator extension unless `/templates/` is implemented.

---

## 4. Path Audit Findings

Initial grep targets:

```txt
axismundi-v3.5.1-phase0-handoff
phase0-handoff
C:\Users\thaum\dev
/home/claude
/mnt/c
Path("/...")
```

Current findings:

| Finding | Classification | Disposition |
| --- | --- | --- |
| `NEXT-SESSION.md` and `CURRENT-STATE.md` contain current and target local paths | Handoff record | Update naturally in Phase 5 or when rename executes. |
| `package.json` name is `axismundi-v3.5.1-phase0-handoff` | Public-facing package metadata | Update to `axismundi` in v3.5.14 Phase 2. |
| `package-lock.json` repeats old package name | Generated package metadata | Update through npm/package metadata edit in Phase 2. |
| `publish_styleguide.py` old `/home/claude/axismundi` reference appears only in historical CHANGELOG/docs | Historical record | Preserve. |
| `bindings/wordpress-material3/legitimacy_audit.json` has `/home/claude/axismundi-theme-pilot-v0.1` | Legacy/generated evidence | Classify in Phase 1; likely regenerate or mark historical. |
| `bindings/wordpress-material3/block_component_rules.json` has `/home/claude/v2_1_axismundi_input/...` | Data provenance path | Phase 1 must decide preserve-as-provenance vs normalize. |
| `core/wordpress/pilots/*.json` contain `/home/claude/v2_0_work/...` | Source provenance paths | Likely historical/provenance, not executable. Document before publish. |
| `core/design-systems/material3/token_ontology.jsonld` contains old `/home/claude/v2_1_axismundi_input/...` | Source provenance paths | Phase 1 must decide whether to normalize into relative provenance. |
| `ontology-theme-pilot/README.md` references old validation command | Public-facing stale instruction | Fix in v3.5.14. |

`tools/generators/publish_styleguide.py` is already portable:

```python
ROOT = Path(__file__).resolve().parent.parent.parent
```

Therefore the hardcoded path audit is not blocked by the publish generator.

---

## 5. GitHub Actions Scope

Phase 0 locks the first CI workflow as validator-only.

Recommended triggers:

```txt
pull_request
push to main
workflow_dispatch
```

Schedule is not recommended for v3.5.14. This repository does not yet need a
daily/weekly recurring job before first public push.

Workflow behavior:

1. Check out repository.
2. Set up Python.
3. Run `python tools/validators/validate_theme_pilot.py`.
4. Uploading validator artifacts is optional; tracking generated outputs in the
   repo remains the current evidence model.

Playwright CI is deferred:

- local Playwright remains part of Phase 2/3 QA,
- no stable cross-component smoke script exists yet,
- adding browser installation to first public CI would increase failure surface.

---

## 6. README Outline

### 6.1 README.md

Recommended outline:

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
   - validator
   - publish styleguide
   - optional local browser open
7. Repository map
8. WordPress binding and pilot
9. Stability notes
10. License and attribution
```

Tone:

- public-facing,
- concise,
- confident but not over-claiming production readiness.

### 6.2 README.ko.md

Recommended outline mirrors README.md but uses Korean-native phrasing:

```txt
1. Axismundi란 무엇인가
2. 현재 상태
3. 구조
4. 실행 방법
5. 공개 표면
6. WordPress / 블록 테마 / 플러그인 경계
7. 라이선스
```

README.ko.md should not be a line-by-line translation. It should be a first-class
Korean companion for the likely Korean development audience.

---

## 7. Publish Mirror Check

Current `publish_styleguide.py` behavior:

- Mirrors `style-guide.html` to `styleguide/index.html`.
- Mirrors blocks/prose styleguide pages.
- Copies lab design-system stylesheets.
- Flattens `lab/modules/*/lab-*.css` into `styleguide/stylesheets/`.
- Does not copy module JS.
- Does not copy module pattern HTML.
- Does not copy module audit docs.

Phase 0 disposition:

```txt
Keep current behavior for v3.5.14.
```

Reason:

- Module pattern HTML is still a lab validation surface.
- Publishing it without a route model would blur lab/public tiers.
- The template/page-layout category should be designed separately.

Required Phase 1 check:

- Run or inspect publish generator output after README/ignore changes.
- Confirm v3.5.x modules do not require additional CSS handling.
- Confirm the generator comment no longer says "RC theme" if README language
  standardizes on "lab source" instead.

---

## 8. License / NOTICE Readiness

Phase 0 adds Lane H as publish-blocking.

### 8.1 Current License State

`LICENSE-MATRIX.md` says:

- root project license intent is GPL-3.0-or-later,
- final root LICENSE is not yet finalized,
- public release status is pending compatibility review,
- tools/scripts license is TBD,
- ontology/bindings license is TBD,
- governance docs license is TBD,
- Beer CSS code-copy audit is pending.

This means v3.5.14 cannot be considered publish-ready unless license tasks are
either completed or explicitly marked as pre-v3.5.15 blockers.

### 8.2 Asset Path Mismatch

Phase 0 found stale paths in license docs.

`NOTICE.md` and `LICENSE-MATRIX.md` reference:

```txt
products/reference-implementations/axismundi-lab/assets/...
```

Actual current asset license files live at:

```txt
core/design-systems/material3/assets/fonts/noto-sans-kr/OFL.txt
core/design-systems/material3/assets/fonts/noto-serif-kr/OFL.txt
core/design-systems/material3/assets/fonts/roboto-flex/OFL.txt
core/design-systems/material3/assets/fonts/roboto-mono/OFL.txt
core/design-systems/material3/assets/fonts/roboto-serif/OFL.txt
core/design-systems/material3/assets/icons/material-symbols-outlined/LICENSE.txt
core/design-systems/material3/assets/icons/material-symbols-rounded/LICENSE.txt
core/design-systems/material3/assets/icons/material-symbols-sharp/LICENSE.txt
```

Disposition:

```txt
P1 publish-prep finding.
```

Phase 1/2 must update `NOTICE.md` and `LICENSE-MATRIX.md` to match current asset
locations before public repository creation.

### 8.3 Root LICENSE

There is no finalized root `LICENSE` file in the current repo root.

Disposition:

```txt
P1 publish-prep finding.
```

Phase 1 must decide whether v3.5.14 creates the root LICENSE file or records it
as a hard blocker for v3.5.15. Recommended: create it in v3.5.14 if the user
confirms GPL-3.0-or-later.

### 8.4 Beer CSS Audit

`NOTICE.md` and `LICENSE-MATRIX.md` state Beer CSS is inspiration only and that
a code audit is pending before public release.

Disposition:

```txt
Phase 1 license check must include targeted Beer CSS provenance review.
```

This does not need to become a full security-style scan; it should compare known
legacy interaction inspirations against current lab runtime files and document
that Axismundi's v3.5.x runtime contracts are M3-grounded.

---

## 9. `.gitignore` And Artifact Policy

Current `.gitignore`:

```txt
node_modules/
docs/**/*-qa.png
docs/**/qa-*.png
```

Phase 0 locks:

- keep `node_modules/` ignored,
- keep Playwright screenshots ignored,
- add Playwright report/test output patterns in Phase 2,
- keep `package.json` and `package-lock.json` tracked,
- keep validator outputs tracked unless Phase 1 explicitly reverses policy,
- consider OS/editor ignores (`.DS_Store`, `Thumbs.db`, `.vscode/`) in Phase 1.

Validator output policy:

```txt
Tracked evidence for now.
```

Reason:

- validator writes the files every run,
- they are useful PASS evidence in PR review,
- changing them to ignored generated artifacts would be a larger evidence-model
  decision.

---

## 10. Phase 1 Work Plan

Phase 1 should produce implementation-ready plans for:

1. README rewrite.
2. README.ko creation.
3. `.gitignore` update.
4. GitHub Actions validator workflow.
5. Path audit remediation table.
6. License/NOTICE/root LICENSE remediation table.
7. Template/page-layout category naming and source/publish route.
8. Publish generator check.

Phase 1 should not implement all edits blindly. It should separate:

- low-risk immediate edits,
- license decisions needing user confirmation,
- path provenance that should remain historical,
- deferred v3.5.15 actions.

---

## 11. Risks And Dispositions

| Risk | Severity | Disposition |
| --- | --- | --- |
| README overclaims production readiness | P1 | README must state reference/publish prep status honestly. |
| License docs point to stale asset paths | P1 | Fix before repo creation. |
| Root LICENSE missing | P1 | Decide/create before first public push. |
| Hifi/template route blurs styleguide meaning | P2 | Use `templates/` source category and separate publish route candidate. |
| GitHub Actions includes flaky browser CI | P2 | Validator-only first. |
| Directory rename breaks package/tooling metadata | P2 | Path audit before rename; package metadata update in Phase 2. |
| Historical `/home/claude` provenance gets over-normalized | P3 | Classify active vs historical before editing. |

---

## 12. Phase 0 Verdict

Phase 0 locks v3.5.14 as an eight-lane publish prep cycle:

```txt
A README.md
B README.ko.md
C .gitignore / artifacts
D GitHub Actions validator-only
E hardcoded path audit
F templates/page-layout category
G publish mirror check
H license / NOTICE / root LICENSE readiness
```

Key decisions:

- `templates/` is the preferred source category name over `hifi-prototype/`.
- Candidate source: `products/reference-implementations/axismundi-lab/templates/`.
- Candidate public route: `/templates/`.
- `/styleguide/` remains the canonical component styleguide mirror.
- GitHub repo creation and Pages activation remain v3.5.15.
- Directory rename waits for path audit.
- License readiness is a P1 publish-prep lane.

Recommended route:

```txt
Review → revise if needed → approve → Phase 1 plan
```

---

## 13. Self-Check

```txt
README lane:              present
README.ko lane:           present
.gitignore lane:          present
GitHub Actions lane:      present
hardcoded path lane:      present
templates/page-layout:    present
publish mirror lane:      present
license lane:             added
repo creation:            non-scope
GitHub Pages:             non-scope
directory rename:         gated
baseline edits:           forbidden
validator:                run after report
```
