# Axismundi Lab

> Active visual authority for Axismundi (as of v3.3.0). Source of the published `/styleguide/` mirror. Surface for visual QA, interaction experiments, and design decisions.

This directory was previously `axismundi-benchmark/`. Renamed in v3.2.2 to reflect its actual role: it is a lab, not a benchmark target.

## What lives here

Components, patterns, and interaction experiments that need:

- **Visual QA** before they can be considered finished
- **Side-by-side comparison** with alternative implementations
- **Iteration** on design decisions that aren't yet locked
- **Accessibility / keyboard / locale testing** before public release
- **Exploration of variable font axes** or other design system frontiers

Lab assets are **not validated for production use** even if they appear to work. Anything stable enough to be relied upon should be promoted to `axismundi-prototype/`.

## Relationship to other reference implementations

| | axismundi-prototype (`_archive/`) | axismundi-lab | ontology-theme-pilot |
|---|---|---|---|
| **Role** | (DEMOTED in v3.3.0 — archived) | **Active visual authority + visual QA + promotion candidates** | Ontology validation target |
| **Stability** | Frozen at archive snapshot | Active; both stable styleguide AND volatile experiments | Frozen until ontology updates |
| **Promotes to** | (none — archive) | `axismundi-pilot/` (v3.3.1+) then `distributables/themes/axismundi/` | Not applicable |
| **Origin** | User-authored | Beer CSS-inspired + M3 Material You experiments | Ontology emitter output |

## Promotion criteria (lab → prototype)

A lab asset can promote to prototype only when **all five** are met:

1. **Works without JS** (or has a graceful fallback if JS fails)
2. **Hover / focus / pressed states match M3 state layer spec** (color-mix(in srgb, ..., ... NN%))
3. **`prefers-reduced-motion: reduce` honored** (animations disabled or simplified)
4. **Keyboard-operable** (Tab focus, Enter/Space activation, Escape dismissal for modals)
5. **Does not leak into `.prose` / `post_content` / federated surfaces** (per `atlas/material/icon-font-scope-policy.md` and Constitution Article 2)

If any one fails, the asset stays in lab with a documented blocker.

## Current lab contents (v3.3.0)

```
axismundi-lab/
├── README.md                                  ← (this file)
├── docs/
│   └── INTERACTION-AUDIT.md                   ← v3.2.2 audit decisions
├── stylesheets/                               ← 8 css files (self-contained)
│   ├── tokens.css, base.css, components.css,
│   │   blocks.css, prose.css                  ← canonical stylesheets
│   ├── fonts.css                              ← @font-face → core assets
│   ├── icons.css                              ← Material Symbols base
│   └── benchmark-interactions.css             ← held experiments
├── scripts/
│   ├── theme.js, style-guide.js               ← canonical
│   └── benchmark-interactions.js              ← held experiments
├── style-guide.html                           ← canonical (mirrored to /styleguide/index.html)
├── style-guide-blocks.html                    ← canonical (mirrored to /styleguide/blocks.html)
├── style-guide-prose.html                     ← canonical (mirrored to /styleguide/prose.html)
├── style-guide-benchmark.html                 ← experimental
└── typography-axis.html                       ← variable font axis explorer
```

Asset references via `core/design-systems/material3/assets/` (relative depth: 4 levels up from `stylesheets/fonts.css`).

Future planned additions (ongoing visual QA work):

- `forms-date-time.html` — date/time picker iteration (currently unfinished design)
- `icon-font-swap.html` — visual QA for inline SVG → Material Symbols swap
- `carousel.html` — carousel experiment before promotion to pilot theme

## License

Inherits root license intent (GPL-3.0-or-later). External code references and inspirations are documented in `NOTICE.md` at repo root.
