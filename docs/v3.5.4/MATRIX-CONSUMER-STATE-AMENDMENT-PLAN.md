# Axismundi v3.5.4 — Matrix Consumer-State Amendment Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before amendment execution.
> **Date**: 2026-05-16
> **Backlog scope**: #24 Matrix consumer-state column + #26 Matrix row #36 allowlist correction.
> **Non-scope**: Ripple v2 implementation (#25), `data-ax-ripple` API (#27), public SVG wording cleanup (#28), Card behavior patterns (#29).

---

## §0 — Goal

v3.5.4 is a small foundation-cleanup release before Wave 1 continues.

It amends the v3.5.0 status matrix so future component audits no longer inherit an ambiguous provider/consumer graph.

Primary goals:

```txt
1. Add explicit consumer-state vocabulary to MODULE-STATUS-MATRIX.md.
2. Correct ripple/ row #36 from a flat inferred consumer list to
   state-aware buckets.
3. Align Button, Icon button, and Card SPEC audits with the same matrix
   vocabulary.
4. Preserve momentum by keeping Ripple v2 implementation out of v3.5.4.
```

---

## §1 — Scope

### §1.1 — Backlog Items Closed By This Release

```txt
#24 Matrix consumer-state column
#26 Matrix row #36 allowlist correction
```

### §1.2 — Backlog Items Explicitly Not Closed

```txt
#25 Ripple v2 contract
#27 data-ax-ripple opt-in
#28 Icon button public specimen SVG wording cleanup
#29 Card behavior patterns
```

Reason:

```txt
#24/#26 are documentation/ontology amendments.
#25/#27 are runtime/API implementation work.
#28/#29 are public-surface / behavior-pattern cleanup work.
```

---

## §2 — Consumer-State Vocabulary

Add or formalize these values in `MODULE-STATUS-MATRIX.md §2`:

```txt
CURRENT:
  Provider is presently wired into this consumer surface.

TARGET:
  Provider is a designed dependency for this consumer, but not wired in
  the current baseline/module surface.

CANDIDATE:
  Provider is a plausible or previously inferred dependency, but the
  design decision or implementation evidence is insufficient.

NONE:
  No provider dependency for this consumer surface.

CURRENT conditional:
  Provider is wired only when a slot/composition path uses it.

CURRENT conditional via composition:
  Provider is not a direct dependency of the host component, but becomes
  current through nested composed components.
```

The release should use uppercase values in docs for readability:

```txt
CURRENT / TARGET / CANDIDATE / NONE
```

---

## §3 — Matrix Amendment Strategy

### §3.1 — Preserve v3.5.0 Provenance

Do not pretend the original matrix never existed.

Add a v3.5.4 amendment notice near the top:

```txt
> v3.5.4 amendment: consumer-state vocabulary added after the first
> three Wave 1 cycles (Button, Icon button, Card). Row #36 ripple/
> corrected from a flat inferred consumer list to state-aware buckets.
```

### §3.2 — Column Definition Update

Current matrix has 12 columns. v3.5.4 may either:

```txt
Option A — add a 13th `Consumer state` column to the component matrix.
Option B — keep component rows stable and add provider-specific consumer
           state sub-tables under infrastructure rows.
```

Decision for v3.5.4:

```txt
Use Option B for the infrastructure rows, plus update §2 column
definitions with the consumer-state vocabulary.
```

Reason:

```txt
The component matrix is wide already. Provider-specific sub-tables are
clearer for ripple/ because one component can have different states for
different providers or surfaces:
  Card base = NONE
  Card action = CANDIDATE
  Button state-layer foundation = CURRENT
  Button ripple = TARGET
  Button icon-system = CURRENT conditional
```

### §3.3 — Status Snapshot Update

Update status rows and distribution to reflect closed Wave 1 cycles:

```txt
Button #1:       TODO    -> DONE (v3.5.1)
Icon button #2:  PARTIAL -> DONE (v3.5.2)
Card #9:         TODO    -> DONE (v3.5.3)
```

Expected component status distribution after v3.5.4 amendment:

```txt
DONE:    6  (Button, Icon button, Chip, Snackbar, Tooltip, Card)
PARTIAL: 3  (Search bar, Date picker, Time picker, Carousel? see note)
TODO:    22 or 23 depending Carousel accounting
RECORD:  3
```

Execution must verify the exact count from the table after edits rather
than trusting this plan's estimate.

Important note:

```txt
The current matrix text says PARTIAL count = 4 but lists Carousel,
Date picker, Time picker, and Search bar. That is 4 rows. After Icon
button becomes DONE, PARTIAL should become 3 if no other row changes.
```

---

## §4 — Ripple Row #36 Correction

Current row #36 flat list claims 13+ inferred consumers.

v3.5.4 target structure:

```txt
CURRENT:
  none

TARGET (current lab-ripple.js allowlist / designed target in current
module contract, not baseline-wired):
  Button #1
  Icon button #2
  Chip #24
  Menu #15
  Nav bar #12
  Nav rail #13
  Tabs #14

CANDIDATE:
  FAB #3 + Extended FAB #4
  FAB menu #5
  Button group #6
  Split button #7
  Toolbar #8
  Card #9 action/interactive surface
  App bar #11 action slots
  List #33 item hover/action surface

NONE:
  Card #9 base visual card
  non-interactive components and baseline-only records unless separately
  promoted by future design decision
```

Clarification:

```txt
TARGET does not mean baseline-wired.
It means current ripple module design/allowlist treats the consumer as
a designed target. Main style-guide.html still does not load ripple.
```

---

## §5 — Audit Doc Alignment

Minor edits only. Do not reopen the releases.

### §5.1 — Button SPEC

Target file:

```txt
products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
```

Add a v3.5.4 note to dependency section:

```txt
v3.5.4 matrix amendment aligns this doc's dependency states with the
canonical consumer-state vocabulary:
  state-layer foundation = CURRENT
  ripple/                = TARGET
  icon-system/           = CURRENT conditional
```

### §5.2 — Icon Button SPEC

Target file:

```txt
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
```

Add v3.5.4 note:

```txt
icon-system/ = CURRENT unconditional
ripple/      = TARGET
```

### §5.3 — Card SPEC

Target file:

```txt
products/reference-implementations/axismundi-lab/modules/card/docs/CARD-SPEC-AUDIT.md
```

Add v3.5.4 note:

```txt
base Card ripple/               = NONE
action/interactive Card ripple/ = CANDIDATE
icon-system/                    = CURRENT conditional via composition
```

---

## §6 — Execution Steps After Approval

```txt
1. Edit docs/v3.5.0/MODULE-STATUS-MATRIX.md:
   - add v3.5.4 amendment notice
   - add consumer-state vocabulary
   - update Button/Icon button/Card status rows
   - correct row #36 with state-aware sub-table
   - update §6 status distribution
   - update §7 dependency snapshot

2. Edit three SPEC audit docs with short v3.5.4 alignment notes:
   - BUTTON-SPEC-AUDIT.md
   - ICON-BUTTON-SPEC-AUDIT.md
   - CARD-SPEC-AUDIT.md

3. Update BACKLOG.md:
   - close #24 and #26 into Closed items summary
   - keep #25/#27/#28/#29 open

4. Update CHANGELOG.md:
   - add v3.5.4 matrix amendment entry

5. Update ROADMAP.md:
   - mark v3.5.4 DONE
   - set v3.5.5 NEXT as either Wave 1 continuation or Ripple v2 decision
     point, depending user decision at Phase 5

6. Run validator:
   python tools\\validators\\validate_theme_pilot.py
```

`CURRENT-STATE.md` and `NEXT-SESSION.md` remain untouched unless the user
explicitly asks for a handoff/status snapshot.

---

## §7 — Validation Plan

After approved execution:

```powershell
# 1. Matrix vocabulary present
Select-String docs\v3.5.0\MODULE-STATUS-MATRIX.md -Pattern "CURRENT","TARGET","CANDIDATE","NONE"

# 2. Row #36 state buckets present
Select-String docs\v3.5.0\MODULE-STATUS-MATRIX.md -Pattern "TARGET","CANDIDATE","allowlist"

# 3. Closed Wave 1 statuses present
Select-String docs\v3.5.0\MODULE-STATUS-MATRIX.md -Pattern "Button","Icon button","Card"

# 4. Audit alignment notes present
Select-String products\reference-implementations\axismundi-lab\modules\button\docs\BUTTON-SPEC-AUDIT.md -Pattern "v3.5.4 matrix amendment"
Select-String products\reference-implementations\axismundi-lab\modules\icon-button\docs\ICON-BUTTON-SPEC-AUDIT.md -Pattern "v3.5.4 matrix amendment"
Select-String products\reference-implementations\axismundi-lab\modules\card\docs\CARD-SPEC-AUDIT.md -Pattern "v3.5.4 matrix amendment"

# 5. Backlog routing
Select-String BACKLOG.md -Pattern "Matrix consumer-state column","Matrix row #36 allowlist correction"

# 6. Validator
python tools\validators\validate_theme_pilot.py
```

Baseline files must remain unchanged:

```txt
components.css
blocks.css
icons.css
style-guide.html
tokens.css
theme.json
```

---

## §8 — Explicit Non-Goals

v3.5.4 does not:

```txt
- edit components.css
- edit blocks.css
- edit icons.css
- edit style-guide.html
- edit tokens.css
- edit theme.json
- edit lab-ripple.css
- edit lab-ripple.js
- edit lab-ripple-pattern.html
- implement Ripple v2
- add data-ax-ripple
- wire ripple to Button/Icon button/Card
- edit lab-button.css / lab-icon-button.css / lab-card.css
- edit pattern HTML pages
- fix Icon button public SVG wording (#28)
- implement Card behavior patterns (#29)
- update NEXT-SESSION.md
- update CURRENT-STATE.md unless explicitly requested
```

---

## §9 — Risks

### Risk A — Rewriting History Instead Of Amending

`MODULE-STATUS-MATRIX.md` was a v3.5.0 deliverable. Direct edits could look like silent historical rewrite.

Mitigation:

```txt
Add explicit v3.5.4 amendment notice.
Use wording "amended after first three Wave 1 cycles."
```

### Risk B — TARGET Misread As CURRENT

Button/Icon button/Chip/Menu/Nav bar/Nav rail/Tabs in ripple TARGET bucket might be mistaken as baseline-wired.

Mitigation:

```txt
Define TARGET as designed but not baseline-wired.
State CURRENT for ripple/ = none.
```

### Risk C — Card Surface Collapse

Card has two ripple states: base NONE, action CANDIDATE.

Mitigation:

```txt
Write Card row and row #36 bucket with explicit surface qualifiers.
```

### Risk D — Scope Creep Into Ripple v2

The same files discuss ripple, tempting implementation work.

Mitigation:

```txt
No lab-ripple files touched.
#25/#27 remain open.
```

---

## §10 — Approval Gate

Execution is blocked until this plan is approved.

Approved execution means:

```txt
Amend matrix doc.
Add short alignment notes to three SPEC docs.
Close BACKLOG #24/#26 only.
Add CHANGELOG/ROADMAP mechanical close.
Run validator.
Do not edit baseline/public/runtime files.
```

---

## §11 — One-Line Summary

```txt
v3.5.4 should close #24/#26 by amending MODULE-STATUS-MATRIX.md with
consumer-state vocabulary and a state-aware ripple row #36, aligning
Button/Icon button/Card SPEC dependency notes, while leaving Ripple v2
(#25/#27), Icon button SVG cleanup (#28), and Card behavior patterns
(#29) open for later releases.
```
