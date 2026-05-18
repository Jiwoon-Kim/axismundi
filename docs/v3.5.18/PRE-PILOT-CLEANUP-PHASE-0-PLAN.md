# Axismundi v3.5.18 — Pre-Pilot Cleanup Phase 0 Plan

Status: CLOSED — Phase 5 complete  
Date: 2026-05-18  
Cycle: v3.5.18 pre-pilot cleanup + Carousel reroute  
Scope: docs-only plan before v3.6.0 Ontology Theme Pilot

## §0. Why This Cycle Exists

v3.5.17 closed the styleguide shell correction cycle and surfaced two lessons
that should be locked before the larger v3.6.0 Pilot:

1. User-specific requests must not be abstracted away into generic lane names.
2. Global portal/overlay runtimes can fail silently when trigger buttons,
   runtime JS, and host elements are split across files.

The same routing discussion also clarified that Carousel should not block the
theme pilot. Carousel needs responsive/runtime improvement and is better
handled as plugin territory.

## §1. Verdict Preview

Recommended Phase 0 direction:

```txt
Proceed with v3.5.18 as a small docs-only pre-pilot cleanup cycle.

Do:
  - reroute Carousel out of the theme pilot path;
  - formalize v3.5.16/v3.5.17 process lessons;
  - verify `blocks.html` and `prose.html` as Pilot specification inputs;
  - classify remaining BACKLOG items before Pilot;
  - write a v3.6.0 Pilot handoff doc.

Do not:
  - extract the Carousel plugin yet;
  - edit baseline CSS/tokens;
  - start the WordPress Pilot implementation;
  - reopen styleguide modernization as a dedicated release.
```

## §2. Inputs To Read

```txt
Required:
  AGENTS.md
  CLAUDE.md
  CURRENT-STATE.md
  NEXT-SESSION.md
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
  docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md
  docs/v3.5.17/STYLEGUIDE-SHELL-REBUILD-PHASE-0-REPORT.md
  BACKLOG.md
  ROADMAP.md

Read selectively:
  docs/v3.5.12/CAROUSEL-*-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/carousel/*
```

## §3. Lane Structure

### Lane A — Carousel Reroute

Goal: classify Carousel as theme-pilot-excluded and plugin-routed without
rewriting history.

Decisions Phase 0 must settle:

```txt
1. Matrix status wording:
   Option A — introduce new status enum REROUTED-TO-PLUGIN.
   Option B — keep DONE (v3.5.12) but add v3.5.18 amendment note:
              "DONE as lab evidence; Pilot-excluded / plugin-routed."
   Option C — move Carousel out of component count entirely.

Recommendation:
  Option B.

Why:
  - v3.5.12 work remains valid historical evidence.
  - 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD arithmetic remains stable.
  - Pilot scope can honestly exclude Carousel without inventing a new matrix
    status enum or rewriting Wave 1.
```

Required outcome:

```txt
Carousel #34:
  status remains DONE (v3.5.12 historical closure);
  notes gain v3.5.18 amendment:
    "plugin-routed; excluded from v3.6.0 theme pilot; lab module retained as
     extraction seed."

Wave 1:
  remains 9 / 9 historical closure;
  v3.6.0 Pilot consumes Wave 1 minus Carousel.
```

### Lane B — Lesson Lock

Goal: formalize two process lessons before v3.6.0.

Lesson 1 — User Request Log:

```txt
If the user gives concrete UX/behavior requirements, Phase 0 plans must preserve
them as explicit acceptance criteria. Do not collapse them into generic lane
names.
```

Lesson 2 — Global Portal / Overlay Smoke Test:

```txt
If a change touches a shell, page-level runtime, publish mirror, or any trigger
surface, Phase 3 QA must verify:
  - trigger button/attribute exists;
  - runtime handler attaches;
  - host/portal element exists;
  - visible/open state contract works;
  - close/dismiss path works;
  - console/page errors are absent.
```

Known v3.5.17 failure mode:

```txt
Dialog/Sheet buttons existed.
style-guide.js existed.
#sg-portal host was missing.
JS returned silently before attaching handlers.
Validator did not detect it.
Playwright acceptance did not include Dialog/Sheet live triggers.
User QA focused on shell/mobile typography-axis.
```

Candidate edit targets:

```txt
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
docs/v3.5.0/PROMOTION-CRITERIA.md (only if Phase 0 confirms this is the right
  canonical location for Phase 3 QA standards)
```

### Lane C — BACKLOG Hygiene

Goal: classify open items into four buckets before Pilot.

Buckets:

```txt
Pilot-before:
  Must close before v3.6.0. Expected count: 0 unless Phase 0 finds a blocker.

Post-Pilot:
  Still valuable, but not needed before theme proof:
    #2 Avatar size tokens
    #3 Floating toolbar selected color
    #19 Date picker grid a11y
    #29 Card behavior patterns
    #30 Extended FAB behavior patterns

Plugin territory:
  Behavior/runtime or block-specific work outside the theme shell:
    NEW #38 Carousel plugin extraction
    #21 M3 Interpreter Plugin
    future Gallery/Carousel binding if needed

Deferred / ongoing:
  Styleguide maintenance, v4.0 directory restructure, dogfooding, legacy cleanup.
```

### Lane D — Blocks / Prose Specification Verification

Goal: verify the two non-component theme product specification surfaces before
Pilot.

Correct framing:

```txt
style-guide.html = component chrome catalog and public visual demo.
blocks.html      = WordPress core block coverage extension for blocks that do
                   not map cleanly to component modules.
prose.html       = WordPress post-body rendering contract.
```

These are not merely documentation pages. They are Pilot reference surfaces.

Phase 1/2 must decide:

```txt
1. Does blocks.html still match blocks.css?
2. Does blocks.html cover the current non-component core block surfaces needed
   for the Pilot?
3. Does prose.html still match prose.css?
4. Does prose.html reflect current typography / spacing / inline element token
   contracts?
5. Are any gaps Pilot blockers?
```

Action rule:

```txt
Pilot blocker / spec gap:
  fix in-cycle if small, or explicitly block v3.6.0.

Cosmetic shell inconsistency:
  defer to BACKLOG #39.
```

### Lane E — v3.6.0 Pilot Handoff

Goal: write a short handoff that lets v3.6.0 begin cleanly.

Expected doc:

```txt
docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md
```

Required sections:

```txt
1. Pilot scope:
   - WordPress block theme proof;
   - theme-only;
   - no Carousel block/plugin;
   - no custom block registration unless explicitly approved.

2. Consumed public surface:
   - Wave 1 minus Carousel;
   - blocks.html as non-component WP block coverage spec;
   - prose.html as post-body rendering spec;
   - infrastructure: popover/ripple/icon-system;
   - mappings: WP-MAPPING docs from Wave 1.

3. Architecture boundary:
   - Charter §3.4 pilot lives outside the theme repo;
   - theme consumes public surface, does not redefine ontology.

4. Lane assignment:
   - Codex implementation;
   - Opus/GPT ontology review;
   - Codex correction pass.

5. Initial deliverables:
   - block theme file tree;
   - `theme.json` consumption strategy;
   - templates/patterns;
   - functions enqueue surface;
   - no Carousel plugin.
```

## §4. New BACKLOG Item

Phase 0 should open:

```txt
#38 Carousel plugin extraction
Bucket: F — Plugin/runtime extraction
Status: Open
Target: v3.6.x parallel or post-Pilot
Source: v3.5.18 Carousel reroute
Scope:
  - extract Carousel into a WordPress plugin/block;
  - use lab/modules/carousel/ as seed;
  - improve responsive behavior, runtime logic, ARIA, reduced motion;
  - keep theme pilot independent from Carousel.
```

## §5. Files To Modify In Phase 1/2

Expected docs-only edit scope:

```txt
docs/v3.5.18/PRE-PILOT-CLEANUP-PHASE-0-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
BACKLOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
CHANGELOG.md
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
```

Phase 0 report may narrow this list.

## §6. Explicit Non-Goals

- Do not extract or scaffold the Carousel plugin in v3.5.18.
- Do not edit `components.css`, `tokens.css`, `blocks.css`, or `theme.json`.
- Do not remove or rewrite v3.5.12 Carousel audit docs.
- Do not delete `lab/modules/carousel/`.
- Do not start v3.6.0 Pilot implementation.
- Do not reopen styleguide modernization as a dedicated release.
- Do not treat `blocks.html` / `prose.html` as cosmetic docs only; verify them
  as Pilot input specs.
- Do not create new component modules.
- Do not change the GitHub Pages source configuration.

## §7. Validation

Expected commands:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
git status --short
```

No Playwright requirement is expected for v3.5.18 because this is docs-only.

## §8. Risks

| Risk | Mitigation |
| --- | --- |
| Carousel reroute looks like history rewrite | Preserve v3.5.12 DONE history; add v3.5.18 Pilot-excluded/plugin-routed note |
| Matrix arithmetic confusion | Prefer amendment note over new status enum |
| Lesson lock becomes vague | Quote v3.5.16/v3.5.17 failure modes directly |
| Pilot handoff accidentally starts implementation | Keep handoff doc declarative; no new theme files |
| BACKLOG hygiene becomes a large cleanup release | Only classify; do not resolve unrelated items |
| blocks/prose get misclassified as cosmetic docs | Verify spec correctness now; defer only shell consistency |

## §9. Phase 0 Report Questions

Phase 0 report must answer:

1. Which Matrix treatment is chosen for Carousel reroute?
2. Does Wave 1 remain 9/9 historical closure?
3. Where exactly should User Request Log discipline live?
4. Where exactly should portal/overlay smoke test discipline live?
5. Are there any true Pilot-before blockers?
6. What is the exact text of BACKLOG #38?
7. Do blocks.html / prose.html have Pilot-blocking spec gaps?
8. What does v3.6.0 consume and explicitly exclude?

## §10. Verdict

Proceed to Phase 0 report if approved.

Recommended route:

```txt
v3.5.18 = docs/spec-only pre-pilot cleanup.
Carousel remains historically DONE but becomes Pilot-excluded / plugin-routed.
blocks.html and prose.html are verified as Pilot input specs, not dismissed as
cosmetic docs.
v3.6.0 Pilot starts after this with theme-only scope.
```

## §11. Phase 5 Close

Closed in v3.5.18 Phase 5. The cycle completed as planned: Carousel was
amended as Pilot-excluded / plugin-routed without rewriting v3.5.12 history,
lesson locks were added to the agent context plane, blocks/prose were verified
as Pilot specification surfaces, and v3.6.0 received a theme-only handoff.
