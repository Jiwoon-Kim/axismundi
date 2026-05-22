# FAB Menu Measurement Audit

Component: FAB menu #5

Cycle: v3.6.13

## Baseline Reuse

The module consumes the existing `components.css` Chunk H1 measurements:

- close button: 56px by 56px
- close icon: 20px
- action row height: 56px
- action icon: 24px
- close-to-first-item gap: 8px
- action item gap: 4px
- action item inline padding: 24px
- action icon/label gap: 8px

## Lab CSS Boundary

`lab-fab-menu.css` only sets validation-page layout and scoped glyph sizing:

```txt
.lab-fab-menu-demo ...
.lab-fab-menu-demo .ax-fab-menu__close .material-symbols-rounded
.lab-fab-menu-demo .ax-fab-menu__item-icon .material-symbols-rounded
```

It does not redefine unscoped `.ax-fab-menu` selectors.

## Ripple Geometry

- close FAB: unbounded ripple
- action rows: bounded ripple
- disabled action rows: no animated ripple
