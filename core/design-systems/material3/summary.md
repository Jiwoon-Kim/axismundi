# M3 Token Ontology — v2.1a v0.1

Generated from `tokens.css` (482 tokens) + `M3-COLOR-TOKEN.md` (canonical reference).

## Token families (8 families, 265 total tokens)

| family | count | WP binding |
|---|---|---|
| ref.palette | 94 | (internal — feeds sys.color) |
| sys.typescale | 75 | wp:ThemeToken.typography (P4-perfect 5/5) |
| sys.color | 36 | wp:ThemeToken.color (P4-perfect 5/5) |
| sys.motion | 24 | (no direct WP binding; runtime layer) |
| sys.shape | 13 | wp:ThemeToken.border (conditional) |
| sys.elevation | 12 | wp:ThemeToken.shadow (conditional) |
| ref.typeface | 7 | (internal — feeds sys.typescale) |
| sys.state | 4 | (no direct WP binding; runtime layer) |

## Binding strength (P4 5-way agreement reference)

- **Strong** (5/5 source agreement in P4): sys.color ↔ ThemeToken.color, sys.typescale ↔ ThemeToken.typography
- **Conditional**: sys.elevation ↔ ThemeToken.shadow, sys.shape ↔ ThemeToken.border
- **Runtime-only** (no WP equivalent): sys.motion, sys.state
- **Internal**: ref.palette, ref.typeface (consumed by sys layer)

## Tier-1 Material binding readiness

Four families have **strong** binding to WP P4-confirmed 5/5 ThemeTokens:
1. `sys.color` (133) → `wp:ThemeToken.color`
2. `sys.typescale` (150) → `wp:ThemeToken.typography`

Two more have **conditional** binding (4/5 in P4):
3. `sys.elevation` (22) → `wp:ThemeToken.shadow`
4. `sys.shape` (16) → `wp:ThemeToken.border`

Total M3 tokens with WP binding readiness: **321 / 482 (66.6%)**