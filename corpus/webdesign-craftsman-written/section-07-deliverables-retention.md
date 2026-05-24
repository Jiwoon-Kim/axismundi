# Section 07 - Deliverables / Retention

Status: promoted refined corpus seed.

Retention policy: keep.

Source section: PART 07.

## Deliverable Taxonomy

SWEBOK and CBD are treated as taxonomy references, not implementation mandates.

```txt
SWEBOK:
  possible knowledge-area vocabulary for deliverables

CBD:
  possible deliverable and traceability vocabulary

Axismundi rule:
  select the smallest deliverable set that supports the current product boundary
  and evidence need
```

## Documentation Audience Boundaries

```txt
README.md:
  repository/project orientation

readme.txt:
  WordPress.org theme submission artifact

Phase 5 close doc:
  cycle evidence and decision close

NEXT-SESSION / CURRENT-STATE / ROADMAP / CHANGELOG:
  handoff meta-docs

final presentation/report:
  portfolio or stakeholder-facing summary
```

## Retention Policy

```txt
keep:
  source-of-authority files, Phase 5 close evidence, promoted memory files,
  release-critical docs, root handoff meta-docs, cross-cutting authority docs

archive:
  superseded but historically meaningful docs

fold:
  duplicate or overlapping framework material consolidated into a stronger
  existing document or memory

restore_remove:
  generated churn, temp reports, stale drafts, duplicate local notes, or files
  produced by tooling outside intended scope

route_forward:
  useful but undecided material routed to BACKLOG, Phase 5 notes, memory watch,
  or a future cycle
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

## Current Axismundi Examples

```txt
v3.6.22 and v3.6.23/24 generated artifact restores -> restore_remove
M13 validator-anchor candidate folded into M9 -> fold
M7 tracked-copy framework memory -> keep
de106ab and 464604a handoff catchup commits -> keep through maintenance
SKELETON.md / SKELETON-RESEARCH-FILL.md -> route_forward until Phase 1/2
```

## M7 / M9 Relationship

```txt
M7:
  generated mirror and tracked-copy cleanup discipline

M9:
  source-of-authority and validator-anchor retention discipline

retention policy:
  upper-layer decision framework choosing keep / archive / fold /
  restore_remove / route_forward
```

## Anti-Collapse Rules

```txt
SWEBOK enumeration is taxonomy, not implementation requirement.
CBD 25 deliverables are reference material, not Pilot/distributable mandate.
Final report, Phase 5 close, GitHub README, and wp.org readme.txt are separate.
Theme Review requirements activate when a distributable package exists.
Archive is not silent deletion.
Fold is not duplicate preservation.
Restore/remove is not commit-and-revert.
Keep without retention rule creates uncontrolled accumulation.
```

