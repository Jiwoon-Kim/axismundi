# WordPress ↔ M3 Binding Map (v2.1a v0.1)

**Philosophy**: Translation layer, not equivalence. WordPress is CMS+block+token authority;
Material is design-system+UI-semantics authority. Binding is **typed translation**.

## Tier 1 — Token bindings (6)

| WP anchor | M3 anchor | confidence | P4 agreement | binding_pattern |
|---|---|---|---|---|
| `wp:ThemeToken.color` | `m3:Family.sys.color` | 0.95 | 5/5 | role_to_slug |
| `wp:ThemeToken.typography` | `m3:Family.sys.typescale` | 0.85 | 5/5 | role_to_slug_plus_utility_class |
| `wp:ThemeToken.spacing` | `—` | 0.7 | 5/5 | wp_authoritative_with_axismundi_recommendation |
| `wp:ThemeToken.shadow` | `m3:Family.sys.elevation` | 0.7 | 4/5 | level_to_preset |
| `wp:ThemeToken.border` | `m3:Family.sys.shape` | 0.6 | 4/5 | radius_token_subset |
| `wp:AppearanceTool` | `(meta-flag)` | 0.9 | 5/5 | meta_flag_to_capability_bundle |


## Strong bindings (3 of 6)

These are the immediate ROI for Axismundi block theme + token system:

### wp:ThemeToken.color ↔ m3:Family.sys.color

**Confidence**: 0.95 | **P4 agreement**: 5/5

WP theme.json settings.color.palette[].slug ↔ M3 sys-color role. Mapping is NOT 1:1 because M3 has fixed 38 sys roles (primary/secondary/tertiary/error × on-/-container/on--container + surface/outline/inverse/scrim/shadow), while WP palette is open string-keyed. Binding policy: theme.json declares a palette entry per M3 role using M3 slugs.

**Axismundi implementation**: tokens.css §2 sys-color (36 tokens, dual light/dark)

### wp:ThemeToken.typography ↔ m3:Family.sys.typescale

**Confidence**: 0.85 | **P4 agreement**: 5/5

WP theme.json settings.typography.fontSizes ↔ M3 sys-typescale roles (display-large/medium/small, headline-*, title-*, body-*, label-*). Each role decomposes into 5 CSS properties (font/size/weight/lineHeight/tracking). Material is more granular than WP fontSize (only size). Binding policy: each WP fontSize slug maps to one M3 role, and base.css §6.5 .t-{role} utility classes provide the missing properties.

**Axismundi implementation**: tokens.css §3 sys-typescale (75 tokens) + base.css §6.5 .t-{role} utilities

### wp:AppearanceTool ↔ (meta-flag)

**Confidence**: 0.9 | **P4 agreement**: 5/5

appearanceTools=true bulk-enables 12+ BlockSupport sub-properties. In Material design system terms, this is equivalent to enabling design-tool exposure for: border (color/radius/style/width), color.link, dimensions (aspectRatio/minHeight), position.sticky, spacing (blockGap/margin/padding), typography.lineHeight. M3 equivalent: 'Component design-tool capability flag' (M3 doesn't have a single meta-flag, but the bundle matches the M3 component-level design freedoms typically exposed in Component Inspector tools).

## Tier 2 — Component bindings

From `block_component_binding_rules.json`: 32 in-scope bindings.

| binding_type | count | confidence |
|---|---|---|
| Direct.CoreBlockStyle | 14 | 0.9 |
| Direct.CustomBlock | 11 | 0.85 |
| Composite.TemplatePart | 7 | 0.75 |

## Out-of-scope / Runtime-only

- **OutOfScope.Handoff** (form plugins, dropped components): 9 rules
- **RuntimeOnly.ThemeJS** (snackbar, tooltip, ripple): 6 rules

These are explicitly NOT in the WP↔M3 ontology binding. They are either:
- Handed off to other plugins (form fields → CF7/WPForms/Gravity)
- Pure JS runtime primitives (no block, no token equivalence)

## Next iterations (v2.1a-P1+)

- **P1**: M3-COMPONENT-SPECS Tier 1 (Button, Card, List, Divider) → component ontology nodes with structure breakdown
- **P2**: base.css semantic policy extraction (§3 heading mapping, §6.5 type utilities, §11 code)
- **P3**: BLOCK-COMPONENT-MAP bucket B (Block Pattern) — only 1 entry currently, expand with composition rules
- **P4**: Axismundi block theme self-validation — does the actual theme implement these bindings?