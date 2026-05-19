# Axismundi v3.6.1 — Token Architecture Refactor Phase 0 Plan

Status: Phase 0 plan-first document  
Date: 2026-05-19  
Cycle: v3.6.1 Token Architecture Refactor  
Scope: plan before token architecture implementation

## §0. User Request Log — Do Not Abstract Away

These are explicit architecture insights and operating requests for v3.6.1.
They are acceptance criteria, not generic lane labels.

### §0.1 Build Direction Duality

Source: `docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md` §1.4.

```txt
Design system construction is FORWARD direction:
  external spec → internal implementation
  (axismundi-lab v3.5.x)

Theme integration is REVERSE direction:
  CMS native surface → M3 overlay
  (axismundi-pilot v3.6.0)
```

```txt
Both directions are valid. Both are needed. The mistake was assuming the
forward direction could be reused as-is for the reverse case.
```

### §0.2 Token Layering Insight

Source: `docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md` §3.5.

```txt
WordPress theme.json is not the source of design tokens.
It is an editor-facing projection of two layers:

  1. wp-preset = M3 sys values that benefit from user selection
  2. wp-custom = M3 system parameters that benefit from theme.json
                  exposure but should not appear in pickers

The runtime source of truth remains:
  md-ref (primitives) → md-sys (semantics)

This means dark mode is a sys-layer-only operation.
Toggling data-theme rewires sys → ref mappings,
and all four downstream layers (preset / custom / ax-comp / component CSS)
follow automatically.
```

### §0.3 Bridge Direction Principle

Source: `docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md` §3.6.

```txt
All bridges flow M3 → WP (downstream projection):
  --wp--preset--color--primary: var(--md-sys-color-primary)
  --wp--custom--axismundi--state-layer--hover: var(--md-sys-state-hover-opacity)

This is the Strict M3 mode direction defined in
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1.

Reverse bridges (WP → M3) are NOT used by the Pilot.
That direction is reserved for the Interpreter Plugin (BACKLOG #21).
```

### §0.4 v3.6.1 Review Direction

Source: user + Opus review relay for this Phase 0 entry.

```txt
Direction: GO with 1 P1 + 2 P2.

P1:
  settings.custom.axismundi.* direction must be locked.

P2:
  lab split must be defined precisely.
  operational rule migration must be part of Phase 0 exit criteria.
```

Phase 0 close is blocked unless the P1 and both P2 items are represented in
this plan and survive the second review.

## §1. Cycle Scope

v3.6.1 implements the nine-item scope from
`docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md` §5.1:

```txt
1. Split tokens.css into layered files (ref / sys.light / sys.dark)
2. Author wp-preset.bridge.css (M3 sys → WP preset)
3. Author wp-custom.bridge.css (M3 system params → WP custom)
4. Update theme.json with settings.custom.axismundi.* entries
5. Add dark mode infrastructure (data-theme + theme switcher)
6. Validate Pilot in both light and dark mode (Wave 1 + WP blocks)
7. Update axismundi-lab tokens.css with same split (cross-cutting)
8. Close BACKLOG #20 (final theme-only policy lock)
9. Update FEEDBACK-AND-STRATEGY.md §1 to refined architecture
```

This is a bucket A architecture cycle, not a normal Wave component cycle.

## §2. Lesson Lock Candidate — wp-custom Direction

This P1 lock is the governing rule for §1 scope item 4:
`Update theme.json with settings.custom.axismundi.* entries`.
It must be carried through Phase 1 implementation and Phase 5 close:

```txt
Every settings.custom.axismundi.* entry MUST be defined as
  var(--ax-comp-*) or var(--md-sys-*) or var(--md-ref-*).

Literal hex / rgb / px / number values are forbidden in this namespace.

Rationale:
  wp-custom is a downstream projection of M3, never a source.
```

Implication:

- `theme.json settings.custom.axismundi.*` may expose WordPress-managed names
  for state-layer, shape, motion, elevation, and similar internal values.
- It must not become a literal token registry that bypasses `md-ref` /
  `md-sys` / `ax-comp`.
- If WordPress requires fallback literals for schema or UI constraints, Phase 1
  must route that as an explicit finding before implementation rather than
  silently breaking the lock.

Candidate 4-location lock at close:

```txt
AGENTS.md / CLAUDE.md:
  Add wp-custom downstream-only rule.

PRE-ENTRY-ONTOLOGY-GROUNDING.md:
  Capture v3.6.1 token-layer authority rule.

bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md:
  Update §1 with wp-preset / wp-custom refined Stage 1 architecture.

NEXT-SESSION.md:
  Preserve the rule as an active handoff item if any v3.6.1 work remains open.
```

## §3. Lab Split Definition

For this cycle, "lab split" means token file layering inside the existing
`axismundi-lab` product surface. It does **not** mean a directory restructure,
module boundary rewrite, or v4.0 architecture migration.

Locked definition:

```txt
lab split =
  preserve existing axismundi-lab directories and component modules;
  split the current token graph into explicit token-layer files;
  keep lab component CSS consuming md-sys / ax-comp contracts;
  update lab/styleguide asset enumeration so the same layered token model loads.
```

Expected implementation surface:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/
products/reference-implementations/axismundi-lab/style-guide.html
tools/generators/publish_styleguide.py
styleguide/ (regenerated only through publish_styleguide.py)
```

Non-meaning:

```txt
No module directory split.
No `lab/modules/*` ownership rewrite.
No v4.0 directory restructure.
No component status matrix amendment unless implementation discovers a real
contract change that the user approves separately.
```

## §4. Files To Read Before Phase 1

Required:

```txt
AGENTS.md
CURRENT-STATE.md
PROJECT-CONTEXT.md
NEXT-SESSION.md
docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-5-CLOSE.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
BACKLOG.md #20 / #21 / #41 / #42 / #43
docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

Implementation discovery:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/stylesheets/prose.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/assets/styles/
products/reference-implementations/axismundi-pilot/bridge/
tools/generators/publish_styleguide.py
tools/validators/validate_pilot_computed_styles.js
```

## §5. Files To Create Or Modify

Phase 0 creates:

```txt
docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-0-PLAN.md
```

Likely Phase 1+ implementation targets, subject to second GO:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.ref.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.light.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.dark.css
products/reference-implementations/axismundi-lab/stylesheets/wp-preset.bridge.css
products/reference-implementations/axismundi-lab/stylesheets/wp-custom.bridge.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/assets/styles/
tools/generators/publish_styleguide.py
tools/validators/validate_pilot_computed_styles.js
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
BACKLOG.md
```

Phase 5 close candidates:

```txt
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
CURRENT-STATE.md
NEXT-SESSION.md
CHANGELOG.md
ROADMAP.md
```

## §6. Dependency Assumptions

```txt
md-ref:
  primitive palette / type / shape / motion / elevation values.

md-sys.light and md-sys.dark:
  runtime semantic mappings; dark mode changes this layer only.

wp-preset.bridge:
  editor-facing projection, especially semantic color preset variables.

wp-custom.bridge:
  theme-managed internal projection for non-picker values.

ax-comp:
  component-facing contracts consumed by lab and Pilot CSS.
```

The Pilot remains Strict M3 mode:

```txt
M3 → WP only.
WP → M3 belongs to the Interpreter Plugin and is out of scope.
```

## §7. Operational Rules

Primary orchestration is user-mediated relay:

```txt
1. Codex writes the Phase plan.
2. User reads it and sends it to Opus.
3. Opus returns P1/P2 findings or GO.
4. User sends findings back to Codex.
5. Codex revises the plan or proceeds after GO.
```

File ownership:

```txt
Codex writes:
  implementation files and phase plan/report docs.

Opus writes:
  review findings only, preferably as user-relayed text or
  docs/v3.6.1/*-review.md if repo-based handoff is requested.

Both read:
  all repo docs and implementation files.
```

Authority:

```txt
Repo docs are the source of truth.
Chat is relay, not source of truth.
Do not rely on chat memory.
```

## §8. Applicable Gates

From `docs/v3.5.0/PROMOTION-CRITERIA.md`, the closest applicable gates are:

```txt
G1-G10:
  universal documentation, source, validation, and public-surface discipline.

G21-G26:
  infrastructure / provider discipline where token bridges behave as shared
  cross-cutting infrastructure for components and the Pilot.
```

Additional v3.6.x Pilot gates:

```txt
Rendered computed values are proof.
Selector presence is not proof.
Generated asset presence is not proof.
Light and dark modes must both pass computed-style validation.
```

## §9. Validation Commands

Run before and after implementation phases:

```powershell
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
```

If token or publish asset enumeration changes:

```powershell
python tools\generators\publish_styleguide.py
npm test
npm run validate:computed
```

If wp-env is not running, start it before computed validation:

```powershell
wp-env start
```

## §10. Phase 3 Acceptance Criteria

Phase 3 must verify:

```txt
Light mode visual QA:
  axismundi-lab public styleguide
  axismundi-pilot front page
  axismundi-pilot pattern QA page
  Korean prose single/page surfaces

Dark mode visual QA:
  same surfaces, same Wave 1 and WP block checks.

Computed audit:
  run against both light and dark modes.

WordPress bridge:
  native core defaults remain reset before M3 mapping.
  Button fill/outline, Search, Table default/stripes, code/pre, quote,
  separator, and prose rhythm retain computed M3 values.
```

The Pilot must not be called complete on source-rule inspection alone.

## §11. Non-Goals

```txt
No Interpreter Plugin implementation.
No HCT generation.
No WP → M3 reverse bridge in the Pilot.
No custom block registration.
No Carousel plugin extraction.
No v4.0 directory restructure.
No module status matrix amendment unless separately approved.
No token implementation before Phase 0 second GO.
```

## §12. Risks

```txt
Risk:
  settings.custom.axismundi.* becomes a literal token registry.
Mitigation:
  P1 lesson lock forbids literal hex / rgb / px / number values.

Risk:
  lab split is mistaken for directory/module restructure.
Mitigation:
  §3 defines lab split as token-layer split only.

Risk:
  dark mode swaps ref values instead of sys mappings.
Mitigation:
  Phase 1 must preserve md-ref as primitive source and make only sys-layer
  mode-specific mappings.

Risk:
  WordPress editor-facing values are treated as source-of-truth.
Mitigation:
  wp-preset and wp-custom are downstream projections only.

Risk:
  operational relay rules stay buried in this Phase 0 doc.
Mitigation:
  Phase 0 exit criteria require migration to AGENTS.md or NEXT-SESSION.md §0.
```

## §13. Phase 0 Exit Criteria

Phase 0 may close only after:

```txt
1. Plan covers all 9 items from PILOT-LESSONS §5.1.
2. User Request Log preserves §1.4, §3.5, and §3.6 verbatim insights.
3. P1 wp-custom downstream-only lesson lock is present.
4. P2 lab split ambiguity is resolved in-plan.
5. P2 operational rule migration is represented in exit criteria.
6. Opus review returns GO (i.e., no P1 findings remain).
7. php -l, npm test, and npm run validate:computed pass on current HEAD.
8. Operational rule (file ownership + relay channel) is migrated to
   AGENTS.md or NEXT-SESSION.md §0 before Phase 0 close.
```

Until these criteria are met, do not proceed to Phase 1 implementation.

## §14. Current Baseline Verification

Baseline verification at Phase 0 plan creation:

```txt
HEAD:                 a1c1370 Close v3.6.0 ontology theme pilot
Working tree before:  clean
php -l:               PASS
npm test:             PASS (1.000 / 1.000 / 1.000 / 1.000)
npm run validate:computed: PASS
```

This Phase 0 doc creation does not start implementation.
