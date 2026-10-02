# Frontend Fluid Typography: Deferred Decision

## Status

Deferred. The Social frontend preserves the current Material 3 baseline and
emphasized type-scale metrics. No `clamp()` values, typography size media queries,
or fluid type classes are implemented yet.

## Decision

Do not decide whether fluid typography belongs in `typography.css` system tokens
or in `base.css` element mappings until the first layout, Material component, and
authored-content surfaces have been implemented and reviewed together.

## Why the decision is premature

### System-token ownership has a wide blast radius

Putting `clamp()` in a `--md-sys-typescale-*-size` token changes every consumer of
that Material role. Type-scale roles are used by both article-like content and
application components. The current fixed values are the Material contract; making
them fluid is a product policy change, not a local presentation adjustment.

### Element mapping is not settled

The application base layer will eventually define defaults for `body` and `h1`
through `h6`, but the semantic-element-to-type-role mapping has not been decided.
The Social application must not introduce a global `p` rule: paragraph elements can
exist inside components whose typography is component-owned. Applying fluid values
to headings before this mapping is tested would make HTML semantics silently choose
the product's responsive typography policy.

### Component geometry must adapt as a unit

Fluid Label roles can make a control label smaller or larger while a button, input,
chip, menu item, or icon retains its fixed geometry. That can break visual balance,
target sizing, and density expectations. If an XL or other adaptive control needs
to change across available space, its label, icon, padding, and height must be
reviewed as one component-level policy. This is not a type-scale-only decision.

For example, this is an invalid shortcut for an XL button because only the label
changes while the button geometry remains fixed:

```css
.ax-button--xl {
  font-size: clamp(0.875rem, 0.35rem + 1vw, 1.125rem);
  min-block-size: 56px;
  padding-inline: 28px;
}
```

If an XL control later needs a smaller compact presentation, the component must
choose a coordinated density policy instead: label type role, icon size, inline
padding, and target height change together. This example is illustrative only;
it does not establish an XL button token or breakpoint policy.

```css
/* Future component policy example; not implemented. */
.ax-button--xl {
  --ax-button-height: 56px;
  --ax-button-padding-inline: 28px;
  --ax-button-icon-size: 24px;
  --ax-button-label-size: var(--md-sys-typescale-label-large-size);
}

@media (max-width: 599px) {
  .ax-button--xl {
    --ax-button-height: 48px;
    --ax-button-padding-inline: 20px;
    --ax-button-icon-size: 20px;
    --ax-button-label-size: var(--md-sys-typescale-label-large-size);
  }
}
```

Label roles are therefore presumed fixed unless a component specification proves
that a coordinated geometry change is needed. Display, headline, title, body, and
label roles must be evaluated independently; no type-scale category becomes fluid
by default.

### Viewport and container adaptation are separate questions

The layout foundation currently defines window-size classes. Component-local
adaptation may later use container queries. A viewport-based fluid token could be
wrong for a narrow card or supporting pane inside a wide window; a container-based
rule has different ownership and verification needs. Neither model is selected.

### Authored content has a separate contract

WordPress document content and frontend application UI share some font assets but
do not share typography ownership. Gutenberg/theme.json presets, future Social
`ax-typescale` user presets, application component roles, and document headings
must not be merged merely because they use similar names.

## Current contract

- `styles/tokens/typography.css` holds the fixed Material baseline and emphasized
  type-scale tokens.
- `styles/base.css` consumes the body-large tokens for the application document
  body. It does not define a global paragraph rule.
- No type-scale size token contains `clamp()`.
- No heading selector contains `clamp()` or responsive type-size media queries.
- `ax-typescale` remains a documented, unimplemented future user-preset contract.

## Revisit after

1. The adaptive layout foundation has been VQA'd across its breakpoint matrix.
2. At least the first Material controls and their fixed geometry are implemented.
3. A Social authored-content surface and its relationship to WordPress block
   content are explicit.
4. Heading role mappings in the application base layer are tested.
5. A decision has been made about viewport adaptation versus container adaptation
   for component-local typography.

At that point, evaluate these options without assuming one is the default:

- keep the M3 system type scale fixed and apply fluid policy only in selected base
  element mappings;
- introduce separate Axismundi fluid aliases while leaving `--md-sys-*` fixed;
- make a limited, explicitly named subset of type roles fluid; or
- apply fluid behavior only to selected document/content surfaces.

Any accepted option must include component geometry VQA and the layout breakpoint
matrix. It must not make Label roles fluid by implication.
