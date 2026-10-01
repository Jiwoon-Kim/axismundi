# Material 3 Typography Reference

## Purpose

This document preserves the Material 3 typography guidance adopted as the reference
for the Axismundi Social frontend. It is reference material, not a CSS generator or
a directive to change the existing theme.json document typography.

## Type scale sets

Material 3 defines one scale with two sets of 15 type styles:

- **Baseline**: the ordinary type styles.
- **Emphasized**: matching roles with a higher weight and small related adjustments.

The two sets are intended to be used together. An emphasized style replaces the
baseline token of the same role; it is not an arbitrary `font-weight` override.

```text
Baseline:   md.sys.typescale.display-large
Emphasized: md.sys.typescale.emphasized.display-large
```

### Emphasized usage

Use emphasized styles to convey hierarchy or importance in a deliberate context:

- selected or active components
- unread messages
- badges
- primary-action buttons and extended FABs
- selected list or menu items
- headlines and editorial treatments

Material components do not use emphasized type styles by default. A component opts
in by choosing the emphasized token for its existing role.

## Brand and plain typefaces

Material distinguishes two typeface roles:

- **Brand**: larger Display and Headline styles, where expression matters.
- **Plain**: smaller Body and Label styles, where readability matters.

Roboto is the Material default for both. The Social frontend realizes both roles
through the theme-provided Roboto Flex face: `--md-ref-typeface-plain` aliases
`--md-ref-typeface-variable-plain`, and brand aliases plain. The runtime stack is:

```text
Roboto Flex -> --axismundi-cjk-sans (or system-ui) -> sans-serif
```

Changing a typeface requires reviewing both baseline and emphasized sets.

## Language height

Type scale line height can adapt to script height. Material recommends **medium**
language height as the default, then selecting another category when the language is
known.

| Category | Typical scripts | Relative height |
| --- | --- | --- |
| Small | Cyrillic, Greek, Hebrew, Latin except Vietnamese | Base |
| Medium | Arabic, CJK, Korean, Vietnamese, and many others | About 7% taller |
| Large | Burmese, Telugu | About 30% taller |
| Extra large | Nastaliq | About 100% taller |

Components with vertical padding need to account for language height. Fixed-height
components are designed for the small category and must not assume all scripts fit
without adaptation.

No language-height detection or token set is implemented in the Social frontend yet.

## Customization rules

When changing the type system:

1. Change brand and plain typeface roles before changing individual styles.
2. Adjust line height, tracking, and variable-font axes only as needed for the
   selected typeface.
3. Avoid changing M3 role sizes casually because component rendering and reflow
   depend on them.
4. Keep the baseline and emphasized sets visually coherent.

Heavier faces can require wider tracking. Faces with longer ascenders or descenders
can require different line heights.

## Web units

Material documents web type sizes in `rem`, based on a 16px root size:

```text
web rem = Android sp / 16

10sp = 0.625rem
12sp = 0.75rem
24sp = 1.5rem
60sp = 3.75rem
```

Tracking is relative to the type size. The source guidance expresses it as the
tracking value divided by the font size, for example `0.2 / 16 = 0.0125`.

## Frontend application boundary

The Social frontend is an application surface, not a WordPress document surface.
Do not add a global `p` typography rule: application paragraphs can appear inside
many components whose typography belongs to that component.

The only candidate element-level defaults for the base layer are `body` and
`h1` through `h6`. Their role mapping remains a later explicit decision. The base
layer must not contain user-customizable typography presets.

### Deferred custom type-scale presets

`ax-typescale` is reserved for a future user-custom preset registry, analogous to
WordPress font-size presets but independent of WordPress block serialization. The
contract is compositional: one base recipe, one type-role modifier, and an optional
emphasis modifier. The generated rules belong in the future override layer:

```css
@layer axismundi.overrides {
  .ax-typescale {
    font-family: var(--ax-typescale-font);
    font-size: var(--ax-typescale-size);
    font-weight: var(--ax-typescale-weight);
    letter-spacing: var(--ax-typescale-tracking);
    line-height: var(--ax-typescale-line-height);
  }

  .ax-typescale.ax-typescale--body-large {
    --ax-typescale-font: var(--md-sys-typescale-body-large-font);
    --ax-typescale-size: var(--md-sys-typescale-body-large-size);
    --ax-typescale-weight: var(--md-sys-typescale-body-large-weight);
    --ax-typescale-tracking: var(--md-sys-typescale-body-large-tracking);
    --ax-typescale-line-height: var(--md-sys-typescale-body-large-line-height);
  }

  .ax-typescale.ax-typescale--body-large.ax-typescale--emphasized {
    --ax-typescale-font: var(--md-sys-typescale-emphasized-body-large-font);
    --ax-typescale-size: var(--md-sys-typescale-emphasized-body-large-size);
    --ax-typescale-weight: var(--md-sys-typescale-emphasized-body-large-weight);
    --ax-typescale-tracking: var(--md-sys-typescale-emphasized-body-large-tracking);
    --ax-typescale-line-height: var(--md-sys-typescale-emphasized-body-large-line-height);
  }
}
```

This is documentation only. No static `ax-typescale` selector, registry, dynamic
stylesheet, frontend API, or `axismundi.overrides` layer is implemented yet.
`has-*-font-size` remains the WordPress block serialization contract and is not the
Social frontend preset namespace.

The Social token file preserves the supplied 15 baseline and 15 emphasized roles.
It does not yet implement variable-font axis-value application or language-height
variants.

## References

- [Material 3 type-scale tokens](https://m3.material.io/styles/typography/type-scale-tokens)
- [Material 3 typography fonts](https://m3.material.io/styles/typography/fonts)
- `src/apps/frontend/styles/tokens/typography.css`
