# Section 05 - Design / UX / Responsive

Status: promoted refined corpus seed.

Retention policy: keep.

Source section: PART 03 CH02.

## Design Workflow Lenses

```txt
design genesis:
  imitation -> modification -> adaptation -> innovation

extended design process:
  initiation -> confirmation -> research -> analysis -> synthesis ->
  evaluation -> development -> delivery

problem-solving loop:
  plan -> research -> analysis -> synthesis -> evaluation
```

These are decision lenses, not permission to copy external layouts.

## Gestalt Evaluation Lens

```txt
proximity -> related items should be spatially close
similarity -> repeated roles should share visual treatment
continuity -> reading path should not fight page goals
closure -> implied wholes should not create ambiguity
symmetry/order -> stable alignment where it clarifies structure
figure-ground -> foreground content must separate from background
common fate -> moving/changing together implies relationship
```

Gestalt supports Section 4 IA/storyboard decisions. It is not decoration.

## Responsive Pattern Lens

```txt
Mostly Fluid:
  default candidate for content-heavy theme pages

Column Drop:
  useful for archive/sidebar-like pages

Layout Shifter:
  higher CSS/design cost; use only when page purpose changes across breakpoints

Tiny Tweaks:
  useful for one-column pages with minor type/spacing changes

Off Canvas:
  useful for navigation/tools only with accessibility fallback
```

Breakpoints should follow content and layout stress, not device names.

## UX Evaluation Lenses

```txt
Honeycomb:
  useful / usable / desirable / findable / accessible / credible / valuable

Nielsen heuristics:
  heuristic review, not normative proof

WCAG:
  normative accessibility gate for testable criteria
```

## Gesture Guardrail

Gesture-like interactions need pointer, keyboard, and accessibility fallback.

```txt
press
long press
scroll
drag
pull to refresh
single tap
double tap
pinch
```

None of these should become core theme requirements without a separate
interaction cycle.

## Future Use

```txt
TT5 audit:
  evaluate layout, grouping, responsive behavior, and accessibility patterns

Google Sites extraction:
  extract reusable patterns through Section 4 + Section 5 filters

Pilot template pass:
  apply bounded, reviewed patterns only
```

