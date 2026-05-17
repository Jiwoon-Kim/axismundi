# Axismundi Pilot — Block Theme Scaffold v0.1

> **NOT a release.** v2.1a-P0.5 validation target. Architectural decision: `tokens.css` is the design Single Source of Truth.

## Architectural decision

**`tokens.css` is the SoT.** `theme.json` is a minimal WordPress integration layer (slug registry + lock-down policy) that points to M3 tokens via `var()`. This preserves:

- M3 ref→sys 2-tier token architecture
- Modern CSS in `block-styles.css` — `color-mix()`, `light-dark()`, `oklch()`, `color-contrast()` all usable
- Dark mode via `[data-theme="dark"]` selector in `tokens.css` (no style variation hack)
- `--md-sys-*` token chain throughout the runtime

### Trade-off: WordPress inspector GUI shows defaults

- Color picker swatch: `#000` (WP can't resolve `var()` to hex for swatch)
- Typography inputs: empty (same reason)
- This is **intentional**, not a bug.

### Roadmap — replacing inspector GUI

A future Axismundi plugin will provide:
- **HCT-based color picker** (Hue/Chroma/Tone) instead of hex picker — M3's native color space
- **Dynamic palette generator** from a seed color (M3 Material You pattern)
- **Theme switcher panel** with light/dark/scheme variants
- **Typography role inspector** that shows the M3 role hierarchy (display/headline/title/body/label × small/medium/large)

The pilot intentionally does not paper over WP inspector limits with `theme.json` hex literals because that would compromise the token system. Hex would have to be regenerated whenever the palette changes — defeating the var() chain that makes dynamic theming possible.

## Binding map → code traceability

### Token bindings — operationalized via var() chain

| Binding | Confidence | Implementation |
|---|---|---|
| `wp:ThemeToken.color ↔ m3:Family.sys.color` | 0.95 | `theme.json:settings.color.palette` (36 slugs, value = `var(--md-sys-color-{slug})`) → resolves to `tokens.css §2` (36 sys-color tokens, dual light/dark) |
| `wp:AppearanceTool ↔ M3 design-tool meta-flag` | 0.90 | `theme.json:settings.appearanceTools=true` |
| `wp:ThemeToken.typography ↔ m3:Family.sys.typescale` | 0.85 | `theme.json:settings.typography.fontSizes` (15 slugs, size = `var(--md-sys-typescale-{slug}-size)`) → `tokens.css §3` |
| `wp:ThemeToken.shadow ↔ m3:Family.sys.elevation` | 0.70 | `theme.json:settings.shadow.presets` (6 levels, shadow = `var(--md-sys-elevation-shadow-level{N})`) → `tokens.css §4` |
| `wp:ThemeToken.spacing` (M3 baseline gap) | 0.70 | `theme.json:settings.spacing.spacingScale` (7-step, WP authoritative) |
| `wp:ThemeToken.border ↔ m3:Family.sys.shape` | 0.60 | Used in `block-styles.css` via `var(--md-sys-shape-corner-*)` |

### Tier 2 component bindings (12 implemented — Direct.CoreBlockStyle)

| M3 component | Block + style | Implemented in |
|---|---|---|
| Button — Filled | `core/button` + `is-style-filled` | `functions.php` + `block-styles.css §1.1` |
| Button — Tonal | `core/button` + `is-style-tonal` | `functions.php` + `block-styles.css §1.2` |
| Button — Elevated | `core/button` + `is-style-elevated` | `functions.php` + `block-styles.css §1.3` |
| Button — Outlined | `core/button` + `is-style-outlined` | `functions.php` + `block-styles.css §1.4` |
| Button — Text | `core/button` + `is-style-text` | `functions.php` + `block-styles.css §1.5` |
| Card — Filled | `core/group` + `is-style-card-filled` | `functions.php` + `block-styles.css §2.1` |
| Card — Elevated | `core/group` + `is-style-card-elevated` | `functions.php` + `block-styles.css §2.2` |
| Card — Outlined | `core/group` + `is-style-card-outlined` | `functions.php` + `block-styles.css §2.3` |
| List — Segmented | `core/list` + `is-style-list-segmented` | `functions.php` + `block-styles.css §3` |
| Divider — Inset | `core/separator` + `is-style-divider-inset` | `functions.php` + `block-styles.css §4` |
| Divider — Middle-inset | `core/separator` + `is-style-divider-middle-inset` | `functions.php` + `block-styles.css §4` |
| Search — Filled | `core/search` + `is-style-filled-search` | `functions.php` + `block-styles.css §5` |

### Composite bindings (1 implemented — Composite.TemplatePart)

| M3 component | Template part | Implemented in |
|---|---|---|
| App Bar | `parts/header.html` | Uses `core/site-title` + `core/navigation` + `core/search` inside `is-style-card-filled` group |

## File structure

```
axismundi-theme-pilot-v0.1/
├── style.css              ← Theme header (WordPress required)
├── theme.json             ← M3 token ingestion (auto-generated from ontology)
├── functions.php          ← Block style registrations
├── assets/css/
│   ├── tokens.css         ← M3 design tokens (265 entries)
│   ├── base.css           ← Semantic runtime policy (14 sections)
│   └── block-styles.css   ← Per-style M3 spec CSS
├── templates/
│   ├── index.html         ← Index template
│   └── single.html        ← Single post template
└── parts/
    ├── header.html        ← App Bar composite
    └── footer.html        ← Minimal footer
```

## Validation

Run the repository validator from the repo root to verify that this pilot still
passes the binding legitimacy audit:

```bash
python tools/validators/validate_theme_pilot.py
```

- Axis A — Schema: theme.json slugs ↔ M3 ontology roles
- Axis B — Theme: appearanceTools + lock-down flags
- Axis C — CSS: asset presence + token usage
- Axis D — Runtime: block style registrations ↔ binding rules

Current verdict: **PASS (1.000 / 1.000)**

## What's NOT implemented (and why)

### Inspector GUI parity — deferred to plugin layer
- WP color picker swatch shows `#000` (var() can't be reverse-resolved to hex)
- WP typography inspector inputs show empty/default (same reason)
- **This is intentional.** The future Axismundi color/HCT panel plugin replaces these.

### Component scope
- M3-COMPONENT-SPECS Tier 1+ components beyond the 5 above
- Bucket B (Block Pattern) compositions
- Bucket D (FSE template-part) beyond header
- Bucket C (custom plugin blocks) — `m3-blocks` plugin reserved for v2.1a-P2

### Misc
- Dark mode toggle UI (CSS variables present, no UI affordance — test via `document.documentElement.dataset.theme = 'dark'` in DevTools)
- editor-styles parity beyond `add_editor_style`
- ActivityPub integration (v2.2)

## License

Same as the Axismundi project (TBD).
