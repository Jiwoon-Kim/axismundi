# WordPress Platform Ontology

114 entities across 2 axes (Block + Theme) + 8 cross-axis bridges.

| File | Purpose |
|---|---|
| `ontology.jsonld` | Unified v0.2 (canonical) |
| `summary.md` | Human-readable composition + tier breakdown |
| `gap_report.md` | All discovered gaps (G1–G5) |
| `validation_sources.json` | 6-tier source priority + pinned WP/GB baseline |
| `anchor_to_repo_map.json` | 47 anchor symbols × file paths in upstream repos |
| `pilots/p1_supports.json` | P1: 28 BlockSupports, 3-way agreement |
| `pilots/p2_attributes.json` | P2: 9 keys × 473 attribute instances |
| `pilots/p3_registration.json` | P3: 31 properties × 5 tiers |
| `pilots/p3_registration_tiered.json` | P3 supplement (provenance classification) |
| `pilots/p4_theme_settings.json` | P4: theme.json settings, 5-way validation |
| `pilots/p4_theme_styles.json` | P4: theme.json styles |
| `pilots/ontology_block_v0_1.jsonld` | Archaeological — Block-only v0.1 |
| `pilots/ontology_theme_v0_1.jsonld` | Archaeological — Theme-only v0.1 |

Atlas coverage: 23/38 block rules (60.5%) + 16/16 theme-config rules (100%) = 39/113 atlas rules (34.5%).

Baseline: WordPress 6.9.4 (97b7f62a) + Gutenberg v23.1.1 (12c6c76e).
