# Axismundi v3.6.0 — Ontology Theme Pilot Phase 0 Plan

Status: Phase 0 plan-first document  
Date: 2026-05-18  
Cycle: v3.6.0 Ontology Theme Pilot  
Scope: plan before creating the new Pilot theme

## §0. User Request Log — Do Not Abstract Away

These are explicit user requests for this Pilot entry. They are acceptance
criteria, not generic lane labels:

```txt
1. Create a new Pilot theme directory:
   products/reference-implementations/axismundi-pilot/

2. Do not reuse or rename the existing:
   products/reference-implementations/ontology-theme-pilot/
   It is an unzipped historical validation theme and must remain untouched.

3. WP theme slug:
   axismundi-pilot

4. WP theme display name:
   Axismundi Pilot

5. wp-env mount target:
   ./products/reference-implementations/axismundi-pilot

6. v4.0 future:
   axismundi-pilot may graduate to axismundi-theme/ or a separate repo,
   but v3.6.0 must not make that decision.

7. Pilot is theme-only.
   No Carousel plugin extraction, no custom block registration, no M3
   Interpreter Plugin work.

8. Color token implementation must reuse the prior recorded decision.
   Do not invent a new color architecture if the binding record already
   contains one.
```

Phase 5 close is blocked unless every item above is checked directly.

## §1. Prior Decision Record Check

Before implementation, v3.6.0 must treat the existing binding records as source
material:

```txt
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
bindings/wordpress-material3/binding_summary.md
bindings/wordpress-material3/binding_map.json
core/wordpress/pilots/p4_theme_settings.json
core/wordpress/pilots/ontology_theme_v0_1.jsonld
docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md
BACKLOG.md #20 / #21 / #38 / #39
```

Rule:

```txt
If the user says a topic was previously discussed, grep/read the record first.
Do not offer fresh option trees until prior decisions are located or proven
missing.
```

## §2. Verdict Preview

Recommended direction:

```txt
Proceed with a new theme-only Pilot at:
  products/reference-implementations/axismundi-pilot/

Mount it in wp-env as:
  ./products/reference-implementations/axismundi-pilot

Use existing binding decisions:
  color: Theme-only mode + Stage 1 static slug bridge
  typography: role_to_slug_plus_utility_class
  spacing: WP-authoritative with Axismundi recommendation

Do not touch:
  ontology-theme-pilot/
  Carousel plugin extraction
  custom block registration
  v4.0 graduation naming
```

## §3. Pilot Location and Identity

Locked proposal:

```txt
Directory:
  products/reference-implementations/axismundi-pilot/

Theme slug:
  axismundi-pilot

Display name:
  Axismundi Pilot

wp-env mount:
  ./products/reference-implementations/axismundi-pilot

Historical directory:
  products/reference-implementations/ontology-theme-pilot/
  untouched, historical evidence
```

Rationale:

- `ontology-theme-pilot/` already means a previous unzipped theme artifact.
- `axismundi-pilot/` matches v3.6.0 scope without pretending this is v4.0's
  final distributable theme.
- The Pilot remains inside the monorepo product layer while staying outside
  `axismundi-lab/`, satisfying Charter §3.4's "pilot outside theme repo"
  boundary.

## §4. Pilot Scope

The Pilot consumes **Wave 1 minus Carousel**:

```txt
1. Button #1
2. Icon button #2
3. FAB / Extended FAB #3 / #4
4. Button group #6
5. Card #9
6. Text field #16
7. Search bar #17
8. List #33
```

Infrastructure dependencies:

```txt
popover/
ripple/
icon-system/
```

Pilot specification surfaces:

```txt
styleguide/index.html  = component chrome catalog
styleguide/blocks.html = WP core block coverage extension spec
styleguide/prose.html  = post body rendering contract
tokens.css             = M3 token graph source
components.css         = Wave 1 component styles
blocks.css             = WP block coverage styles
prose.css              = post content styles
```

Explicit exclusions:

```txt
Carousel #34 plugin/block work      -> BACKLOG #38
blocks/prose shell consistency      -> BACKLOG #39
M3 Interpreter Plugin / HCT panel   -> BACKLOG #21
theme-only color policy close check -> BACKLOG #20
v4.0 directory restructure          -> BACKLOG #36
GitHub Pages dogfooding             -> BACKLOG #37
```

## §5. WordPress Theme Deliverables

Expected theme files:

```txt
products/reference-implementations/axismundi-pilot/
├── style.css
├── theme.json
├── functions.php
├── readme.txt
├── screenshot.png              (can be placeholder until visual pass)
├── assets/
│   ├── styles/
│   ├── fonts/                  (if copied for self-contained test)
│   └── icons/                  (if copied for self-contained test)
├── templates/
│   ├── index.html
│   ├── single.html
│   ├── page.html
│   └── archive.html            (Phase 2 may defer if not needed)
├── parts/
│   ├── header.html
│   └── footer.html
└── patterns/
    ├── hero.php
    ├── prose-sample.php
    ├── card-list.php
    └── search-section.php
```

Phase 0 report must confirm which of these are v3.6.0 minimum deliverables and
which are Phase 2 optional.

## §6. Lab Artifact Consumption

Preferred mechanism:

```txt
Build/copy step:
  axismundi-lab styles/assets
    -> axismundi-pilot/assets/
```

Do not rely on live relative imports from the lab directory in the active WP
theme. A Pilot theme should behave like a self-contained WordPress theme when
mounted by `wp-env`.

Phase 0 report must decide whether to:

```txt
A. write a new copy script under tools/generators/
B. use a small npm script
C. manually copy only for Phase 2 and formalize later
```

Default recommendation: A small generator script, because v3.5.14 already uses
generator discipline for publish surfaces.

## §7. Color Token Integration

This is **not a new decision**. Use the existing binding record:

```txt
Source:
  bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
  bindings/wordpress-material3/binding_summary.md
  BACKLOG #20 / #21

Mode:
  Theme-only mode

Bridge:
  Stage 1 — Static slug bridge

Direction:
  Strict M3 mode: M3 sys token -> WP preset

Policy:
  settings.color.custom = false
```

Meaning:

- `tokens.css` remains the Material 3 token graph source of truth.
- `theme.json` is the Gutenberg UI contract / preset slug registry /
  compatibility layer, not the source of truth.
- `theme.json settings.color.palette` registers M3 sys-role slugs that point to
  `var(--md-sys-color-*)`; it is a static slug registry, not a hex source of
  truth.
- Full color customization is plugin territory, not Pilot scope.
- BACKLOG #20 can close only after Pilot verifies `settings.color.custom =
  false` in the new `axismundi-pilot/theme.json`.
- BACKLOG #21 owns Stage 2 / Stage 3 Interpreter Plugin behavior.

Phase 0 report must list the exact palette subset for Phase 2. Default:

```txt
primary, on-primary, primary-container, on-primary-container
secondary, on-secondary, secondary-container, on-secondary-container
tertiary, on-tertiary, tertiary-container, on-tertiary-container
error, on-error, error-container, on-error-container
surface, on-surface, surface-variant, on-surface-variant
outline, outline-variant, background, on-background
```

Do not expose a fake color control that cannot affect the runtime token graph.

## §8. Typography, Spacing, Elevation, and Shape Tokens

Use the binding map rather than inventing new policy:

```txt
Typography:
  pattern = role_to_slug_plus_utility_class
  source  = tokens.css §3 sys-typescale + base.css type utilities
  theme.json registers fontSizes for editor discoverability
  missing M3 properties (weight/lineHeight/tracking) remain CSS utility/runtime

Spacing:
  pattern = wp_authoritative_with_axismundi_recommendation
  source  = theme.json spacing scale for WP controls
  note    = M3 baseline has no canonical spacing token spec

Elevation:
  pattern = level_to_preset
  source  = M3 sys-elevation -> WP shadow presets

Shape:
  pattern = radius_token_subset
  source  = M3 sys-shape subset -> WP border radius presets
```

Korean typography:

```txt
Noto Sans KR / Noto Serif KR must remain available for content rendering.
Roboto / Material Symbols remain M3-facing assets.
```

Phase 0 report must decide whether the Pilot copies font assets or relies on
existing repository-root asset paths during wp-env verification.

## §9. WordPress Environment

Preferred:

```txt
wp-env
```

Proposed root `.wp-env.json`:

```json
{
  "themes": [
    "./products/reference-implementations/axismundi-pilot"
  ],
  "plugins": []
}
```

Open questions for Phase 0 report:

```txt
1. Is wp-env installed and authenticated in the user's environment?
2. Should .wp-env.json live at repo root or inside axismundi-pilot/?
3. Which WP version should be pinned?
4. Is Korean language pack setup in scope for v3.6.0, or manual QA only?
5. Do we need a seed content import file for post/prose QA?
```

Default recommendation:

- root `.wp-env.json` for monorepo convenience;
- latest stable WordPress unless a compatibility issue appears;
- Korean language pack as Phase 2 optional, not initial blocker.

## §10. Cycle Decomposition

v3.6.0 is larger than a one-pass lab module. Phase 2 should be subdivided:

```txt
Phase 0  Plan/report: lock location, scope, token integration, environment.
Phase 1  Theme architecture plan: file tree, copy/build strategy, WP bindings.
Phase 2A Scaffold: style.css, theme.json, functions.php, wp-env.
Phase 2B Asset bridge: copy/build styles/fonts/icons.
Phase 2C Templates/parts: index, single, page, header, footer.
Phase 2D Patterns/block styles: Button/Card/List/Search/prose examples.
Phase 3  WP runtime QA: activation, frontend, editor, responsive, a11y.
Phase 5  Close: docs, changelog, backlog, pilot verdict.
```

Multiple commits during Phase 2 are allowed if each commit has a coherent
theme-building boundary.

## §11. Lane Assignment

```txt
Codex implementation:
  scaffold files
  generate/copy assets
  write theme.json / functions.php / templates / patterns
  run wp-env and automated checks where available

Opus/GPT ontology review:
  Charter §3.4 scope
  theme-can / plugin-should boundaries
  WordPress ↔ M3 binding correctness
  Matrix and BACKLOG consistency

Codex correction:
  apply ontology review fixes
  rerun runtime QA
```

Implementation review is not the same as ontology review; both may be needed,
but the named Opus/GPT lane is ontology consistency.

## §12. Phase 3 Acceptance Criteria

Pilot cannot close on file existence alone. It must pass runtime checks:

```txt
□ Theme appears in wp-env and activates.
□ Front page renders without fatal errors.
□ Single post renders prose content according to prose.html contract.
□ blocks.html coverage examples have matching WP theme behavior.
□ Wave 1 minus Carousel components have visible Pilot specimens.
□ Button / Icon button / FAB / Button group / Card / Text field / Search bar /
  List visual QA passes at 390 / 768 / 1280.
□ theme.json contains Theme-only color policy:
  settings.color.custom = false.
□ No Carousel block/plugin is registered.
□ No custom blocks are registered.
□ No visible fake controls are introduced.
□ Global portal/overlay smoke rule is applied if any trigger/host runtime is
  used.
□ User Request Log items from §0 are checked one by one.
```

Optional but recommended:

```txt
□ Playwright frontend smoke.
□ axe or Lighthouse accessibility pass.
□ WordPress editor manual QA.
□ Korean content sample render.
```

## §13. Non-Goals

- Do not edit or rename `ontology-theme-pilot/`.
- Do not extract Carousel plugin.
- Do not implement the M3 Interpreter Plugin.
- Do not register custom blocks.
- Do not start WP.org submission.
- Do not decide v4.0 `axismundi-theme/` graduation.
- Do not redo GitHub Pages styleguide modernization.
- Do not add unclosed Wave 2 components to the theme.

## §14. Phase 0 Report Questions

Phase 0 report must answer:

1. Is `products/reference-implementations/axismundi-pilot/` accepted as the
   Pilot location?
2. What exact theme.json color palette subset is in scope?
3. Does Pilot use a generator/copy step, npm script, or manual copy for assets?
4. Where will `.wp-env.json` live?
5. What is the minimum template/pattern set for Phase 2A-D?
6. What current files in `ontology-theme-pilot/` are useful references without
   being copied blindly?
7. What can close BACKLOG #20 during v3.6.0?
8. What would block v3.6.0 Phase 2 from starting?

## §15. Validation for This Plan

Expected plan-only validation:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
git status --short
```

No Pilot files should be created during this Phase 0 plan.

## §16. Verdict

Proceed to Phase 0 report after review.

Recommended lock:

```txt
Directory: products/reference-implementations/axismundi-pilot/
Theme:     Axismundi Pilot / axismundi-pilot
Mode:      theme-only
Color:     Stage 1 Static slug bridge, settings.color.custom=false
Scope:     Wave 1 minus Carousel + blocks/prose + infrastructure
Runtime:   wp-env
```
