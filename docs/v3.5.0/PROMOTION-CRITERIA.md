# Promotion Criteria — v3.5.0 Phase 1B

> Phase 1B first deliverable. Formal policy document deriving promotion rules from `MODULE-STATUS-MATRIX.md` (37-entry canonical source) and `COMPONENT-COVERAGE-MAP.md` (3 distribution maps).
>
> This document does NOT implement anything. It defines the operating rules under which the v3.5.x+ wave releases will move components through status states.
>
> Companion document: `PUBLIC-SURFACE-CHARTER.md` (4-tier architecture and DISTINCT but COUPLED principle).

## §1 — What this document is, and what it is not

```
IS:
  - Operating rules for status transitions
  - Per-category completion criteria
  - Infrastructure provider qualification rules
  - Dependency contract enforcement language
  - Validation gates per category

IS NOT:
  - Implementation
  - Module authoring (Wave 1+ work)
  - Charter (the architectural posture lives in PUBLIC-SURFACE-CHARTER.md)
  - Naming sweep execution (BACKLOG #18 — Phase 1B records schedule only)
```

## §2 — Status state machine

The matrix uses four status values: `TODO`, `PARTIAL`, `DONE`, `RECORD`. Transitions are:

```
       (initial)
          │
          ▼
        TODO  ───────────────────────────┐
          │                              │
          │ (some extraction or         │
          │  partial coverage exists)   │
          ▼                              │
       PARTIAL                          │
          │                              │
          │ (target category criteria   │
          │  met — see §4)              │
          ▼                              │
        DONE  ←── (no further transition unless regression — §3)
          │
          │ (baseline ALONE remains the
          │  authoritative surface; no
          │  module is needed; record-only
          │  audit doc exists)
          ▼
       RECORD  ←── (only valid initial-or-final state for
                    Baseline-only Record components)
```

### State semantics

```
TODO     Component has a baseline section + style-guide anchor, but no
         lab module work yet. May or may not be authored in the future.

PARTIAL  Component has SOME lab module work (typically an interaction
         extracted from a benchmark, or a partial spec module), but
         the target category criteria (§4) are not fully met.
         Examples (post-v3.4.10):
           - Icon button #2 (icon-system/ covers icon button base but
             Full-Spec audit not done)
           - Search bar #17 (search-expansion/ covers the interaction;
             Full-Spec audit + measurement not done)
           - Date+Time picker #22+#23 (date-time/ covers interaction;
             Full-Spec audit not done)
           - Carousel #34 (carousel/ covers interaction; Full-Spec
             audit + measurement not done)

DONE     Target category criteria (§4) are fully met. All required
         artifacts exist, hard rules enforced, validator passes.
         Examples (post-v3.4.10):
           - Chip #24 — Component Full-Spec Module DONE (v3.4.9)
           - Snackbar #28 — Interaction Runtime Module DONE (v3.4.10)
           - Tooltip #29 — Interaction Runtime Module DONE (v3.4.6)

RECORD   Baseline is sufficient; no module exists; a 1-2 page record-
         only audit doc captures variant inventory + WP mapping +
         attach patterns. The Baseline-only Record category exists
         precisely to keep the 33-component audit promise honest
         without forcing trivial primitives into the
         lab/modules/<name>/ shape.
         Members: Avatar #32, Divider #10, Badge #25.
```

## §3 — DONE regression triggers (rare but defined)

A DONE component returns to PARTIAL (or rarely TODO) only under these conditions:

```
1. Baseline change breaks module assumptions.
   Example: if components.css §11 chip rules were rewritten in a way
   that conflicts with lab-chip.css §1 native form mapping, Chip #24
   would regress to PARTIAL until the conflict is resolved.

2. Hard rule violation discovered in production.
   Example: if a snackbar Hard rule (e.g., "visible snackbar never
   aria-hidden") is found violated in a real consumer, Snackbar #28
   regresses until the violation is closed.

3. Module dependency contract violated.
   Example: if popover/ starts to absorb menu-specific semantics
   (DISTINCT but COUPLED violation, §5.2), affected consumers
   regress because the dependency boundary is no longer clean.

4. Category reclassification.
   Example: if a component initially shipped as Interaction Runtime
   is later found to need Full-Spec measurement coverage too, it
   moves to "Full-Spec + Interaction" dual category and regresses
   to PARTIAL until the additional criteria (§4) are met.
   (Snackbar would have been a candidate here had Phase 0 correction
   not caught the scope; lesson learned: Phase 0 inventory accuracy
   prevents this kind of regression.)
```

Regression is recorded honestly in CHANGELOG and audit-doc — the same posture as v3.4.10 Phase 0 inventory correction.

## §4 — Category-specific completion criteria

The four categories have different completion shapes. A component moves to DONE only when ALL criteria for its category are met.

### §4.1 — Component Full-Spec Module (chip-pattern)

Reference template: `lab/modules/chip/` (v3.4.9).

Required artifacts (5):

```
1. lab/modules/<name>/lab-<name>.css
   - Module CSS that expands the baseline primitive
   - Uses M3 system tokens only
   - NEVER redefines baseline selectors
   - Section structure per audit doc

2. lab/modules/<name>/lab-<name>-pattern.html
   - Live pattern page demonstrating all variants
   - Theme switcher present
   - Principle 1 + Principle 2 applied per variant
   - No fake controls

3. lab/modules/<name>/docs/<NAME>-SPEC-AUDIT.md
   - M3 spec coverage analysis
   - Variant matrix
   - Exception inventory
   - Phase 1+ phase tracking (§N skeleton + §8 5-criterion verdict)

4. lab/modules/<name>/docs/<NAME>-MEASUREMENT-AUDIT.md
   - M3 spec table compared against baseline values
   - Token coverage analysis (% token-driven)
   - WCAG SC citations where target-size applies
   - Deviation documentation

5. lab/modules/<name>/docs/<NAME>-WP-MAPPING.md
   - WordPress block mapping (per Charter §4 theme can / plugin should)
   - 3 rendering paths analysis (block style / pattern / custom block)
   - Anti-pattern inventory
   - Plugin surface index
```

Required verification (3):

```
A. Static Visual QA Gate PASS (0 actual issues)
   - Principle 1 compliance per pattern HTML
   - CSS selector coverage
   - Boundary checks (no out-of-tree references)
   - Cross-references to audit docs

B. validate_theme_pilot.py 1.000 PASS
   - A schema / B theme / C css / D runtime all 1.000

C. Baseline untouched verification
   - components.css §N for this component UNCHANGED
   - style-guide.html#components-<anchor> UNCHANGED
```

PARTIAL → DONE for Full-Spec components requires ALL 5 artifacts + 3 verifications.

### §4.2 — Interaction Runtime Module (snackbar-pattern)

Reference template: `lab/modules/snackbar/` (v3.4.10), `lab/modules/tooltip/` (v3.4.6).

Required artifacts (4):

```
1. lab/modules/<name>/lab-<name>.css
   - Runtime-only CSS (positioning, states, reduced-motion, live-region)
   - Does NOT redefine baseline visual chrome
   - 5-6 sections typical

2. lab/modules/<name>/lab-<name>.js
   - IIFE wrapper, "use strict"
   - Public API namespace (e.g., window.lab<Name>.{...})
   - Hard rules enforced in code (verified by QA gate)
   - Forbidden-ancestor bail-out per Charter §5

3. lab/modules/<name>/lab-<name>-pattern.html
   - Live runtime demo
   - Theme switcher present
   - Baseline static reference section
   - Runtime trigger buttons
   - Forbidden-ancestor negative demo
   - Reduced-motion note

4. lab/modules/<name>/docs/<NAME>-RUNTIME-AUDIT.md
   (or <NAME>-AUDIT.md for short modules)
   - 8-10 sections including: framing / baseline-module split /
     inventory / runtime policies / a11y hard rules / reduced motion /
     forbidden ancestor / 5-criterion verdict / out-of-scope
   - Hard rules locked in §5 (or equivalent)
   - One-line summary (§10 or equivalent)
```

Required verification (4):

```
A. Static Visual QA Gate PASS
   - 10-point checklist (per snackbar v3.4.10 template)
   - All hard rules verified

B. validate_theme_pilot.py 1.000 PASS

C. Baseline untouched verification

D. Phase 0 inventory accuracy
   - Inventory regex catches indented selectors (snackbar lesson)
   - Baseline rule blocks counted correctly BEFORE module CSS authored
   - Module scope matches actual baseline gaps, not assumed gaps
```

PARTIAL → DONE for Interaction Runtime requires ALL 4 artifacts + 4 verifications.

### §4.3 — Component Full-Spec + Interaction Runtime (dual category)

Members (Phase 1A): Tabs #14, Search bar #17, Carousel #34, Date+Time picker #22+#23, FAB menu #5, Split button #7.

Required: BOTH §4.1 AND §4.2 artifact sets.

Practically: 3 audit docs + 1 runtime audit doc + lab-*.css + lab-*.js + lab-*-pattern.html.

PARTIAL state for dual-category typically means ONE side is done (usually Interaction extracted from benchmark; Full-Spec still owed). Examples: Search bar #17, Date+Time picker #22+#23, Carousel #34.

### §4.4 — Baseline-only Module Record (NEW v3.5.0)

Members: Avatar #32, Divider #10, Badge #25.

Required artifacts (1):

```
1. lab/modules/_records/<NAME>-RECORD.md
   (or similar path — Phase 2 implementation decision)
   - 1-2 pages
   - Variant inventory (from baseline)
   - WP mapping (e.g., core/separator for Divider)
   - Attachment patterns (e.g., Badge attaches to Icon button + App bar)
   - Brief deviation note
   - "Why no module" justification
```

Required verification (2):

```
A. validate_theme_pilot.py 1.000 PASS (baseline untouched)
B. Record doc cross-referenced from MODULE-STATUS-MATRIX.md row
```

NO `lab/modules/<name>/` directory is created. NO module CSS. NO module JS. NO pattern HTML. The component remains baseline-only.

RECORD state is initial-or-final for Baseline-only Record components. It is NOT a transient state.

### §4.5 — Plugin-territory Mapping

Members (Phase 1A): 0 within baseline §1-§34. Future plugin items live in `bindings/`.

Required artifacts (when used):

```
1. bindings/<binding-name>/MAPPING.md or similar
   - Theme-can / plugin-should boundary per Charter §4
   - WordPress block / API surface mapped
   - Anti-pattern inventory (what NOT to do on theme side)
```

This category is reserved; v3.5.0 does not currently use it for any matrix row.

## §5 — Infrastructure provider criteria

Phase 1A identified 3 infrastructure providers: `popover/`, `ripple/`, `icon-system/`. The criteria for "what makes something infrastructure" matter for two reasons: (1) preventing premature extraction of utilities into infrastructure status, and (2) preventing infrastructure from absorbing consumer-specific semantics.

### §5.1 — Infrastructure qualification criteria

A lab module qualifies as Infrastructure (and appears in §4 of `MODULE-STATUS-MATRIX.md` rather than §3) when ALL of the following hold:

```
1. Multi-consumer requirement
   The module is consumed (or planned to be consumed) by MORE THAN ONE
   component module. A single-consumer utility stays inside that
   consumer's module — it is not infrastructure.

   Examples (qualifying):
     popover/ — 5 consumers (Menu, Split button, FAB menu,
                Date+Time picker, future Select)
     ripple/  — 13 consumers (Button family, Chip, List items, etc.)
     icon-system/ — 10 consumers (Icon button, FAB family, Menu, etc.)

   Examples (NOT qualifying — hypothetical):
     A "carousel-scroll-detection" utility used only by Carousel
     would stay inside lab/modules/carousel/, not become infrastructure.

2. Semantic neutrality
   The module's behavior is reusable across consumers with DIFFERENT
   semantics. Infrastructure must serve consumers that bring their
   own meaning.

   Example: popover/ serves Menu (list of choices), Split button
   (button + chevron), FAB menu (expanding FAB actions), Date+Time
   (calendar/clock surface) — each consumer has DIFFERENT semantics,
   but ALL need anchored-surface runtime.

3. Independent audit doc
   The module has its own audit doc that describes its provider role,
   not its consumer use cases.

4. Stable public dependency contract
   The module's public API (CSS classes, JS namespace, attribute
   conventions) is documented and stable enough for downstream
   modules to depend on it.
```

### §5.2 — Infrastructure boundary rules (DISTINCT but COUPLED enforcement)

These rules govern what infrastructure modules MAY and MUST NOT do. They derive directly from Phase 0B Menu/Popover refinement.

```
Infrastructure modules MAY:
  - Provide runtime mechanisms (anchor, position, dismiss, state-layer,
    etc.) reusable across consumers
  - Define CSS classes or JS APIs that consumers attach to or call
  - Add their own audit doc, pattern HTML, validation
  - Be DONE without any single consumer being DONE

Infrastructure modules MUST NOT:
  - Absorb consumer-specific semantics
    Example: popover/ MUST NOT contain menu-item logic
    Example: ripple/ MUST NOT contain button-label logic
    Example: icon-system/ MUST NOT contain navigation slot logic

  - Hardcode behavior that only one consumer needs
    Example: popover/ MUST NOT have a "if surface is a menu, do X"
    branch. Each consumer brings its own semantic structure.

  - Serve a single consumer
    If infrastructure ends up with only one consumer, it should be
    folded back into that consumer's module (BACKLOG re-classification
    needed). A 1-consumer "infrastructure" module is mis-categorized.

Consumer modules MAY:
  - Declare a dependency on an infrastructure module in their audit doc
  - Reuse infrastructure CSS classes and JS APIs
  - Add consumer-specific semantics on TOP of infrastructure behavior

Consumer modules MUST NOT:
  - Reimplement infrastructure behavior (positioning, dismissal,
    state-layer, etc.) when an infrastructure provider exists
    Example: Menu module MUST NOT reimplement anchored-surface
    positioning when popover/ already provides it.

  - Modify or extend infrastructure module code in a consumer-specific way
    If a consumer needs new infrastructure behavior, the change MUST
    happen in the infrastructure module itself (validated against
    Multi-consumer criterion §5.1).
```

### §5.3 — Latent infrastructure candidates

Phase 1A identified candidates for future infrastructure extraction:

```
focus-trap utility       — Dialog + Sheet both need focus-trap behavior.
                           If both Wave 2 modules implement focus-trap
                           independently, consider extracting to
                           focus-trap/ infrastructure (qualifies under
                           §5.1 multi-consumer + semantic neutrality).
                           DECISION: defer to Wave 2 authoring; decide
                           based on actual code overlap observed.

backdrop utility         — Dialog + Sheet both render backdrop overlays.
                           Same deferral path as focus-trap.

dismissible/closable     — Snackbar / Dialog / Sheet / Tooltip all have
                           dismiss semantics, but each is specific to
                           its surface (timeout-based, button-based,
                           ESC, click-outside, etc.).
                           DECISION: NO extraction. Dismiss is too
                           semantic-coupled to be infrastructure.
```

Phase 1B records these as decision deferrals; v3.5.0 does not extract any new infrastructure.

## §6 — Dependency contract criteria (per-module)

When a component module declares an infrastructure dependency, the module's audit doc MUST record the dependency in a structured way. Recommended template (Phase 2 v3.5.x+ Wave authoring):

```
### Dependencies

This module depends on:
- `popover/` (infrastructure) — for anchor positioning + dismiss + focus restore
  - Public API used: <list>
  - Consumer-side responsibilities: <list>
  - DISTINCT but COUPLED contract: this module owns <semantic scope>;
    popover/ owns <runtime scope>

This module is depended on by:
  (or "no consumers" if this is a leaf component)
```

The `MODULE-STATUS-MATRIX.md` columns 8-10 (Dep type / Provider / Consumers) already record this at the matrix level. The per-module audit doc records it at the contract level.

## §7 — Validation gates

Every status transition is gated by validation. The gates differ by category.

### §7.1 — Universal gates (apply to ALL transitions)

```
G1. validate_theme_pilot.py 1.000 PASS on the working tree
G2. Baseline untouched per Phase 0 inventory
    - components.css §N for this component UNCHANGED
    - style-guide.html#components-<anchor> UNCHANGED
G3. publish_styleguide.py runs cleanly (no errors)
G4. Module artifacts present at the locations required by §4
G5. CHANGELOG entry added for the release
```

### §7.2 — Category-specific gates

```
Component Full-Spec (§4.1):
  G6.  Static Visual QA Gate PASS (0 actual issues)
  G7.  Principle 1 compliance per pattern HTML
  G8.  Principle 2 (native semantics) applied where applicable
  G9.  WCAG SC citations accurate (SC 2.5.8 AA vs SC 2.5.5 AAA
       distinction maintained)
  G10. 3-doc audit pattern complete (SPEC + MEASUREMENT + WP-MAPPING)

Interaction Runtime (§4.2):
  G11. Hard rules locked in audit §5 (or equivalent)
  G12. Hard rules verified in code (QA gate confirms)
  G13. Phase 0 inventory accuracy verified (lesson from snackbar
       v3.4.10 Phase 0 correction)
  G14. Forbidden-ancestor bail-out implemented
  G15. Reduced motion handled in CSS
  G16. Single audit doc (NOT 3-doc — that's Component Full-Spec pattern)

Dual category §4.3:
  G6-G16 ALL apply

Baseline-only Record §4.4:
  G17. Record doc exists at agreed path (Phase 2 decides exact path)
  G18. Variant inventory + WP mapping + attach patterns recorded
  G19. "Why no module" justification recorded

Plugin-territory Mapping §4.5:
  G20. Mapping doc in bindings/<name>/
  G21. Theme-can / plugin-should boundary explicit
```

### §7.3 — Infrastructure gates

```
G22. Multi-consumer requirement met or declared imminent
     (§5.1 criterion 1)
G23. Semantic neutrality verified (§5.1 criterion 2)
G24. Boundary rules respected (§5.2)
G25. Independent audit doc exists (§5.1 criterion 3)
G26. Public dependency contract documented (§5.1 criterion 4)
```

## §8 — Schedule items recorded (NOT executed in Phase 1B)

Phase 1B records but does NOT execute:

```
S1. .snackbar → .ax-snackbar rename sweep  (BACKLOG #18)
    Schedule: v3.5.x mini-release ("naming sweep").
    Coordinated with similar inconsistencies in other baseline classes
    (if any are surfaced by Wave 1+ authoring).
    Rationale for deferral:
      - Touches baseline + style-guide + lab modules + tests
      - Better as a single sweep release than scattered across waves
      - Phase 1B charter records the policy; execution is separate

S2. data-theme="auto" 3-state model  (BACKLOG #22)
    Schedule: v3.5.x mini-release ("theme policy").
    Phase 1B charter records the model; execution is separate.

S3. Theme-only color customization policy  (BACKLOG #20)
    Schedule: v3.5.x mini-release ("theme policy") — same release as S2.

S4. Wave 1 module authoring  (9 entries)
    Schedule: v3.5.1+ (Wave 1A, Wave 1B sub-releases possible).

S5. Wave 2 module authoring  (14 entries — likely splits into 2A/2B)
    Schedule: v3.5.x — v3.6.x range.

S6. Wave 3 module authoring  (3 entries)
    Schedule: single release after Waves 1 and 2.

S7. Baseline-only Record sweep  (3 records: Avatar / Divider / Badge)
    Schedule: v3.5.x mini-release ("records sweep") — independent
    of Wave 1+ work.

S8. Ontology Theme Pilot  (v3.6.0 candidate)
    Schedule: after Wave 1 complete + at least one form-family Wave 2
    item complete (so the pilot has enough surface to render).
    Consumer of: Wave 1 modules, popover/, ripple/, icon-system/.
```

## §9 — Cross-reference summary

How this document feeds and is fed by other Phase 1A/1B docs:

```
Inputs (Phase 1A):
  MODULE-STATUS-MATRIX.md      → §2 status state machine references
                                  the 37-entry matrix
  COMPONENT-COVERAGE-MAP.md     → §5 infrastructure criteria reference
                                  Map 3 (Infrastructure dependency graph)

Outputs (Phase 1B):
  PUBLIC-SURFACE-CHARTER.md     → consumes §5 (Infrastructure boundary
                                  rules) and §8 (schedule items) to
                                  derive the 4-tier architecture and
                                  the "Infrastructure may be public
                                  dependency without being public
                                  component" principle
```

## §10 — One-line summary

```
v3.5.0 Phase 1B PROMOTION-CRITERIA.md locks the operating rules for
the 37-entry matrix: status transitions (TODO → PARTIAL → DONE +
RECORD final-state), category-specific completion criteria across
4 categories, infrastructure qualification + boundary rules
(DISTINCT but COUPLED), and validation gates G1-G26 — without
implementing any rename, theme model, new module, or pilot work.
```
