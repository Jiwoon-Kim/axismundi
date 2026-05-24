# Web Production Ontology

Status: promoted core seed.

Retention policy: keep.

## Purpose

This core seed defines stable Axismundi web-production concepts extracted from
the v3.6.25 refined corpus and atlas. It intentionally does not model PMBOK,
MaRMI, SWEBOK, or CBD in full.

## Core Entities

```txt
WorkflowStage:
  a reusable stage in web-production work

DecisionPoint:
  a bounded choice requiring input evidence and output artifact

EvidenceGate:
  a required check before a decision may advance

ArtifactBoundary:
  the audience and lifecycle separation between documentation artifacts

WorkPackage:
  a bounded unit of execution, documentation, research, or verification

LayoutDecision:
  a page/template structure choice filtered through IA and responsive evidence

EvaluationLens:
  a heuristic, UX, accessibility, or visual grouping frame

RetentionPolicy:
  keep / archive / fold / restore_remove / route_forward

DocumentationClass:
  a category of document or artifact with default retention behavior

AxismundiRoute:
  corpus / atlas / core / route_forward / Pilot_harness_later /
  TT5_audit_later / Google_Sites_extraction_later / distributable_later /
  out_of_scope

SourceConfidence:
  user_note / official_source / professional_source / needs_verification /
  official_pointer_fetch_limited

CopyrightClassification:
  user_authored / paraphrase / structural_reference / external_link_only /
  do_not_import_body_text
```

## Relationships

```txt
WorkflowStage contains DecisionPoint.
DecisionPoint requires input_evidence.
DecisionPoint produces output_artifact.
DecisionPoint may be blocked_by EvidenceGate.
WorkPackage scopes DecisionPoint execution.
LayoutDecision depends on IA/storyboard evidence.
EvaluationLens evaluates a candidate output_artifact.
ArtifactBoundary determines deliverable_audience.
DocumentationClass has default RetentionPolicy.
SourceConfidence qualifies input_evidence.
CopyrightClassification constrains corpus ingestion.
AxismundiRoute determines whether work is promoted now or routed forward.
```

## Non-Entities

These are not modeled as full core ontologies in v3.6.25:

```txt
PMBOK
MaRMI
SWEBOK
CBD
TT5
Google Sites
WordPress Theme Review
```

They remain source references, evaluation inputs, or later route-forward
contexts.

## Evidence Gate for Page/Layout Work

```txt
written note
-> matrix row
-> atlas/core route
-> TT5/Google Sites comparison
-> Pilot storyboard
-> Pilot implementation
```

Written material is workflow ontology source, not page-layout authority.

