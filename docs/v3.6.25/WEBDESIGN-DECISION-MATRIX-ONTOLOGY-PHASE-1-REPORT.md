# v3.6.25 Phase 1 Report - Webdesign Decision Matrix Ontology

Date: 2026-05-25

## Verdict

APPROVE Phase 1 diagnostic.

Recommended Phase 2 route: **Route D-lite, narrowed**.

```txt
Route D-lite, narrowed:
  create durable corpus/refined + atlas + core seed artifacts from the four
  reviewed sections only:
    Section 1 - Project Concept / Structure
    Section 4 - IA / Wireframe / Storyboard
    Section 5 - Aesthetic / Usability Design
    Section 7 - Project Close / Deliverables + retention/disposal policy

  do not create decisions/ yet.
  do not implement Pilot templates.
  do not copy TT5 or Google Sites layouts.
  do not create distributable skeleton files.
```

Reason:

```txt
The four reviewed sections cover the required core:
  Section 1 = project/process/work-package vocabulary
  Section 4 = page structure and handoff frame
  Section 5 = visual/responsive/UX evaluation frame
  Section 7 = deliverable, release-readiness, and documentation retention frame

Sections 2/3/6 remain useful, but they are not required before Phase 2.
They should remain add-as-needed route-forward material.
```

## Read-Only Lock

Phase 1 remained diagnostic except for this report.

```txt
Working tree before report:
  ?? corpus/source/webdesign-craftsman-2026/
  ?? docs/v3.6.25/

Input line counts:
  SKELETON-RESEARCH-FILL.md  : 1516
  SKELETON.md                : 1062
  CONTENTS-OUTLINE.md        : 119
  Phase 0 plan               : 362
```

## Source Inventory

Local inputs:

```txt
dev/wdd.md
corpus/source/webdesign-craftsman-2026/CONTENTS-OUTLINE.md
corpus/source/webdesign-craftsman-2026/SKELETON.md
corpus/source/webdesign-craftsman-2026/SKELETON-RESEARCH-FILL.md
docs/v3.6.25/WEBDESIGN-DECISION-MATRIX-ONTOLOGY-PHASE-0-PLAN.md
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-5-CLOSE.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-5-CLOSE.md
```

Memory guardrails applied:

```txt
project-axismundi-goal-direct-vs-framework-completeness
project-axismundi-source-of-authority-inventory
project-axismundi-tracked-copy-mirror-handling-framework
project-axismundi-theme-switcher-selector-ownership
project-axismundi-theme-switcher-separation
written-material rule: workflow ontology source, not page-layout source
```

## Findings

### F1. Four-Section Core Is Sufficient

The reviewed sections form a coherent minimum viable ontology seed.

```txt
Section 1:
  PMBOK / MaRMI / UML / WBS vocabulary.
  Strongest artifact: PMBOK -> Axismundi phase mapping.

Section 4:
  IA / navigation / wireframe / layout / storyboard separation.
  Strongest artifact: minimal storyboard fields for future Pilot template pass.

Section 5:
  Gestalt / grid / responsive / UX / heuristics / WCAG separation.
  Strongest artifact: design review lenses that do not become visual doctrine.

Section 7:
  deliverables / release-readiness / retention-disposal policy.
  Strongest artifact: keep / archive / fold / restore_remove / route_forward.
```

This is enough to test the Decision Matrix schema without forcing Sections 2,
3, and 6 into the cycle.

### F2. Section 2/3/6 Should Remain Routed Forward

```txt
Section 2 - Ideation / Concept Visualization / Fidelity:
  keep as corpus material.
  Route Hyperbolic Tree and CodePen as decisions_candidate, not Phase 2 work.

Section 3 - Usability Testing:
  keep as corpus material.
  Much of it overlaps Section 5 heuristic / WCAG / human QA framing.

Section 6 - Process / Implementation:
  keep as corpus material.
  Much of it overlaps Section 1 PMBOK phase mapping and Section 7 lifecycle.
```

Phase 2 should not force all seven sections into durable ontology files.

### F3. Decision Matrix Needs Layered Schema, Not One Flat 55-Field Table

The prep work produced many useful fields. Flattening all of them into one
table would create a brittle matrix.

Recommended layered schema:

```txt
Base fields:
  matrix_id
  source_part
  source_chapter
  source_section
  user_note_present
  research_fill_present
  source_confidence
  copyright_classification
  workflow_stage
  decision_point
  input_evidence
  output_artifact
  applies_to
  blocked_by
  Axismundi_route
  G1_G2_relevance
  retention_policy
  notes

Section-specific extension fields:
  concept/project:
    methodology_source
    legacy_or_current
    process_group
    knowledge_area
    modeling_artifact
    work_package
    dependency

  IA/storyboard:
    page_or_template
    IA_type
    navigation_type
    layout_type
    WordPress_surface
    reusable_surface
    asset_dependency
    responsive_strategy
    acceptance_gate

  design/UX:
    design_principle
    grouping_rule
    layout_grid_primitives
    responsive_pattern
    breakpoint_basis
    media_performance_risk
    UX_dimension
    heuristic_gate
    accessibility_gate
    interaction_assumption
    token_or_layout_dependency

  deliverables/retention:
    deliverable_type
    deliverable_audience
    lifecycle_state
    source_or_generated
    disposal_policy
    traceability_requirement
    release_relevance
    submission_relevance
    maintenance_relevance
    artifact_boundary
```

This preserves expressiveness without making every row carry every field.

### F4. Retention Policy Is Now a First-Class Operating Framework

Section 7's retention/disposal amend should become a durable ontology seed.

```txt
Five-tier policy:
  keep
  archive
  fold
  restore_remove
  route_forward

Documentation class defaults:
  cycle_docs -> keep
  cross_cutting_docs -> keep
  root_handoff_meta_docs -> keep
  corpus_source_material -> route_forward until classified
  atlas_core_ontology -> keep
  decisions_layer -> keep if introduced
  generated_reports -> restore_remove unless retained as evidence
  memory_files -> keep / route_forward / fold
  release_artifacts -> keep after release activation
  legacy_archive -> archive
```

This policy directly controls current prep files and future documentation
growth.

### F5. `decisions/` Is Not Needed Yet

The prep work generated decision candidates, but not enough evidence to create
a new top-level `decisions/` layer.

```txt
Current decision candidates:
  Hyperbolic Tree
  CodePen
  IA structure choice
  responsive layout pattern choice
  documentation retention/disposal
  WordPress submission readiness

Verdict:
  keep these as Decision Matrix rows and route-forward items for now.
  do not create decisions/ in Phase 2.
```

Creating `decisions/` now would introduce a new layer before ownership, naming,
and retention rules are exercised in a real implementation cycle.

### F6. Source Location Should Separate Raw From Refined

Recommended source-of-authority split:

```txt
raw/source:
  corpus/source/webdesign-craftsman-2026/
    CONTENTS-OUTLINE.md
    SKELETON.md
    SKELETON-RESEARCH-FILL.md

refined/promoted:
  corpus/webdesign-craftsman-written/
    README.md
    matrix-seed.md
    section-01-project-concept.md
    section-04-ia-wireframe-storyboard.md
    section-05-design-ux-responsive.md
    section-07-deliverables-retention.md

atlas:
  atlas/web-production-workflow/
    workflow-atlas.md
    decision-point-atlas.md
    deliverable-atlas.md
    retention-policy-atlas.md

core:
  core/web-production/
    web-production-ontology.md
```

This treats current prep files as route-forward source material, not polished
ontology.

## Diagnostic Questions

### Q1. Does the source outline support a clean workflow sequence?

Yes, with refinement.

```txt
intake -> concept -> prototype -> usability -> IA/wireframe/storyboard ->
design/UX/responsive -> implementation -> color/palette -> handoff/report
```

However, Phase 2 should not implement the whole outline. It should seed the
four reviewed sections first.

### Q2. Which sections are reusable methodology versus exam-only terminology?

```txt
Reusable now:
  Section 1 PMBOK/UML/WBS vocabulary
  Section 4 IA/wireframe/storyboard
  Section 5 design/UX/responsive evaluation
  Section 7 deliverable and retention policy

Corpus only for now:
  Section 2 SCAMPER / visualization / Hyperbolic Tree / CodePen
  Section 3 usability testing details
  Section 6 implementation process terms

Needs later source alignment:
  MaRMI/CBD details
  SWEBOK version-specific details
```

### Q3. Which parts map to Axismundi's immediate page/template work?

```txt
Immediate template-input framework:
  Section 4:
    IA, storyboard, template handoff fields
  Section 5:
    Gestalt, grid, responsive pattern, UX/WCAG review gates

Indirect but important:
  Section 1:
    WBS/work package and phase vocabulary
  Section 7:
    deliverable retention and handoff evidence
```

### Q4. Which parts should become corpus notes only?

```txt
For Phase 2:
  Section 2
  Section 3
  Section 6
  Part 05 and Part 06 color material from outline only
  untouched book sections with no user-authored notes
```

### Q5. Which concepts are stable enough for atlas rules?

```txt
Stable enough:
  PMBOK -> Axismundi phase mapping
  WBS -> bounded work package split
  IA vs navigation separation
  wireframe vs layout separation
  storyboard handoff fields
  Gestalt as evaluation lens
  responsive pattern selection by content stress
  Honeycomb / Nielsen / WCAG separation
  five-tier documentation retention policy
  documentation audience separation
```

### Q6. Which concepts are stable enough for core ontology entities?

Recommended core seed:

```txt
WorkflowStage
DecisionPoint
EvidenceGate
ArtifactBoundary
WorkPackage
LayoutDecision
EvaluationLens
RetentionPolicy
DocumentationClass
AxismundiRoute
SourceConfidence
CopyrightClassification
```

Do not over-model PMBOK, MaRMI, SWEBOK, or CBD as full core ontologies.

### Q7. Is a decisions/ layer needed now?

No.

```txt
Decision candidates exist, but they can live in:
  docs/v3.6.25 Phase 1/2 docs
  corpus matrix seed
  atlas decision-point map

decisions/ should remain routed forward.
```

### Q8. What Decision Matrix fields are necessary and sufficient?

Use the layered schema in F3.

Key decision:

```txt
Base fields stay compact.
Section-specific fields stay attached to domain-specific rows.
Retention policy becomes a base field because it controls future document debt.
```

### Q9. How should later TT5 and Google Sites audits consume this matrix?

They should use it as an evaluation frame.

```txt
TT5 audit:
  evaluate templates, parts, patterns, navigation, responsive behavior,
  documentation boundaries, and release artifacts against the matrix.

Google Sites extraction:
  extract IA/layout/component evidence through Section 4/5 fields.
  do not copy visual appearance directly.
```

### Q10. What evidence gate prevents page-layout decisions from being copied directly from written notes?

Required gate:

```txt
written note -> matrix row -> atlas/core route -> TT5/Google Sites comparison ->
Pilot storyboard -> Pilot implementation
```

Written material is workflow ontology source, not page-layout authority.

### Q11. What Phase 2 route is narrowest while still useful?

Route D-lite, narrowed.

```txt
Durable docs only.
No runtime implementation.
No Pilot template edits.
No distributable files.
No decisions/ layer.
```

### Q12. Should Phase 2 use Route D-lite as expected, or narrow to corpus-only?

Use Route D-lite, narrowed.

Corpus-only would not exercise the Decision Matrix or retention policy enough.
Full D-lite across all seven parts is too broad.

### Q13. Should `decisions/` be created later, proposed only, or explicitly rejected for now?

Proposed later.

```txt
status: route_forward
trigger:
  2+ implementation cycles need durable product-specific decision records that
  are awkward inside phase docs / atlas / core.
```

## Phase 2 Recommendation

Recommended output shape:

```txt
Add:
  docs/v3.6.25/WEBDESIGN-DECISION-MATRIX-ONTOLOGY-PHASE-2-IMPLEMENTATION.md

  corpus/webdesign-craftsman-written/README.md
  corpus/webdesign-craftsman-written/matrix-seed.md
  corpus/webdesign-craftsman-written/section-01-project-concept.md
  corpus/webdesign-craftsman-written/section-04-ia-wireframe-storyboard.md
  corpus/webdesign-craftsman-written/section-05-design-ux-responsive.md
  corpus/webdesign-craftsman-written/section-07-deliverables-retention.md

  atlas/web-production-workflow/workflow-atlas.md
  atlas/web-production-workflow/decision-point-atlas.md
  atlas/web-production-workflow/deliverable-atlas.md
  atlas/web-production-workflow/retention-policy-atlas.md

  core/web-production/web-production-ontology.md

Keep as route-forward source:
  corpus/source/webdesign-craftsman-2026/CONTENTS-OUTLINE.md
  corpus/source/webdesign-craftsman-2026/SKELETON.md
  corpus/source/webdesign-craftsman-2026/SKELETON-RESEARCH-FILL.md
```

Expected Phase 2 classification:

```txt
narrow documentation/ontology implementation
Lock 5: 15th overall / 10th implementation-cycle if implemented
```

## Phase 4 Assessment

Phase 4 is not recommended.

```txt
No new top-level decisions/ layer.
No new broad knowledge/ umbrella.
No cross-layer architecture policy beyond corpus/atlas/core.
No runtime or product implementation.
```

If Phase 2 attempts to create `decisions/`, Phase 4 should reopen.

## Retention Policy First Application

Current prep file status:

```txt
corpus/source/webdesign-craftsman-2026/CONTENTS-OUTLINE.md:
  route_forward as raw/source outline

corpus/source/webdesign-craftsman-2026/SKELETON.md:
  route_forward as organized source digest

corpus/source/webdesign-craftsman-2026/SKELETON-RESEARCH-FILL.md:
  route_forward as research fill and section review evidence

Phase 2 refined files:
  keep if created under corpus/webdesign-craftsman-written/
```

Do not fold or archive the source files in Phase 2. They are still useful
source evidence while the refined tree is being created.

## Routed Forward

```txt
1. Section 2 ideation / concept visualization / fidelity:
   add detail only if Hyperbolic Tree / CodePen / prototype index becomes active.

2. Section 3 usability testing:
   add detail when human visual QA or usability testing becomes active.

3. Section 6 implementation process:
   add detail when Pilot template implementation pass starts.

4. decisions/ layer:
   keep route_forward until repeated implementation cycles prove need.

5. MaRMI/CBD source alignment:
   ad-hoc verification only if it becomes core authority.

6. SWEBOK version verification:
   keep as user-note + official pointer; verify only if release governance uses it.

7. Documentation naming / metadata / access policy:
   not required for Phase 2; candidate future governance refinement.

8. TT5 audit:
   next major candidate after Phase 2 close.

9. Google Sites extraction:
   use Section 4/5 matrix after TT5 or alongside Pilot template pass.

10. Pilot template implementation:
   later cycle, Pilot-only, not distributable inheritance.
```

## Validation

Read-only checks performed during Phase 1:

```txt
rg detail_pass / candidate_rows / retention markers
line-count snapshot for source files
git status --short --branch
```

Expected after report:

```txt
?? corpus/source/webdesign-craftsman-2026/
?? docs/v3.6.25/
```

