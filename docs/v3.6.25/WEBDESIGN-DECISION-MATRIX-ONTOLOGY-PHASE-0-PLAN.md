# v3.6.25 Phase 0 Plan - Webdesign Decision Matrix Ontology

Date: 2026-05-24

## Verdict

Proceed to Phase 1 diagnostic only.

v3.6.25 exists to turn reusable webdesign-development methodology notes into a
Decision Matrix and ontology input before TT5, Google Sites, Pilot template, or
distributable work uses those references.

This is not a page-template implementation cycle.

## Strategic Framing

The user reframed the post-v3.6.24 path:

```txt
1. Webdesign development methodology documentation refinement
2. Decision Matrix
3. Ontology
4. Harness / Pilot application
```

This supersedes the earlier route that would have moved directly from the
catalog full spec into distributable skeleton or Pilot template work.

WP block styleguide human visual QA is not a v3.6.25 gate. The user accepted
the current catalog state for now and routed human visual QA to a later moment
after additional full-spec gaps are filled.

Reason:

```txt
External references such as TT5 or an existing Google Site need an evaluation
framework before they become implementation input. Otherwise the project risks
copying layout choices without a reusable decision model.
```

## Source Inputs

Local sources:

```txt
corpus/source/webdesign-craftsman-2026/CONTENTS-OUTLINE.md
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-5-CLOSE.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-5-CLOSE.md
CURRENT-STATE.md
NEXT-SESSION.md
ROADMAP.md
```

Memory guardrails:

```txt
project-axismundi-goal-direct-vs-framework-completeness
project-axismundi-source-of-authority-inventory
project-axismundi-tracked-copy-mirror-handling-framework
project-axismundi-theme-switcher-selector-ownership
project-axismundi-theme-switcher-separation
```

Previously established user rule:

```txt
The written webdesign material is a workflow ontology source, not direct
page-layout source.
```

## Scope

Phase 1 diagnostic should produce:

```txt
1. Source inventory for the user-provided book outline and future notes.
2. A cleaned documentation structure for note intake.
3. A draft Decision Matrix shape.
4. A routing recommendation for corpus -> atlas -> core.
5. A decision on whether a new decisions/ layer is needed now, later, or not.
6. A Phase 2 route recommendation.
```

Phase 2 candidate work, depending on Phase 1 evidence:

```txt
Route A: no-code decision only
Route B: corpus documentation seed only
Route C: corpus + atlas workflow map
Route D: corpus + atlas + core ontology stub
Route E: decisions/ layer proposal only
Route F: bounded harness-readiness doc only
```

Expected route:

```txt
Route D-lite:
  whole 7-part corpus seed + Decision Matrix + core/atlas stubs for the
  highest-priority workflow concepts, while decisions/ remains a routed
  question rather than a new layer created by default.

Why:
  Big-bang 7-part corpus + atlas + core + decisions/ is too large.
  Layer-by-layer or part-by-part splitting would add too many indirect cycles.
  Route D-lite keeps the user's documentation -> matrix -> ontology sequence
  intact while deferring harness/Pilot application.
```

## Non-Goals

v3.6.25 must not:

```txt
1. Implement Pilot templates.
2. Create or modify distributable skeleton files.
3. Copy TT5 code or design patterns.
4. Extract Google Sites layout/components.
5. Add page prototypes.
6. Modify styleguide HTML/CSS/JS.
7. Modify Pilot theme templates, patterns, functions.php, or theme.json.
8. Add release-seal derivatives.
9. Create wp.org submission files.
10. Decide product slug, text domain, namespace, or distributable identity.
11. Treat the written exam book as page-layout authority.
12. Paste copyrighted book body text into the repository.
13. Create a broad `knowledge/` umbrella.
14. Add a `decisions/` layer without explicit evidence and route decision.
15. Collapse corpus, atlas, and core into one document.
16. Run WP block styleguide human visual QA as a blocking gate.
```

## Proposed Documentation Ladder

```txt
documentation refinement:
  preserve user notes, clean headings, normalize terms, identify deliverables

Decision Matrix:
  map each concept to a decision point, input, output, owner, and evidence gate

Ontology:
  promote stable concepts into rule/entity structures only after matrix review

Harness / Pilot application:
  use the ontology to evaluate TT5, Google Sites, and Pilot template decisions
```

## Initial Corpus Classification

```txt
PART 01 -> intake / reference / project framing
PART 02 -> prototype assets / visualization / usability loop
PART 03 -> IA / wireframe / storyboard / responsive / standards / a11y
PART 04 -> implementation process / interface / function / trend
PART 05 -> color model and color inspection vocabulary
PART 06 -> palette planning / role / harmony / functional color
PART 07 -> deliverable collection / preservation / final report
```

Phase 1 must test this classification. It is not locked.

## Priority Order

Phase 1 should prioritize parts by near-term template/prototype usefulness:

```txt
Priority 1:
  PART 03 Design components
  PART 02 Prototype production

Priority 2:
  PART 04 Implementation and application

Priority 3:
  PART 07 Project close materials

Priority 4:
  PART 05 Color mixing
  PART 06 Color scheme

Priority 5:
  PART 01 Data / reference intake
```

This priority order does not remove any part from corpus inventory. It only
guides which sections become matrix / ontology candidates first.

## Decision Matrix Draft Shape

Phase 1 should test this matrix schema:

```txt
matrix_id
source_part
source_chapter
source_section
workflow_stage
decision_point
input_evidence
output_artifact
applies_to
blocked_by
Axismundi_route
G1_G2_relevance
notes
```

Expected `workflow_stage` candidates:

```txt
intake
concept
prototype
usability
IA
wireframe
storyboard
visual_design
responsive
standards_accessibility
implementation
color_system
palette_system
handoff_report
```

Expected `Axismundi_route` candidates:

```txt
corpus
atlas
core
decisions_candidate
Pilot_harness_later
TT5_audit_later
Google_Sites_extraction_later
distributable_later
out_of_scope
```

## Corpus / Atlas / Core Policy

```txt
corpus:
  user notes, source outline, cleaned summaries, page references, examples

atlas:
  workflow rules and decision heuristics that can be reused across projects

core:
  stable typed concepts only, such as WorkflowStage, Deliverable,
  EvaluationGate, PrototypeType, LayoutDecision, ColorRole

decisions/:
  not created by default. Phase 1 must decide whether Axismundi-specific
  decision records need a separate layer or can remain in docs/v3.6.25.
```

Candidate path shape for Phase 1 to evaluate:

```txt
corpus/webdesign-craftsman-written/
  raw/
  summaries/
  terms/
  workflow-notes/

atlas/web-production-workflow/
  workflow-atlas.md
  deliverable-atlas.md
  decision-point-atlas.md
  quality-check-atlas.md

core/web-production/
  workflow-rules/
  deliverable-rules/
```

The existing source seed is currently stored under:

```txt
corpus/source/webdesign-craftsman-2026/CONTENTS-OUTLINE.md
```

Phase 1 must decide whether that remains the canonical source location or is
copied / promoted into a new `corpus/webdesign-craftsman-written/` refined
tree during Phase 2.

## Diagnostic Questions

```txt
Q1. Does the source outline support a clean workflow sequence?
Q2. Which sections are reusable methodology versus exam-only terminology?
Q3. Which parts map to Axismundi's immediate page/template work?
Q4. Which parts should become corpus notes only?
Q5. Which concepts are stable enough for atlas rules?
Q6. Which concepts are stable enough for core ontology entities?
Q7. Is a decisions/ layer needed now?
Q8. What Decision Matrix fields are necessary and sufficient?
Q9. How should later TT5 and Google Sites audits consume this matrix?
Q10. What evidence gate prevents page-layout decisions from being copied
     directly from written notes?
Q11. What Phase 2 route is narrowest while still useful?
Q12. Should Phase 2 use Route D-lite as expected, or narrow to corpus-only?
Q13. Should `decisions/` be created later, proposed only, or explicitly rejected
     for now?
```

## Risks

```txt
R1. Treating exam-book structure as product architecture.
R2. Turning written notes into page-layout source.
R3. Creating an overbroad knowledge/ umbrella.
R4. Creating decisions/ before its ownership is clear.
R5. Over-ontologizing terms that should remain corpus notes.
R6. Skipping the Decision Matrix and jumping straight to TT5 / Google Sites.
R7. Mixing G1 Pilot progress and G2 wp.org release progress without naming the
    trade-off.
R8. Copying copyrighted body text instead of user-authored notes/summaries.
R9. Letting this cycle absorb Pilot template implementation.
R10. Producing a matrix that is too abstract to guide harness/application work.
R11. Splitting the 7 parts into too many indirect cycles and delaying G2 direct
     progress without clear framework gain.
R12. Running visual QA now and turning v3.6.25 into a catalog polish cycle
     instead of a methodology ontology cycle.
```

## Lock 5 Count Expectation

```txt
v3.6.24: 14th overall / 9th implementation-cycle, narrow implementation
v3.6.25: 15th overall / conditional implementation-cycle

No-code or documentation-only decision:
  15th overall / 9th implementation-cycle unchanged

Narrow documentation/ontology implementation:
  15th overall / 10th implementation-cycle if new corpus/atlas/core files are
  added as durable project artifacts
```

## Phase 4 Policy

Phase 4 is not expected.

Use Phase 4 only if Phase 1 finds that a new top-level `decisions/` layer, a
new ontology namespace, or cross-layer architecture policy must be audited
before Phase 2.

## Phase 1 Entry Criteria

Phase 1 may start when the reviewer approves this plan.

Expected Phase 1 report:

```txt
docs/v3.6.25/WEBDESIGN-DECISION-MATRIX-ONTOLOGY-PHASE-1-REPORT.md
```

Phase 1 must remain read-only except for the report itself.
