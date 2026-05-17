# v3.5.13 Wave 1 Closure Cleanup — Phase 0 Plan

> **Status**: Phase 5 closed.  
> **Release**: v3.5.13.  
> **Target**: Wave 1 closure cleanup after Wave 1 reached 9/9 at v3.5.12.  
> **Authoring rule**: plan-only; no baseline edits, no audit docs, no record docs, no release bookkeeping in this phase.

---

## §0 — Why This Release Exists

Wave 1 is complete:

```txt
Button #1
Icon button #2
Card #9
FAB family #3 + #4
Text field #16
Search bar #17
Button group #6
List #33
Carousel #34

Wave 1: 9 / 9 DONE
Matrix: 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD
```

Wave 1 also left behind three cleanup classes that should be handled before
publish prep:

```txt
1. BACKLOG #32 — Button family size variants
2. BACKLOG #33 — List M3 full token coverage extension
3. Baseline-only Records sweep — Avatar / Divider / Badge
```

This release is not a new component cycle. It is a post-Wave-1 cleanup and
integrity pass.

---

## §1 — Proposed Release Shape

Recommended shape:

```txt
Single v3.5.13 cleanup release with three explicit lanes:

Lane A — BACKLOG #32 Button family size variants
  Cross-cutting baseline correction.

Lane B — BACKLOG #33 List M3 full token coverage
  List-specific audit extension with possible narrow baseline correction.

Lane C — Records sweep
  Avatar / Divider / Badge record-only audit docs. Baseline edit not expected.
```

Reason:

```txt
- All three items are direct Wave 1 closure cleanup.
- They are small enough to coordinate in one release, but different enough that
  Phase 1/2 must keep lane boundaries explicit.
- Keeping them together gives a cleaner v3.5.14 publish-prep narrative:
  "Wave 1 closed, cleanup closed, now publish prep."
```

Alternative shapes Phase 0 may reject or adopt:

```txt
Option 1 — Split into three releases
  v3.5.13 #32, v3.5.14 #33, v3.5.15 Records.
  Safer but delays publish prep and creates bookkeeping churn.

Option 2 — #32 + #33 only; defer Records
  Leaves RECORD entries open into publish prep, which weakens the "Wave 1
  closure cleanup" story.

Option 3 — Records only first
  Lowest risk, but does not address the two known user-surfaced technical debts.
```

Recommended Phase 0 lock: **single release, three lanes**.

---

## §2 — Lane A: BACKLOG #32 Button Family Size Variants

Source:

```txt
BACKLOG #32
v3.5.10 Button group Phase 3 finding
```

Problem statement:

```txt
Button #1, Icon button #2, and Button group #6 are closed, but the Button
family does not yet implement M3 XS/S/M/L/XL size variants. Button group has
`is-size-*` hooks, but Phase 3 showed rendered heights and font sizes remain
constant.
```

Phase 0 must inspect:

```txt
tokens.css:
  - --comp-button-height
  - --comp-button-radius
  - --comp-icon-size-sm/md/lg/xl

components.css:
  - §2 Button
  - §3 Icon button
  - §28 Button group

lab modules:
  - button/
  - icon-button/
  - button-group/
```

Phase 0 must decide:

```txt
1. Token surface:
   - per-size public component tokens, or
   - local per-component variables, or
   - hybrid public height/font/padding tokens with local mapping.

2. Component scope:
   - Button #1 only first, then Icon button/Button group follow-up, or
   - coordinated Button + Icon button + Button group patch.

3. Baseline edit scope:
   - tokens.css + components.css §2/§3/§28 expected.
   - style-guide.html / blocks.css / theme.json should remain untouched unless
     Phase 0 finds hard evidence.

4. Audit alignment:
   - update closed Button / Icon button / Button group audit docs as alignment
     notes only, not release reopens.
```

Recommended lock:

```txt
Coordinated 3-component size cycle.
Phase 2 may edit tokens.css + components.css §2/§3/§28.
No style-guide.html markup edits.
```

Fallback trigger:

```txt
If M3 size matrices require incompatible semantics across Button, Icon button,
and Button group, split #32 into its own release and let v3.5.13 handle #33 +
Records only.
```

---

## §3 — Lane B: BACKLOG #33 List M3 Full Token Coverage

Source:

```txt
BACKLOG #33
v3.5.11 post-close M3 List token dump
```

Problem statement:

```txt
v3.5.11 mapped Common / Enabled and Selected token rows and patched two narrow
List color mismatches. The post-close M3 token dump includes additional rows:
Disabled, Disabled-Selected, Hovered, Focused, Pressed, Dragged, spacing,
shape, size, and typography.
```

Phase 0 must inspect:

```txt
components.css:
  - §0 State-layer foundation
  - §26 List

lab modules:
  - list/docs/LIST-SPEC-AUDIT.md
  - list/docs/LIST-MEASUREMENT-AUDIT.md
  - list/lab-list.css
  - list/lab-list-pattern.html
```

Classification required:

```txt
Each M3 token row must be classified as one of:
  - already covered by List §26,
  - covered by generic §0 state-layer foundation,
  - composition-owned (Avatar / icon-system),
  - behavior-deferred (dragged / reorder / expand),
  - real List baseline mismatch.
```

Recommended lock:

```txt
Audit-extension first.
Baseline edit only if Phase 0/1 identifies a narrow List-specific mismatch
inside components.css §26.
```

Fallback trigger:

```txt
If token rows require broad §0 state-layer framework changes or drag/reorder
runtime work, keep #33 documentation-only at v3.5.13 and route the broader
work to a future release.
```

---

## §4 — Lane C: Records Sweep

Scope:

```txt
Avatar #32
Divider #10
Badge #25
```

Current matrix status:

```txt
All three are Baseline-only Record rows.
They are not component modules and should not be forced into lab/modules/<name>
unless Phase 0 discovers a real module need.
```

Expected output:

```txt
Record-only audit docs, likely under:
  products/reference-implementations/axismundi-lab/modules/_records/

Candidate files:
  AVATAR-RECORD-AUDIT.md
  DIVIDER-RECORD-AUDIT.md
  BADGE-RECORD-AUDIT.md
```

Phase 0 must decide:

```txt
1. Records path:
   - modules/_records/ (recommended), or
   - docs/records/, or
   - per-component folders with no lab artifacts.

2. Record doc shape:
   - short 1-doc record audit, not SPEC/MEASUREMENT/WP trio.

3. Matrix close semantics:
   - RECORD status may remain RECORD, or
   - RECORD may become DONE after record docs exist.
```

Recommended lock:

```txt
Use modules/_records/ and keep each record as a single audit file.
Convert RECORD -> DONE only if Phase 0 confirms the matrix vocabulary supports
that without losing the Baseline-only Record distinction.
```

Important existing context:

```txt
Avatar:
  - standalone baseline visual primitive.
  - used in List leading slot, chat/profile contexts.
  - existing `.ax-avatar` supports xs/sm/default/lg.

Divider:
  - simple visual primitive.
  - maps naturally to WordPress core/separator.

Badge:
  - attaches to other components.
  - numeric/dot variants and host-positioning semantics matter.
```

---

## §5 — Phase Shape

Recommended v3.5.13 phase shape:

```txt
Phase 0:
  This plan -> report.
  Decide single-release vs split.
  Decide record path/status semantics.
  Decide #32 token scope.
  Classify #33 token rows.

Phase 1:
  Lane A: cross-cutting size correction audit.
  Lane B: List token coverage audit extension.
  Lane C: record-only audit docs.

Phase 2:
  Lane A: baseline patch if Phase 1 confirms.
  Lane B: minimal List §26 patch if Phase 1 confirms.
  Lane C: no baseline patch expected.

Phase 3:
  Playwright + user visual QA:
    - Button / Icon button / Button group size matrix.
    - List disabled / selected / hover / focus / pressed / spacing matrix.
    - Avatar / Divider / Badge record specimens if added.

Phase 5:
  CHANGELOG / ROADMAP / MATRIX / CURRENT-STATE / NEXT-SESSION.
  Close #32 and/or #33 only if actual work landed.
```

---

## §6 — Baseline Edit Risk

Allowed only after Phase 0 report + Phase 1 approval:

```txt
Potential:
  - tokens.css
  - components.css §2 Button
  - components.css §3 Icon button
  - components.css §26 List
  - components.css §28 Button group

Not expected:
  - style-guide.html
  - blocks.css
  - theme.json
  - lab module JS
```

Strict fallback:

```txt
If a baseline patch escapes the confirmed section or starts requiring broad
theme-level framework changes, abort that lane and route it to a future
release. Do not let a cleanup release become a rewrite.
```

---

## §7 — Playwright / Validator Expectations

Phase 0:

```txt
Validator must remain 1.000 / 1.000 / 1.000 / 1.000.
No Playwright required except optional M3 spec extraction if pages are JS-rendered.
```

Phase 2/3 expected:

```txt
Button family:
  - measure XS/S/M/L/XL heights, font sizes, padding, icon size, target size.
  - verify Button active morph remains stable after v3.5.9.
  - verify Button group connected outer/inner radii still stable.

List:
  - measure disabled, selected-disabled, hover, focus, pressed.
  - verify spacing and typography rows where implemented.
  - verify state-layer foundation mapping vs component-specific rows.

Records:
  - screenshot / computed-style smoke only if record specimens are created.
```

---

## §8 — Non-Goals

```txt
- Do not begin v3.5.14 publish prep.
- Do not rename the repository directory.
- Do not create the GitHub repository.
- Do not add GitHub Pages.
- Do not start Ontology Theme Pilot.
- Do not reorganize docs/releases unless Phase 0 explicitly proves it is small.
- Do not change `dev/` workspace semantics. Future repo path target is
  `C:\Users\thaum\dev\axismundi\`, not replacing `dev/` itself.
- Do not reopen Wave 1 component closure verdicts.
- Do not edit NEXT-SESSION.md or CURRENT-STATE.md during Phase 0.
```

---

## §9 — Inputs to Read in Phase 0 Report

Required:

```txt
BACKLOG.md #32 + #33
docs/v3.5.0/MODULE-STATUS-MATRIX.md rows #1, #2, #6, #10, #25, #32, #33
components.css §2 / §3 / §26 / §28
tokens.css component token block
Button / Icon button / Button group audit docs
List audit docs
```

Recommended:

```txt
M3 Buttons / Icon buttons / Button groups specs
M3 Lists specs token table
M3 Avatar / Divider / Badge specs if available
```

Use Playwright extraction when M3 pages are JS-rendered and text fetch fails.

---

## §10 — Expected Phase 0 Report Shape

```txt
§0  Framing: Wave 1 9/9 complete; cleanup release begins.
§1  Inputs read.
§2  Single release vs split-release decision.
§3  Lane A #32 inventory + token-surface options.
§4  Lane B #33 token-row classification.
§5  Lane C Records path + status semantics.
§6  Baseline edit risk table.
§7  Phase 1 deliverables.
§8  Phase 2 expected patch surface.
§9  Playwright QA plan.
§10 Non-goals.
§11 Verdict.
```

---

## §11 — Self-Check

```txt
self-check:
  #32 mentions: 15+
  #33 mentions: 10+
  Records mentions: 10+
  baseline edit lock: present
  repo path correction: dev/axismundi, not replacing dev/
  CURRENT-STATE/NEXT-SESSION untouched by plan phase
  plan-only: no baseline edits
```
