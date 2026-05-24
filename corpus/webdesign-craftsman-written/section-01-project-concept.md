# Section 01 - Project Concept / Structure

Status: promoted refined corpus seed.

Retention policy: keep.

Source section: PART 01 CH01 SECTION 02.

## User-Note Coverage

```txt
PMBOK:
  five process groups
  ten knowledge areas
  webdesign process
  outcome / output / artifact
  RFP / proposal / project plan / final report

MaRMI-III / CBD:
  component-based development
  four processes and thirty activities
  ISO/IEC 12207 reference
  layered development process
  UML-based object-oriented modeling

UML:
  class / sequence / use case / state / activity diagrams

WBS:
  goal / phases / work packages / assignment / dependencies / schedule
```

## Axismundi Mapping

PMBOK vocabulary maps to Axismundi phase language without replacing Lock 1-5.

```txt
initiating -> cycle trigger, user GO, entry fence, standing state
planning -> Phase 0 plan, scope, source inputs, non-goals, risks
executing -> Phase 2 implementation or documentation drafting
monitoring and controlling -> Phase 1 diagnostic, Phase 3 verification,
  reviewer verdicts, amend cycles
closing -> Phase 5 close, commit/push, handoff meta-docs, memory promotion
```

## Artifact Mapping

```txt
RFP -> user trigger or external source brief
proposal -> route proposal or Phase 0 candidate plan
project plan -> Phase 0 plan plus expected route
final report -> Phase 5 close and later portfolio/release report
```

## UML Use

UML is optional modeling support.

```txt
class diagram -> plugin/runtime object model if needed
sequence diagram -> theme switcher, editor bridge, future plugin flows
use case diagram -> user goal and stakeholder framing
state diagram -> explicit state contracts such as data-theme auto/light/dark
activity diagram -> workflow, publish tooling, validation, release process
```

## WBS Use

WBS is a scope-control lens.

```txt
project goal -> G1/G2 objective or cycle objective
major phases -> Phase 0/1/2/3/5 or multi-cycle route
work packages -> bounded implementation/doc/research units
assignment -> Codex execution / Opus review / user decision split
dependencies -> source inputs, memory guardrails, validation gates, user GO
```

## Guardrails

```txt
PMBOK vocabulary does not replace Axismundi Lock 1-5 phase discipline.
MaRMI/CBD remains user-note evidence until source-aligned.
UML diagrams are optional modeling aids, not mandatory deliverables.
WBS creates smaller work units; it does not justify expanding one cycle.
```

