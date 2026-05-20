# v3.6.3 - WP Block Bridge Expansion - Semantic Decisions

Date: 2026-05-20

Cycle: v3.6.3 - WP Block Bridge Expansion

Phase: 3 - Semantic Decisions

## Verdict

Phase 3 routes both semantic-decision findings without implementing custom
blocks or plugin behavior.

```txt
button-anchor-semantics:   theme-owned core/button bridge route
quote-pullquote-semantics: theme-owned split styling route
custom block work:         not implemented
plugin work:               routed only
```

This document is a routing artifact. It does not patch CSS, register block
styles, edit theme.json, or add custom blocks.

## Sources

```txt
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-WP-MAPPING.md
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
```

## Decision 1 - button-anchor-semantics

### Finding

v3.6.2 Phase 3 recorded:

```txt
Button variants remain link-based core/button output; underline/user-select
leakage persists across modes.
```

The risk is treating the visual leak as only a CSS defect without first naming
the semantic route for `core/button` anchor output.

### Route

`core/button` remains the primary theme-owned route for button-shaped
WordPress content.

When `core/button` renders an anchor with `href`, the anchor is semantically
valid navigation. The theme may bridge that anchor to an M3 button visual
surface because the semantic action remains navigation. The theme must not
force that anchor into `<button>` semantics just because the visual surface is
button-shaped.

Allowed theme territory:

```txt
- bridge CSS for `.wp-block-button__link`
- block style variations on `core/button`
- block patterns that compose `core/buttons`
- progressive theme runtime for visual affordances only
- mechanical cleanup after this route is named
  (text-decoration, user-select, focus/hover/pressed visual states)
```

Not allowed in v3.6.3:

```txt
- custom button block implementation
- plugin behavior for form submit, AJAX, federation, or validation
- changing navigation anchors into action buttons
- hiding semantic mismatch behind a CSS-only visual patch
```

### Consequence

Underline and user-select cleanup may be accepted as a later bridge patch only
because the route is now explicit: a native `core/button` anchor is a valid
navigation link styled as an M3 button surface.

If a future use case requires plugin-owned behavior, durable custom schema,
dynamic save markup, or federation action handling, that work must be routed to
plugin/custom-block territory and not implemented inside this theme bridge
cycle.

## Decision 2 - quote-pullquote-semantics

### Finding

v3.6.2 Phase 3 recorded:

```txt
core/quote uses `blockquote`; core/pullquote wraps `blockquote` inside
`figure`, mixing quote styling and semantic concerns.
```

The risk is collapsing both structures into one generic `blockquote` style,
which makes `core/pullquote` inherit quote styling merely because it contains a
blockquote element.

### Route

`core/quote` and `core/pullquote` remain distinct theme-owned surfaces.

`core/quote` route:

```txt
WordPress block: core/quote
Markup shape:    blockquote.wp-block-quote
Theme meaning:   prose quote
Visual route:    primary-bordered quote treatment with citation styling
```

`core/pullquote` route:

```txt
WordPress block: core/pullquote
Markup shape:    figure.wp-block-pullquote > blockquote
Theme meaning:   editorial pullquote
Visual route:    centered emphasis with top/bottom dividers and larger type
```

Allowed theme territory:

```txt
- selector narrowing so generic quote styling does not accidentally swallow
  pullquote internals
- distinct `.wp-block-pullquote` bridge CSS
- citation styling that respects each block's markup shape
- computed/visual evidence on the specimen wall before accepting a patch
```

Not allowed in v3.6.3:

```txt
- custom quote or pullquote block implementation
- plugin behavior
- silently treating every `blockquote` in post content as `core/quote`
- collapsing `core/quote` and `core/pullquote` into one shared quote style
  without preserving the distinct pullquote route
```

### Consequence

Any future quote/pullquote bridge patch must prove that `core/quote` and
`core/pullquote` remain visually and semantically distinct. A broad selector
such as `blockquote` may still be useful for prose defaults, but block bridge
rules must avoid making `core/pullquote` a side effect of quote styling.

## Plugin / Custom Block Routing

No plugin or custom block work is required to close Phase 3.

If future requirements include editor-specific custom controls, durable custom
attributes, dynamic server rendering, federation actions, or form behavior,
those requirements must be routed outside the theme bridge:

```txt
custom block implementation drift = P1
semantic mismatch routing must stay in scope
```

## Close Criteria

```txt
core/button explicit route before link-affordance fix:       yes
core/quote + core/pullquote explicit route before shared CSS: yes
plugin/custom-block need routed, not implemented:            yes
semantic mismatch silently ignored:                          no
```
