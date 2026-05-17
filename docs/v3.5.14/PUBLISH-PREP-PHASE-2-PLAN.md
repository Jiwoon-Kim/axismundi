# v3.5.14 Publish Prep — Phase 2 Plan

> Status: CLOSED — v3.5.14 Phase 5  
> Date: 2026-05-17  
> Cycle: v3.5.14 publish prep  
> Scope: approved publish-prep implementation plan  
> Non-scope: GitHub repository creation, Pages activation, directory rename execution

---

## 0. Phase Framing

Phase 2 is the first v3.5.14 implementation phase. It edits public-facing
repository files, but it must not edit component baseline CSS/tokens, create a
GitHub repository, enable GitHub Pages, or move the local directory.

Execution order remains:

```txt
H → E → G → C → D → A → B → F
```

This order is publish-blocking first:

1. license and metadata,
2. path drift,
3. publish mirror,
4. artifact policy,
5. CI,
6. README,
7. Korean README,
8. templates/category note.

---

## 1. Global Edit Protocol

All Phase 2 edits follow:

```txt
apply_patch → readback → abort on mismatch
```

No automatic fresh-write fallback. A full rewrite is allowed only when the file
is intentionally being replaced wholesale, such as README.md or README.ko.md,
and the final readback passes.

Validation sequence:

1. Pre-edit validator.
2. Lane H/E/G/C/D edits.
3. Intermediate validator.
4. Lane A/B/F edits.
5. Final validator.
6. Run publish generator.
7. Final path and license grep.

Required commands:

```powershell
python .\tools\validators\validate_theme_pilot.py
python .\tools\generators\publish_styleguide.py
```

---

## 2. Lane H — License / NOTICE / Package Metadata

### 2.1 License Decision

Lock:

```txt
Primary code license: GPL-3.0-or-later
```

Reason:

- `LICENSE-MATRIX.md` already records GPL-3.0-or-later as project intent.
- Material Symbols are Apache-2.0; GPL-3.0-or-later is the compatible direction
  already documented in the matrix.
- WordPress theme direction makes GPL-family licensing expected.
- Current `package.json` says `ISC`, which is stale and unsafe for public
  metadata.
- WordPress.org Theme Directory requirements expect theme zip contents to use
  GPL or GPL-compatible licensing; this aligns code/theme deliverables with the
  submission goal.

This phase is not legal advice. It aligns repository files with the already
documented license intent and user-confirmed direction.

### 2.1a Multi-License Matrix

Lock:

```txt
Code:              GPL-3.0-or-later
Documentation:     CC BY-SA 4.0
Ontology / data:   CC BY-SA 4.0 by default
Upstream assets:   preserve original licenses and notices
```

Rationale:

- Code needs GPL compatibility for WordPress.org and bundled theme/plugin
  direction.
- Documentation should be reusable but reciprocal; CC BY-SA 4.0 fits the
  developer education and audit-doc surface better than a code license.
- Ontology and binding data are knowledge structures, not ordinary executable
  code. A content/data license is clearer while preserving attribution and
  share-alike expectations.
- Upstream assets keep their own terms: Material Symbols Apache-2.0; shipped
  fonts OFL 1.1 where applicable; WordPress-derived corpus terms remain
  separately identified.

Phase 2 should add a clear "Code vs Content boundary" table to
`LICENSE-MATRIX.md`.

### 2.2 Files To Edit/Create

| File | Required Phase 2 action |
| --- | --- |
| `LICENSE` | Create GPL-3.0-or-later root code license text or SPDX-forward license file. |
| `LICENSE-CC-BY-SA-4.0.md` | Create or reference documentation/ontology license terms. |
| `LICENSE-MATRIX.md` | Mark root code LICENSE finalized; add multi-license matrix; fix asset paths; update package metadata policy; reduce stale TBD ambiguity. |
| `NOTICE.md` | Fix asset paths to `core/design-systems/material3/assets/...`. |
| `package.json` | Set `name` to `axismundi`; set `license` to `GPL-3.0-or-later`; replace placeholder description/test script if appropriate. |
| `package-lock.json` | Sync root package name/license fields. |
| `ROADMAP.md` | Add concise commercial sustainability strategy note, without implementing monetization. |

### 2.3 Root LICENSE Text

Preferred implementation:

```txt
SPDX-License-Identifier: GPL-3.0-or-later
```

with a short pointer to `LICENSE-MATRIX.md` and `NOTICE.md` for third-party
assets, if the project wants a concise root file.

Alternative:

```txt
Full GPL-3.0-or-later license text.
```

Phase 2 must choose one and record the choice in `LICENSE-MATRIX.md`.

Recommended:

```txt
Create a concise root LICENSE with SPDX identifier + notice pointer.
```

Reason: the repository has mixed content and per-asset license files; the
matrix remains the detailed source of truth.

Documentation and ontology/data license terms must not be hidden inside the GPL
file. If Phase 2 creates `LICENSE-CC-BY-SA-4.0.md`, the root `LICENSE` should
point readers to the matrix for non-code surfaces.

### 2.4 NOTICE / LICENSE-MATRIX Asset Path Fix

Replace old path references:

```txt
products/reference-implementations/axismundi-lab/assets/
```

with current paths:

```txt
core/design-systems/material3/assets/
```

Known current license files:

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

### 2.5 Beer CSS Note

Do not remove Beer CSS attribution.

Recommended Phase 2 wording:

- keep inspiration attribution,
- state that v3.5.x runtime contracts are Material 3-grounded,
- keep a concise provenance caveat if no line-by-line provenance audit is run.

Do not overclaim "no code copied" unless Phase 2 performs targeted provenance
review and records evidence.

### 2.6 WordPress.org Submission Compatibility

Phase 2 should add a `WP.org submission compatibility` subsection to
`LICENSE-MATRIX.md`.

Checklist:

```txt
□ Theme/distributable code is GPL-3.0-or-later.
□ Bundled assets have GPL-compatible or separately preserved compatible terms.
□ Material Symbols Apache-2.0 attribution remains in NOTICE.md.
□ Font OFL.txt files remain preserved in current asset directories.
□ WordPress-derived corpus terms remain separately identified.
□ Theme readme.txt / screenshot / tags are deferred to v3.5.15+ submission prep.
□ Google / Material trademark claims are avoided; attribution only.
```

Phase 2 should not create `readme.txt` unless the user explicitly expands scope.
That belongs to a later WordPress.org submission prep lane.

### 2.7 Commercial Sustainability Note

Commercial strategy is not a license blocker, but public release should not
pretend sustainability is out of scope.

Phase 2 should add a short ROADMAP note, not a full strategy doc:

```txt
Commercial sustainability strategy is deferred to a future v4.0 public-release
planning document. Candidate models include paid support, hosted services,
premium GPL-compatible plugins, template packs, and sponsorship.
```

Rules:

- Do not compromise WordPress.org GPL-compatible submission goals.
- Do not add proprietary licensing to theme code in v3.5.14.
- Do not implement monetization features in v3.5.14.

---

## 3. Lane E — Hardcoded Path Remediation

### 3.1 Files To Edit

| File | Required action |
| --- | --- |
| `package.json` | Rename package to `axismundi`. |
| `package-lock.json` | Sync package name/license. |
| `products/reference-implementations/ontology-theme-pilot/README.md` | Replace stale `/home/claude/...` validator command with current repo-root validator command. |

### 3.2 Files To Classify, Not Edit By Default

| File family | Classification |
| --- | --- |
| `core/wordpress/pilots/*.json` | Source/provenance snapshots. |
| `core/wordpress/validation_sources.json` | Source/provenance metadata. |
| `core/design-systems/material3/token_ontology.jsonld` | Source/provenance metadata. |
| `bindings/wordpress-material3/block_component_rules.json` | Source/provenance metadata. |
| `bindings/wordpress-material3/legitimacy_audit.json` | Legacy/generated evidence. |
| historical `docs/v3.5.x` and `CHANGELOG.md` | Historical release record. |

Phase 2 must not normalize provenance paths just to make grep clean. It should
document remaining provenance paths in the Phase 5 close.

### 3.3 Required Grep

Run after path remediation:

```powershell
rg -n "axismundi-v3\.5\.1-phase0-handoff|phase0-handoff|C:\\Users\\thaum\\dev|/home/claude|/mnt/c" -S . --glob "!node_modules/**" --glob "!styleguide/**"
```

Phase 3 pass condition:

- no active executable/public-facing stale instruction remains,
- remaining hits are historical/provenance/handoff and classified.

### 3.4 Directory Rename

Do not rename the directory in Phase 2.

Phase 5 may recommend:

```txt
C:\Users\thaum\dev\axismundi-v3.5.1-phase0-handoff
→ C:\Users\thaum\dev\axismundi
```

Actual move needs a separate user go.

---

## 4. Lane G — Publish Mirror Check

### 4.1 File To Edit

| File | Action |
| --- | --- |
| `tools/generators/publish_styleguide.py` | Replace stale "RC theme" wording with "Axismundi lab source" wording. |

Do not change generator behavior unless verification fails.

### 4.2 Required Run

```powershell
python .\tools\generators\publish_styleguide.py
```

Required checks:

- `styleguide/index.html`
- `styleguide/blocks.html`
- `styleguide/prose.html`
- `styleguide/README.md`
- design-system CSS copied,
- `lab-*.css` module CSS copied,
- module JS not copied,
- module pattern HTML not copied,
- `_records/` docs are not published as public components.

### 4.3 Templates Route

No `/templates/` generator extension in Phase 2 unless Lane F is explicitly
upgraded. Default is documentation-only.

---

## 5. Lane C — `.gitignore`

### 5.1 File To Edit

| File | Action |
| --- | --- |
| `.gitignore` | Add public-repo artifact hygiene patterns. |

Recommended final content:

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
.vscode/
.idea/
```

### 5.2 Explicit Non-Ignore

Do not ignore:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
package.json
package-lock.json
```

Validator outputs remain tracked evidence.

---

## 6. Lane D — GitHub Actions

### 6.1 File To Create

```txt
.github/workflows/validator.yml
```

### 6.2 Workflow Scope

Lock:

```txt
validator-only
```

Triggers:

```yaml
pull_request
push to main
workflow_dispatch
```

No schedule.
No Pages deploy.
No Playwright install.

### 6.3 Version Policy

Because action versions may change over time, Phase 2 should either:

1. verify current maintained versions from official GitHub documentation before
   writing exact `uses:` pins, or
2. use conservative maintained versions and record that CI version refresh is a
   future maintenance task.

Recommended if no external verification is done:

```txt
Use commonly maintained versions and keep workflow minimal.
```

### 6.4 Workflow Command

```bash
python tools/validators/validate_theme_pilot.py
```

---

## 7. Lane A — README.md

### 7.1 File To Rewrite

```txt
README.md
```

This is an intentional full rewrite. Readback required.

### 7.2 Required Content

README must include:

- project identity,
- Wave 1 completion status,
- v3.5.14 publish prep status,
- 6-layer repository map,
- 4-tier architecture summary,
- publish surfaces:
  - `index.html`,
  - `styleguide/`,
  - planned `templates/`,
- quick start:
  - install dependencies,
  - run validator,
  - publish styleguide mirror,
- WordPress binding and pilot direction,
- license and attribution pointers,
- status caveat: GitHub Pages/repo publish is next step, not already done.

### 7.3 Tone

Public-facing, short, and honest.

Avoid:

- stale v3.3 source-path references,
- "production-ready theme" claims,
- implying templates already exist,
- implying GitHub Pages is already active.

---

## 8. Lane B — README.ko.md

### 8.1 File To Create

```txt
README.ko.md
```

### 8.2 Required Content

Korean-native companion:

- Axismundi란 무엇인가,
- 현재 상태,
- 구조,
- 실행 방법,
- 공개 표면,
- WordPress / 블록 테마 / 플러그인 경계,
- 라이선스.

It should match README.md facts, not necessarily every sentence.

---

## 9. Lane F — Templates / Page-Layout Category

### 9.1 File To Create

Recommended:

```txt
docs/v3.5.14/TEMPLATES-PUBLISH-CATEGORY-NOTE.md
```

No `products/reference-implementations/axismundi-lab/templates/` directory by
default in Phase 2.

### 9.2 Locked Content

The note should state:

- preferred source category: `products/reference-implementations/axismundi-lab/templates/`,
- preferred publish route: `/templates/`,
- purpose: page layout / template / composition preview,
- distinct from `/styleguide/`,
- distinct from WordPress pilot theme,
- implementation deferred.

---

## 10. Phase 3 QA

Run:

```powershell
python .\tools\validators\validate_theme_pilot.py
python .\tools\generators\publish_styleguide.py
```

Check:

- `LICENSE` exists and aligns with `package.json`.
- `LICENSE-CC-BY-SA-4.0.md` exists or `LICENSE-MATRIX.md` clearly references the
  documentation/ontology license source.
- `LICENSE-MATRIX.md` no longer says root LICENSE is pending if created.
- `LICENSE-MATRIX.md` includes Code / Documentation / Ontology-Data boundary.
- `LICENSE-MATRIX.md` includes WP.org compatibility notes.
- `NOTICE.md` asset paths are current.
- `package.json` name is `axismundi`.
- `package.json` license is `GPL-3.0-or-later`.
- `ROADMAP.md` includes a short commercial sustainability future-strategy note.
- `README.md` no longer references stale v3.3 prototype paths.
- `README.ko.md` exists.
- `.github/workflows/validator.yml` exists and uses repo-relative command.
- `.gitignore` keeps validator evidence tracked.
- publish generator succeeds.
- path grep remaining hits are classified.

---

## 11. Fallback Triggers

Abort Phase 2 execution and report if:

- root license text cannot be confidently created,
- package lock rewrite corrupts dependency metadata,
- validator fails after metadata edits,
- publish generator fails,
- Actions workflow requires dependency installation not currently captured,
- path remediation touches provenance data in a way that changes ontology meaning,
- README rewrite would need claims not supported by current repo state.

---

## 12. Phase 5 Close Preview

Phase 5 should update:

- `CHANGELOG.md` v3.5.14 entry,
- `ROADMAP.md` v3.5.14 DONE / v3.5.15 NEXT,
- `CURRENT-STATE.md`,
- `NEXT-SESSION.md`,
- `PUBLISH-PREP-PHASE-0-PLAN.md` status if desired,
- `PUBLISH-PREP-PHASE-0-REPORT.md` status if desired,
- `PUBLISH-PREP-PHASE-1-PLAN.md` status if desired,
- `PUBLISH-PREP-PHASE-2-PLAN.md` status.

v3.5.15 handoff should say:

```txt
Create GitHub repository `axismundi`, perform local directory rename if user
approves, push initial public repo, and enable GitHub Pages.
```

---

## 13. Non-Goals

Phase 2 must not:

- create GitHub repository,
- push to GitHub,
- enable GitHub Pages,
- rename the local directory,
- implement templates,
- edit component baseline CSS/tokens,
- reorganize docs into `docs/releases/`,
- ignore validator evidence,
- publish lab module pattern HTML,
- start the WordPress pilot theme.

---

## 14. Self-Check

```txt
Lane H license:        exact files + GPL lock included
Lane E path audit:     exact files + grep included
Lane G publish mirror: generator run + wording fix included
Lane C gitignore:      exact patterns included
Lane D Actions:        validator-only workflow included
Lane A README:         rewrite scope included
Lane B README.ko:      creation scope included
Lane F templates:      note-only scope included
Repo creation:         forbidden
Pages activation:      forbidden
Directory rename:      forbidden until user go
Baseline edits:        forbidden
Validator:             required
Publish generator:     required
```

## 15. Verdict

Phase 2 execution and Phase 3 visual QA follow-up are complete.

Closed results:

```txt
License / NOTICE / package metadata: complete
Hardcoded path remediation:         complete
Publish mirror check:               complete
.gitignore / artifact policy:       complete
GitHub Actions validator workflow:  complete
README.md / README.ko.md:           complete
Templates category note:            complete
Root index.html refresh:            complete
Validator:                          1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:                           PASS
```

v3.5.14 is closed. Next route: v3.5.15 GitHub repository `axismundi` +
GitHub Pages publish.
