# Axismundi Brand Assets

`axismundi-symbol.svg` is the canonical Axismundi project identity source
asset.

The source SVGs in this folder are complete. Deployment-derivative artifacts
remain unlocked until the Pilot vs distributable theme context is stable:

```txt
favicon
512 / 1024 PNG exports
screenshot.png
README hero
plugin icon
WordPress.org assets
```

## Files

| File | Role |
|---|---|
| `axismundi-symbol.svg` | Canonical project identity source asset |
| `axismundi-symbol-static.svg` | Static symbol variant with a square background, for plugin-icon derivatives |
| `icon.svg` | Icon derivative of the static variant: 46-dot halo sized for a 128/256 render, accessible name, prefixed ids. **The only file in this folder under GPL-3.0-or-later** |
| `axismundi-symbol-glow.svg` | Glow / presentation variant |
| `axismundi-logo.png` | Raster logo export — the demo **Site Logo** source (core blocks SVG uploads for the site-logo slot, so the slot needs a raster). Lives here in the repo brand source, NOT bundled in the distributable theme. |

## License

Brand assets in this folder are original project assets by Jiwoon Kim.

Two derivatives carry their own narrow grant, and neither widens to the rest of
this folder.

`products/wordpress/plugins/axismundi-emoji/emoji/axismundi.webp` is a 200x200
custom-emoji derivative of `axismundi-logo.png`. Its copyright holder releases that
specific bundled derivative under GPL-3.0-or-later; its adjacent `LICENSE.txt` records
the narrow grant.

`icon.svg` is released under GPL-3.0-or-later on the same terms, so it can be
distributed with a plugin and through the WordPress Plugin Directory, whose first
guideline requires GPL-compatible assets. Its `dc:rights` carries the grant in the file.

Neither grant touches the marks. A copyright licence is not a trademark licence: it
lets someone copy and modify the file, not call their own product Axismundi. That is
the same split the WordPress Foundation keeps between GPL software and the W Mark, and
the reason `axismundi-emoji/emoji/LICENSE.txt` treats the bundled `:wordpress:` emoji
under a trademark policy rather than a copyright grant.

See `../LICENSES.md` for the repository asset license table.
