# Webdesign Decision Matrix Seed

Status: promoted refined corpus seed.

Retention policy: keep.

## Matrix Shape

The matrix uses a layered schema. Every row gets base fields. Domain-specific
fields are attached only where they are useful.

## Base Fields

```txt
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
```

## Section Extension Fields

Project concept:

```txt
methodology_source
legacy_or_current
process_group
knowledge_area
modeling_artifact
work_package
dependency
```

IA / storyboard:

```txt
page_or_template
IA_type
navigation_type
layout_type
WordPress_surface
reusable_surface
asset_dependency
responsive_strategy
acceptance_gate
```

Design / UX / responsive:

```txt
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
```

Deliverables / retention:

```txt
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

## Seed Rows

```txt
WDD-01-PROCESS-VOCAB:
  source_section: PART 01 CH01 SECTION 02
  workflow_stage: concept
  decision_point: choose project/process vocabulary
  output_artifact: mapped vocabulary, not replacement methodology
  Axismundi_route: core, atlas
  retention_policy: keep

WDD-04-IA-STORYBOARD:
  source_section: PART 03 CH01
  workflow_stage: IA / wireframe / storyboard
  decision_point: choose page structure and handoff artifact
  output_artifact: storyboard handoff fields
  Axismundi_route: atlas, Pilot_harness_later
  retention_policy: keep

WDD-05-DESIGN-UX:
  source_section: PART 03 CH02
  workflow_stage: visual_design / responsive / UX_design
  decision_point: choose design grouping and UX review lens
  output_artifact: evaluation lenses and responsive route candidates
  Axismundi_route: atlas, Pilot_harness_later
  retention_policy: keep

WDD-07-DELIVERABLES:
  source_section: PART 07
  workflow_stage: handoff_report
  decision_point: choose deliverable and retention policy
  output_artifact: documentation class and lifecycle policy
  Axismundi_route: atlas, core, release_seal_later
  retention_policy: keep
```

## Route-Forward Rows

```txt
Hyperbolic Tree:
  route: decisions_candidate
  retention_policy: route_forward
  trigger: prototype index needs dense non-linear relationship browsing

CodePen:
  route: decisions_candidate
  retention_policy: route_forward
  trigger: external throwaway demo explicitly desired

decisions/ layer:
  route: route_forward
  retention_policy: route_forward
  trigger: 2+ implementation cycles need durable product-specific records that
    do not fit phase docs, atlas, or core

MaRMI/CBD source alignment:
  route: route_forward
  retention_policy: route_forward
  trigger: MaRMI/CBD becomes core authority rather than user-note evidence
```

