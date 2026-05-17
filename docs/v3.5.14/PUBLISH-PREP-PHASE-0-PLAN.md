# v3.5.14 Publish Prep — Phase 0 Plan

> Status: CLOSED — v3.5.14 Phase 5  
> Date: 2026-05-17  
> Cycle: v3.5.14 publish prep  
> Scope: repository publishing readiness only  
> Non-scope: GitHub repository creation, GitHub Pages activation, directory rename execution

---

## 0. Phase Framing

v3.5.14 is not a component, runtime, token, or baseline correction cycle.
It prepares the Axismundi repository for public GitHub and GitHub Pages after
Wave 1 and the Wave 1 cleanup have closed.

The release should make the repository understandable, reproducible, and safe
to publish without changing the component baseline.

Locked predecessor state:

- v3.5.12 closed Wave 1 component coverage.
- v3.5.13 closed Wave 1 cleanup.
- BACKLOG #32 and #33 are resolved.
- Avatar, Divider, and Badge remain RECORD entries, not DONE entries.
- Public repo name is `axismundi`.
- Current local path is `C:\Users\thaum\dev\axismundi-v3.5.1-phase0-handoff`.
- Target local path after path audit is `C:\Users\thaum\dev\axismundi`.

Phase 0 is plan-only:

- Do not edit README files yet.
- Do not edit `.gitignore` yet.
- Do not create `.github/workflows/` yet.
- Do not rename the directory yet.
- Do not create the GitHub repository yet.
- Do not activate GitHub Pages yet.
- Do not implement hifi-prototype templates yet.

---

## 1. Authoritative Inputs

Phase 0 and later publish prep phases must read or reference:

1. `NEXT-SESSION.md` v3.5.14 handoff.
2. `CURRENT-STATE.md` v3.5.13 closed state.
3. `ROADMAP.md` v3.5.14 NEXT entry.
4. `CHANGELOG.md` v3.5.13 entry.
5. `docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md`.
6. `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md`.
7. `docs/v3.5.0/PROMOTION-CRITERIA.md`.
8. `docs/v3.5.0/MODULE-STATUS-MATRIX.md`.
9. `products/reference-implementations/axismundi-lab/README.md`.
10. `products/reference-implementations/axismundi-lab/modules/README.md`.
11. `tools/generators/publish_styleguide.py`.
12. `tools/validators/validate_theme_pilot.py`.
13. Existing root `README.md`.
14. Existing root `.gitignore`.
15. `package.json` and `package-lock.json`.

Phase 0 should treat the local repository contents as authoritative. External
GitHub Actions version pins may be verified later in Phase 1 or Phase 2 if the
workflow is implemented.

---

## 2. Scope Lanes

v3.5.14 has six publish-prep lanes. Phase 0 must lock their order and scope.

### Lane A — README Surface

Deliverables in later phases:

- Refresh `README.md`.
- Create `README.ko.md`.

The README should explain:

- What Axismundi is.
- Why the repo exists.
- The 6-layer structure.
- The 4-tier public/lab/baseline/plugin architecture.
- Wave 1 completion status.
- How to run validator.
- How to publish the styleguide mirror.
- What is stable versus experimental.
- Where WordPress binding and ontology pilot work live.

The Korean README should be a first-class sibling, not a rough machine
translation. It may mirror structure while using Korean-native wording.

### Lane B — Ignore And Artifact Policy

Deliverables in later phases:

- Review and update `.gitignore`.
- Decide whether validator outputs are tracked evidence or ignored generated
  artifacts.

Default recommendation:

- Keep `package.json` and `package-lock.json` tracked.
- Ignore `node_modules/`.
- Ignore Playwright screenshots and reports.
- Keep validator outputs tracked for now because they are current canonical
  validation evidence and already part of the local validation loop.

Validator output policy is a lock decision. Do not silently add
`binding_legitimacy_audit.json` or `pilot_validation_report.md` to `.gitignore`
without an explicit policy change.

### Lane C — GitHub Actions

Deliverables in later phases:

- Add a minimal validator workflow if approved.

Default recommendation:

- Start with validator-only CI.
- Defer Playwright CI until there is a stable smoke-test script rather than
  ad hoc cycle scripts.
- Run from repository root.
- Use the existing validator command:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

The workflow should not require creating or publishing GitHub Pages in v3.5.14.

### Lane D — Hardcoded Path Audit

Deliverables in later phases:

- Grep and classify hardcoded paths before directory rename.
- Fix executable/tooling paths that would break under `dev\axismundi`.
- Leave historical release notes untouched unless they create active confusion.

Required search patterns:

```txt
axismundi-v3.5.1-phase0-handoff
phase0-handoff
C:\Users\thaum\dev
/home/claude
/mnt/c
publish_styleguide.py
styleguide/
```

Required search areas:

- `tools/**/*.py`
- root docs and handoff files
- `README.md`
- `products/reference-implementations/axismundi-lab/**/*.html`
- `products/reference-implementations/axismundi-lab/**/*.css`
- module pattern HTML links
- module docs that mention publish routes
- audit docs with absolute local paths

Classification:

| Bucket | Meaning | Expected Action |
| --- | --- | --- |
| Active executable path | Tool or workflow would break after rename | Fix before rename |
| Public-facing stale path | README or publish instruction would mislead users | Fix in v3.5.14 |
| Historical record | Old phase report or audit caveat | Usually preserve |
| Generated artifact | Validator or publish output | Decide per artifact policy |

Directory rename execution is not Phase 0. It is a later v3.5.14 gate or v3.5.15
entry step after the path audit is clean.

### Lane E — Hifi-Prototype Phase 0

`hifi-prototype` is a new publish category candidate, not a hidden component
module.

Phase 0 must decide its ontology before implementation:

- Is it a lab composition prototype surface?
- Is it a publish route under GitHub Pages?
- Is it separate from the canonical `/styleguide/` mirror?
- Does it live under `products/reference-implementations/axismundi-lab/`?
- Does it require a `publish_styleguide.py` extension?

Default recommendation:

- Treat hifi-prototype as a lab composition/prototype category.
- Do not merge it into canonical component styleguide pages.
- Do not treat it as the WordPress pilot theme.
- Keep implementation out of v3.5.14 unless a separate plan approves it.

Candidate source location for evaluation:

```txt
products/reference-implementations/axismundi-lab/hifi-prototype/
```

Candidate publish route for evaluation:

```txt
styleguide/hifi-prototype/
```

The route name and directory are not locked by this plan. Phase 0 report should
settle them or explicitly defer.

### Lane F — Publish Mirror Check

Deliverables in later phases:

- Verify `publish_styleguide.py` still reflects the intended publish boundary.
- Decide whether module pattern HTML remains lab-only.
- Decide whether orphan module CSS on the publish surface remains acceptable.

Current known behavior:

- Source: `products/reference-implementations/axismundi-lab/`.
- Publish target: root `styleguide/`.
- Canonical styleguide HTML is copied and renamed.
- Module CSS files are flattened to `styleguide/stylesheets/`.
- Module JS, pattern HTML, and docs remain lab-internal.

Default recommendation:

- Keep this boundary in v3.5.14.
- Do not publish module pattern HTML unless hifi-prototype or lab routes are
  explicitly approved.

---

## 3. Lock Decisions Phase 0 Must Surface

### Lock 1 — README Narrative

Decide whether README presents Axismundi primarily as:

1. WordPress-first design system.
2. Ontology-driven multi-binding design system.
3. Material 3 lab/reference implementation.

Recommended lock:

```txt
Ontology-driven Material 3 design system with a WordPress binding and pilot.
```

This keeps WordPress visible without locking the repository identity to a
single binding.

### Lock 2 — README.ko Authorship

Decide whether `README.ko.md` is:

- a strict translation, or
- a Korean-native companion document.

Recommended lock:

```txt
Korean-native companion with the same technical facts and comparable section
order, not a line-by-line translation.
```

### Lock 3 — Validator Outputs

Decide whether these remain tracked:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

Recommended lock:

```txt
Tracked evidence for now.
```

Reason: the project currently uses them as validation readback artifacts. Moving
them to ignored generated output should be a deliberate policy change, not a
drive-by `.gitignore` cleanup.

### Lock 4 — GitHub Actions Scope

Decide whether v3.5.14 implements:

- validator-only workflow,
- validator + Playwright,
- no Actions yet, only a plan.

Recommended lock:

```txt
Validator-only workflow.
```

Playwright CI should wait until stable smoke scripts exist. Local Playwright QA
remains part of component and publish visual gates.

### Lock 5 — Directory Rename Timing

Decide whether v3.5.14 executes:

- path audit only,
- path audit + rename at end,
- defer rename to v3.5.15.

Recommended lock:

```txt
Path audit in v3.5.14; rename only after active executable/public-facing stale
paths are clean. Repo creation remains v3.5.15.
```

### Lock 6 — Hifi-Prototype Category

Decide whether hifi-prototype is:

- styleguide subroute,
- lab-only source category,
- product prototype category,
- WordPress pilot concern.

Recommended lock:

```txt
Lab composition prototype category, distinct from canonical styleguide and
distinct from the WordPress pilot theme.
```

### Lock 7 — Docs Reorganization

Decide whether v3.5.14 reorganizes `docs/v3.5.x/` into
`docs/releases/v3.5.x/`.

Recommended lock:

```txt
Defer docs/releases reorganization.
```

Reason: cross-reference churn is high and publish prep already has enough
surface area. Revisit after GitHub Pages is live if navigation becomes painful.

---

## 4. Phase Shape

Recommended v3.5.14 shape:

```txt
Phase 0  Plan + report
Phase 1  README / ignore / Actions / path audit design
Phase 2  Implement approved publish-prep file edits
Phase 3  Local publish QA
Phase 5  Mechanical close and v3.5.15 handoff
```

Phase 3 publish QA should include:

- `python .\tools\validators\validate_theme_pilot.py`
- `python .\tools\generators\publish_styleguide.py`
- Open or inspect root `index.html`.
- Inspect `styleguide/index.html`.
- Confirm generated styleguide links are not broken at the top level.
- Confirm no accidental module pattern HTML exposure unless explicitly approved.

If GitHub Actions is implemented, Phase 3 should also include a local workflow
sanity check where feasible, or a dry-read of workflow commands if no `act`
runtime exists.

---

## 5. Non-Goals

v3.5.14 must not:

- Create the GitHub repository.
- Push to GitHub.
- Enable GitHub Pages.
- Rename the local directory before the path audit is complete.
- Reorganize the 6-layer architecture.
- Move `docs/v3.5.x/` into `docs/releases/` unless explicitly re-scoped.
- Modify component baseline tokens or CSS.
- Reopen Wave 1 component audits.
- Implement the WordPress block theme pilot.
- Implement hifi-prototype pages before its ontology is approved.
- Convert lab module pattern HTML into public publish routes by default.

---

## 6. Risks

### Risk 1 — Publishing Stale README Claims

The current root README still contains older v3.3-era language and an outdated
styleguide source reference.

Disposition:

- README refresh is Lane A and should happen before GitHub repository creation.

### Risk 2 — Hifi-Prototype Layer Drift

Adding a new prototype category without ontology placement can blur the
difference between canonical styleguide, lab module, and WordPress pilot.

Disposition:

- Phase 0 must settle category and route before implementation.

### Risk 3 — CI Overreach

Adding Playwright to GitHub Actions before stable smoke scripts exist can create
slow or flaky public checks.

Disposition:

- Start with validator-only CI unless Phase 1 discovers a stable Playwright
  smoke command.

### Risk 4 — Ignoring Canonical Validation Evidence

Adding validator outputs to `.gitignore` would change the repository's evidence
model.

Disposition:

- Keep validator outputs tracked unless Phase 1 explicitly changes policy.

### Risk 5 — Directory Rename Before Path Audit

Renaming the directory before hardcoded path cleanup can break local tooling or
mislead README instructions.

Disposition:

- Path audit before rename.

### Risk 6 — Publish Mirror Ambiguity

`publish_styleguide.py` currently flattens module CSS but not module HTML/JS.
This is intentional, but public readers may need the boundary explained.

Disposition:

- README and lab README should document source versus publish mirror clearly.

---

## 7. Phase 1 Entry Conditions

Phase 1 may begin when review confirms:

- Six scope lanes are accepted.
- Validator output policy is accepted or explicitly revised.
- GitHub Actions scope is accepted or explicitly revised.
- Hifi-prototype ontology decision path is accepted.
- Directory rename timing is accepted.
- Non-goals are accepted.

Phase 1 should produce concrete implementation plans for:

- README.md
- README.ko.md
- `.gitignore`
- optional `.github/workflows/validate.yml`
- hardcoded path fixes
- hifi-prototype Phase 0 report or category note

---

## 8. Self-Check

```txt
README scope mentions:        24
hifi-prototype mentions:      14
GitHub Actions mentions:      7
hardcoded path mentions:      5
.gitignore mentions:          8
validator output mentions:    8
repo creation non-goal:       locked
directory rename non-goal:    locked until path audit gate
baseline component edits:     forbidden
```

## 9. Verdict

Phase 0 plan v1.0 is ready for review.

Recommended route:

```txt
Review → revise if needed → approve → Phase 0 report
```
