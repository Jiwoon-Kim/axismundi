# Axismundi

Axismundi is an ontology-driven Material 3 design system with a WordPress
binding and pilot theme path.

It is currently a public-prep repository: Wave 1 component coverage is complete,
the validation pipeline passes, and GitHub repository / Pages publication is the
next release step.

- Author and decision owner: KIM JIWOON (designbusan.ai.kr) — Busan, Korea. See [AUTHORSHIP.md](AUTHORSHIP.md).
- Architecture: see [CONSTITUTION.md](CONSTITUTION.md).
- Current route: see [CURRENT-STATE.md](CURRENT-STATE.md) and [ROADMAP.md](ROADMAP.md).
- Korean companion: [README.ko.md](README.ko.md).

## Current Status

```txt
v3.5.12  Wave 1 components closed        9 / 9
v3.5.13  Wave 1 cleanup closed           #32 / #33 / records
v3.5.14  Publish prep                    in progress
v3.5.15  GitHub repo + GitHub Pages      next
v3.6.0   Ontology Theme Pilot            planned
```

Wave 1 covered Button, Icon button, FAB family, Button group, Card, Text field,
Search bar, List, and Carousel. Follow-up foundation corrections for matrix
state, ripple, pill radius, size variants, and list tokens are also closed.

## What This Repository Is

Axismundi is not only a WordPress theme. It is a layered system for translating
platform ontologies, design-system tokens, component contracts, and publishable
reference implementations into a coherent product path.

The current design-system layer is Material Design 3. The current platform
binding is WordPress. The architecture is intended to allow other design systems
or platform bindings later without rewriting the whole project.

## Architecture

Axismundi uses six repository layers:

| Layer | Directory | Role |
|---|---|---|
| A. Corpus | `corpus/` | Raw and refined source documents |
| B. Atlas | `atlas/` | Rule-based knowledge and audits |
| C. Core | `core/` | Formal platform and design-system ontologies |
| D. Bindings | `bindings/` | Cross-ontology translation |
| E. Products | `products/` | Reference implementations and future distributables |
| F. Tools | `tools/` | Builders, generators, and validators |

The public-surface model has four tiers:

| Tier | Meaning |
|---|---|
| Baseline | Stable visual primitives: `components.css`, `tokens.css`, styleguide source |
| Lab | Module validation surface: audits, pattern pages, runtime experiments |
| Public | Stable surfaces that downstream consumers may rely on |
| Plugin | Federation, editor UI, custom blocks, external data, and integration behavior |

Publishing surfaces are mirrors, not authorities. Edit the source, then run the
generator.

## Public Surfaces

| Surface | Status | Source authority |
|---|---|---|
| `index.html` | Project landing | README / project docs |
| `styleguide/` | Generated component styleguide mirror | `products/reference-implementations/axismundi-lab/style-guide*.html` |
| `templates/` | Planned page-layout / template preview route | Future `products/reference-implementations/axismundi-lab/templates/` |

`styleguide/` is generated output. Do not edit it directly.

## Quick Start

Install local development dependencies:

```powershell
npm install
```

Run the validator:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

Regenerate the styleguide publish mirror:

```powershell
python .\tools\generators\publish_styleguide.py
```

Expected validator result:

```txt
Overall: 1.000 (PASS)
A schema:  1.000
B theme:   1.000
C css:     1.000
D runtime: 1.000
```

## Repository Map

```txt
axismundi/
├── index.html
├── styleguide/                  generated publish mirror
├── CONSTITUTION.md
├── LICENSE-MATRIX.md
├── NOTICE.md
├── README.md
├── README.ko.md
├── corpus/
├── atlas/
├── core/
│   ├── wordpress/
│   └── design-systems/material3/
├── bindings/
│   └── wordpress-material3/
├── products/
│   └── reference-implementations/
│       ├── axismundi-lab/
│       └── ontology-theme-pilot/
└── tools/
    ├── generators/
    └── validators/
```

## WordPress Binding And Pilot

The WordPress binding lives in `bindings/wordpress-material3/`. The current
validation target is `products/reference-implementations/ontology-theme-pilot/`.

Theme territory:

- style core blocks,
- register block style variations,
- provide template parts and layout slots,
- enqueue progressive interaction CSS/JS.

Plugin territory:

- durable custom blocks,
- editor UI,
- icon picker registry,
- ActivityPub or external protocol integration,
- content parsing and data persistence.

## Stability Notes

- Wave 1 public-surface component audits are complete.
- Lab module pattern HTML files are validation surfaces, not public API by
  default.
- `styleguide/` is derived and can be regenerated.
- GitHub repository creation and GitHub Pages activation are planned for
  v3.5.15.
- The WordPress.org submission package is not yet assembled.

## Sponsor

Axismundi is an open-source WordPress project focused on Material Design 3,
Korean and CJK typography, reusable block-theme architecture, and ActivityPub-
ready publishing experiences.

Sponsorship helps fund ongoing development, testing, documentation,
localization, WordPress.org releases, and open social web research.

[Sponsor Axismundi development through GitHub Sponsors](https://github.com/sponsors/Jiwoon-Kim).

## License

Axismundi is multi-licensed by surface:

- code, theme, and tooling: GPL-3.0-or-later,
- documentation: CC BY-SA 4.0,
- ontology and binding data: CC BY-SA 4.0,
- third-party assets: original upstream licenses preserved.

See [LICENSE](LICENSE), [LICENSE-CC-BY-SA-4.0.md](LICENSE-CC-BY-SA-4.0.md),
[LICENSE-MATRIX.md](LICENSE-MATRIX.md), and [NOTICE.md](NOTICE.md).
