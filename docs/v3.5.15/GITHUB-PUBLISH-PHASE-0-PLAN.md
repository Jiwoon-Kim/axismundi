# v3.5.15 GitHub Repository + Pages Publish — Phase 0 Plan

> Status: Phase 0 plan v1.0  
> Date: 2026-05-17  
> Cycle: v3.5.15 GitHub repository + Pages publish  
> Scope: plan-first for first public GitHub publish  
> Non-scope: v3.5.16 modernization, WordPress pilot implementation, v4.0 directory restructure

---

## 0. Phase Framing

v3.5.15 is the first external-publish cycle. Unlike component cycles, it includes
local repository initialization, optional local directory rename, GitHub remote
creation, initial push, and GitHub Pages activation.

This cycle must distinguish:

```txt
Codex-automatable local work
User approval gates
GitHub-web / authenticated remote work
```

The current workspace is not a git repository:

```txt
Test-Path .git -> False
```

Therefore v3.5.15 is also the first `git init` cycle for this working tree.

---

## 1. Authoritative Inputs

Read before execution:

1. `CURRENT-STATE.md` v3.5.15 next route.
2. `NEXT-SESSION.md` v3.5.15 handoff.
3. `CHANGELOG.md` latest v3.5.14 entry.
4. `ROADMAP.md` v3.5.15 NEXT entry.
5. `README.md` / `README.ko.md`.
6. `LICENSE-MATRIX.md` / `NOTICE.md`.
7. `.gitignore`.
8. `.github/workflows/validator.yml`.
9. `index.html`.
10. `tools/generators/publish_styleguide.py`.

Future but not in scope:

```txt
docs/v3.5.16/MODERNIZATION-AUDIT.md
docs/v3.5.16/STALE-STATE-AUDIT.md
```

These are authoritative inputs for v3.5.16, not for v3.5.15 execution.

---

## 2. Scope Lanes

### Lane A — Final Local Preflight

Codex can run:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
python .\tools\generators\publish_styleguide.py
```

Then verify:

- validator remains 1.000 / 1.000 / 1.000 / 1.000,
- `npm test` passes,
- styleguide mirror is regenerated,
- `index.html` local links exist,
- `README.md` / `README.ko.md` / license files exist,
- author metadata remains `KIM JIWOON (designbusan.ai.kr) — Busan, Korea`.

### Lane B — Final Hardcoded Path Grep

Codex can run grep for active stale paths:

```txt
phase0-handoff
axismundi-v3.5.1-phase0-handoff
/home/claude/
C:\Users\thaum\dev\
file:///
computer://
```

Classification required:

| Class | Action |
|---|---|
| Active command / README / publish instruction | Fix before publish |
| Local handoff / current-state path | Update naturally if rename executes |
| Historical provenance / audit record | Preserve |
| v3.5.14 / v3.5.15 phase docs documenting grep targets | Preserve |

### Lane C — Directory Rename Gate

Target rename:

```txt
C:\Users\thaum\dev\axismundi-v3.5.1-phase0-handoff
-> C:\Users\thaum\dev\axismundi
```

This must not happen automatically. Required gate:

```txt
User explicit GO for rename
```

If approved, Codex may perform the rename using PowerShell after resolving the
absolute source and destination paths and verifying that the destination does
not already contain an unrelated project.

If not approved, v3.5.15 can still initialize and publish from the current
directory. The GitHub repo name remains `axismundi`.

### Lane D — Git Initialization

Codex can run local git setup after preflight:

```powershell
git init
git add .
git status --short
git commit -m "Initial Axismundi public release"
```

Commit strategy:

```txt
Initial commit only.
No synthetic historical commit reconstruction.
Do not squash or invent previous history.
Release narrative lives in CHANGELOG / ROADMAP / docs.
```

Before commit, verify `.gitignore` excludes:

- `node_modules/`,
- Playwright artifacts,
- temp/editor/OS files.

Validator generated evidence remains tracked by design.

### Lane E — GitHub Repository Creation

Repository name:

```txt
axismundi
```

GitHub creation requires authentication. Possible routes:

1. User creates repo in GitHub web UI.
2. Codex uses `gh repo create axismundi` only if `gh` is installed and
   authenticated, and the user explicitly approves this route.
3. GitHub connector / plugin route only if available and authorized.

Codex must not assume GitHub authentication.

Remote URL decision:

| Option | Notes |
|---|---|
| HTTPS | Easy to copy from GitHub UI. Works with credential manager. |
| SSH | Preferred only if user's SSH key is already configured. |

Phase 0 default:

```txt
Use the remote URL supplied by user after repo creation.
```

### Lane F — Initial Push

After remote is available:

```powershell
git remote add origin <remote-url>
git branch -M main
git push -u origin main
```

If push fails due to auth, Codex reports the exact failure and waits for user
credential / remote correction. Do not rewrite history blindly.

### Lane G — GitHub Pages Activation

Pages source decision:

```txt
Recommended: main branch root
```

Reason:

- Root `index.html` is now the public entry point.
- `/styleguide/` is linked from root.
- Lab modules are browsable as validation/source surfaces, which is acceptable
  after v3.5.16 modernization reframes lab as module workspace.
- `/templates/` is future route; current template category note remains in docs.

Non-preferred options:

| Option | Reason not preferred |
|---|---|
| `/styleguide/` only | Hides README/license/root index and makes index work pointless. |
| `gh-pages` branch | More moving parts for first publish; can revisit later. |

GitHub Pages activation likely requires user action in GitHub web UI unless
authenticated GitHub tooling is available.

### Lane H — Public Verification

After Pages is active:

Verify:

- GitHub repository README renders.
- README.ko.md renders.
- LICENSE / LICENSE-MATRIX / NOTICE render.
- Root Pages URL loads `index.html`.
- `index.html -> styleguide/` works.
- `styleguide/` loads CSS.
- `index.html -> lab overview / lab module index` links work.
- No obvious 404s in first navigation pass.

Codex can use browser/Playwright once the Pages URL is available.

---

## 3. User Approval Gates

v3.5.15 has three explicit gates:

```txt
Gate 1 — Directory rename
  Required before moving the local folder.

Gate 2 — GitHub repo creation route
  User chooses web UI, gh CLI, or connector route.

Gate 3 — Pages source
  Recommended: main branch root. User confirms before enabling.
```

Do not proceed through a gate by inference.

---

## 4. Preflight Checklist

Before any rename, git init, or GitHub action:

```txt
□ validator PASS
□ npm test PASS
□ publish_styleguide.py PASS
□ index.html link targets exist
□ author metadata correct
□ license files exist
□ .gitignore reviewed
□ .git absent or intentionally initialized
□ final path grep classified
```

---

## 5. Non-Goals

v3.5.15 must not:

- implement v3.5.16 styleguide modernization,
- add `index.ko.html`,
- add styleguide lab dialog navigation,
- change lab directory structure,
- rename `axismundi-lab/`,
- drop `lab-*` file prefixes,
- start WordPress pilot theme implementation,
- rewrite license strategy,
- reorganize docs into `docs/releases/`,
- change component baseline CSS/tokens.

---

## 6. Risks

| Risk | Severity | Disposition |
|---|---:|---|
| Directory rename breaks hardcoded commands | P1 | Final path grep + user GO gate. |
| GitHub auth unavailable | P1 | User-created repo or remote URL handoff. |
| Pages source mis-set to `/styleguide/` only | P2 | Recommend main branch root. |
| `node_modules/` accidentally committed | P1 | `.gitignore` + `git status --short` before commit. |
| Validator evidence ignored | P2 | Keep validator outputs tracked by v3.5.14 policy. |
| Lab framing tension visible on Pages | P2 | Accept for v3.5.15; v3.5.16 modernization already audited. |

---

## 7. Phase Shape

Recommended:

```txt
Phase 0  Plan-first (this document)
Phase 1  Local preflight + path audit report
Phase 2  Rename/git/repo/push plan or execution, depending on user gates
Phase 3  GitHub Pages visual/navigation QA
Phase 5  Mechanical close + v3.5.16 handoff
```

Because this cycle depends on external GitHub state, phases may be shorter than
component cycles and may pause at user gates.

---

## 8. Self-Check

```txt
Codex/user split:           explicit
Directory rename gate:      explicit
GitHub repo creation gate:  explicit
Pages source recommendation: main branch root
Initial git state:          .git absent
v3.5.16 modernization:      out of scope
Backlog #34/#35:            out of scope
Baseline edits:             forbidden
```

## 9. Verdict

Phase 0 plan v1.0 is ready for review.

Recommended route:

```txt
Review → approve/revise → Phase 1 local preflight + path audit
```
