# Material 3 Elevation Reference Notes

## Status

Derived implementation and VQA index. 2026-10-03.

This is not a CSS shadow recipe. The unedited reading record is
`SOURCE-M3-ELEVATION-RAW.md`; local ownership remains
`DECISION-FRONTEND-ELEVATION-OWNERSHIP.md`.

## Semantic contract

Elevation is a closed semantic scale, not an alias for `box-shadow`, a tonal surface
role, `transform`, or `z-index`.

| Level | Material distance | Intended use |
| --- | ---: | --- |
| `0` | `0dp` | Default, co-planar surfaces and components |
| `1` | `1dp` | Low resting separation |
| `2` | `3dp` | Raised navigation and transient controls |
| `3` | `6dp` | Foreground resting surfaces such as dialogs and FABs |
| `4` | `8dp` | Transient interaction only |
| `5` | `12dp` | Transient interaction only |

`0..3` are valid resting levels. `4..5` must not become arbitrary default surface
levels; they reserve contrast for hover, focus, drag, or a similar transient state.

## Web rendering rules

- A Social primitive will accept a semantic level and choose its visual depiction.
- Tonal separation is the default Material signal. A shadow is conditional visual
  protection, not the token's meaning.
- A scrim is a separate backdrop treatment, not elevation level `n`; use the scrim
  colour role at `32%` opacity when an overlay needs it.
- Elevation never assigns `z-index`. Stacking policy is a distinct future contract.
- Components retain their M3 resting level. A state transition can move one level
  upward only where that component's interaction contract calls for it.

## Social implementation boundary

The theme's `--md-sys-elevation-shadow-level*` properties remain the WordPress and
block-theme bridge. Social must not consume those properties as its elevation API.
When `Surface` or a comparable frontend primitive is introduced, its public input is
the level `0..5`; its rendering tokens, shadow treatment, and transitions belong to
the frontend bundle.

This keeps three concerns separate:

```text
semantic elevation      0..5 relationship between surfaces
visual depiction        tonal role, shadow, outline, or scrim
stacking                 explicit z-index policy
```

## First-component lookup

Use this index during initial component work rather than inventing a resting level:

| Component family | Resting level | State note |
| --- | ---: | --- |
| Filled, tonal, outlined button | `0` | Do not promote by default |
| Elevated button, card, chip | `1` | May use visible shadow for protection |
| Navigation bar, menu, scrolled app bar, rich tooltip, toolbar | `2` | Contextual raised layer |
| Modal dialog, FAB, extended FAB, search | `3` | Foreground resting layer |
| Hovered/dragged foreground element | `4` or `5` | Only if interaction needs the extra separation |

## Required VQA once implemented

1. Verify the same semantic level produces a consistent treatment across components.
2. Verify levels `4` and `5` appear only during their declared interaction state.
3. Verify tonal differences and shadows retain a perceivable edge against the active
   scheme's surrounding surface.
4. Verify a scrim uses the active scrim role and `32%` opacity.
5. Verify elevation changes do not alter an element's stacking order unless a separate
   stacking rule explicitly does so.

