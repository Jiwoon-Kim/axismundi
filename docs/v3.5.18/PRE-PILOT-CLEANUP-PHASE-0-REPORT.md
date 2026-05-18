# Axismundi v3.5.18 — Pre-Pilot Cleanup Phase 0 Report

Status: CLOSED — Phase 5 complete  
Date: 2026-05-18  
Cycle: v3.5.18 pre-pilot cleanup + Carousel reroute  
Input: `PRE-PILOT-CLEANUP-PHASE-0-PLAN.md`

## §0. Verdict

v3.5.18 should proceed as a **docs-only pre-pilot cleanup** cycle.

Locked direction:

```txt
1. Carousel remains historically DONE at v3.5.12, but is amended as
   Pilot-excluded / plugin-routed for v3.6.0.
2. v3.6.0 Ontology Theme Pilot consumes Wave 1 minus Carousel.
3. Carousel plugin extraction becomes BACKLOG #38, not v3.5.18 work.
4. User Request Log + Global portal/overlay smoke test become process rules.
5. BACKLOG items are classified for Pilot-before / post-Pilot /
   plugin territory / deferred.
6. blocks.html / prose.html are verified as Pilot specification references,
   not treated as cosmetic docs.
```

## §1. Carousel Amendment Decision

### Decision

Use **amendment note**, not a new status enum.

```txt
Matrix row #34 status remains:
  DONE (v3.5.12)

v3.5.18 amendment adds:
  Pilot-excluded; plugin-routed; lab/modules/carousel/ retained as plugin
  extraction seed. See BACKLOG #38.
```

### Why

This preserves history without pretending Carousel is ready for the theme pilot.

```txt
Keep:
  - v3.5.12 4-doc audit set;
  - reduced-motion + Home/End fixes;
  - lab-carousel.css/js/pattern.html as real evidence;
  - Wave 1 historical closure.

Change:
  - v3.6.0 Pilot does not consume Carousel;
  - future Carousel work occurs in a plugin/block lifecycle.
```

### Rejected Options

| Option | Rejected because |
| --- | --- |
| New `REROUTED-TO-PLUGIN` status enum | Adds matrix complexity for one row and destabilizes distribution arithmetic. |
| Move Carousel out of the component count | Rewrites Wave 1 history and makes v3.5.12 look invalid. |
| Keep Carousel in Pilot scope | Pulls runtime/responsive/block behavior into the theme proof and delays v3.6.0. |

## §2. Wave 1 Accounting Lock

Wave 1 remains **9 / 9 historically closed**.

Pilot scope is a different statement:

```txt
Wave 1 closure history:
  9 / 9, including Carousel #34.

v3.6.0 Pilot consumption:
  Wave 1 minus Carousel.
```

Matrix distribution remains unchanged:

```txt
13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD
```

## §3. Carousel Matrix Wording

Proposed `MODULE-STATUS-MATRIX.md` row #34 note addition:

```txt
v3.5.18 amendment: Carousel remains historically DONE as a v3.5.12 lab
Full-Spec + Interaction closure, but is excluded from v3.6.0 theme pilot and
routed to plugin/block extraction. `lab/modules/carousel/` is retained as the
plugin extraction seed; see BACKLOG #38.
```

Proposed matrix amendment notice:

```txt
v3.5.18 amendment: Carousel #34 is not reopened. Its v3.5.12 closure remains
historical evidence, but it is marked Pilot-excluded / plugin-routed for
v3.6.0. Component status counts remain unchanged.
```

## §4. BACKLOG #38 Draft

```txt
### 38. Carousel plugin extraction

- Bucket: F — Plugin/runtime extraction
- Status: Open
- Priority: Medium after v3.6.0 Pilot entry
- Target: v3.6.x parallel or post-Pilot
- Source: v3.5.18 Carousel reroute

Scope:
  - Extract Carousel into a WordPress plugin/block lifecycle.
  - Use `lab/modules/carousel/` as seed evidence, not as final plugin code.
  - Improve responsive behavior, runtime navigation, ARIA, reduced motion, and
    block integration.
  - Keep v3.6.0 Ontology Theme Pilot independent from Carousel.

Non-goals:
  - Do not change v3.5.12 audit history.
  - Do not include Carousel in the theme-only Pilot.
  - Do not implement the plugin in v3.5.18.
```

## §5. Lesson Lock Location

### User Request Log

Edit locations:

```txt
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Protocol wording:

```txt
If a user gives concrete UX, behavior, or acceptance requirements, preserve
them as a "User Request Log" in Phase 0. Do not compress them into generic
lane names. Phase 5 close is blocked until those explicit requirements are
verified or the user explicitly defers them.
```

Source cases:

```txt
v3.5.16:
  styleguide modernization closed on lane completion, but missed the requested
  mobile top app bar, Sheet-style drawer, icon theme switcher, and body polish.

v3.5.17:
  User Request Log + acceptance checklist forced direct implementation and
  produced the first user-satisfaction pass.
```

### Global Portal / Overlay Smoke Test

Edit locations:

```txt
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Protocol wording:

```txt
If a change touches a page shell, publish mirror, global runtime, trigger
button, overlay, portal, or host element, Phase 3 QA must verify the full
contract:
  1. trigger exists;
  2. runtime handler attaches;
  3. host/portal element exists;
  4. open/visible state works;
  5. close/dismiss path works;
  6. console/page errors are absent.
```

Source case:

```txt
v3.5.17:
  Dialog/Sheet trigger buttons existed, but #sg-portal was missing. JS returned
  silently before attaching handlers. Validator and user shell QA missed it.
  Hotfix 81d0317 restored the portal and added live-trigger verification.
```

## §6. blocks.html / prose.html Specification Decision

Previous framing corrected:

```txt
blocks.html is NOT merely a docs page.
prose.html is NOT merely a docs page.
```

Pilot role:

```txt
style-guide.html:
  Component chrome catalog and public visual demo.

blocks.html:
  WordPress core block coverage extension for block surfaces that do not map
  cleanly to component modules.

prose.html:
  WordPress post-body rendering contract: typography, spacing, inline elements,
  lists, quotes, tables, media, and content rhythm.
```

Therefore v3.5.18 must verify their **spec correctness** before v3.6.0.

Required Phase 1/2 checks:

```txt
blocks.html:
  □ matches `blocks.css`
  □ reflects current theme block coverage
  □ identifies non-component core block gaps that affect Pilot
  □ has no stale contract that would mislead Pilot implementation

prose.html:
  □ matches `prose.css`
  □ reflects current typography tokens
  □ reflects current spacing / inline / list / quote / table contracts
  □ has no stale contract that would mislead Pilot post rendering
```

Action rule:

```txt
Spec gap that blocks Pilot:
  fix in v3.5.18 if small; otherwise explicitly block v3.6.0.

Cosmetic shell inconsistency:
  defer to BACKLOG #39.
```

## §7. Phase 3 Smoke Standard

Reusable checklist:

```txt
□ trigger button/data attribute exists
□ runtime handler attaches
□ host/portal element exists
□ open state visibly applies
□ close button works
□ Escape/backdrop/scrim path works where applicable
□ no console/page errors
□ source and generated mirror agree
```

Initial v3.5.18 smoke targets:

```txt
styleguide/index.html:
  Dialog basic/full live buttons
  Sheet bottom/side live buttons
  styleguide drawer
  Snackbar trigger if present

styleguide/blocks.html:
  render smoke
  theme switcher/theme.js presence
  console/page error check

styleguide/prose.html:
  render smoke
  theme switcher/theme.js presence
  console/page error check

products/reference-implementations/axismundi-lab/typography-axis.html:
  render smoke
  collapsible axis controls

lab/modules/*/lab-*-pattern.html:
  render smoke only unless Phase 0 finds a known live trigger.
```

## §8. blocks.html / prose.html Shell Decision

Do **not** rebuild these shells in v3.5.18.

Rationale:

```txt
styleguide/index.html:
  canonical component showcase, already rebuilt in v3.5.17.

blocks.html:
  Pilot specification reference, but not the public shell priority.

prose.html:
  Pilot post-body rendering specification, but lower public shell priority.
```

v3.5.18 action:

```txt
Spec correctness verification required.
Critical broken runtime or Pilot-blocking stale spec: fix in-cycle.
Cosmetic shell inconsistency: defer to BACKLOG #39.
```

## §9. BACKLOG #39 Draft

```txt
### 39. Styleguide shell consistency — blocks.html + prose.html

- Bucket: E — Public surface / docs maintenance
- Status: Open
- Priority: Low, post-Pilot unless a broken interaction is found
- Target: Ongoing maintenance
- Source: v3.5.18 pre-pilot smoke scope

Scope:
  - Consider applying the v3.5.17 styleguide-local shell pattern to
    `blocks.html` and `prose.html`.
  - Align mobile top bar, theme switcher, and reading polish if/when those
    pages become public showcase surfaces.

Non-goals:
  - Does not own spec correctness for blocks/prose; v3.5.18 verifies that
    before Pilot.
  - Not required before v3.6.0 Pilot.
  - Do not block theme implementation.
  - Do not introduce unclosed Wave 2 navigation components.
```

## §10. BACKLOG Four-Bucket Classification Draft

### Pilot-before

Expected: none.

Criteria:

```txt
Blocks v3.6.0 if it prevents the theme-only pilot from being created, loaded,
or validated.
```

### Post-Pilot

```txt
#2  Avatar size token consistency
#3  Floating toolbar selected color
#19 Date Picker Grid Navigation A11y
#29 Card behavior patterns
#30 Extended FAB behavior patterns
#34 residual N3 module picker/dialog UX
#35 Root index Korean version and language toggle
#39 Styleguide shell consistency — blocks.html + prose.html
```

### Plugin Territory

```txt
#6  Monotone SVG theming plugin concept
#21 M3 Interpreter Plugin separation
#38 Carousel plugin extraction
```

### Deferred / Ongoing

```txt
#5  WordPress logo styleguide specimen
#7  Search bar leading icon known delta
#14 Material Symbols ligature layout shift
#16 Tooltip delay and touch long-press refinement
#18 Snackbar class naming inconsistency
#20 Theme-only color customization policy
#22 Explicit data-theme="auto" 3-state model
#23 Elevated Chip Variants
#36 v4.0 directory restructure
#37 GitHub Pages dogfooding
```

Phase 1 should verify all open items and amend this list if needed.

## §11. v3.6.0 Handoff Structure

Create:

```txt
docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md
```

Required structure:

```txt
§0 Verdict
§1 Scope
§2 Exclusions
§3 Consumed public surface
§4 Architecture boundary
§5 Expected file tree
§6 Lane assignment
§7 Validation plan
§8 Risks
§9 Entry checklist
```

Scope lock:

```txt
Theme-only.
No Carousel plugin/block.
No custom block registration unless explicitly approved.
Wave 1 minus Carousel + infrastructure providers.
style-guide.html = component chrome catalog.
blocks.html = non-component WP core block extension spec.
prose.html = post-body rendering contract.
Codex implementation -> Opus/GPT ontology review -> Codex correction pass.
```

## §12. Phase 1 Plan Shape

Phase 1 plan should define:

```txt
Lane A — Matrix + Carousel reroute doc edits
Lane B — Lesson lock doc edits
Lane C — BACKLOG classification + #38/#39
Lane D — blocks/prose spec verification
Lane E — v3.6.0 handoff doc
Lane F — Smoke test script / checklist for styleguide, blocks, prose,
          typography-axis, and lab patterns
```

## §13. Non-Goals Reconfirmed

- Do not extract Carousel plugin.
- Do not scaffold Pilot theme.
- Do not edit baseline CSS/tokens/blocks/theme.
- Do not rebuild `blocks.html` / `prose.html` shells.
- Do not dismiss `blocks.html` / `prose.html` as cosmetic docs; verify their
  Pilot-facing spec contracts.
- Do not reopen v3.5.12 Carousel docs except for cross-reference notes if
  Phase 1 explicitly chooses that path.
- Do not start Wave 2.
- Do not decide v4.0 architecture freeze.

## §14. Validation

Phase 0 report is docs-only.

Expected:

```txt
python .\tools\validators\validate_theme_pilot.py
git status --short
```

No publish regeneration is required until execution touches publish source or
script files.

## §15. Verdict

Proceed to Phase 1 plan.

Recommended execution:

```txt
Small docs-only release.
Keep Carousel history.
Exclude Carousel from Pilot.
Lock lessons.
Classify BACKLOG.
Verify blocks/prose as Pilot spec inputs.
Prepare v3.6.0 handoff.
Smoke-test docs surfaces, but do not redesign them.
```

## §16. Phase 5 Close

Closed in v3.5.18 Phase 5. The report's lock decisions were implemented:
Carousel remains historically DONE but Pilot-excluded, BACKLOG #38/#39 now own
plugin extraction and shell maintenance, blocks/prose are verified as Pilot
input specs, and the global portal/overlay smoke-test rule is now part of the
agent operating discipline.
