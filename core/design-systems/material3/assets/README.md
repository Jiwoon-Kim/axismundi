# Material 3 Design System — Assets

Shared assets used by all consumers (prototype, lab, future distributable themes).

## Contents

```
assets/
├── fonts/                          ← OFL 1.1
│   ├── roboto-flex/                ← non-CJK base (full coverage)
│   ├── roboto-serif/
│   ├── roboto-mono/
│   ├── noto-sans-kr/               ← Korean fallback (unicode-range)
│   └── noto-serif-kr/
└── icons/                          ← Apache 2.0
    ├── material-symbols-outlined/  ← Stored design-system asset
    ├── material-symbols-rounded/   ← Current theme chrome runtime style
    └── material-symbols-sharp/     ← Stored design-system asset
```

Material Symbols Outlined, Rounded, and Sharp are stored here as design-system
asset authority files.

Current lab / Pilot / styleguide runtime CSS registers **Rounded only**. Outlined
and Sharp remain plugin / future variation territory until a future cycle
explicitly enables them in runtime CSS.

## Consumer paths

| Consumer | How it references these assets |
|---|---|
| `products/reference-implementations/axismundi-lab/` (active) | `../../../core/design-systems/material3/assets/...` |
| `products/_archive/axismundi-prototype/` (legacy) | `../../../core/design-systems/material3/assets/...` (or its archived copy) |
| `products/distributables/themes/axismundi/` (future) | Theme bundles a copy at build time |
| Root `/styleguide/` (publish mirror of lab) | `../core/design-systems/material3/assets/...` |

## License compliance

Per-folder `OFL.txt` (fonts) and `LICENSE.txt` (icons) preserved.
Per-folder `source.txt` documents provenance.

Aggregate attribution in `NOTICE.md` at repo root.

## Rationale for relocation (v3.3.0)

Previously these assets lived in `axismundi-prototype/assets/`. The
prototype was social-CMS-frame contaminated and was demoted to legacy
status in v3.3.0. Assets are not "prototype-owned"; they are Material 3
design system runtime resources shared across consumers. Hence the move
into the design system layer.
