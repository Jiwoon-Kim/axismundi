# Axismundi v3.6.0 — Ontology Theme Pilot Phase 0 Report

Status: Phase 0 report v1.0  
Date: 2026-05-18  
Cycle: v3.6.0 Ontology Theme Pilot  
Input: `ONTOLOGY-THEME-PILOT-PHASE-0-PLAN.md`

## §0. Verdict

v3.6.0 should proceed as a **new theme-only WordPress block theme Pilot** at:

```txt
products/reference-implementations/axismundi-pilot/
```

The existing `ontology-theme-pilot/` directory is a previous unzipped validation
theme and remains untouched.

Locked direction:

```txt
Theme slug:       axismundi-pilot
Display name:     Axismundi Pilot
wp-env mount:     ./products/reference-implementations/axismundi-pilot
Color mode:       Theme-only + Stage 1 Static slug bridge
Color policy:     settings.color.custom = false
Scope:            Wave 1 minus Carousel + infrastructure + blocks/prose
Custom blocks:    forbidden in Pilot
Carousel:         excluded, BACKLOG #38
```

## §1. User Request Log Disposition

| User request | Phase 0 disposition |
|---|---|
| New Pilot path should not reuse `ontology-theme-pilot/` | Locked: `axismundi-pilot/` |
| Existing `ontology-theme-pilot/` is a theme artifact | Preserve untouched |
| Slug/display should be explicit | `axismundi-pilot` / `Axismundi Pilot` |
| wp-env should mount the new Pilot theme | Root `.wp-env.json` recommended |
| v4.0 may graduate naming later | Deferred; no v3.6.0 decision |
| Color token approach was already discussed | Existing binding record found and reused |

No item is abstracted into a generic lane without a matching acceptance check.

## §2. Binding Artifact Utilization Plan

| Artifact | Role in v3.6.0 |
|---|---|
| `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md` | Governs color policy, 3-tier architecture, visible-control principle, Interpreter Plugin separation |
| `bindings/wordpress-material3/binding_summary.md` | Quick reference for token and component binding confidence |
| `bindings/wordpress-material3/binding_map.json` | Source for theme.json token binding patterns and block style registration candidates |
| `bindings/wordpress-material3/block_component_rules.json` | Source for `register_block_style()` rules and block/pattern/plugin boundary classification |
| `bindings/wordpress-material3/gap_report.md` | Sanity check for strong bindings, current gaps, and self-validation limitations |
| `core/wordpress/pilots/p4_theme_settings.json` | Theme settings ontology: color, typography, spacing, appearanceTools and runtime defaults |
| `core/wordpress/pilots/ontology_theme_v0_1.jsonld` | JSON-LD traceability for theme-token entities |
| `docs/v3.5.18/BLOCKS-PROSE-PILOT-SPEC-VERIFY.md` | Confirms blocks/prose are Pilot input specs and notes expected `core/button` PHP work |
| `docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md` | Pre-entry handoff; must be updated from old `axismundi-pilot-theme/` wording to `axismundi-pilot/` |

## §3. Prior Color Decision

The binding record already defines the color strategy. v3.6.0 does not invent a
new one.

Source:

```txt
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
BACKLOG #20
BACKLOG #21
```

Locked mode:

```txt
Theme-only mode:
  settings.color.custom = false
  Gutenberg custom color picker is locked.
  M3 token graph is protected.

Bridge:
  Stage 1 — Static slug bridge.

Direction:
  Strict M3 mode: M3 sys token -> WP preset.

Source of truth:
  tokens.css = runtime token graph
  theme.json = Gutenberg contract / preset slug registry
```

Do not show fake color controls. If a color control is visible in WP, it must
affect real runtime behavior or be disabled by policy.

### Phase 2 palette subset

Use a focused Phase 2 subset, with room to expand if generation is trivial:

```txt
primary
on-primary
primary-container
on-primary-container
secondary
on-secondary
secondary-container
on-secondary-container
tertiary
on-tertiary
tertiary-container
on-tertiary-container
error
on-error
error-container
on-error-container
surface
on-surface
surface-variant
on-surface-variant
background
on-background
outline
outline-variant
```

BACKLOG #20 close trigger:

```txt
axismundi-pilot/theme.json contains settings.color.custom = false and the Pilot
validates that the theme can run without fake Gutenberg color customization.
```

## §4. Typography / Spacing / Elevation / Shape Decisions

Use `binding_summary.md` and `binding_map.json`:

| Token family | Binding pattern | Pilot policy |
|---|---|---|
| Color | `role_to_slug` | Stage 1 static slug bridge; `custom=false` |
| Typography | `role_to_slug_plus_utility_class` | Register fontSizes for discoverability; keep full M3 role properties in CSS utilities/runtime |
| Spacing | `wp_authoritative_with_axismundi_recommendation` | Theme.json owns spacing scale; do not claim M3 canonical spacing |
| Shadow/elevation | `level_to_preset` | Register level presets if useful for block styles |
| Shape | `radius_token_subset` | Register useful radius presets; full component shape remains CSS |
| AppearanceTools | `meta_flag_to_capability_bundle` | Use cautiously; do not expose controls that violate M3 strict mode |

Korean content:

```txt
Noto Sans KR / Noto Serif KR remain content fonts.
Roboto and Material Symbols remain M3-facing assets.
```

## §5. Pilot Deliverables

### Phase 2A — Scaffold

```txt
products/reference-implementations/axismundi-pilot/
├── style.css
├── theme.json
├── functions.php
├── readme.txt
├── README.md
├── screenshot.png
└── .wp-env-note.md       (optional if root .wp-env.json is enough)
```

### Phase 2B — Asset bridge

```txt
assets/styles/tokens.css
assets/styles/components.css
assets/styles/blocks.css
assets/styles/prose.css
assets/styles/base.css
assets/styles/fonts.css
assets/styles/icons.css
assets/fonts/...
assets/icons/...
```

Preferred mechanism:

```txt
tools/generators/build_pilot_theme_assets.py
```

The script should copy source assets from `axismundi-lab/` into
`axismundi-pilot/assets/` so the mounted theme is self-contained.

### Phase 2C — Templates and parts

Minimum:

```txt
templates/index.html
templates/single.html
templates/page.html
parts/header.html
parts/footer.html
```

Optional if quick and useful:

```txt
templates/archive.html
templates/search.html
templates/404.html
parts/sidebar.html
```

### Phase 2D — Patterns and block styles

Minimum patterns:

```txt
patterns/hero.php
patterns/card-list.php
patterns/prose-sample.php
patterns/search-section.php
patterns/button-actions.php
```

Block style registration:

Use `bindings/wordpress-material3/block_component_rules.json` for candidates.
First-pass direct bindings should include `core/button` variants and safe
`core/group` / Card-like styles if already supported by current CSS.

Do not register custom blocks.

## §6. wp-env Decision

Recommended:

```txt
Root .wp-env.json
```

Reason:

- the project is a monorepo;
- the mounted theme path is explicit;
- commands can be run from the repo root alongside validators and npm scripts.

Draft:

```json
{
  "themes": [
    "./products/reference-implementations/axismundi-pilot"
  ],
  "plugins": []
}
```

Open environment checks before Phase 2:

```txt
wp-env available?
Docker available?
Should WP version be pinned or latest?
Korean language pack in scope?
Seed content import needed?
```

Default:

- latest stable WP unless a compatibility issue appears;
- Korean language sample content should be included in patterns/posts, but
  language-pack installation is optional for initial activation.

## §7. Existing `ontology-theme-pilot/` Reference Policy

Allowed:

```txt
Read it as historical evidence.
Compare theme.json/functions.php patterns.
Borrow ideas only after checking against current v3.6.0 locks.
```

Forbidden:

```txt
Rename it.
Move it.
Mutate it.
Copy it wholesale.
Treat it as the active Pilot directory.
```

## §8. Cycle Decomposition

v3.6.0 should be executed in sub-cycles:

```txt
Phase 1  Architecture implementation plan
Phase 2A Scaffold + wp-env
Phase 2B Asset bridge
Phase 2C Templates / parts
Phase 2D Patterns / block styles
Phase 3  WordPress runtime QA
Phase 5  Release close
```

Expected rhythm:

```txt
Phase 2A: 1 day
Phase 2B: 1 day
Phase 2C: 1-2 days
Phase 2D: 2-3 days
Phase 3: 1-2 days plus user QA
```

Each sub-cycle should end with:

```txt
validator PASS
npm test PASS
git status review
```

Commits may be split by sub-cycle.

## §9. Lane Assignment

```txt
Codex:
  implementation, local verification, wp-env setup, smoke checks.

Opus / GPT:
  ontology review after implementation sub-cycles:
    - Charter §3.4
    - theme-can / plugin-should
    - binding map consistency
    - no custom blocks
    - Carousel remains excluded

User:
  wp-env activation confirmation
  frontend visual QA
  editor/manual QA where needed
```

## §10. Phase 3 Acceptance Checklist

```txt
□ Theme appears in wp-env.
□ Theme activates without fatal errors.
□ Front page renders.
□ Single post renders prose content according to prose.html.
□ blocks.html coverage examples have corresponding WP behavior.
□ Button appears through core/button block style registration.
□ Card-like group style renders if registered.
□ List / search / text-field patterns render without custom blocks.
□ Mobile 390px / tablet 768px / desktop 1280px pass.
□ settings.color.custom = false.
□ No Carousel block/plugin registered.
□ No custom blocks registered.
□ No fake visible controls.
□ User Request Log §1 is checked one by one.
```

Recommended:

```txt
□ Playwright frontend smoke.
□ axe or Lighthouse a11y smoke.
□ Korean prose sample render.
□ Reduced-motion check if runtime JS is introduced.
```

## §11. Phase 1 Plan Requirements

Phase 1 implementation plan must define:

1. exact file tree;
2. asset bridge script shape;
3. theme.json token subset;
4. functions.php block-style registration list;
5. template/part/pattern minimum set;
6. wp-env command sequence;
7. verification commands per sub-cycle;
8. rollback plan if wp-env cannot run locally.

## §12. Non-Goals

- Do not create Pilot files in Phase 0.
- Do not edit `ontology-theme-pilot/`.
- Do not extract Carousel plugin.
- Do not implement M3 Interpreter Plugin.
- Do not register custom blocks.
- Do not modify WordPress core or Gutenberg.
- Do not start v4.0 graduation.
- Do not treat theme.json as the M3 token source of truth.

## §13. Validation

Phase 0 report is docs-only. Expected:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
git status --short
```

## §14. Final Phase 0 Verdict

Proceed to Phase 1 implementation plan after approval.

The v3.6.0 Pilot is ready to enter planning as:

```txt
Theme-only WordPress block theme Pilot
Directory: products/reference-implementations/axismundi-pilot/
Color: Theme-only + Stage 1 Static slug bridge
Source of truth: tokens.css
WP contract: theme.json
Runtime: wp-env
Scope: Wave 1 minus Carousel
```
