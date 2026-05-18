# Axismundi v3.6.0 — Ontology Theme Pilot Phase 2E Report

Status: PASS
Date: 2026-05-19
Scope: WordPress block → M3 reverse mapping bridge

## 1. User Request Log

- Do not solve prose rendering by forcing a `.prose` wrapper on `core/post-content`.
- Preserve WordPress block-level customization and block supports.
- Map WordPress core block markup back to the M3 token/component contracts.
- Keep custom block registration forbidden.
- Keep Carousel out of the theme.

## 2. Architecture Decision

Phase 2E introduces a Pilot-specific block bridge:

```txt
products/reference-implementations/axismundi-pilot/bridge/
  pilot-block-bridge.css
  pilot-block-bridge.js
```

The bridge is copied into the runtime asset bundle by:

```txt
tools/generators/build_pilot_assets.py
```

Generated assets:

```txt
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

This keeps the lab/styleguide `.prose` contract intact while allowing the Pilot
theme to style real WordPress post bodies block-by-block.

## 3. Implementation Summary

### Prose reverse mapping

`pilot-block-bridge.css` maps:

- `.wp-block-post-content`
- `core/paragraph`
- `core/heading`
- `core/list`
- `core/quote`
- `core/pullquote`
- `core/code`
- `core/preformatted`
- `core/separator`
- `core/table`
- `core/table` stripes variant
- `core/image`
- `core/gallery`
- inline links and code

The previous temporary `className: "prose"` workaround was removed from:

- `templates/single.html`
- `templates/page.html`

### Button block state bridge

`pilot-block-bridge.css` extends `.wp-block-button__link` with:

- hover state layer
- focus-visible ring
- pressed state layer
- active finite-radius morph
- disabled opacity guard
- ripple span styling

`pilot-block-bridge.js` adds minimal bounded ripple enhancement to
`.wp-block-button__link` on the front end. This does not register a custom
block and does not promote a new component.

### Pattern coverage

`patterns/prose-sample.php` now includes:

- Korean paragraphs
- inline code
- quote
- code block
- list
- separator
- table
- stripes table

This gives the Pilot a concrete prose/block bridge specimen without relying on
the lab `.prose` wrapper.

## 4. Verification

```txt
build_pilot_assets.py: PASS
functions.php lint:    PASS
core/button custom styles: tonal, elevated, text
core/button native styles: fill, outline (mapped to M3 Filled / Outlined)
custom blocks:         none
```

Single post verification:

```txt
post-content has .prose: false
pilot-block-bridge.css: true
pilot-block-bridge.js:  true
table border:           1px
table overflow-x:       auto
stripe table:           second row surface-container-high / cell border reset
code block padding:     24px
separator margin:       32px
horizontal overflow:    0
```

Pattern QA verification:

```txt
button count:        8
data-ax-ripple:     bounded
bridge attached:    true
ripple span on tap: yes
fill style:          maps to M3 Filled
outline style:       maps to M3 Outlined
horizontal overflow: 0
```

Computed-style audit:

```txt
pattern QA page:      PASS
single prose page:    PASS
front page:           PASS
styleguide blocks:    PASS
console/page errors:  0
horizontal overflow:  0
```

Key computed values:

```txt
Button fill:          non-transparent primary container
Button outline:       native border 0px; inset outline via box-shadow
Button text:          transparent container
Search filled:        border 0px; visible surface container
Code block wrapper:   surface-container background; 24px padding
Table default cell:   top border 0px; bottom separator 1px
Table stripes odd:    transparent row/cell background
Table stripes even:   surface-container-high row/cell background
```

## 5. Remaining Work

Phase 2E proves the minimum reverse mapping path. Full WordPress block bridge
coverage remains a follow-up architecture item:

- More core blocks beyond the Pilot proof set.
- Editor-canvas parity for every state bridge.
- Systematic asset slicing for plugin-routed components.
- Deeper ripple policy for editor vs front-end surfaces.

Tracked by BACKLOG #41 and BACKLOG #40.

## 6. Verdict

Phase 2E passes. The Pilot now demonstrates block-first reverse mapping instead
of container-level prose wrapping.
