# Axismundi v3.5.18 — Pre-Pilot Cleanup Phase 1 Implementation Plan

Status: CLOSED — Phase 5 complete  
Date: 2026-05-18  
Cycle: v3.5.18 pre-pilot cleanup + Carousel reroute  
Inputs:

- `PRE-PILOT-CLEANUP-PHASE-0-PLAN.md`
- `PRE-PILOT-CLEANUP-PHASE-0-REPORT.md`
- `docs/v3.5.0/MODULE-STATUS-MATRIX.md`
- `docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md`
- `BACKLOG.md`

## §0. Verdict

Phase 1 is **READY FOR DOCS-ONLY EXECUTION** after user approval.

v3.5.18 does not implement Carousel plugin extraction, block theme Pilot files,
or styleguide shell rebuilds. It prepares the project to enter v3.6.0 with
clean scope and stronger process gates.

## §1. Lane Overview

```txt
Lane A — Carousel matrix reroute amendment
Lane B — Lesson lock in AGENTS / CLAUDE / grounding pack
Lane C — BACKLOG four-bucket classification + #38 / #39
Lane D — blocks.html / prose.html Pilot spec verification
Lane E — v3.6.0 Pilot handoff doc
Lane F — Phase 3 smoke checklist
```

Execution order:

```txt
A -> B -> D -> C -> E -> F
```

Rationale:

- Carousel scope should be settled before Pilot handoff.
- Process lessons should be active before smoke/spec verification.
- blocks/prose verification informs Pilot handoff.
- BACKLOG classification records any spec gaps found.

## §2. Lane A — Carousel Matrix Reroute

### Files

```txt
docs/v3.5.0/MODULE-STATUS-MATRIX.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
CHANGELOG.md
```

### Matrix Amendment Notice

Add after the v3.5.13 amendment block:

```txt
> **v3.5.18 amendment**: Carousel #34 is not reopened. Its v3.5.12
> Full-Spec + Interaction closure remains historical evidence, but Carousel is
> marked Pilot-excluded / plugin-routed for v3.6.0. `lab/modules/carousel/` is
> retained as the plugin extraction seed; see BACKLOG #38. Component status
> counts are unchanged: 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.
```

### Canonical Count Note

Keep count unchanged:

```txt
13 DONE includes Carousel #34 as historical closure.
Pilot consumption excludes Carousel.
```

### Row #34 Notes Update

Append to row #34 Notes:

```txt
v3.5.18 amendment: Pilot-excluded / plugin-routed. `lab/modules/carousel/`
is retained as BACKLOG #38 extraction seed; v3.6.0 theme Pilot does not consume
Carousel.
```

### §6 Status Distribution

No count change. Add short note after table:

```txt
Carousel #34 remains in DONE count as v3.5.12 historical closure, but v3.6.0
Pilot scope excludes it.
```

### §9 One-Line Summary

Update current wording from "v3.5.12 updates..." to include v3.5.18 amendment
without changing arithmetic.

## §3. Lane B — Lesson Lock

### Files

```txt
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

### AGENTS.md Insertion

Add under `## Plan-first protocol` after the existing seven plan bullets:

```md
### User Request Log — Do Not Abstract Away

When the user gives concrete UX, behavior, or acceptance requirements, preserve
them as a `User Request Log` in the plan. Do not compress them into generic lane
titles. Phase close is blocked until those explicit requests are verified or
the user explicitly defers them.
```

Add under `## Reporting protocol` or a new QA subsection:

```md
### Global portal / overlay smoke test

If a change touches page shell, publish mirror, global runtime, trigger buttons,
overlays, portals, dialogs, sheets, drawers, popovers, menus, tooltips, or
snackbars, Phase 3 QA must verify:

1. trigger exists;
2. runtime handler attaches;
3. host / portal element exists;
4. open and visible state works;
5. close / dismiss path works;
6. console and page errors are absent.
```

### CLAUDE.md Insertion

Add equivalent wording under `## Operating principles`, using Claude-oriented
language:

```md
8. **User Request Log.** Do not abstract concrete user requests into generic
   phase lanes. Preserve them as explicit acceptance criteria and verify them
   before close.
9. **Portal / overlay smoke.** Shell or runtime-trigger changes require
   trigger + runtime + host + open/close contract verification.
```

### Grounding Pack Insertion

Add a new section after `§3 — Discipline patterns` or near the Phase workflow
section:

```md
### v3.5.16 / v3.5.17 process lessons

1. User Request Log — Do Not Abstract Away
   - v3.5.16 closed framing work but missed concrete mobile shell requests.
   - v3.5.17 preserved those requests as acceptance criteria and passed.

2. Global portal/overlay smoke test
   - v3.5.17 Dialog/Sheet buttons existed but #sg-portal was missing.
   - JS returned silently, validator did not catch it, and QA scope missed it.
   - Any trigger/runtime/host split must be smoke-tested end to end.
```

## §4. Lane D — blocks.html / prose.html Spec Verification

### Files

Read:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/stylesheets/prose.css
styleguide/blocks.html
styleguide/prose.html
```

Write:

```txt
docs/v3.5.18/BLOCKS-PROSE-PILOT-SPEC-VERIFY.md
```

### Verification Checklist

```txt
blocks.html:
  □ declares its role as WP core block coverage extension
  □ specimens correspond to selectors in blocks.css
  □ no obvious missing Pilot-critical core block family is discovered
  □ theme.js reference is satisfied in generated /styleguide/
  □ no console/page errors in smoke run

prose.html:
  □ declares its role as post body rendering contract
  □ specimens correspond to selectors in prose.css
  □ inline elements / headings / lists / quotes / tables / media are represented
  □ theme.js reference is satisfied in generated /styleguide/
  □ no console/page errors in smoke run
```

### Fix Rule

```txt
Small stale copy/spec label mismatch:
  patch in-cycle.

Pilot-blocking missing coverage:
  record as blocker and ask before v3.6.0.

Shell/mobile cosmetic inconsistency:
  do not patch; route to BACKLOG #39.
```

## §5. Lane C — BACKLOG Classification

### Files

```txt
BACKLOG.md
```

### Add #38

Use the Phase 0 report wording for `Carousel plugin extraction`.

### Add #39

Use the Phase 0 report wording for `Styleguide shell consistency —
blocks.html + prose.html`.

### Classification Section

Add or update a `Pre-Pilot classification` table near the backlog summary:

```txt
Pilot-before:
  none unless blocks/prose verification finds a blocker.

Post-Pilot:
  #2, #3, #19, #29, #30, #34, #35, #39

Plugin territory:
  #6, #21, #38

Deferred / ongoing:
  #5, #7, #14, #16, #18, #20, #22, #23, #36, #37
```

## §6. Lane E — v3.6.0 Pilot Handoff

### File

```txt
docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md
```

Create `docs/v3.6.0/` if needed.

### Required Sections

```txt
§0 Verdict
§1 Scope
§2 Explicit exclusions
§3 Consumed public surface
§4 Architecture boundary
§5 Expected file tree
§6 Lane assignment
§7 Validation plan
§8 Risks
§9 Entry checklist
```

### Scope Lock

```txt
Theme-only.
No Carousel plugin/block.
No custom block registration unless explicitly approved.
Consumes:
  - Wave 1 minus Carousel;
  - blocks.html as non-component WP block coverage spec;
  - prose.html as post body rendering contract;
  - infrastructure providers: popover/ripple/icon-system;
  - WP-MAPPING docs from Wave 1.
```

### Lane Assignment

```txt
Codex:
  implementation and validation.

Opus/GPT:
  ontology review after Codex implementation.

Codex:
  correction pass after review.
```

## §7. Lane F — Smoke Test Checklist

### File

```txt
docs/v3.5.18/PRE-PILOT-SMOKE-CHECKLIST.md
```

### Targets

```txt
styleguide/index.html:
  Dialog basic/full
  Sheet bottom/side
  styleguide drawer
  theme switcher

styleguide/blocks.html:
  render
  theme.js present
  no console/page errors

styleguide/prose.html:
  render
  theme.js present
  no console/page errors

products/reference-implementations/axismundi-lab/typography-axis.html:
  render
  axis controls collapse/open

lab/modules/*/lab-*-pattern.html:
  render smoke for 16 current pattern files
```

### Automation

Use Playwright via local Node script or one-off command. No screenshot artifacts
should be committed.

## §8. Validation Sequence

Before edits:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
```

After docs edits:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
```

If publish source changes:

```powershell
python .\tools\generators\publish_styleguide.py
```

Expected: validator 1.000 / 1.000 / 1.000 / 1.000 PASS.

## §9. Non-Goals

- Do not edit `components.css`, `tokens.css`, `blocks.css`, `prose.css`, or
  `theme.json` unless blocks/prose verification finds a small Pilot-blocking
  spec mismatch and user approves.
- Do not implement Carousel plugin extraction.
- Do not scaffold the Pilot theme.
- Do not rebuild `blocks.html` / `prose.html` shells.
- Do not resolve unrelated BACKLOG items.
- Do not start Wave 2.

## §10. Phase 2 Entry Criteria

Proceed to Phase 2 execution only after user approval of:

```txt
□ Matrix amendment wording
□ Lesson lock wording
□ blocks/prose verification scope
□ BACKLOG #38/#39 creation
□ v3.6.0 handoff doc structure
□ smoke checklist targets
```

## §11. Verdict

Phase 1 plan is ready for review.

Expected Phase 2 output:

```txt
Docs-only changes, except tiny approved spec-copy fixes if blocks/prose
verification finds them.
No baseline styling changes.
No Pilot implementation.
```

## §12. Phase 5 Close

Closed in v3.5.18 Phase 5. Phase 2 executed the approved lanes with docs/process
edits plus one in-cycle prose mobile spec fix. Phase 3 accepted the result and
routed the remaining `sg-sidebar` shell inconsistency to BACKLOG #39.
