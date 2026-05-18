# v3.5.5 Pre-Entry — Ontology Grounding Pack

> **Purpose**: Strategist (Opus) grounding anchor before v3.5.5 cycle entry. Internalizes the 6 canonical docs + Wave 1 closures (Button/Icon button/Card) + outstanding amendments. Codex briefs hereafter derive from this document.
> **Author**: Opus (cowork lane) during Tier 1 grounding pass, 2026-05-16.
> **Status**: Reference document. Not a phase deliverable. Updated when canonical sources change (CONSTITUTION amendment, matrix amendment, new framework doc).
> **Triggers update**: framework doc edit, new Wave closure that surfaces a new discipline, new amendment that affects multi-cycle reasoning.

---

## §0 — How to use this document

```
Strategist read order:
  §1 → §2 → §3 → §4 → §5
  (framework digest → 6 layers → discipline → candidate risks → execution constraints)

When to consult:
  - Before authoring a strategic brief to Codex / GPT review
  - Before approving any plan v1.0 from Codex
  - When a new cycle enters a previously-untouched component family
  - When tempted to extrapolate from Wave 1 cycles into a new domain

When NOT to consult:
  - For per-cycle mechanical work (Codex handles via per-doc reads)
  - For ad-hoc questions (point at the canonical source instead)
```

---

## §1 — Framework digest (6 canonical docs)

The architectural substrate. Each rule is sourced; do not paraphrase outside this section without checking the source.

### §1.1 — CONSTITUTION (12 articles)

```
Article 1   Six layers, distinct authorities
            A Corpus / B Atlas / C Core / D Bindings / E Products / F Tools
            Layers are NOT collapsible. Each has its own form of correctness.

Article 2   Platform ≠ Design system ≠ Federation
            3 orthogonal authority axes.
            Bindings connect exactly 2 axes.

Article 3   Design systems are replaceable; bindings are derivative
            Swapping design system = bindings/ change, not core/ change.

Article 4   Tools operate on layers; tools are NOT a layer
            Transformation between layers, not knowledge representation.

Article 5   Provenance is mandatory
            schema+instance / schema+php_runtime / schema_only / etc.
            No anonymous origin.

Article 6   Three-source minimum for confidence ≥0.85 bindings
            Cheap to audit, hard to fake.

Article 7   Products consume layers, don't define them
            products/* reads from core/* + bindings/* + design-systems/*.
            Products are thin consumers; interchangeable.

Article 8   Naming reflects function, not history
            "What would someone who hasn't seen the history call it?"

Article 9   Backward compatibility is not a layer concern
            Corrected entities replace old ones. Provenance records change.

Article 10  Failure modes to watch (5):
            (1) Material entities under core/wordpress/
            (2) Atlas rules emitted from core
            (3) Bindings without confidence
            (4) Products defining own ontology entities
            (5) Tools persisting state in layer directories

Article 11  Design Doctrine is delegated, not duplicated
            core/design-systems/<name>/DESIGN-DOCTRINE.md owns design-specific rationale.
            M3 locked: tokens.css as Source of Truth; theme.json as ingestion layer.

Article 12  Publishing surfaces are mirrors, not authorities (5 rules)
            Source authority comes first.
            Generators are explicit (tools/generators/*).
            Do NOT edit files in publishing surfaces.
            Publishing surfaces are NOT layers.
            Source authority migrates as project evolves.
```

### §1.2 — 4-Tier Architecture (CHARTER §2)

```
┌─────────────────────────────────────────────────────────────┐
│  PUBLIC TIER          consumed by external (pilot/theme/    │
│                       plugin). Stable; breaking changes     │
│                       require charter amendment.            │
│  Composition: components.css + tokens.css + base.css +      │
│               DONE lab modules + infrastructure (popover/   │
│               ripple/ icon-system/ as public deps, NOT      │
│               public components themselves)                 │
└─────────────────────────────────────────────────────────────┘
                          ▲ (promotion via PROMOTION-CRITERIA)
┌─────────────────────────────────────────────────────────────┐
│  LAB TIER             validation surface. Phase 0→1→2→3→5.  │
│                       Public consumers MUST NOT reach in.   │
│  Composition: lab/modules/<name>/                           │
└─────────────────────────────────────────────────────────────┘
                          ▲ (baseline NEVER mutates for module work)
┌─────────────────────────────────────────────────────────────┐
│  BASELINE TIER        authoritative visual catalog.         │
│                       VERY stable. Module work never        │
│                       mutates baseline.                     │
│  Composition: components.css §1-§34 + style-guide.html      │
└─────────────────────────────────────────────────────────────┘
                          ▲ (federation/data binding sits outside)
┌─────────────────────────────────────────────────────────────┐
│  PLUGIN TIER          federation / data binding.            │
│                       Out-of-tree for theme repo.           │
│  Composition: bindings/<binding-name>/MAPPING.md only       │
│               (plugin code in separate plugin repo)         │
└─────────────────────────────────────────────────────────────┘
```

### §1.3 — Surface-Specific Meanings (CHARTER §3)

```
style-guide.html       baseline CATALOG (NOT final app)
                       Module work NEVER mutates.

components.css §1-§34  visual PRIMITIVE source (NOT runtime)
                       Runtime/behavior lives in lab modules.

lab/modules/*          VALIDATION surface (NOT public)
                       Graduates to public only at DONE.

bindings/              plugin-TERRITORY documentation
                       Not theme code, not plugin code.
                       Anti-pattern inventory + mapping docs only.
```

### §1.4 — Naming Convention (CHARTER §5.1)

```
.ax-<name>             public component primitive
.ax-<name>__<slot>     BEM slot
.ax-<name>--<modifier> BEM modifier
.is-<state>            state class (no prefix — universal)
.has-<mechanism>       mechanism class (no prefix — universal)
.lab-<name>-*          lab-internal pattern utility

Current inconsistencies:
  .snackbar (lacks .ax-* prefix) — BACKLOG #18, deferred to v3.5.x sweep
  .card (lacks .ax-* prefix)     — recorded as candidate for sweep
```

### §1.5 — 4-Category Framework (PROMOTION §4)

```
1. Component Full-Spec Module
   Reference: chip v3.4.9, Button v3.5.1, Icon button v3.5.2, Card v3.5.3
   Required artifacts (5):
     - lab-<name>.css
     - lab-<name>-pattern.html
     - <NAME>-SPEC-AUDIT.md
     - <NAME>-MEASUREMENT-AUDIT.md
     - <NAME>-WP-MAPPING.md
   Required verifications (3):
     - Static Visual QA Gate PASS (0 actual issues)
     - validate_theme_pilot.py 1.000 PASS
     - Baseline untouched

2. Interaction Runtime Module
   Reference: snackbar v3.4.10, tooltip v3.4.6
   Required artifacts (4):
     - lab-<name>.css (runtime-only)
     - lab-<name>.js (IIFE wrapper, Hard rules enforced in code)
     - lab-<name>-pattern.html
     - <NAME>-RUNTIME-AUDIT.md (single doc, 8-10 sections)
   Required verifications (4):
     - Static Visual QA Gate PASS
     - validator PASS
     - Baseline untouched
     - Phase 0 inventory accuracy (snackbar lesson)

3. Component Full-Spec + Interaction Runtime (dual category)
   Members: Tabs, Search bar, Carousel, Date+Time picker, FAB menu, Split button
   Requires BOTH §4.1 and §4.2 artifact sets.
   PARTIAL often means one side done (typically Interaction); Full-Spec owed.

4. Baseline-only Module Record
   Members: Avatar, Divider, Badge
   Required artifacts (1): <NAME>-RECORD.md (1-2 pages)
   Required verifications (2): validator PASS + cross-ref from matrix
   NO lab/modules/<name>/ directory. NO module CSS/JS/pattern.

5. Plugin-territory Mapping
   Members: 0 inside baseline scope
   Future items live in bindings/<binding>/MAPPING.md.
```

### §1.6 — G1-G26 Validation Gates (PROMOTION §7)

```
Universal (G1-G5):
  G1  validate_theme_pilot.py 1.000 PASS
  G2  Baseline untouched
  G3  publish_styleguide.py runs cleanly
  G4  Module artifacts present
  G5  CHANGELOG entry added

Component Full-Spec (G6-G10):
  G6  Static Visual QA Gate PASS
  G7  Principle 1 (real <button>, no fake controls)
  G8  Principle 2 (native semantics applied)
  G9  WCAG SC citations accurate (SC 2.5.8 AA vs SC 2.5.5 AAA distinction)
  G10 3-doc audit pattern complete

Interaction Runtime (G11-G16):
  G11 Hard rules locked in audit
  G12 Hard rules verified in code
  G13 Phase 0 inventory accuracy (snackbar lesson)
  G14 Forbidden-ancestor bail-out implemented
  G15 Reduced motion handled in CSS
  G16 Single audit doc (NOT 3-doc)

Baseline-only Record (G17-G19):
  G17 Record doc exists
  G18 Variant inventory + WP mapping + attach patterns
  G19 "Why no module" justification

Plugin-territory (G20-G21):
  G20 Mapping doc in bindings/<name>/
  G21 Theme-can / plugin-should boundary explicit

Infrastructure (G22-G26):
  G22 Multi-consumer requirement met
  G23 Semantic neutrality verified
  G24 Boundary rules respected
  G25 Independent audit doc
  G26 Public dependency contract documented
```

### §1.7 — DISTINCT but COUPLED Principle (CHARTER §4)

```
Infrastructure modules MAY be public dependencies WITHOUT becoming
public components.

A component may depend on infrastructure runtime.
Infrastructure MUST NOT absorb consumer-specific semantics.

Enforcement (PROMOTION §5.2):
  Infrastructure MAY:    provide runtime mechanisms reusable across consumers
                          define CSS/JS APIs consumers attach to
                          have own audit doc + pattern HTML
                          be DONE without any consumer DONE

  Infrastructure MUST NOT: absorb consumer-specific semantics
                          hardcode single-consumer behavior
                          serve only 1 consumer (fold back if so)

  Consumer MAY:          declare infrastructure dependency
                          reuse infrastructure APIs
                          add consumer-specific semantics on top

  Consumer MUST NOT:     reimplement infrastructure behavior when provider exists
                          modify infrastructure module in consumer-specific way

Aligned external patterns:
  WAI-ARIA APG Menu Button — distinct trigger vs menu semantics
  Material 3 spec — Menu §19, Button §2, FAB §15 in separate sections
```

### §1.8 — Consumer-State Vocabulary (MATRIX v3.5.4 amendment)

```
CURRENT                       provider presently wired into this consumer
TARGET                        designed dependency, NOT yet wired
CANDIDATE                     plausible/inferred, design decision insufficient
NONE                          no dependency

CURRENT conditional           wired only when slot/composition path used
CURRENT conditional via       provider becomes current through nested
  composition                 composed components (not direct dep of host)
```

### §1.9 — 3-Axis Ontology

```
Every component matrix row has 3 independent axes:

  TOC Group   Foundation / Actions / Containers / Navigation /
              Inputs / Selection / Feedback / Display
              (8 functional families)

  Category    Component Full-Spec / Interaction Runtime /
              Baseline-only Record / Plugin-territory Mapping
              (4 module shapes; Full-Spec+Interaction is dual)

  Dependency  Consumer / Provider / Independent
              + provider edges with consumer-state per §1.8
```

### §1.10 — 37-Entry Matrix Snapshot (post v3.5.4)

```
34 TOC components + 3 infrastructure providers = 37 canonical entries

Status distribution:
  DONE        6   Button, Icon button, Chip, Snackbar, Tooltip, Card
  PARTIAL     4   Search bar, Date picker, Time picker, Carousel
  TODO        21  rest
  RECORD      3   Avatar, Divider, Badge
  Infra DONE  3   popover/, ripple/, icon-system/

Infrastructure consumers:
  popover/     5 consumers     (Menu, Split button, FAB menu, Date picker, Time picker)
  ripple/      state-aware     CURRENT=0 / TARGET=7 / CANDIDATE=8 / NONE=rest
  icon-system/ 10 consumers    (Icon button, FAB family, FAB menu, Menu,
                                 App bar, Nav bar, Nav rail, Chip, List, etc.)
```

### §1.11 — 7 Phase 0B Reconciliation Decisions (INVENTORY §7)

```
1. Avatar         standalone (Baseline-only Record), not folded into List
2. FAB+Extended   MERGE at module level (1 module, 2 TOC anchors)
3. Date+Time      MERGE at module level (date-time/ already structured this way)
4. Menu/Popover   DISTINCT but COUPLED (Menu owns role=menu semantics,
                  popover/ owns anchored-surface runtime)
5. Tabs           dual category (Full-Spec + Interaction)
6. Slider         Full-Spec only (NO separate Interaction; native <input range>)
7. Search bar     distinct from Text field (search-specific affordances)
```

---

## §2 — 6-layer Repo Map (Layer A-F)

What lives where, and what authority each layer carries. Pragmatic Opus reference.

```
A. corpus/                  source-of-truth (upstream docs)
                            corpus/source/   raw upstream
                            corpus/refined/  cleaned (v1.1a)
                            corpus/patches/  transformation history
                            CORRECTNESS: "is it preserved faithfully?"

B. atlas/                   rule-based knowledge (DDD-partitioned)
                            atlas/wordpress/ 113 rules × 11 bounded contexts
                            atlas/material/  M3 rule-grain knowledge
                            CORRECTNESS: "is the rule discoverable + consistent?"

C. core/                    formal ontology (typed JSON-LD)
                            core/wordpress/         WP entities (Phase 7+ KB chunks)
                            core/design-systems/    per-design-system ontologies
                              material3/  (current)
                              <future>/   (replaceable per Art. 3)
                            core/federation/        future federation ontologies
                            CORRECTNESS: "does the type system agree with multiple sources?"

D. bindings/                typed translation (confidence-scored)
                            bindings/wordpress-material3/   (current)
                            bindings/wordpress-activitypub/ (future)
                            CORRECTNESS: "does the binding pattern operationalize in code?"

E. products/                reference implementations + distributables
                            products/reference-implementations/
                              axismundi-lab/         primary lab (E1)
                                stylesheets/         baseline CSS (components, tokens, blocks, etc.)
                                style-guide.html     baseline catalog
                                modules/             lab modules (Phase 0→5)
                              ontology-theme-pilot/  pilot theme (consumes lab + bindings)
                            products/distributables/
                              themes/                shippable themes (future)
                              plugins/               shippable plugins (future)
                            CORRECTNESS: "does it actually run / render / validate?"

F. tools/                   builders, validators, generators
                            tools/builders/     (Phase 7 KB chunkers etc.)
                            tools/validators/   validate_theme_pilot.py
                            tools/generators/   publish_styleguide.py
                            tools/refine/       corpus refinement
                            CORRECTNESS: "does it reproduce the artifact from layer inputs?"
```

### Where Opus is most often working

```
Most of strategic work:    E products/.../axismundi-lab/  (lab modules + baseline)
                            docs/v3.5.x/                   (phase plans + reports)
                            ROOT docs                      (CHANGELOG, ROADMAP, BACKLOG,
                                                           CURRENT-STATE, NEXT-SESSION)

Boundary-adjacent:          D bindings/wordpress-material3/  (theme/plugin boundary)
                            C core/design-systems/material3/ (DESIGN-DOCTRINE.md)

Rarely touched by Opus:     A corpus/ + B atlas/ + C core/wordpress/
                            (these are Phase 7/8 doctrinal layers, mostly stable)
```

---

## §3 — v3.5.1-3 Cycle Discipline (Wave 1 closures)

What 3 successful Wave 1 cycles (Button / Icon button / Card) locked into operational discipline.

### §3.1 — Phase Sequence

```
Phase 0     Inventory + dependency scan + risk identification
            Deliverable: docs/v3.5.x/<COMPONENT>-PHASE-0-REPORT.md
            Goal: surface risks, NOT solve them
            v3.5.4-style amendments may run Phase 0 lightly (already-known
            scope, just consolidate)

Phase 0.5   Root Context Pack (Phase 0.5 only happened once at v3.5.1;
            subsequent cycles inherit the 5-file structure)

Phase 1     Audit body authoring (3-doc trio for Component Full-Spec)
            Deliverables:
              lab/modules/<name>/docs/<NAME>-SPEC-AUDIT.md
              lab/modules/<name>/docs/<NAME>-MEASUREMENT-AUDIT.md
              lab/modules/<name>/docs/<NAME>-WP-MAPPING.md
            Plan-first: PHASE-1-PLAN.md authored before execution
            Reviewer findings (P1/P2) → plan v1.1 → approve → execute

Phase 1.5   Cross-reference + validation review (C3-style)
            Optional formal phase; can be folded into Phase 1 close
            Verification: marker scan + stale-phrase scan + dependency
            verbatim check + cross-doc consistency + risk-to-BACKLOG
            routing + validator + integrity check

Phase 2     Implementation (lab CSS + pattern HTML, optionally JS)
            Deliverables: lab-<name>.css + lab-<name>-pattern.html
                          (+ lab-<name>.js for Interaction Runtime)
            Plan-first: PHASE-2-PLAN.md with lock decisions
            Reviewer findings (P1/P2) → plan v1.1 → approve → execute

Phase 3     Static Visual QA Gate (10-point)
            User-side direct verification typical
            Deliverable: QA-GATE-REPORT.md OR inline verdict in audit docs

Phase 5     Mechanical close
            CHANGELOG entry + ROADMAP update + BACKLOG cross-ref +
            SPEC §verdict criteria to PASS + validator final
            (Phase 4 reserved for category-specific deeper QA)
```

### §3.2 — Plan-First Protocol (reviewer findings cycle)

```
Codex authors Plan v1.0 → Opus reviews (P1 = blocker / P2 = nice-to-have
  / P3 = informational) → Plan v1.1 if revisions needed → approve →
  Codex executes

Validated through:
  Button #1 Phase 2 plan:      4 findings (2 P1 + 2 P2)
  Icon button Phase 1 plan:    4 findings (P1 #1 + #2 / P2 #3 + #4)
  Icon button Phase 2 plan:    1 finding (P2 only — toggle caption)
  Card Phase 1 plan:           1 finding (P3 only — .card__body warning)
  Card Phase 2 plan:           0 findings
  Card Phase 0 review notes:   3 (CHARTER §3.4 citation, icon-system composition,
                                  Option A reasoning) — Phase 0 report absorbed

Trend: findings count decreased as framework stabilized.
       Card v3.5.3 = framework discipline fully internalized in Codex.
```

### §3.2a — v3.5.16 / v3.5.17 Process Lessons

```
1. User Request Log — Do Not Abstract Away

   v3.5.16 closed framing/plumbing work but missed concrete user requests:
   mobile top app bar, Sheet-style drawer, icon theme switcher, and body
   mobile polish.

   v3.5.17 corrected this by preserving those requests as explicit acceptance
   criteria. Phase close was blocked until the user confirmed the actual UX.

   Rule:
     If the user gives concrete UX, behavior, or acceptance requirements,
     preserve them in a User Request Log. Do not compress them into generic
     lane names.

2. Global portal / overlay smoke test

   v3.5.17 hotfix 81d0317: Dialog/Sheet trigger buttons existed and
   style-guide.js existed, but #sg-portal was missing from source and the
   generated mirror. The JS returned silently before attaching handlers.
   Validator, focused Playwright QA, and user shell QA all missed it.

   Rule:
     If trigger button, runtime handler, and host/portal live in separate
     places, Phase 3 must verify the full contract:
       trigger exists;
       runtime attaches;
       host/portal exists;
       open state works;
       close/dismiss path works;
       console/page errors are absent.
```

### §3.3 — Locked Patterns (cross-cycle)

```
Deliverable / bookkeeping separation:
  Phase 2 deliverables = exactly 2 files (CSS + HTML) [or 3 for Interaction]
  Phase bookkeeping (SPEC §verdict update, CURRENT-STATE update) NOT
  counted in deliverable artifacts

Selector policy (lab CSS):
  unscoped .ax-<name> override = FORBIDDEN
  .lab-<name>-demo .ax-<name>[...] scoped vis = ALLOWED
    (limited to opacity/state-layer overlay/token-swap only;
     never modify color/size/border/radius/font)

Disabled split (per component complexity):
  Button family (Pattern A):    §5 native disabled + §5a aria-disabled plugin-managed
  Card (Pattern B):              §5 non-interactive locked article
                                + §5a native disabled button-card
                                + §5b aria-disabled plugin-managed (3-way)

Static specimen captions (Icon button toggle / Card aria-disabled):
  When pattern HTML shows "interactive" state without JS, MUST have
  bilingual user-facing caption clarifying it's catalog demo, not real
  interaction.

Native semantics decision tree (Card established):
  Static content card    <article>, <section>, <div>
  Action card            <button class="card card--interactive">
  Navigation card        <a class="card card--interactive" href>
  FORBIDDEN              <article role="button" tabindex="0">

Icon slot canonical pattern (Button established, Icon button reused):
  <span class="material-symbols-rounded notranslate ax-<name>-icon"
        translate="no" aria-hidden="true" draggable="false">add</span>

WCAG SC accuracy (per-component nuance):
  Button (40px):       SC 2.5.8 AA met; SC 2.5.5 AAA NOT met (documented)
  Icon button (48px):  SC 2.5.8 AA + SC 2.5.5 AAA BOTH met (positive finding)
  Card (interactive):  trivially met at any reasonable card size
  Card (static):       N/A (not a control)

Dependency declaration structure (3-axis verbatim):
  Per provider:
    Consumer state:       CURRENT / TARGET / CURRENT conditional /
                          CURRENT conditional via composition / NONE
    Public API used:      (list)
    Consumer responsibilities: (list)
    DISTINCT but COUPLED contract:
      this module owns:    (semantic scope)
      provider owns:       (runtime scope)
```

### §3.4 — Doc / State Discipline (process locks)

```
CURRENT-STATE.md
  Update ONLY at:
    - new session / agent handoff
    - long pause
    - state confusion
  NOT at:
    - every micro-phase
    - every plan approval
    - every Phase boundary (even when "real")
  Rationale: same-flow continuation doesn't need state-snapshot churn

NEXT-SESSION.md
  Update ONLY at:
    - new chat / EOD handoff
    - true session boundary
    - "switching agent runtime" moments
  Even stricter than CURRENT-STATE.

Task-specific docs (PHASE-N-PLAN.md / PHASE-N-REPORT.md):
  Created per phase
  Live as canonical artifact of that decision
  Never overwritten — new revision = v1.1, v1.2 if needed

Edit-first / readback / abort discipline:
  Use Edit / apply_patch first
  Readback after each (Read tool + bash if available)
  If mismatch: STOP, report. fresh-Write is corruption-recovery ONLY.
```

### §3.5 — Multi-agent Orchestration

```
User (Ji-woon)        direction / philosophy / final decisions / ontology authority
GPT                   strategy review / reviewer findings / cross-model evaluation
Claude Opus           strategist / architectural review / cycle planning
  cowork lane         documentation / handoff / quick decisions
  local lane          Claude Code direct repo work (when needed)
Codex (local)         plan-first executor / mechanical implementation
Claude Design         prototype / reference surface (separate lane)

Principle: judgment vs execution SEPARATED
           validator gate runs BEFORE GPT review
           documents are single source of truth (not chat memory)

Routing typical:
  Plan-first work    → Codex (with Opus review)
  Mechanical close   → Codex (with Opus review)
  Strategic decision → Opus → User judgment
  Architectural Q    → Opus (after grounding pass)
```

---

## §4 — v3.5.5 Candidate Risk Map

5 viable options for v3.5.5 + 1 currently-scheduled. Risk assessment per option.

### §4.1 — Option A: Ripple v2 contract release (#25 + #27)

```
Scope:    lab/modules/ripple/ rewrite to Material Web alignment
          data-ax-ripple opt-in declarative attribute
          All TARGET consumers (Button + Icon button + Chip + Menu +
            Nav bar + Nav rail + Tabs) re-wiring decision
          BACKLOG #25 + #27 closure
          Possibly #26 (matrix row #36 allowlist) further refinement

Type:     Infrastructure module rewrite (G22-G26 applicable)
Phase:    Full 0→1→2→3→5 cycle (largest single deliverable since v3.5.0)

Risks:
  HIGH    Material Web spec alignment: bounded/unbounded variants,
          explicit attach API, hover/pressed token model (--md-ripple-*)
          Multi-consumer impact: 7 TARGET consumers' audit docs need
          alignment notes if API surface changes
  MED     v3.5.4 just amended matrix row #36 — Ripple v2 may amend it
          again with new consumer-state arrangements
  MED     WordPress integration boundary unclear (Material Web is web-
          component oriented; Axismundi is CSS+selective JS)

Rewards:
  Cleans up consumer-state ambiguity throughout Wave 1
  Card action surface gets a real ripple target (currently CANDIDATE)
  Future Wave 1 (FAB, List, Button group) inherits cleaner contract
  Closes Phase 0 §3.5 finding from Button #1 (the big ontology
    insight that started v3.5.0 framework)

Codex execution feasibility: requires deep Material Web read (external
  source), infrastructure module audit pattern (G22-G26 less
  exercised than G6-G10), and re-routing 7 consumer audit docs.
  Largest cognitive load of any option.

Opus grounding need: HIGH — Material Web spec is external to repo;
  Phase 0 of v3.5.5 would be heavy read pass.
```

### §4.2 — Option B: Text field #16 (Wave 1 Inputs entry)

```
Scope:    Wave 1 fourth component, first Inputs entry
          New TOC family (Inputs deepest dual-category density)
          Component Full-Spec + Interaction (dual category)
          Most complex M3 component per Phase 0B note

Type:     Component Full-Spec + Interaction Runtime (G6-G16 applicable)
Phase:    Full 0→1→2→3→5 cycle

Risks:
  HIGH    Filled + outlined variants × label transition × error/help
          states × form integration = M3's most complex surface
  HIGH    WordPress form boundary (theme-can/plugin-should is more
          nuanced — Contact Form 7, WooCommerce, custom forms all touch
          text input differently)
  MED     Native <input type="text"> + label/output composition is
          baseline-fragile; need careful selector policy
  MED     M3 motion: label "float" transition requires CSS @keyframes
          or transition+transform; baseline-untouched rule limits
          options
  LOW     Search bar #17 (PARTIAL) is sibling — Text field framework
          will inform Search bar's Full-Spec completion

Rewards:
  Opens Inputs family momentum (Search bar partial leverage + form
    trio Checkbox/Radio/Switch in Wave 2)
  Wave 1 5/9 closure (Button family done + Card + Text field)
  Form integration audit (theme-can/plugin-should refined)

Codex execution feasibility: dual category = first Wave 1 dual case.
  Snackbar/Tooltip (Interaction-only) and Button/Icon button/Card
  (Full-Spec-only) didn't exercise dual G6-G16 simultaneously.
  Audit doc structure for dual category needs deliberate design.

Opus grounding need: MEDIUM — patterns established by Button/Card
  apply, but Inputs family ontology + WordPress form boundary
  are new territory.
```

### §4.3 — Option C: FAB family #3+#4 (Wave 1 Actions continuation)

```
Scope:    Wave 1 fourth component, FAB + Extended FAB merged module
          (Phase 0B decision #2 → first family-merge case)
          Action category continuation (Button family closure)
          Elevation-heavy (level 3 rest, level 4 hover)

Type:     Component Full-Spec (G6-G10 applicable)
Phase:    Full 0→1→2→3→5 cycle

Risks:
  MED     First family-merge case (TOC anchors 2, module 1)
          — needs explicit module structure decision
  MED     35 SVG at 56px context (icon-system load increases)
  MED     FAB+Extended overlap with toolbar floating-with-FAB
          (deferred v1.5+) — boundary clarification needed
  LOW     Button + Icon button patterns directly applicable

Rewards:
  Actions family momentum (4 of 8: Button + Icon button + FAB family
    counts as 2 anchors)
  Reference: Button #1 audit patterns reused with elevation +
    extended-label adjustments
  Action category increasingly homogeneous → Wave 1 momentum maintained

Codex execution feasibility: Button + Icon button patterns reusable.
  Family-merge structure needs lock decisions (single audit doc
  or paired? module folder structure for 2 anchors?).
  Lower cognitive load than Option B.

Opus grounding need: LOW-MEDIUM — patterns 90% inherited from
  Button/Icon button cycle; family-merge structure is the new piece.
```

### §4.4 — Option D: List #33 (Wave 1 Display)

```
Scope:    Wave 1 fourth component, Display family
          1/2/3-line variants
          Avatar leading-slot integration (but Avatar is RECORD)
          ripple/ consumer (item hover) — CANDIDATE state

Type:     Component Full-Spec (G6-G10 applicable)
Phase:    Full 0→1→2→3→5 cycle

Risks:
  MED     Avatar leading-slot composition without an Avatar module
          (Avatar is Baseline-only Record, but Phase 0 inventory of
          Avatar attachment patterns might be needed first)
  MED     1/2/3-line variant complexity (typography hierarchy + spacing)
  LOW     List item hover = ripple CANDIDATE (resolved at Ripple v2)

Rewards:
  Foundation for content rendering pilot theme (v3.6.0 dependency)
  Display family momentum (List + Carousel pending)
  Avatar Baseline-only Record gets concrete attachment patterns
    documented

Codex execution feasibility: List is moderate complexity. Avatar
  composition is the unique aspect.

Opus grounding need: MEDIUM — Avatar (RECORD) attachment patterns
  need exploration before List audit (might justify Avatar
  Baseline-only Record sweep PRECEDING List).
```

### §4.5 — Option E: Baseline-only Record sweep (Avatar/Divider/Badge)

```
Scope:    3 record-only audit docs (1-2 pages each)
          Avatar / Divider / Badge final-state closure
          G17-G19 gates applicable

Type:     Baseline-only Record cluster (small)
Phase:    Light 0→1→5 cycle (no Phase 2, no Phase 3)

Risks:
  LOW     3 small docs, well-understood templates
  LOW     Avatar attachment patterns might surface composition
          dependencies (used in List, chat, profile)

Rewards:
  Closes the 3 RECORD entries
  Avatar leading-slot conventions documented (would help future List #33)
  Small win — momentum without big-cycle cost

Codex execution feasibility: Simplest option. Could be folded into
  another release as bookkeeping.

Opus grounding need: LOW — records are inventories, not specs.
```

### §4.6 — Option F: Lab Preview Routes (carry-forward from earlier)

```
Scope:    publish_styleguide.py extension to expose lab pattern HTML
          as /styleguide/lab/<name>/index.html subroutes
          CHARTER §3 amendment (publish surface includes lab preview
          routes under namespaced /lab/ prefix)
          Solves orphan lab CSS issue (publish_styleguide.py docstring
          noted asymmetry)

Type:     Tooling + CHARTER amendment (NOT a component cycle)
Phase:    Modified cycle (Phase 0 charter amendment + Phase 1 tooling +
          Phase 2 publish + Phase 5 close)

Risks:
  MED     CHARTER amendment is rare — needs explicit user authorization
  LOW     Tooling extension is mechanical
  LOW     Per-pattern HTML path-rewrite for publish (small)

Rewards:
  Solves real asymmetry that 11+ existing lab modules carry
  Future Wave 1+ items get preview URLs automatically
  v3.5.0 framework completion (publish surface fully consistent)

Codex execution feasibility: tooling work + CHARTER doc edit.
  Modest scope.

Opus grounding need: LOW — clear scope, established CHARTER
  amendment pattern (none of v3.5.x has amended CHARTER yet,
  but Article 12 explicitly allows publishing-surface evolution).
```

### §4.7 — Risk Map Summary

```
                  Cognitive   Wave 1     Foundation   v3.5.5
Option            load        momentum   cleanup      readiness
                  -----       --------   ----------   ----------
A. Ripple v2      HIGH        weak       HIGH         needs grounding +
                                                       Material Web read
B. Text field     MED-HIGH    strong     LOW          needs Inputs family +
                                                       WP form boundary read
C. FAB family     MED         strong     LOW          patterns inherited
D. List           MED         medium     LOW          Avatar precursor helpful
E. Records sweep  LOW         neutral    LOW          minimal grounding
F. Lab Preview    LOW         neutral    MEDIUM       CHARTER amendment ground

Recommendation framing (Opus opinion, User judgment):
  Foundation-first sequence:
    v3.5.5  Records sweep (E) — small warmup
    v3.5.6  Ripple v2 (A) — foundation lock
    v3.5.7+ Wave 1 continues with clean Ripple contract

  Momentum-first sequence:
    v3.5.5  FAB family (C) — easiest Wave 1 continuation
    v3.5.6  Text field (B) — Inputs family entry
    v3.5.7  Ripple v2 (A) — after Wave 1 mid-point

  Hybrid (my actual lean):
    v3.5.5  Records sweep (E) [if appetite low] OR FAB family (C) [if appetite normal]
    v3.5.6  Ripple v2 (A) — before more consumers accumulate
    v3.5.7  Text field (B) — Inputs entry on cleaned foundation

Lab Preview Routes (F) is independent — can slot anywhere.
```

---

## §5 — Codex Execution Constraints (locked by v3.5.0 framework + Wave 1 closures)

What ANY v3.5.5+ Codex execution must respect, regardless of which option User picks.

### §5.1 — Universal Constraints

```
G1-G10 gates apply to ALL Component Full-Spec work
G1-G5 + G11-G16 apply to ALL Interaction Runtime work
G1-G5 + G17-G19 apply to ALL Baseline-only Record work
G1-G5 + G22-G26 apply to ALL Infrastructure work
G3 (publish_styleguide.py runs cleanly) — Codex tooling cleanup applied;
  no Windows-side workaround needed
```

### §5.2 — Forbidden Without Explicit Authorization

```
- baseline mutation (components.css §1-§34 + style-guide.html
  #components-* anchors + tokens.css + theme.json)
- naming sweep execution (BACKLOG #18 — separate release)
- data-theme="auto" implementation (BACKLOG #22 — separate release)
- pilot theme generation (v3.6.0 candidate)
- ripple v2 implementation (BACKLOG #25 — only Option A is v3.5.5 venue)
- matrix amendment (closed v3.5.4 for #24 + #26; future #28+ open)
- plugin / editor integration runtime work
- ActivityPub federation runtime work
- ad-hoc renaming of any class
```

### §5.3 — Plan-First Discipline

```
Any new file creation that produces 2+ artifacts, or any deliverable
beyond a single audit doc, MUST have a plan-first cycle:
  Plan v1.0 → Opus review (P1/P2/P3) → Plan v1.1 if revisions →
  approve → execute → report

Plan v1.0 should contain:
  §0 Scope / Gate
  §1 Lock decisions (Card-style for Wave 1; lighter for amendments)
  §2 Deliverable scope (exactly N files, NOT N-1 or N+1)
  §3 Pattern HTML / module structure (if applicable)
  §4 Dependency declaration (if applicable)
  §5 G-gate readiness mapping
  §6 Validation plan
  §7 Explicit non-goals
  §8 Risks
  §9 Approval gate
```

### §5.4 — Documentation State Discipline

```
CURRENT-STATE.md       Codex SHOULD NOT update unless agent-handoff
                       imminent or User explicitly requests
NEXT-SESSION.md        Codex SHOULD NOT update unless true session
                       boundary (Codex normally stays in same session
                       — Opus invocation = new session boundary
                       candidate)
Phase docs             Codex creates per phase, never overwrites
                       (v1.1 amendment if revisions needed)
CHANGELOG / ROADMAP    Codex updates only at Phase 5 mechanical close
BACKLOG                Codex updates only when explicitly authorized
                       (e.g., Phase 0 risk routing or Phase 5 closure
                       cross-ref)
```

### §5.5 — Edit-First Discipline

```
Use Edit / apply_patch first (small diffs, targeted edits)
Readback after each edit (Read tool + bash if available)
On mismatch: STOP, report to User. Do NOT fresh-Write to bridge.
Fresh-Write is RESERVED for:
  (a) New file creation
  (b) Corruption recovery confirmed by User
```

### §5.6 — Reference Template Order (when authoring audit docs)

```
For Component Full-Spec audit docs:
  1. Latest sibling (e.g., Card v3.5.3 for next Container; Button v3.5.1 for
     next Action; Chip v3.4.9 if no Wave 1 sibling exists)
  2. Phase 0 report of current cycle
  3. Phase 1 plan of current cycle (for section structure)
  4. Other Wave 1 closures (cross-reference patterns)

For Interaction Runtime audit:
  1. Snackbar v3.4.10 (most recent)
  2. Tooltip v3.4.6
  3. Hard rule pattern from snackbar §5

For Baseline-only Record:
  1. (No precedent yet — Avatar/Divider/Badge would be first sweep)
  2. Use PROMOTION §4.4 spec directly

For Infrastructure:
  1. popover/ existing audit (5 consumers, DISTINCT but COUPLED)
  2. ripple/ existing audit (state-layer Pattern A; v3.5.4 amended)
  3. PROMOTION §5.1 + §5.2 enforcement language
```

---

## §6 — Maintenance

```
Update this document when:
  - A new Wave closure surfaces a new discipline rule (§3.3)
  - A canonical framework doc is amended (§1.x)
  - Status distribution changes meaningfully (§1.10)
  - A new v3.5.x candidate is added (§4.x)
  - Codex execution constraints change (§5.x)

Do NOT update for:
  - Per-cycle progress (Phase 0 → 1 → 2 movement within a known cycle)
  - Plan revisions (lives in the plan doc itself)
  - Validator runs (lives in change reports)

Last updated: 2026-05-16 (Tier 1 grounding pass, post-v3.5.4 close)
Next expected update trigger: v3.5.5 close (whichever option User picks)
```

---

## §7 — One-Line Summary

```
This grounding pack internalizes the 6 canonical Axismundi framework
docs (CONSTITUTION + 5 v3.5.0 docs) + 3 Wave 1 cycle closures
(Button v3.5.1 + Icon button v3.5.2 + Card v3.5.3) + v3.5.4 matrix
amendment, surfaces 6 v3.5.5 candidate options with cognitive load /
momentum / foundation cleanup / grounding need per option, and locks
Codex execution constraints (G1-G26 gates, baseline immutability,
plan-first discipline, doc state hygiene, edit-first protocol,
reference template order) so the next strategist brief to Codex is
grounded rather than extrapolated from accumulated context shards.
```
