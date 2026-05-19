# Axismundi v3.6.1 — Token Architecture Refactor Phase 1 Plan

Status: Phase 1 implementation in progress; Phase 1A + 1B complete  
Date: 2026-05-19  
Cycle: v3.6.1 Token Architecture Refactor  
Scope: implementation plan only; no token implementation before second GO

## §0. User Request Log — Carry Forward

Phase 1 inherits the Phase 0 User Request Log and P1 lock from
`docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-0-PLAN.md`.

Required verbatim architecture:

```txt
Design system construction is FORWARD direction:
  external spec → internal implementation
  (axismundi-lab v3.5.x)

Theme integration is REVERSE direction:
  CMS native surface → M3 overlay
  (axismundi-pilot v3.6.0)
```

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

```txt
All bridges flow M3 → WP (downstream projection):
  --wp--preset--color--primary: var(--md-sys-color-primary)
  --wp--custom--axismundi--state-layer--hover: var(--md-sys-state-hover-opacity)
```

P1 lock for `theme.json settings.custom.axismundi.*`:

```txt
Every settings.custom.axismundi.* entry MUST be defined as
  var(--ax-comp-*) or var(--md-sys-*) or var(--md-ref-*).

Literal hex / rgb / px / number values are forbidden in this namespace.

Rationale:
  wp-custom is a downstream projection of M3, never a source.
```

## §1. Phase 1 Goal

Phase 1 should implement the token architecture refactor in dependency-aware
sub-phases while keeping the public/lab/Pilot behavior equivalent until each
bridge step is explicitly validated.

Phase 1 is implementation planning for the nine-item v3.6.1 scope:

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

## §2. Current Implementation Shape

Discovered current state:

```txt
Lab:
  products/reference-implementations/axismundi-lab/stylesheets/tokens.css
    currently contains md-ref, md-sys light/dark, typescale, shape, spacing,
    state, motion, elevation, component tokens, aliases, layout.

  style-guide.html loads:
    fonts.css → icons.css → tokens.css → base.css → components.css

  style-guide.js owns the catalog [data-theme-button] switcher.
  theme.js owns production/module [data-theme-set] switchers.

Pilot:
  tools/generators/build_pilot_assets.py copies lab/stylesheets/*.css into
  products/reference-implementations/axismundi-pilot/assets/styles/.

  functions.php currently enqueues:
    fonts.css → tokens.css → base.css → icons.css → components.css →
    blocks.css → prose.css → pilot-block-bridge.css

  theme.json palette/font sizes already reference var(--md-sys-*).
  settings.custom.axismundi.* does not exist yet.

Validator:
  tools/validators/validate_pilot_computed_styles.js checks one viewport and
  current effective theme, not an explicit light/dark matrix yet.
```

## §3. Dependency Arrows

Phase 1 must preserve this direction:

```txt
md-ref
  primitive source values
  ↓
md-sys.light / md-sys.dark
  runtime semantic mapping; dark mode swaps this layer only
  ↓
ax-comp
  component-facing contracts
  ↓
component CSS / block bridge CSS / prose CSS
```

WordPress projections are downstream branches from `md-sys` / `ax-comp`:

```txt
md-sys.light / md-sys.dark
  ↓
wp-preset.bridge.css
  ↓
--wp--preset--color--*
  ↓
theme.json palette/editor-facing semantic color slugs
```

```txt
md-sys / ax-comp / md-ref
  ↓
wp-custom.bridge.css
  ↓
theme.json settings.custom.axismundi.*
  ↓
WordPress-managed internal values
```

Forbidden arrows:

```txt
theme.json literal values → md-sys
wp-preset → md-sys
wp-custom → md-sys
WP global styles UI → M3 token graph
```

Reverse direction remains BACKLOG #21 Interpreter Plugin territory.

## §4. Execution Order

Phase 1 implementation should be split into reviewable sub-phases:

```txt
Phase 1A — Token layer extraction
  Create layered lab token files while preserving tokens.css as the compatibility
  entry point.

Phase 1B — Style loading and asset bridge update
  Update lab HTML, publish_styleguide.py, build_pilot_assets.py, and Pilot
  enqueue/editor-style order so layered files load before consumers.

Phase 1C — wp-preset / wp-custom bridge files
  Add bridge CSS files and keep both downstream from md-sys / ax-comp.

Phase 1D — theme.json settings.custom.axismundi.* hunk
  Add only var(...) entries under settings.custom.axismundi.* and validate the
  P1 lock hunk-by-hunk.

Phase 1E — Dark mode infrastructure and validator matrix
  Add/confirm data-theme light/dark/auto surfaces and extend computed audit to
  run explicit light + dark checks.

Phase 1F — Policy docs and backlog routing
  Update FEEDBACK-AND-STRATEGY.md §1 and prepare BACKLOG #20 close evidence.
```

Do not merge these into one broad patch. Each sub-phase should have a validation
checkpoint.

Review cadence:

```txt
Phase 1A + 1B:
  Codex reports validation results to user. Opus mini-review optional.

Phase 1C:
  Opus mini-review required before moving to theme.json changes.

Phase 1D:
  Opus mini-review required for the settings.custom.axismundi.* P1 lock.

Phase 1E:
  Report light/dark validator matrix results to Opus.

Phase 1F:
  Full Phase 1 close review before commit/phase close.
```

## §5. Planned File Changes

### Phase 1A — Token Layer Extraction

Create:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.ref.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.light.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.dark.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.comp.css
```

Modify:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
```

Planned behavior:

- `tokens.css` remains as the compatibility entry point during v3.6.1.
- Loading order for the new layer files is preserved via explicit link/enqueue
  in HTML/PHP during Phase 1B. `tokens.css` itself contributes no tokens.
- `md-ref` literals live in `tokens.ref.css`.
- Light semantic mappings live in `tokens.sys.light.css`.
- Dark semantic mappings live in `tokens.sys.dark.css`.
- Component-facing contracts and semantic aliases move to
  `tokens.comp.css`.
- `tokens.css` becomes an empty compatibility shim with a header explaining
  that v3.6.1 uses explicit layer loading. It must not contain residual tokens.

Naming decision:

```txt
Use tokens.comp.css, not tokens.ax-comp.css.
Rationale: existing token names are --comp-* and token rename is explicitly out
of scope for v3.6.1. File naming should match current token content.
```

### Phase 1B — Loading / Copy Pipeline

Modify:

```txt
products/reference-implementations/axismundi-lab/style-guide.html
tools/generators/publish_styleguide.py
tools/generators/build_pilot_assets.py
products/reference-implementations/axismundi-pilot/functions.php
```

Required order:

```txt
fonts.css
tokens.ref.css
tokens.sys.light.css
tokens.comp.css
tokens.sys.dark.css
wp-preset.bridge.css
wp-custom.bridge.css
tokens.css
base.css
icons.css
components.css
blocks.css
prose.css
pilot-block-bridge.css
```

Compatibility rule:

```txt
Use explicit link/enqueue order. Do not rely on @import for the active path.
WordPress editor styles are order-sensitive, and @import can be fragile across
front end, editor canvas, and file:// styleguide surfaces.
```

Post-extraction role of tokens.css:

```txt
tokens.css is an empty compatibility shim.
It exists so old paths fail softly during the transition, but the real token
graph lives only in the explicit layer files:
  tokens.ref.css
  tokens.sys.light.css
  tokens.comp.css
  tokens.sys.dark.css
  wp-preset.bridge.css
  wp-custom.bridge.css

tokens.css must contain no residual token definitions and no @import chain.
```

Implementation finding:

```txt
tokens.sys.dark.css loads after tokens.comp.css because it contains dark-mode
overrides for tokens first declared in tokens.comp.css, such as elevation
shadow suppression. Loading sys.dark before comp would let light/default comp
definitions overwrite the dark override.
```

### Phase 1C — Bridge Files

Create:

```txt
products/reference-implementations/axismundi-lab/stylesheets/wp-preset.bridge.css
products/reference-implementations/axismundi-lab/stylesheets/wp-custom.bridge.css
```

Expected examples:

```css
:root {
  --wp--preset--color--primary: var(--md-sys-color-primary);
}
```

```css
:root {
  --wp--custom--axismundi--state-layer--hover: var(--md-sys-state-hover-state-layer-opacity);
  --wp--custom--axismundi--shape--corner-medium: var(--md-sys-shape-corner-medium);
  --wp--custom--axismundi--motion--duration-default: var(--md-sys-motion-curve-default-effects-duration);
}
```

Guardrail:

```txt
No literal hex / rgb / px / number values in wp-custom.bridge.css except as
already-upstream var() values from md-ref/md-sys/ax-comp.
```

### Phase 1D — theme.json settings.custom.axismundi.*

Modify:

```txt
products/reference-implementations/axismundi-pilot/theme.json
```

Allowed shape:

```json
{
  "settings": {
    "custom": {
      "axismundi": {
        "stateLayer": {
          "hover": "var(--md-sys-state-hover-state-layer-opacity)"
        },
        "shape": {
          "cornerMedium": "var(--md-sys-shape-corner-medium)"
        },
        "motion": {
          "durationDefault": "var(--md-sys-motion-curve-default-effects-duration)"
        },
        "elevation": {
          "level1": "var(--md-sys-elevation-level1)"
        }
      }
    }
  }
}
```

P1 hunk review:

```txt
Every added value under settings.custom.axismundi.* must match:
  ^var\(--(ax-comp|md-sys|md-ref)-[a-z0-9-]+\)$

Any required literal fallback becomes a Phase 1 finding, not an implementation
shortcut.
```

### Phase 1E — Dark Mode Infrastructure / Validation Matrix

Modify:

```txt
tools/validators/validate_pilot_computed_styles.js
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/templates/
products/reference-implementations/axismundi-pilot/parts/
```

Plan:

- Lab already has `data-theme-button` / `data-theme-set` switcher contracts.
- Pilot needs explicit light/dark/auto verification. Phase 1 should first
  implement validator-forced modes before adding visible Pilot UI.
- Computed audit should run at least:

```txt
mode=light: force html[data-theme="light"]
mode=dark:  force html[data-theme="dark"]
```

Optional mode:

```txt
mode=auto: no attribute, OS preference emulation where Playwright supports it
```

Visible Pilot switcher is allowed only if it is fully wired. Do not render a
fake control.

### Phase 1F — Policy Docs / BACKLOG #20

Modify after implementation validates the model:

```txt
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
BACKLOG.md
```

BACKLOG #20 close trigger:

```txt
Close only after:
  settings.color.custom=false remains confirmed;
  wp-preset/wp-custom bridge files exist;
  dark mode sys-layer swap validates in Pilot;
  theme.json settings.custom.axismundi.* obeys downstream-only lock.
```

## §6. Validation Checkpoints

Run before starting implementation:

```powershell
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
```

After Phase 1A token extraction:

```powershell
npm test
```

Manual grep checks:

```powershell
rg "--md-ref-" products\reference-implementations\axismundi-lab\stylesheets\tokens.ref.css
rg "--md-sys-" products\reference-implementations\axismundi-lab\stylesheets\tokens.sys.light.css
rg "--md-sys-" products\reference-implementations\axismundi-lab\stylesheets\tokens.sys.dark.css
```

After Phase 1B loading/copy pipeline:

```powershell
python tools\generators\publish_styleguide.py
python tools\generators\build_pilot_assets.py
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
```

After Phase 1C/1D bridge + theme.json:

```powershell
npm test
npm run validate:computed
```

Additional P1 lock check:

```powershell
rg "\"axismundi\"" products\reference-implementations\axismundi-pilot\theme.json
rg "#[0-9A-Fa-f]{3,8}|rgb\\(|[0-9]+px|\": [0-9]" products\reference-implementations\axismundi-pilot\theme.json
```

The second grep may find allowed non-axismundi values elsewhere in
`theme.json`; Phase 1 must inspect only `settings.custom.axismundi.*` before
calling it a finding.

After Phase 1E validator matrix:

```powershell
npm run validate:computed
```

Expected upgrade:

```txt
computed-style audit PASS
  light PASS
  dark PASS
```

## §7. Applicable Gates

Use:

```txt
G1-G10:
  documentation, source authority, validation, generated mirror discipline.

G21-G26:
  token bridge files act as shared infrastructure and must preserve explicit
  provider/consumer boundaries.
```

Additional v3.6.x gates:

```txt
Computed style is proof.
Light and dark mode both pass.
WP core reset precedes M3 mapping.
Strict M3 mode means M3 → WP only.
```

## §8. Non-Goals

```txt
No implementation before Opus second GO.
No token rename sweep from --comp-* to --ax-comp-* unless separately approved.
No Interpreter Plugin.
No HCT generation.
No WP → M3 bridge.
No custom block registration.
No Carousel plugin extraction.
No v4.0 directory restructure.
No module boundary rewrite.
```

## §9. Risks

```txt
Risk:
  @import behaves differently between front end, file:// styleguide, and WP
  editor styles.
Mitigation:
  Use explicit link/enqueue order as the active path; keep tokens.css as an
  empty shim, not an @import chain.

Risk:
  Token split changes cascade order.
Mitigation:
  Preserve current tokens.css order and validate after Phase 1A.

Risk:
  wp-custom becomes a literal source-of-truth.
Mitigation:
  P1 regex/hunk review for settings.custom.axismundi.* and bridge file values.

Risk:
  Dark mode modifies ref values.
Mitigation:
  Keep md-ref mode-agnostic; only sys files contain mode-specific mappings.

Risk:
  Pilot asset bridge drifts from lab.
Mitigation:
  build_pilot_assets.py must copy the same layer files and validation must run
  after every asset bridge change.

Risk:
  BACKLOG #20 is closed too early.
Mitigation:
  Tie closure to Phase 1F only after implementation validation.
```

## §10. Phase 1 Exit Criteria

Phase 1 may proceed to implementation only after:

```txt
1. Opus review returns GO (i.e., no P1 findings remain).
2. Dependency arrows in §3 are accepted or corrected.
3. Execution order in §4 is accepted or corrected.
4. P1 theme.json hunk review rule is accepted or corrected.
5. Validation checkpoints in §6 are accepted or corrected.
6. Review cadence in §4 is accepted or corrected.
7. php -l, npm test, and npm run validate:computed pass on current HEAD.
```

Phase 1 implementation should not start until these are true.
