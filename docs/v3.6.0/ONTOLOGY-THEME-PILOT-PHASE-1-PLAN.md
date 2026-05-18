# Axismundi v3.6.0 — Ontology Theme Pilot Phase 1 Implementation Plan

Status: Phase 1 plan v1.0  
Date: 2026-05-18  
Cycle: v3.6.0 Ontology Theme Pilot  
Inputs:

- `ONTOLOGY-THEME-PILOT-PHASE-0-PLAN.md`
- `ONTOLOGY-THEME-PILOT-PHASE-0-REPORT.md`
- `ONTOLOGY-THEME-PILOT-PRE-PHASE-1-REVIEW.md`
- `ONTOLOGY-THEME-PILOT-HANDOFF.md`
- `bindings/wordpress-material3/*`
- `core/wordpress/pilots/p4_theme_settings.json`

## §0. Verdict

Phase 1 is **READY FOR REVIEW**. It defines the v3.6.0 Pilot implementation
without creating the Pilot theme yet.

Recommended execution route after approval:

```txt
Phase 2A — Scaffold + wp-env
Phase 2B — Asset bridge
Phase 2C — Templates / parts
Phase 2D — Patterns / block styles
Phase 3  — WordPress runtime QA
Phase 5  — Close
```

## §1. User Request Log — Carried Forward

These requests remain direct acceptance criteria:

```txt
□ Create products/reference-implementations/axismundi-pilot/
□ Use slug axismundi-pilot and display name Axismundi Pilot.
□ Mount that path with wp-env.
□ Do not touch products/reference-implementations/ontology-theme-pilot/.
□ Preserve v4.0 graduation as a future decision.
□ Use existing color-token decision, not a newly invented architecture.
□ Do not include Carousel.
□ Do not register custom blocks.
```

## §2. Review Findings Absorbed

Opus/GPT deep review findings are absorbed as follows:

| Finding | Phase 1 lock |
|---|---|
| Lane assignment needs precision | Split Opus ontology review from GPT external narrative review |
| Korean content render optional -> required | Add Korean prose sample to Phase 3 required criteria |
| Asset bridge default -> lock | Lock generator script approach |
| `readme.txt` / `screenshot.png` scope unclear | Treat as draft/placeholder, not WP.org submission artifacts |
| Palette 24 slug needs grep | Completed; all 24 sys-color tokens exist in `stylesheets/tokens.css` |
| wp-env install check | `wp-env` command exists; Docker is not running and is a Phase 2A gate |
| Commit boundary needs precision | Each Phase 2 sub-cycle ends with validator/npm/status and may commit |
| Rollback / scope reduction missing | Add contingency triggers in §14 |

Codex review finding already fixed:

```txt
Stage 1 Static bridge -> Stage 1 Static slug bridge
theme.json palette values must use var(--md-sys-color-*), not hex literals.
```

## §3. Environment Status

Observed before Phase 1:

```txt
wp-env command: available globally
@wordpress/env local dependency: not installed
Docker: not running / not reachable
```

Phase 2A gate:

```txt
User must start Docker Desktop before wp-env activation testing.
```

If Docker remains unavailable, Phase 2A may still scaffold files, but runtime QA
must be marked BLOCKED until Docker is running.

## §4. Exact File Tree

Phase 2 should create:

```txt
products/reference-implementations/axismundi-pilot/
├── style.css
├── theme.json
├── functions.php
├── README.md
├── readme.txt              (draft; not WP.org submission-ready)
├── screenshot.png          (placeholder allowed)
├── assets/
│   ├── styles/
│   │   ├── fonts.css
│   │   ├── tokens.css
│   │   ├── base.css
│   │   ├── icons.css
│   │   ├── components.css
│   │   ├── blocks.css
│   │   └── prose.css
│   ├── fonts/
│   │   ├── roboto-flex/
│   │   ├── roboto-mono/
│   │   ├── noto-sans-kr/
│   │   └── noto-serif-kr/       (copy if used; otherwise document omission)
│   └── icons/
│       └── material-symbols-rounded/
├── templates/
│   ├── index.html
│   ├── single.html
│   └── page.html
├── parts/
│   ├── header.html
│   └── footer.html
└── patterns/
    ├── hero.php
    ├── button-actions.php
    ├── card-list.php
    ├── search-section.php
    └── prose-sample.php
```

Optional Phase 2C files:

```txt
templates/archive.html
templates/search.html
templates/404.html
parts/sidebar.html
```

Optional files must not block Phase 3 if the minimum theme proves the ontology.

## §5. Phase 2A — Scaffold + wp-env

Deliverables:

```txt
.wp-env.json              (repo root)
products/reference-implementations/axismundi-pilot/style.css
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/README.md
products/reference-implementations/axismundi-pilot/readme.txt
products/reference-implementations/axismundi-pilot/screenshot.png
```

### `style.css`

Must include WordPress theme header:

```css
/*
Theme Name: Axismundi Pilot
Theme URI: https://github.com/Jiwoon-Kim/axismundi
Author: KIM JIWOON
Author URI: https://designbusan.ai.kr
Description: Ontology theme pilot for Axismundi's Material 3 WordPress binding.
Version: 0.1.0-pilot
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: axismundi-pilot
*/
```

### `theme.json`

Minimum:

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 3,
  "settings": {
    "appearanceTools": true,
    "color": {
      "custom": false,
      "customGradient": false,
      "defaultPalette": false,
      "defaultGradients": false,
      "defaultDuotone": false,
      "palette": []
    }
  }
}
```

Palette values must use:

```json
"color": "var(--md-sys-color-primary)"
```

not hex literals.

### `functions.php`

Minimum responsibilities:

```txt
after_setup_theme:
  add_theme_support('wp-block-styles')
  add_theme_support('editor-styles')
  add_theme_support('responsive-embeds')
  add_theme_support('post-thumbnails')
  load_theme_textdomain('axismundi-pilot')
  add_editor_style([...])

wp_enqueue_scripts:
  enqueue assets/styles/fonts.css
  enqueue assets/styles/tokens.css
  enqueue assets/styles/base.css
  enqueue assets/styles/icons.css
  enqueue assets/styles/components.css
  enqueue assets/styles/blocks.css
  enqueue assets/styles/prose.css

init:
  register allowed core block styles only.
```

No custom block registration.

### `.wp-env.json`

Root file:

```json
{
  "themes": [
    "./products/reference-implementations/axismundi-pilot"
  ],
  "plugins": []
}
```

Do not pin WordPress version in Phase 2A unless latest stable fails.

### Phase 2A verification

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
wp-env start
wp-env run cli wp theme list
wp-env run cli wp theme activate axismundi-pilot
git status --short
```

If Docker is off:

```txt
wp-env commands are BLOCKED, not failed.
Ask user to start Docker Desktop before runtime QA.
```

## §6. Phase 2B — Asset Bridge

Lock:

```txt
Use generator script approach.
```

Deliverable:

```txt
tools/generators/build_pilot_theme_assets.py
```

The script must copy from:

```txt
products/reference-implementations/axismundi-lab/stylesheets/
core/design-systems/material3/assets/fonts/
core/design-systems/material3/assets/icons/
```

to:

```txt
products/reference-implementations/axismundi-pilot/assets/styles/
products/reference-implementations/axismundi-pilot/assets/fonts/
products/reference-implementations/axismundi-pilot/assets/icons/
```

CSS files:

```txt
fonts.css
tokens.css
base.css
icons.css
components.css
blocks.css
prose.css
```

### URL rewrite requirement

`fonts.css` currently points from lab stylesheets to:

```txt
../../../../core/design-systems/material3/assets/...
```

Pilot asset copy must rewrite those URLs to local theme paths:

```txt
../fonts/...
../icons/...
```

This rewrite is required before wp-env frontend/editor QA, otherwise the theme
is not self-contained.

### Phase 2B verification

```powershell
python .\tools\generators\build_pilot_theme_assets.py
Test-Path .\products\reference-implementations\axismundi-pilot\assets\styles\tokens.css
rg -n "\.\./fonts|\.\./icons" .\products\reference-implementations\axismundi-pilot\assets\styles\fonts.css
python .\tools\validators\validate_theme_pilot.py
npm test
git status --short
```

## §7. Phase 2C — Templates / Parts

Minimum templates:

```txt
templates/index.html
templates/single.html
templates/page.html
parts/header.html
parts/footer.html
```

Principles:

- use only WordPress core blocks;
- do not encode unclosed components as fake components;
- keep header/footer simple and theme-only;
- single template must prove prose rendering;
- index template must prove block pattern and component consumption.

Suggested block structure:

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->
<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
...
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

### Phase 2C verification

```powershell
wp-env run cli wp theme activate axismundi-pilot
wp-env run cli wp post list
python .\tools\validators\validate_theme_pilot.py
npm test
git status --short
```

If runtime unavailable, verify static file validity and hold Phase 3 runtime QA.

## §8. Phase 2D — Patterns / Block Styles

Minimum pattern files:

```txt
patterns/hero.php
patterns/button-actions.php
patterns/card-list.php
patterns/search-section.php
patterns/prose-sample.php
```

Pattern requirements:

- include pattern headers;
- use core blocks only;
- demonstrate Wave 1 minus Carousel;
- include at least one Korean prose sample;
- avoid fake controls and unimplemented interactions.

### `functions.php` block style registration list

Allowed first-pass registrations:

```txt
core/button:
  filled
  tonal
  elevated
  outlined
  text

core/group:
  card-filled
  card-elevated
  card-outlined

core/list:
  list-segmented

core/separator:
  divider-inset
  divider-middle-inset

core/search:
  filled-search
```

Source:

```txt
bindings/wordpress-material3/block_component_rules.json
products/reference-implementations/ontology-theme-pilot/functions.php
```

Policy:

```txt
The old functions.php is reference evidence only. Do not copy it wholesale.
```

### Phase 2D verification

```powershell
wp-env run cli wp eval "print_r( WP_Block_Styles_Registry::get_instance()->get_all_registered() );"
python .\tools\validators\validate_theme_pilot.py
npm test
git status --short
```

## §9. Korean Content Requirement

Korean content render is required, not optional.

Phase 2D must include at least one Korean sample in a pattern or seeded post:

```txt
액시스문디 파일럿은 머티리얼 3 토큰과 워드프레스 블록 테마의 경계를 검증합니다.
```

Phase 3 acceptance:

```txt
□ Korean prose sample renders with Noto Sans KR fallback.
□ Latin text remains Roboto Flex.
□ No layout overflow at 390px.
```

## §10. Handoff Drift Correction

Before Phase 2A scaffold, update:

```txt
docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md
```

Replace old candidate:

```txt
products/reference-implementations/axismundi-pilot-theme/
```

with locked path:

```txt
products/reference-implementations/axismundi-pilot/
```

This is a documentation correction, not an implementation step.

## §11. Lane Assignment

Implementation lane:

```txt
Codex:
  Phase 2A-D file implementation
  asset generator
  wp-env commands
  validator/npm checks
  Playwright/frontend smoke where possible
```

Ontology review lane:

```txt
Opus:
  Charter §3.4
  theme-can / plugin-should
  binding map consistency
  component/matrix scope
  no custom blocks
  Carousel excluded
  color policy and visible-control principle
```

External narrative review lane:

```txt
GPT:
  user-facing narrative
  README / readme.txt clarity
  release story
  public explanation consistency
```

User QA lane:

```txt
User:
  Docker/wp-env availability
  WordPress activation confirmation
  frontend visual QA
  editor manual QA
```

## §12. Commit / Checkpoint Policy

Each sub-cycle may commit independently after approval:

```txt
2A scaffold       -> commit allowed
2B asset bridge   -> commit allowed
2C templates      -> commit allowed
2D patterns       -> commit allowed
Phase 5 close     -> final release commit
```

Every sub-cycle checkpoint requires:

```txt
validator 1.000 / 1.000 / 1.000 / 1.000 PASS
npm test PASS
git status reviewed
User Request Log still honored
No custom blocks
No Carousel
No ontology-theme-pilot/ mutation
```

## §13. Phase 3 Acceptance Checklist

Required:

```txt
□ Theme appears in wp-env.
□ Theme activates without fatal errors.
□ Front page renders.
□ Single post renders prose content according to prose.html.
□ Korean prose sample renders correctly.
□ blocks.html coverage examples have corresponding WP behavior.
□ Button appears through core/button block style registration.
□ Card-like group style renders if registered.
□ List / search / text-field patterns render without custom blocks.
□ Mobile 390px / tablet 768px / desktop 1280px pass.
□ settings.color.custom = false.
□ Palette slugs point to var(--md-sys-color-*), not hex literals.
□ No Carousel block/plugin registered.
□ No custom blocks registered.
□ No fake visible controls.
□ User Request Log §1 is checked one by one.
```

Recommended:

```txt
□ Playwright frontend smoke.
□ axe or Lighthouse a11y smoke.
□ WordPress editor manual QA.
```

## §14. Contingency / Scope Reduction

If wp-env cannot run:

```txt
Continue static scaffold and asset checks.
Mark runtime QA BLOCKED.
Do not claim Phase 3 PASS.
```

If theme.json schema rejects a token family:

```txt
Keep color + typography minimum.
Route lower-confidence token family (shadow/shape/spacing) to follow-up.
Do not mutate tokens.css to satisfy theme.json.
```

If asset bridge URL rewrite becomes risky:

```txt
Copy styles only and document font/icon remote-path gap.
Block Phase 3 visual QA until fonts/icons are resolved.
```

If templates grow too large:

```txt
Reduce Phase 2C to index + single + header + footer.
Defer archive/search/404.
```

If block style registration conflicts with WordPress behavior:

```txt
Keep core/button only.
Route Card/List/Search registrations to Phase 2D follow-up.
Do not create custom blocks as a workaround.
```

## §15. Non-Goals

- No Pilot files are created in Phase 1.
- Do not edit `ontology-theme-pilot/`.
- Do not extract Carousel plugin.
- Do not implement M3 Interpreter Plugin / HCT.
- Do not register custom blocks.
- Do not perform WP.org submission.
- Do not claim v4.0 graduation.
- Do not rebuild GitHub Pages styleguide.

## §16. Phase 2A Entry Criteria

Before Phase 2A:

```txt
□ User approves this Phase 1 plan.
□ User starts Docker Desktop or accepts runtime QA BLOCKED until later.
□ Handoff path drift correction is included in Phase 2A.
□ Existing untracked Phase 0 / Phase 1 docs are acceptable.
```

## §17. Validation

Phase 1 plan is docs-only. Expected:

```powershell
python .\tools\validators\validate_theme_pilot.py
npm test
git status --short
```

## §18. Verdict

Proceed to Phase 2A after review.

Phase 2A should create the new Pilot scaffold only; asset copying, templates,
and patterns remain separate sub-cycles.
