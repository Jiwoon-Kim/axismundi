#!/usr/bin/env python3
"""
build_theme_ontology_v0_1.py — Build Theme ontology v0.1 + merge with Block v0.1 → WordPress Ontology Core v0.2.

Inputs:
  core/wordpress/pilot_theme_settings.json    (P4-settings)
  core/wordpress/pilot_theme_styles.json      (P4-styles)
  core/wordpress/ontology_core_draft_v0_1.jsonld   (Block v0.1 baseline)

Outputs:
  core/wordpress/ontology_theme_draft_v0_1.jsonld   (Theme-only)
  core/wordpress/ontology_core_draft_v0_2.jsonld   (Block + Theme unified)
  core/wordpress/ontology_core_summary_v0_2.md
"""

import json
from pathlib import Path
from datetime import datetime
from collections import Counter

P4S = Path("core/wordpress/pilot_theme_settings.json")
P4STYLES = Path("core/wordpress/pilot_theme_styles.json")
V01 = Path("core/wordpress/ontology_core_draft_v0_1.jsonld")
ATLAS_ROOT = Path("atlas/wordpress")

OUT_THEME = Path("core/wordpress/ontology_theme_draft_v0_1.jsonld")
OUT_V02 = Path("core/wordpress/ontology_core_draft_v0_2.jsonld")
OUT_SUMMARY = Path("core/wordpress/ontology_core_summary_v0_2.md")


def build_atlas_index():
    ids = set()
    if not ATLAS_ROOT.exists():
        return ids
    for fp in ATLAS_ROOT.rglob("*.md"):
        try:
            txt = fp.read_text()
        except Exception:
            continue
        for line in txt.split("\n")[:30]:
            if line.startswith("rule_id:"):
                rid = line.split(":", 1)[1].strip()
                if rid and not rid.startswith("<"):
                    ids.add(rid)
                break
    return ids


ATLAS_RULES = None


def atlas_link(rule_id):
    if ATLAS_RULES and rule_id in ATLAS_RULES:
        return rule_id
    return None


def build_theme_graph(p4s, p4styles):
    """Build theme ontology graph nodes."""
    nodes = []

    # Root entity: ThemeJSON
    nodes.append({
        "@id": "wp:ThemeJSON",
        "@type": "wp:RootEntity",
        "label": "ThemeJSON",
        "comment": "WordPress theme.json — global presentation configuration: settings (token registration + policy) + styles (computed values) + templateParts + customTemplates + patterns + blockTypes.",
        "tier": "Root",
        "schema_path": "schemas/json/theme.json",
        "wp_runtime_path": "wp-includes/theme.json",
        "version_required": True,
        "thematic_domain": "theme-config",
        "atlas_related_root": [r for r in [
            atlas_link("theme-config.json-appearanceTools"),
            atlas_link("theme-config.json-settings-color"),
            atlas_link("theme-config.json-settings-typography"),
            atlas_link("theme-config.json-settings-spacing"),
            atlas_link("theme-config.json-settings-layout"),
            atlas_link("theme-config.json-settings-residual-governance"),
            atlas_link("theme-config.json-styles-color"),
            atlas_link("theme-config.json-styles-typography"),
            atlas_link("theme-config.json-styles-spacing"),
            atlas_link("theme-config.json-styles-css"),
            atlas_link("theme-config.json-styles-filter"),
            atlas_link("theme-config.json-styles-realization-batch"),
            atlas_link("theme-config.json-customTemplates"),
            atlas_link("theme-config.json-patterns"),
            atlas_link("theme-config.json-templateParts"),
            atlas_link("theme-config.global-styles-user-persistence"),
        ] if r],
    })

    # === Tier 1: ThemeToken root class ===
    nodes.append({
        "@id": "wp:ThemeToken",
        "@type": "wp:TokenClass",
        "label": "ThemeToken",
        "comment": "A named design value registered in theme.json settings that becomes a CSS custom property and an inserter option (color/spacing/typography/shadow preset).",
        "tier": "Tier1_Identity",
        "thematic_domain": "theme-config",
        "atlas_rule_id_pattern": "theme-config.settings.*",
        "binding_candidates": {
            "material": "DesignToken (token system root)",
            "activitypub": None,
        },
    })

    # 5/5 perfect-match categories — these become Tier 1 token nodes
    settings_categories = p4s["categories"]
    for cat in settings_categories:
        display = cat["display"]
        n = cat["n_sources"]
        if n < 3:  # only include 3+ source coverage
            continue
        # Determine ontology slot type
        slot_type = "wp:ThemeTokenInstance"
        tier = "Tier1_Identity"
        if display == "appearanceTools":
            slot_type = "wp:Bridge"  # special — bridges block ontology
            tier = "Tier_Bridge"
        elif display == "blocks":
            slot_type = "wp:GlobalSetting"
        elif display == "custom":
            slot_type = "wp:CustomToken"
        elif display in ("layout", "border", "dimensions", "position",
                         "lightbox", "shadow", "use-root-padding-aware-alignments"):
            slot_type = "wp:GlobalSetting"

        node = {
            "@id": f"wp:ThemeToken.{display}",
            "@type": slot_type,
            "label": display,
            "broader": "wp:ThemeToken",
            "tier": tier,
            "comment": f"theme.json settings.{display} — {cat.get('schema_property_count', 0)} schema properties.",
            "thematic_domain": "theme-config",
            "schema_field": f"settings.{display}",
            "n_sources_agreement": n,
            "in_corpus": cat["in_corpus"],
            "in_docs": cat["in_docs"],
            "in_schema": cat["in_schema"],
            "in_wp_runtime": cat["in_wp_runtime"],
            "in_atlas": cat["in_atlas"],
        }

        # Schema properties (when schema-defined)
        if cat.get("schema_property_count"):
            node["schema_property_count"] = cat["schema_property_count"]
            node["schema_properties_top10"] = cat.get("schema_properties", [])[:10]

        # WP runtime defaults
        if cat.get("wp_runtime_details"):
            node["wp_runtime_details"] = cat["wp_runtime_details"]

        # Atlas link — pattern: theme-config.json-settings-X (except appearanceTools)
        atlas_id = None
        if display == "appearanceTools":
            atlas_id = atlas_link("theme-config.json-appearanceTools")
        elif display in {"color", "typography", "spacing", "layout"}:
            atlas_id = atlas_link(f"theme-config.json-settings-{display}")
        elif display == "residual-governance":
            atlas_id = atlas_link("theme-config.json-settings-residual-governance")
        if atlas_id:
            node["atlas_rule_id"] = atlas_id

        # Provenance
        if n == 5:
            node["provenance"] = "schema+instance+atlas+runtime+docs"
        elif n == 4 and cat["in_atlas"]:
            node["provenance"] = "schema+instance+atlas (no runtime default)"
        elif n == 4:
            node["provenance"] = "schema+runtime+docs+corpus"
        else:
            node["provenance"] = f"{n}/5_partial"

        # Binding candidates for the 5/5 core 4
        if display == "color":
            node["binding_candidates"] = {
                "material": "ColorRoles (M3 color tokens: primary/secondary/...)",
                "activitypub": None,
            }
        elif display == "spacing":
            node["binding_candidates"] = {
                "material": "SpacingScale (M3 spacing tokens)",
                "activitypub": None,
            }
        elif display == "typography":
            node["binding_candidates"] = {
                "material": "TypeScale (M3 type tokens: display/headline/body/label)",
                "activitypub": None,
            }
        elif display == "appearanceTools":
            node["binding_candidates"] = {
                "material": "Component design-tool meta-flag (bulk enable)",
                "activitypub": None,
            }
            # Enumerate the 12+ BlockSupport sub-properties it bulk-enables
            node["bulk_enables"] = [
                "wp:BlockSupport.border (color/radius/style/width)",
                "wp:BlockSupport.color.link",
                "wp:BlockSupport.dimensions (aspectRatio/minHeight)",
                "wp:BlockSupport.position.sticky",
                "wp:BlockSupport.spacing (blockGap/margin/padding)",
                "wp:BlockSupport.typography.lineHeight",
            ]

        nodes.append(node)

    # === Tier 2: secondary slots (ColorPalette / TypographyScale / SpacingScale / ShadowPreset / LayoutConstraint) ===
    tier2_slots = [
        ("ColorPalette", "color", "Ordered list of named color Presets exposed in color picker.",
         atlas_link("theme-config.json-styles-color"), "Material.ColorScheme"),
        ("TypographyScale", "typography", "Ordered font-size Presets + fluid scaling rules + fontFamily list.",
         atlas_link("theme-config.json-styles-typography"), "Material.TypeScale"),
        ("SpacingScale", "spacing", "Ordered spacing Presets (margin/padding/blockGap).",
         atlas_link("theme-config.json-styles-spacing"), "Material.SpacingTokens"),
        ("ShadowPreset", "shadow", "Named shadow Presets for shadow picker UI.",
         None, "Material.ElevationTokens"),
        ("LayoutConstraint", "layout", "contentSize / wideSize root-level constraints.",
         None, "Material.LayoutGrid"),
    ]
    for slot, parent, comment, atlas_id, material_bind in tier2_slots:
        node = {
            "@id": f"wp:{slot}",
            "@type": "wp:TokenSubclass",
            "label": slot,
            "broader": f"wp:ThemeToken.{parent}",
            "tier": "Tier2_TokenSubclass",
            "comment": comment,
            "thematic_domain": "theme-config",
            "schema_path": f"settings.{parent}",
            "binding_candidates": {"material": material_bind},
        }
        if atlas_id:
            node["atlas_rule_id"] = atlas_id
        nodes.append(node)

    # === Tier 3: derived slots ===
    derived = [
        {
            "@id": "wp:PresetOrigin",
            "@type": "wp:DerivedSlot",
            "label": "PresetOrigin",
            "tier": "Tier3_Derived",
            "comment": "Origin layer of a preset value in the cascade: core defaults < theme.json < user style preferences.",
            "enum_values": ["core", "theme", "user"],
            "thematic_domain": "style-engine",
        },
        {
            "@id": "wp:InheritanceBoundary",
            "@type": "wp:DerivedSlot",
            "label": "InheritanceBoundary",
            "tier": "Tier3_Derived",
            "comment": "block-level settings.X override theme-level settings.X (settings.blocks.{name}.X has precedence).",
            "thematic_domain": "style-engine",
        },
        {
            "@id": "wp:StyleRule",
            "@type": "wp:DerivedSlot",
            "label": "StyleRule",
            "tier": "Tier3_Derived",
            "comment": "Computed style value at a styles.X path. Distinct from ThemeToken (registration) vs StyleRule (assignment).",
            "thematic_domain": "style-engine",
            "atlas_rule_id": atlas_link("style-engine.style-engine-pipeline") or None,
        },
    ]
    nodes.extend([n for n in derived if n.get("atlas_rule_id") or "atlas_rule_id" not in n])

    return nodes


def build_bridges(p4s):
    """Cross-axis: Block ontology ↔ Theme ontology bridges."""
    bridge_data = p4s["bridges_block_to_theme"]
    bridge_nodes = []

    # Mapping fix: convert pilot's atlas naming to actual atlas rule_ids
    def fix_atlas_block(rid):
        # pilot uses "block.supports.X" — these match atlas directly
        return rid

    def fix_atlas_theme(rid):
        # pilot uses "theme-config.settings.X" but atlas uses "theme-config.json-settings-X"
        if not rid:
            return None
        if rid.startswith("theme-config.settings."):
            suffix = rid[len("theme-config.settings."):]
            if suffix == "appearanceTools":
                return "theme-config.json-appearanceTools"
            return f"theme-config.json-settings-{suffix}"
        return rid

    for bridge_name, b in bridge_data.items():
        node = {
            "@id": f"wp:Bridge[{bridge_name.replace(' ', '_').replace('↔', 'XX')}]",
            "@type": "wp:BlockThemeBridge",
            "label": bridge_name,
            "comment": b.get("binding", "Bridge between block ontology slot and theme ontology slot."),
            "tier": "Tier_Bridge",
            "block_anchor": b.get("block_support"),
            "theme_anchor": b.get("theme_token"),
            "thematic_domain": "block-authoring↔theme-config",
        }
        if "atlas_block_rule" in b and b["atlas_block_rule"]:
            atlas_id = atlas_link(fix_atlas_block(b["atlas_block_rule"]))
            if atlas_id:
                node["atlas_block_rule"] = atlas_id
        if "atlas_theme_rule" in b and b["atlas_theme_rule"]:
            atlas_id = atlas_link(fix_atlas_theme(b["atlas_theme_rule"]))
            if atlas_id:
                node["atlas_theme_rule"] = atlas_id
        if "block_supports" in b:
            node["bulk_block_supports"] = b["block_supports"]

        bridge_nodes.append(node)

    return bridge_nodes


def merge_with_block_v01(theme_graph, bridges_graph):
    """Merge into v0.2 with Block v0.1."""
    v01 = json.loads(V01.read_text())

    # Block v0.1 graph entities
    block_graph = v01["@graph"]

    # Top-level metadata
    merged = {
        "@context": v01["@context"],
        "@id": "wp:WordPressOntologyCore",
        "@type": "wp:OntologyCore",
        "label": "WordPress Ontology Core v0.2",
        "comment": (
            "Unified WordPress ontology spanning Block ontology (P1/P2/P3) "
            "+ Theme ontology (P4 settings/styles) + Block↔Theme bridges. "
            "Provenance-aware, atlas-linked, binding-ready."
        ),
        "version": "0.2.0",
        "generated_at": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"),
        "baseline": v01["baseline"],
        "thematic_domains_total": v01["thematic_domains_total"],
        "provenance_taxonomy": v01["provenance_taxonomy"] + [
            "schema+instance+atlas+runtime+docs",
        ],
        "atlas_integration": v01["atlas_integration"],
        "axis_count": 2,
        "axes": {
            "block": {
                "root": "wp:BlockType",
                "entities": len(block_graph),
                "thematic_domain": "block-authoring",
            },
            "theme": {
                "root": "wp:ThemeJSON",
                "entities": len(theme_graph),
                "thematic_domain": "theme-config",
            },
            "bridges": {
                "root": "wp:BlockThemeBridge",
                "entities": len(bridges_graph),
                "thematic_domain": "block-authoring↔theme-config",
            },
        },
        "@graph": block_graph + theme_graph + bridges_graph,
    }
    return merged


def main():
    global ATLAS_RULES
    ATLAS_RULES = build_atlas_index()
    print(f"Loaded {len(ATLAS_RULES)} atlas rule IDs")

    p4s = json.loads(P4S.read_text())
    p4styles = json.loads(P4STYLES.read_text())

    theme_nodes = build_theme_graph(p4s, p4styles)
    bridge_nodes = build_bridges(p4s)

    # Theme-only JSON-LD
    theme_only = {
        "@context": json.loads(V01.read_text())["@context"],
        "@id": "wp:WordPressThemeOntologyCore",
        "@type": "wp:OntologyCore",
        "label": "WordPress Theme Ontology Core Draft v0.1",
        "version": "0.1.0",
        "generated_at": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"),
        "@graph": theme_nodes,
    }
    OUT_THEME.write_text(json.dumps(theme_only, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT_THEME}")

    # Merged v0.2
    merged = merge_with_block_v01(theme_nodes, bridge_nodes)
    OUT_V02.write_text(json.dumps(merged, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT_V02}")

    # Summary
    types = Counter(n.get("@type") for n in merged["@graph"])
    tiers = Counter(n.get("tier", "(none)") for n in merged["@graph"])
    provs = Counter(n.get("provenance", "(none)") for n in merged["@graph"] if "provenance" in n)
    atlas_count = sum(1 for n in merged["@graph"] if n.get("atlas_rule_id"))
    bind_count = sum(1 for n in merged["@graph"] if n.get("binding_candidates"))

    summary = f"""# WordPress Ontology Core v0.2

Generated: {merged['generated_at']}
Baseline: WP {merged['baseline']['wordpress']}, Gutenberg {merged['baseline']['gutenberg']}

## 2-Axis Architecture

| Axis | Root | Entities |
|---|---|---|
| Block | wp:BlockType | {merged['axes']['block']['entities']} |
| Theme | wp:ThemeJSON | {merged['axes']['theme']['entities']} |
| Bridges | wp:BlockThemeBridge | {merged['axes']['bridges']['entities']} |
| **Total** | | **{len(merged['@graph'])}** |

## @type distribution
""" + "\n".join(f"- {t}: {c}" for t, c in types.most_common()) + f"""

## Tier distribution
""" + "\n".join(f"- {t}: {c}" for t, c in tiers.most_common()) + f"""

## Provenance distribution
""" + "\n".join(f"- {p}: {c}" for p, c in provs.most_common()) + f"""

## Atlas + Binding readiness
- Entities with `atlas_rule_id`: {atlas_count}
- Entities with `binding_candidates`: {bind_count}

## v0.2 milestone
- v0.1 (Block) + v0.1 (Theme) → v0.2 unified
- WordPress ontology has 2 axes + Block↔Theme bridges
- Material binding readiness: appearanceTools + spacing + typography + color confirmed perfect (5/5 source agreement)
- Next: P5 data-core-block-editor (Store ontology) → v0.3
"""
    OUT_SUMMARY.write_text(summary)
    print(f"Wrote {OUT_SUMMARY}\n")

    # Print
    print(f"=== v0.2 Composition ===")
    print(f"  Total entities: {len(merged['@graph'])}")
    print(f"  Block axis: {merged['axes']['block']['entities']}")
    print(f"  Theme axis: {merged['axes']['theme']['entities']}")
    print(f"  Bridges:    {merged['axes']['bridges']['entities']}")
    print(f"  Atlas-linked: {atlas_count}")
    print(f"  Binding-ready: {bind_count}")


if __name__ == "__main__":
    main()
