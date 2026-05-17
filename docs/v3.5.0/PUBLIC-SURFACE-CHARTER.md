# Public Surface Charter — v3.5.0 Phase 1B

> Phase 1B second deliverable. Architectural charter for the v3.5.0 Public Surface Reframe. Defines the 4-tier architecture, the meaning of each surface, the infrastructure-vs-component principle, and the policy schedule for naming and theme work.
>
> This document does NOT implement anything. It is the architectural posture under which Wave 1+ module authoring and pilot theme work will operate.
>
> Companion documents:
> - `MODULE-STATUS-MATRIX.md` (37-entry canonical source)
> - `COMPONENT-COVERAGE-MAP.md` (3 distribution maps)
> - `PROMOTION-CRITERIA.md` (operating rules)

## §1 — What this charter is

```
Public Surface Reframe
≠ Public release / RC
= Public-facing structure + ontology definition
```

The v3.5.0 milestone is named "Public Surface Reframe" because it reframes the **meaning** of each existing surface, not because it ships the surface as a polished v1.0 release. After v3.5.0, downstream work (Wave 1+, pilot theme) operates against a clear definition of what each tier is for.

## §2 — 4-tier architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                          PUBLIC TIER                                 │
│                                                                      │
│  Outward-facing surface that an external consumer (pilot theme,      │
│  downstream block theme, plugin) consumes.                           │
│                                                                      │
│  Composition:                                                        │
│    - components.css         (baseline visual primitives)             │
│    - base.css / tokens.css  (foundation)                             │
│    - lab/modules/*          (graduated module CSS + JS where DONE)   │
│    - Infrastructure modules (popover/ ripple/ icon-system/) as       │
│      public dependencies — NOT public components themselves          │
│                                                                      │
│  Stability: target stable; breaking changes require explicit         │
│            charter amendment.                                        │
└──────────────────────────────────────────────────────────────────────┘
            ▲
            │  (promotion when PROMOTION-CRITERIA satisfied)
            │
┌──────────────────────────────────────────────────────────────────────┐
│                            LAB TIER                                  │
│                                                                      │
│  Module validation surface. Where modules are authored, audited,     │
│  and pass through Phase 0 → 1 → 2 → 3 → 5.                          │
│                                                                      │
│  Composition:                                                        │
│    - lab/modules/<name>/    (component modules)                      │
│    - lab/modules/<infra>/   (infrastructure modules)                 │
│    - lab/modules/_records/  (Baseline-only Records — Phase 2         │
│                             path decision)                          │
│    - lab/docs/              (ARCHITECTURE-BOUNDARIES, etc.)          │
│                                                                      │
│  Stability: experimental. Module shape may change during Wave        │
│            authoring. Public consumers MUST NOT reach into lab       │
│            tier directly (use the public surface instead).           │
└──────────────────────────────────────────────────────────────────────┘
            ▲
            │  (used for visual reference; baseline NEVER mutates                                  │
            │   for module work — Phase 0 inventory respects this)
            │
┌──────────────────────────────────────────────────────────────────────┐
│                         BASELINE TIER                                │
│                                                                      │
│  Authoritative visual catalog of component primitives.               │
│                                                                      │
│  Composition:                                                        │
│    - components.css §1-§34   (Material 3 component primitives)       │
│    - style-guide.html        (visual catalog with 34 anchors +       │
│                              foundation surfaces)                    │
│                                                                      │
│  Stability: very stable. Changes happen rarely and only via          │
│            explicit baseline release (not module work). v3.4.x       │
│            cycle preserved this with the "baseline UNCHANGED"        │
│            posture across all 11 lab modules.                        │
└──────────────────────────────────────────────────────────────────────┘
            ▲
            │  (federation / data binding — does NOT touch baseline,
            │   lab, or public tiers; lives in bindings/)
            │
┌──────────────────────────────────────────────────────────────────────┐
│                          PLUGIN TIER                                 │
│                                                                      │
│  Federation / data binding layer. WordPress admin notices,           │
│  Gutenberg editor integration, ActivityPub federation, data          │
│  sourcing — anything that is NOT theme territory.                    │
│                                                                      │
│  Composition:                                                        │
│    - bindings/<binding-name>/MAPPING.md (theme-can/plugin-should     │
│                                         boundary documents)          │
│    - (future) plugin proposals / surfaces                            │
│                                                                      │
│  Stability: out-of-tree for v3.5.0. Documentation only at the        │
│            theme repo level.                                         │
└──────────────────────────────────────────────────────────────────────┘
```

### Tier promotion direction

```
Lab tier → Public tier   : promotion when PROMOTION-CRITERIA met
Baseline tier            : almost never mutates; used for visual reference
Plugin tier              : out-of-tree for the theme repo
```

### Tier composition rule

```
A module's existence in the lab tier does NOT imply public exposure.
A module enters the public tier ONLY when it reaches DONE status
(per PROMOTION-CRITERIA §4 category-specific criteria).

Baseline tier is always public (it's the foundation).
Plugin tier is never inside theme-repo public surface.
```

## §3 — Surface-specific meanings

This section locks the meaning of each concrete surface file/directory. These definitions resolve ambiguity that was present implicitly across v3.4.x.

### §3.1 — `style-guide.html` is a **baseline catalog**, not a final app surface

```
WHAT IT IS:
  - Authoritative visual catalog of the 34 baseline components
  - Reference rendering for each component variant
  - Foundation surface index (Color, Typography)
  - Korean-first content (per project tradition)

WHAT IT IS NOT:
  - A consumer-facing app
  - A pilot theme (pilot theme is a separate downstream consumer)
  - A test harness for lab modules (lab pattern HTMLs do that)
  - Mutable for module purposes (Phase 0 baseline-untouched rule)

CHANGES TO style-guide.html:
  - Only via explicit baseline release
  - Module authoring NEVER mutates style-guide.html
  - This rule held throughout v3.4.x (11 modules authored, 0 changes
    to style-guide.html component anchors)
```

### §3.2 — `components.css §1-§34` is the **authoritative visual primitive source**

```
WHAT IT IS:
  - Material 3 component primitives (CSS only)
  - 34 sections covering 34 TOC components
  - Mature implementations (e.g., baseline §14 Snackbar has 5 rule
    blocks including full state-layer Pattern A on .snackbar__action)
  - Token-driven (M3 system tokens via tokens.css)

WHAT IT IS NOT:
  - A runtime layer (positioning, queue, timeout, focus management
    live in lab modules, not in components.css)
  - A behavior layer (state machines, transitions live in lab modules)
  - The complete picture for components that need runtime
    (e.g., Snackbar baseline CSS comment explicitly says
    "positioning + queue management live in prototype JS" —
    the lab module fills that gap)

CHANGES TO components.css:
  - Baseline UNCHANGED rule applies to all module work
  - Charter-level baseline updates happen via explicit baseline
    release with M3 spec rationale
```

### §3.3 — `lab/modules/*` is a **validation surface**

```
WHAT IT IS:
  - Module authoring + audit + validation territory
  - Pattern pages for visual + runtime verification
  - Audit doc workspace
  - Module contract proving ground

WHAT IT IS NOT:
  - A public API directly
  - Consumed by downstream consumers without graduation
  - A replacement for the baseline (the baseline remains authoritative
    for visual primitives; lab modules layer runtime/behavior on top)

CHANGES TO lab/modules/*:
  - Free experimentation during Wave authoring
  - Graduation to public tier requires PROMOTION-CRITERIA satisfaction
```

### §3.4 — `bindings/` is the **plugin territory mapping**

```
WHAT IT IS:
  - Theme-side documentation of where plugin territory begins
  - Mapping docs (e.g., bindings/wordpress-material3/
    FEEDBACK-AND-STRATEGY.md authored in v3.4.9)
  - Anti-pattern inventories (what NOT to do on theme side)

WHAT IT IS NOT:
  - Plugin code (plugin code lives in a separate plugin repo)
  - Theme integration (theme should not import from bindings/)
  - Auto-generated (these are intentional architectural documents)

CHANGES TO bindings/:
  - Free during charter / strategy work
  - Plugin proposals authored here remain conceptual until a real
    plugin consumes them
```

## §4 — Infrastructure dependency principle

The v3.5.0 third ontology axis (Phase 0B Menu/Popover refinement) is formalized here as a charter principle.

### §4.1 — The principle

```
Infrastructure modules may be public dependencies
without becoming public components.

A component module may depend on infrastructure runtime.
Infrastructure modules MUST NOT absorb consumer-specific semantics.
```

```
인프라 모듈은 public dependency가 될 수 있지만,
public component 그 자체는 아니다.

컴포넌트는 인프라 런타임에 의존할 수 있지만, 인프라 모듈이
소비자 컴포넌트의 의미론을 흡수해서는 안 된다.
```

### §4.2 — Why this principle matters

```
Without this principle:
  - Popover would either be absorbed into Menu (and become menu-
    specific, breaking Split button / FAB menu / Date+Time picker
    consumers), OR Menu would reimplement popover's positioning
    (duplicating infrastructure).
  - Ripple would face the same question for 13 consumers.
  - icon-system would face it for 10 consumers.

With this principle:
  - popover/ remains generic anchored-surface infrastructure
  - Menu, Split button, FAB menu, Date+Time picker each bring their
    own semantic structure
  - The lab module graph stays composable
  - Public API contracts stay stable
```

### §4.3 — External alignment

The principle aligns with established external patterns:

```
WAI-ARIA APG (Menu Button pattern):
  "A button that opens a menu" — button structure is distinct from
  menu structure. The opened surface has role="menu" and menu item
  semantics; the trigger button has no menu-specific behavior beyond
  opening.
  Axismundi mapping: trigger button is a Button component (consumer);
  the menu is a Menu component (consumer); the popover anchored
  behavior is popover/ infrastructure (provider). Three distinct
  responsibilities, one composed surface.

Material 3 spec:
  M3 distinguishes the menu surface from the trigger button and
  describes them in separate sections.
  Axismundi mapping: distinct M3 sections → distinct components
  (Menu §19, Button §2, FAB §15). Anchored runtime is a Material
  pattern reused across these — captured as popover/ infrastructure.
```

### §4.4 — Cross-reference for boundaries

For specific DO / DO NOT rules on infrastructure-vs-consumer boundaries, see `PROMOTION-CRITERIA.md §5.2`. This charter only states the principle; the criteria doc spells out the enforcement.

## §5 — Naming and public exposure policy

### §5.1 — Class naming convention

```
PUBLIC SURFACE convention (target):
  Component primitives  : .ax-<component-name>
  BEM slots             : .ax-<component-name>__<slot>
  BEM modifiers         : .ax-<component-name>--<modifier>
  State classes         : .is-<state>  (no prefix — universal)
  Mechanism classes     : .has-<mechanism>  (no prefix — universal)
  Lab pattern utilities : .lab-<component-name>-* (lab-internal)

CURRENT STATE (post v3.4.10):
  Most components already follow .ax-* (e.g., .ax-button, .ax-chip,
  .ax-icon-button).
  Some components lack the .ax-* prefix in baseline (e.g., .snackbar
  — recorded as BACKLOG #18).
```

### §5.2 — Naming inconsistency schedule

```
BACKLOG #18 — .snackbar → .ax-snackbar rename sweep

Schedule (v3.5.x mini-release after Phase 1B):

  1. Phase 0 — Sweep inventory
     - Audit all baseline classes for .ax-* prefix consistency
     - Identify ALL inconsistencies (not just .snackbar)
     - Record in a sweep audit doc

  2. Phase 1 — Sweep plan
     - Per-class rename mapping
     - Touch points: components.css, style-guide.html, lab modules,
       any test/validation references
     - Migration policy: dual-class period? hard switch?

  3. Phase 2 — Sweep execution
     - Coordinated edits across all touch points
     - QA gate per affected lab module

  4. Phase 3/5 — Verification + release
     - validate_theme_pilot.py 1.000 PASS
     - All affected lab modules' QA gates re-run
     - CHANGELOG entry + ROADMAP update

Rationale for batched sweep:
  - Single .snackbar rename touches baseline + style-guide +
    lab module + audit docs — better as a single coordinated release
    than scattered across Wave 1+ work
  - Other inconsistencies (if surfaced) batched into the same sweep
  - Wave 1+ module authors assume .ax-* prefix as target convention
    even before the sweep lands (they author with .ax-<name> from
    the start)
```

### §5.3 — Anti-pattern: ad-hoc renaming during Wave work

```
DO NOT rename baseline classes during Wave 1+ component module work.
The rename sweep is a separate coordinated release (§5.2).

DO author Wave 1+ modules using the target .ax-* convention from
the start. When the sweep lands, baseline catches up to what modules
already used.
```

## §6 — Theme policy

### §6.1 — `data-theme="auto"` 3-state model (BACKLOG #22 — design only in Phase 1B)

```
The 3-state model (design, not implementation):

  data-theme="light"
    Light theme regardless of OS preference
    Explicit user opt-in

  data-theme="dark"
    Dark theme regardless of OS preference
    Explicit user opt-in

  data-theme="auto"
    Follow OS-level prefers-color-scheme
    Falls back to light if OS preference is unknown

Theme switcher UI:
  - 3 chips (Light / Dark / Auto) — already in lab pattern pages
  - Auto is default unless user explicitly chose Light or Dark
  - Choice persists across sessions (localStorage or similar — Phase 2)

Implementation:
  - data-theme attribute on <html>
  - CSS uses both [data-theme="dark"] selectors AND
    @media (prefers-color-scheme: dark) for the auto state

Schedule:
  v3.5.x mini-release ("theme policy") — separate from Wave 1+ work
  and from the naming sweep.
  Phase 1B charter records the model; implementation is later.
```

### §6.2 — Theme-only color customization policy (BACKLOG #20)

```
Theme tier is responsible for color customization within the
M3 system token scheme. Users (or pilot theme) MAY:

  - Override --md-sys-color-* tokens at :root level
  - Switch palettes via theme.json (when WordPress integration lands)
  - Provide alternative palettes via [data-theme="<name>"] selectors

Theme tier MUST NOT:

  - Hardcode color literals outside the token system
  - Expect plugin-territory features to customize colors
    (color customization is theme territory, not plugin territory)

Schedule:
  v3.5.x mini-release ("theme policy") — same release as §6.1.
```

### §6.3 — M3 Interpreter Plugin (BACKLOG #21 — future, plugin tier)

```
M3 Interpreter Plugin is plugin territory, NOT v3.5.x scope.

Scope (when authored):
  - WordPress plugin that interprets M3 token specs
  - Bridges theme.json color settings to --md-sys-color-* tokens
  - 3-stage path: Static / Preset / Semantic M3
    (per bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
     authored in v3.4.9)

Schedule:
  v3.5.x+ — likely v3.6.x or beyond.
  Phase 1B charter only acknowledges its existence as a future
  plugin-tier deliverable. No implementation, no API surface
  in v3.5.x core.
```

## §7 — What this charter does NOT do

```
Phase 1B charter explicitly does NOT:

  ✗ Execute the .snackbar → .ax-snackbar rename sweep (BACKLOG #18)
  ✗ Implement data-theme="auto" 3-state code (BACKLOG #22)
  ✗ Modify theme.json
  ✗ Author any new lab module
  ✗ Generate the pilot theme (v3.6.0 candidate)
  ✗ Declare a public RC (no RC until Wave 1+ is sufficiently complete)
  ✗ Promote any current PARTIAL/TODO row to DONE (Wave releases do that)
  ✗ Define the M3 Interpreter Plugin's API (plugin tier, future)
  ✗ Specify pilot theme's theme.json shape (v3.6.0 scope)

Phase 1B charter DOES:

  ✓ Define the 4-tier architecture (public / lab / baseline / plugin)
  ✓ Define each surface's meaning
  ✓ Formalize the Infrastructure dependency principle
  ✓ Record naming sweep schedule (not execution)
  ✓ Record theme policy schedule (not execution)
  ✓ Provide reference frame for Wave 1+ authoring
```

## §8 — Cross-reference summary

```
Inputs (Phase 1A + Phase 1B PROMOTION):
  MODULE-STATUS-MATRIX.md      → §2 4-tier architecture refers to
                                  the 37-entry matrix for lab→public
                                  promotion
  COMPONENT-COVERAGE-MAP.md     → §4 infrastructure principle refers
                                  to Map 3 (Infrastructure dependency
                                  graph)
  PROMOTION-CRITERIA.md         → §4.4 cross-references PROMOTION
                                  §5.2 for DO / DO NOT enforcement;
                                  §5 naming policy + §6 theme policy
                                  align with PROMOTION §8 schedule items

Outputs (Wave 1+ and downstream):
  Wave 1+ module authoring     → uses §2 to know what's lab vs public,
                                  §4 to honor infrastructure boundaries,
                                  §5.1 to author with .ax-* prefix
  Pilot theme (v3.6.0)         → consumes public tier per §2; respects
                                  §3.3 (lab is validation, not public
                                  contract)
  v3.5.x mini-releases         → §5.2 naming sweep, §6 theme policy
```

## §9 — One-line summary

```
v3.5.0 Phase 1B PUBLIC-SURFACE-CHARTER.md locks the 4-tier architecture
(public / lab / baseline / plugin), the meaning of each surface
(style-guide.html as baseline catalog, components.css as primitive
source, lab/modules/* as validation surface, bindings/ as plugin-
territory documentation), the Infrastructure dependency principle
(infrastructure may be public dependency without being public
component), and the schedule for naming sweep + theme policy +
M3 Interpreter Plugin — without executing any of it.
```
