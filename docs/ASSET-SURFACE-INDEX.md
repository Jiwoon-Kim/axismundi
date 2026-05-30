# Asset Surface Index

> Cross-cutting project document. This is not a v3.6.19 cycle artifact.
> It survives the cycle and may be referenced by future asset, theme bootstrap,
> catalog, and distributable packaging work.

## Purpose

Axismundi intentionally keeps different asset classes in different paths because
the path itself carries policy: source identity, third-party research,
design-system runtime authority, product-local copy, generated mirror, or legacy
reference surface.

This document is an index, not a consolidation plan.

## Surface Map

| Surface | Authority | Ownership / license class | Ships where | Consumer / reference pattern | Policy notes | Canonical local README | Known drift / follow-on |
|---|---|---|---|---|---|---|---|
| `assets/brand/` | Project identity source assets | Original Axismundi identity assets by Jiwoon Kim | Future theme / plugin / project identity surfaces | Referenced by future brand, favicon, plugin icon, screenshot, README, and WordPress.org asset pipelines | Source SVGs are complete; deployment derivatives are not release-locked | `assets/brand/README.md` | Release-seal derivatives pending Pilot vs distributable context |
| `assets/media/` | Placeholder media slots | Mixed per-file provenance: CC0 image, project-author audio, Pixabay video | Future block catalog / template / specimen work | Referenced only after each file's provenance and placement are accepted | Not one license bucket; video is not project-owned | `assets/media/README.md`, `assets/LICENSES.md` | Third-party video isolation remains a follow-on decision |
| `compare/brand-assets-research/` | Third-party brand / trademark research | Third-party brand/trademark reference assets | Does not ship | May seed lab-only reference specimens with fresh source verification | DO-NOT-SHIP; never theme core, plugin core, or publish mirror content | `compare/brand-assets-research/README.md` | Keep isolated; add manifest rows only when research changes |
| `core/design-systems/material3/assets/` | Material 3 design-system runtime asset authority | Upstream fonts/icons: OFL 1.1 and Apache 2.0 | Referenced by lab and publish mirror; copied into product bundles when needed | Lab/styleguide reference core assets; future distributables copy at build/package time | Stores all three Material Symbols style sets; current theme runtime registers Rounded only | `core/design-systems/material3/assets/README.md` | Outlined / Sharp runtime enablement remains plugin / future variation territory |
| `products/reference-implementations/axismundi-pilot/assets/` | Pilot product runtime copy | Product-local copies of M3 fonts/icons plus Pilot CSS/JS | Ships inside the Pilot theme probe | `theme.json`, `functions.php`, and Pilot CSS reference local `assets/...` paths | Font/icon WOFF2 payloads are byte-identical to core design-system assets at v3.6.19 | `products/reference-implementations/axismundi-pilot/assets/fonts/README.md`, `products/reference-implementations/axismundi-pilot/assets/icons/README.md` | Future distributable build-copy policy should decide how copies are produced |
| `products/reference-implementations/ontology-theme-pilot/assets/` | Legacy E-layer reference surface | Legacy CSS assets | Legacy reference pilot only | No active v3.6.19 consumer decisions | Frozen unless a future cycle explicitly reopens this Pilot | none observed | Legacy status should be preserved; no active modernization route |
| `styleguide/` | Generated publish mirror | Generated HTML/CSS mirror, no binary font/icon copies | GitHub Pages publish surface | `styleguide/stylesheets/fonts.css` references `../core/design-systems/material3/assets/...` | Article 12 mirror; do not edit directly as authority | none; source is `products/reference-implementations/axismundi-lab/` plus publish tooling | Regenerate only through publish tooling in a dedicated cycle |

## Material Symbols Runtime Policy

Three different facts must stay separate:

```txt
Stored binaries:
  core/design-systems/material3/assets/icons/ stores Outlined, Rounded, and
  Sharp Material Symbols WOFF2 files with license/source records.

Registered current runtime:
  Current lab / Pilot / styleguide runtime CSS registers Material Symbols
  Rounded only.

Outlined / Sharp route:
  Outlined and Sharp remain plugin / future variation territory until a future
  cycle explicitly registers them.
```

This index does not authorize registering Outlined or Sharp in runtime CSS.

## Brand Source vs Release Seal

`assets/brand/*.svg` are complete project identity source assets.

Deployment-derivative artifacts remain unlocked:

```txt
favicon
512 / 1024 PNG exports
screenshot.png
README hero
plugin icon
WordPress.org assets
```

Those derivatives should be locked after the relevant Pilot vs distributable
theme context exists.

## Placeholder Media Policy

Root media placeholders have per-file provenance:

```txt
image-placeholder-mogu-1024.webp       CC0 WordPress Photo Directory image by Jiwoon Kim
audio-placeholder-jazzy-lofi.ogg       Opus-in-Ogg derivative with album art preserved
video-placeholder-gwangan-720p.webm    Pixabay Content License, not project-owned
```

Do not infer one license or ownership model for all files under
`assets/media/`.

## Future Update Rule

When a future cycle changes an asset surface, update only the affected row and
its local README / license record. Do not collapse the surfaces into one
directory unless a future architecture cycle explicitly reopens the path policy.
