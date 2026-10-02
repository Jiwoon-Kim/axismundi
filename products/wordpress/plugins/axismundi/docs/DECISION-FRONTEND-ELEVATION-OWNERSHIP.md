# Decision: Frontend elevation ownership

## Status

Accepted. Initial frontend primitive implemented; asset-loader separation deferred.

## Current theme bridge

The block theme keeps
`products/wordpress/themes/axismundi/assets/styles/tokens/tokens.sys.elevation.css`
as its web translation of Material elevation. It defines scheme-neutral shadow and
scrim colors plus `--md-sys-elevation-shadow-level0` through
`--md-sys-elevation-shadow-level5`. In dark schemes it suppresses physical shadows.

`theme.json` exposes those rendering tokens to WordPress through six shadow presets:
`elevation-0` through `elevation-5`. This is a WordPress bridge: the editor and
blocks require `box-shadow` values, while Material elevation is conceptually the
relative z-axis distance between surfaces.

The theme intentionally does not define `--md-sys-elevation-level*` distance tokens.
Those dp values do not map unambiguously to CSS shadow, transform, or stacking order.

## Frontend decision

The `/social/` frontend must not adopt theme `tokens.sys.elevation.css` as its
elevation API. When the frontend implements elevation, it will own a distinct
Material primitive whose public contract is a semantic level in the closed range
`0..5`, not a WordPress shadow preset and not a CSS `z-index` value.

Material Web provides the relevant precedent: `<md-elevation>` renders the visual
layer for the nearest `position: relative` surface, configured by
`--md-elevation-level` from `0` to `5`. Its shadow color is a separate token.

The future frontend primitive may use React composition such as:

```jsx
<Surface elevation={ 3 }>{ children }</Surface>
```

Its implementation may render shadows, tonal treatment, and transitions differently
from the WordPress bridge. It must not silently depend on
`--md-sys-elevation-shadow-level*` from the theme.

## Consequences

- Theme and block-editor elevation remain unchanged.
- The Social frontend will eventually exclude `tokens.sys.elevation.css` from its
  foundation asset set.
- The frontend primitive owns its rendering tokens and transition behavior when it
  is introduced.
- Elevation and stacking stay separate: semantic elevation never implies a
  `z-index` scale.
- `components/material/elevation.js` and `styles/material/elevation.css` implement
  the initial Social visual primitive. It follows Material Web's two-shadow geometry,
  accepts only semantic levels `0..5`, and never suppresses shadows for dark schemes.
- The current theme foundation-only branch still enqueues
  `tokens.sys.elevation.css`. Social does not consume its
  `--md-sys-elevation-shadow-level*` properties; separating that asset and relocating
  its shadow/scrim colour tokens remain a later theme-contract change.

## References

- [Material Web elevation documentation](https://github.com/material-components/material-web/blob/main/docs/components/elevation.md)
- `products/wordpress/themes/axismundi/assets/styles/tokens/tokens.sys.elevation.css`
- `products/wordpress/themes/axismundi/theme.json`
- `products/wordpress/plugins/axismundi/docs/DECISION-FRONTEND-THEME-ASSET-CONTRACT.md`
