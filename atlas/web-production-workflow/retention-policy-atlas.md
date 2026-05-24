# Documentation Retention Policy Atlas

Status: promoted atlas seed.

Retention policy: keep.

## Purpose

This atlas turns the Section 7 retention/disposal policy into reusable operating
rules for Axismundi documentation growth.

## Five-Tier Policy

```txt
keep:
  preserve in working tree and git lineage

archive:
  retain historically meaningful but superseded material with clear status

fold:
  merge overlapping material into a stronger document or memory, then remove or
  archive the redundant source with reason

restore_remove:
  remove generated churn, temporary files, stale drafts, duplicates, and
  unrelated tooling output from the working tree

route_forward:
  keep useful but unresolved material as a future-cycle input
```

## Documentation Classes

```txt
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

## Current Self-Application

```txt
corpus/source/webdesign-craftsman-2026/CONTENTS-OUTLINE.md -> route_forward
corpus/source/webdesign-craftsman-2026/SKELETON.md -> route_forward
corpus/source/webdesign-craftsman-2026/SKELETON-RESEARCH-FILL.md -> route_forward
corpus/webdesign-craftsman-written/* -> keep
atlas/web-production-workflow/* -> keep
core/web-production/web-production-ontology.md -> keep
```

## Anti-Collapse Rules

```txt
Archive is not silent deletion.
Fold is not duplicate preservation.
Restore/remove is not commit-and-revert.
Keep without a retention rule creates uncontrolled accumulation.
Route-forward is not a promise to implement.
```

## M7 / M9 Relationship

```txt
M7:
  procedure for generated mirror and tracked-copy cleanup

M9:
  procedure for source-of-authority and validator-anchor retention

retention policy:
  upper-layer classification deciding which procedure applies
```

