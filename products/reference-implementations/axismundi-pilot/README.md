# Axismundi Pilot

`axismundi-pilot` is the v3.6.0 WordPress block theme Pilot for Axismundi.

It validates the Axismundi Material 3 public surface in a real WordPress theme
without absorbing plugin/runtime work into the theme.

## Scope

Consumes:

- Wave 1 minus Carousel
- `popover/`, `ripple/`, and `icon-system/` infrastructure
- `tokens.css`, `components.css`, `blocks.css`, and `prose.css`
- `blocks.html` and `prose.html` as Pilot specification surfaces

Does not implement:

- custom blocks
- Carousel plugin/block extraction
- M3 Interpreter Plugin / HCT color panel
- v4.0 distributable theme graduation

## Color Policy

The theme runs in Theme-only mode:

- `tokens.css` is the source of truth.
- `theme.json` is the Gutenberg preset slug registry.
- palette values point to `var(--md-sys-color-*)`.
- `settings.color.custom` is `false`.

Full M3 color customization belongs to the future Interpreter Plugin.

## wp-env

From the repository root:

```powershell
python .\tools\generators\build_pilot_assets.py
wp-env start
wp-env run cli wp theme activate axismundi-pilot
```

Docker Desktop must be running before `wp-env start`.
