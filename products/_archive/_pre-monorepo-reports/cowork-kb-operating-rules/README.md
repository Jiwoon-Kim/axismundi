# Cowork KB Operating Rules — Archive

> **Why this matters**: These five files are not historical artifacts. They are the *operating doctrine* under which the KB (`corpus/` + `atlas/` + `core/`) was constructed during Phase 7–8. The Phase 8 closure report (`../cowork-phase8-kb-build-closure.md`) records *what was built*; these files record *how it was built* — the procedural rules that determined chunk shape, authoring sequence, and quality gates.
>
> The rules remain live doctrine for any future KB extension: ActivityPub KB, Material 3 v2 update, a second design system, alternate platform ontology. They are not retired with v3.3.0.

## Contents

| File | What it contains |
|---|---|
| `project-kb-vision.md` | The KB project's foundational vision. "Knowledge OS for WP dev" — KB is NOT prose transcription; it IS a Prompt DSL extracting actionable rules. 4 system layers, 4 phases, hard filter criteria. KB Constitution v1 declaration and Phase 7-8 closure detail. |
| `chunk-authoring-strategy.md` | 11 numbered rules covering schema atomization, spike-then-batch workflow, DSL extension policy, substrate-first ordering, operational-density-over-completeness, KB evolution layer order, capability family abstraction, DSL pressure pattern, and ontology-based audit triggers. |
| `english-only-chunks.md` | All `./knowledge/` chunks in English (token economy). Chat stays Korean. |
| `generic-vs-project-layers.md` | WP handbook + M3 spec chunks must be project-agnostic. Project-specific content in separate layer. Generic chunks must NEVER reference project chunks; project chunks SHOULD reference generic ones. |
| `memory-index.md` | The 4-entry feedback memory used by the Cowork session — bare summary linking to each rule file. |

## How these rules became the monorepo

Each rule, traced into its current effect:

### Rule 1 — Schema atomization (field-cluster, not monster)

**Then**: split `block.json-schema.md` into 7 field-cluster chunks instead of one monster.

**Now**: visible in `corpus/refined/dev-handbook-clean/` — each refined handbook page is a focused topic, not a megafile. Also visible in `atlas/material/` (`text-fields-spec.md` and `text-fields-impl.md` as separate documents rather than one combined "text fields" file).

### Rule 2 — Spike-then-batch

**Then**: write 2-3 sample chunks first, validate DSL, then batch the rest.

**Now**: visible in v3.2.0–v3.2.3 monorepo era as well — license/font foundation was *spiked* in v3.2.0 (small scope), validated through v3.2.1 (runtime integration test), then full corrections in v3.2.3 (font coverage fix). Same doctrine, different terrain.

### Rule 3 — DSL extension only when justified

**Then**: 6-slot DSL (WHEN/SHAPE/REQUIRES/INVARIANTS/ANTIPATTERNS/RELATED) extends only when SHAPE/INVARIANTS slots become heterogeneous bags.

**Now**: `atlas/material/` uses the 6-slot frontmatter; never extended despite covering capability-heavy areas. Rule 9 (DSL pressure pattern, validated through 6 supports chunks) confirmed: chunk *boundary* changes, not slot *category*.

### Rule 4 — Substrate-first ordering

**Then**: write `attributes/context/hierarchy` substrate before `supports` capability flags.

**Now**: directly visible in the current decision **"lab visual QA before pilot block theme"** (v3.3.0 → v3.3.1). Same doctrine: substrate must lock in before features built on top of it. The pilot theme would re-pose every "where does this CSS land?" question if lab isn't stable.

### Rule 5 — Stay in one bounded context

**Then**: complete `block-authoring/` before crossing to `theme-config/` or `plugin-dev/`.

**Now**: visible in monorepo migration discipline — v3.2.0 didn't touch lab; v3.2.2 didn't touch fonts; v3.3.0 didn't touch ontology. Each version stayed in one bounded layer until done.

### Rule 6 — Operational semantic density > historical completeness

**Then**: deprecated APIs absorbed as ANTIPATTERNS in active chunks, not preserved as separate chunks.

**Now**: directly applied in v3.3.0 — prototype demoted to `_archive/` with `_LEGACY.md`. The prototype was not preserved as authoritative reference; only its *demotion* and *what was learned from it* travel forward. Same doctrine, different artifact level.

### Rule 7 — KB evolution layer order (observed, not prescribed)

**Then**: KB organically evolved through syntax → behavior → runtime → graph → execution layers; this wasn't planned, it emerged.

**Now**: the monorepo's 6-layer architecture (A–F) similarly *emerged* during Phase 8 GPT analysis, not prescribed at v3.0.0. Constitution Article 1 was amended from 4-layer to 6-layer in v3.3.0 to *codify what had emerged*. The pattern of *observe-then-codify* is repeating at a higher scale.

### Rule 8 — Capability family abstraction

**Then**: after substrate closure, validate "how reusable is the capability ontology archetype across sibling supports flags?" — color → typography → spacing as a family.

**Now**: visible in lab promotion criteria (5-point checklist in `axismundi-lab/README.md`). Each component (ripple, popover, slider, carousel) is evaluated against the same criteria — capability family abstraction at the interaction level. Same doctrine: build reusable evaluation skeleton, then run multiple instances through it.

### Rule 9 — DSL pressure pattern

**Then**: ontology complexity → INVARIANTS density, not slot count. Pressure is at chunk boundary, not slot category.

**Now**: same principle in CSS layer organization. As Axismundi grew, the 5-stylesheet split (tokens/base/components/prose/blocks) stayed — and grew with components.css reaching 5,929 lines. The pressure was answered by *internal organization* (§ sub-sections), not by *adding more stylesheets*. The same doctrine prevented file-count explosion.

### Rule 10 — Layout spike audit (subsystem-tier capability)

**Then**: layout is the first capability that is NOT styling-with-cascade but structural governance subsystem; needs split-trigger criteria.

**Now**: the same "structural-governance-vs-styling" distinction is the foundation of `atlas/material/icon-font-scope-policy.md` — the icon font isn't just visual styling, it's a *content/chrome governance* policy. The doctrine of recognizing when an element has crossed from styling to governance is reused.

### Rule 11 — Governance batch audit

**Then**: governance flags need ontology-based split criteria (editor-affordance vs render-affordance bifurcation).

**Now**: visible in v3.3.0's prototype/lab separation. The same bifurcation logic — "is this an editor-affordance (lab, experimental) or a render-affordance (theme chrome, stable)?" — drove the decision to demote prototype and promote lab.

### English-only chunks (memory rule)

**Then**: `./knowledge/` chunks in English for token economy; chat stays Korean.

**Now**: `corpus/refined/dev-handbook-clean/` is English; chat (and this README) is Korean. The rule cleanly continues into the monorepo.

### Generic vs project layers (memory rule)

**Then**: `./knowledge/wordpress/`, `./knowledge/material/` are generic; `./knowledge/axismundi/` is project-specific overlay.

**Now**: directly visible in the 6-layer monorepo:
- `corpus/` + `atlas/wordpress/` + `core/wordpress/` = generic WP layer
- `core/design-systems/material3/` = generic M3 layer
- `products/reference-implementations/axismundi-*/` = project-specific overlay
- `bindings/wordpress-material3/` = the bridge

The rule scaled from `./knowledge/` subfolders to the entire repo's layer architecture.

## What's still useful in here

For future KB extension (after v3.3.0):

- **Adding ActivityPub KB**: re-read `project-kb-vision.md` "Knowledge OS" framing + `chunk-authoring-strategy.md` Rule 5 (stay in one bounded context) + Rule 7 (layer order will emerge organically). Start with substrate (Actor + Object schema) before behaviors (federation primitives).
- **Adding Fluent or another design system**: re-read `generic-vs-project-layers.md`. Build `core/design-systems/fluent/` as a generic layer; do NOT mix Axismundi-specific decisions into it.
- **Re-auditing an existing area**: re-read `chunk-authoring-strategy.md` Rule 10+11 audit-trigger criteria. They are operational metrics for "when has a chunk grown beyond its ontology?"
- **Building any new structured knowledge artifact**: re-read Rule 6. The operational-density-over-completeness principle prevents both the deprecated-API archivalism failure and the prototype-preservation failure mode.

## What's NOT preserved here

- The actual KB chunks themselves (`corpus/refined/dev-handbook-clean/`) — those are *in the monorepo* as living authority, not in archive.
- Specific chunk content (e.g., `block-authoring/registration/register-block-type.md`'s actual text) — that's in `corpus/refined/dev-handbook-clean/`.
- Phase 8 closure details (Constitution v2, 6 Laws, 6 Doctrines, etc.) — those are in `../cowork-phase8-kb-build-closure.md` and now amplified in PROJECT-REPORT.md and CONSTITUTION.md.

This folder is *meta-doctrine* — the rules that authorized the substantive work, not the substantive work itself.

## Relationship to authorship

The 11 numbered rules in `chunk-authoring-strategy.md` are pure Ji-woon decisions — user originated, Cowork-session-articulated, then crystallized into operating doctrine. They sit alongside the Tier 3 decisions listed in `AUTHORSHIP.md` but operate at a meta level (not "what was decided" but "how decisions get made").

In that sense, these files are the *clearest fingerprint* of authorship: anyone can adopt the 6-layer A–F architecture, but the *rules under which content is added* are the author's working style. They are reusable for other projects but distinctly identifiable as patterns the author trusts.
