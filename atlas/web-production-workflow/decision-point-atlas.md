# Web Production Decision-Point Atlas

Status: promoted atlas seed.

Retention policy: keep.

## Purpose

This atlas collects reusable decision points extracted from the refined corpus.
It is not a product decision log and does not create a `decisions/` layer.

## Decision Point Families

```txt
project/process vocabulary:
  choose project-management vocabulary without replacing Axismundi Lock 1-5

work decomposition:
  split G1/G2 objectives into bounded work packages

IA classification:
  choose hierarchy / hub / sequence / grid / network / dashboard route

navigation pattern:
  choose navigation surface after IA is known

wireframe/layout:
  choose responsive layout posture from content and page goal

storyboard handoff:
  define page/template implementation instruction before editing templates

visual grouping:
  choose Gestalt/evaluation lens for hierarchy and grouping

responsive pattern:
  choose Mostly Fluid / Column Drop / Layout Shifter / Tiny Tweaks /
  Off Canvas / custom local pattern

UX review:
  choose Honeycomb dimensions, Nielsen heuristic review, and WCAG gate

deliverable boundary:
  choose artifact audience and lifecycle state

retention/disposal:
  choose keep / archive / fold / restore_remove / route_forward
```

## Route-Forward Candidates

```txt
Hyperbolic Tree:
  decision_candidate until prototype index needs dense relationship browsing

CodePen:
  decision_candidate until external throwaway demo is explicitly desired

decisions/ layer:
  route_forward until repeated implementation cycles prove a need

MaRMI/CBD source alignment:
  route_forward until methodology becomes authority, not just user-note evidence
```

## Trigger Rule for `decisions/`

```txt
Create or propose decisions/ only after 2+ implementation cycles need durable
product-specific decision records that do not fit phase docs, atlas, or core.
```

