#!/usr/bin/env python3
"""
v2_0_theme_settings_pilot.py — 5-way validation for theme.json settings (P4).

Sources:
  1. corpus:    dev_handbook_clean/theme-handbook/03-theme-json/02-settings/*.md
  2. docs:      GB-23.1.1/docs/reference-guides/theme-json-reference/theme-json-living.md
  3. schema:    GB-23.1.1/schemas/json/theme.json (settings definitions)
  4. wp-runtime: WP-6.9.4/wp-includes/theme.json (default values)
  5. atlas:     knowledge/wordpress/theme-config/settings/*.md

Outputs:
  core/wordpress/pilot_theme_settings.json
"""

import json
import re
from pathlib import Path
from collections import Counter, OrderedDict


CORPUS_DIR = Path("dev_handbook_clean/theme-handbook/03-theme-json/02-settings")
DOCS = Path("${GUTENBERG_ROOT}/docs/reference-guides/theme-json-reference/theme-json-living.md")
SCHEMA = Path("${GUTENBERG_ROOT}/schemas/json/theme.json")
WP_RUNTIME = Path("${WORDPRESS_ROOT}/wp-includes/theme.json")
ATLAS_DIR = Path("atlas/wordpress/theme-config/settings")

OUT = Path("core/wordpress/pilot_theme_settings.json")


# Map atlas rule files → corpus file (slug-based)
# atlas: theme-config/settings/{slug}.md → rule_id: theme-config.settings.{slug}
ATLAS_RULE_PATTERN = "theme-config.settings.{slug}"

# Map corpus file → schema definition name in theme.json
CORPUS_TO_SCHEMA_DEF = {
    "appearance-tools": "settingsAppearanceToolsProperties",
    "blocks": "settingsBlocksPropertiesComplete",
    "border": "settingsBorderProperties",
    "color": "settingsColorProperties",
    "custom": "settingsCustomProperties",
    "dimensions": "settingsDimensionsProperties",
    "layout": "settingsLayoutProperties",
    "lightbox": "settingsLightboxProperties",
    "position": "settingsPositionProperties",
    "shadow": "settingsShadowProperties",
    "spacing": "settingsSpacingProperties",
    "typography": "settingsTypographyProperties",
    "use-root-padding-aware-alignments": None,  # part of settingsProperties top-level
    "background": "settingsBackgroundProperties",
}


def extract_h2(text):
    return [m.group(1) for m in re.finditer(r"^## ([^\n]+)$", text, re.MULTILINE)]


def load_corpus_settings():
    """Map slug → file path for all settings/*.md (except index/settings-reference)."""
    out = {}
    for fp in sorted(CORPUS_DIR.glob("*.md")):
        slug = fp.stem
        if slug in ("index", "settings-reference"):
            continue
        out[slug] = fp
    return out


def load_atlas_settings():
    """Map slug → file path for all atlas theme-config/settings/*.md."""
    out = {}
    if not ATLAS_DIR.exists():
        return out
    for fp in sorted(ATLAS_DIR.glob("*.md")):
        slug = fp.stem
        out[slug] = fp
    return out


def extract_schema_settings_keys(schema_json):
    """Extract all settings.* keys from schema definitions."""
    defs = schema_json.get("definitions", {})
    out = {}
    for defname, defspec in defs.items():
        if not defname.startswith("settings") or not defname.endswith("Properties"):
            continue
        if defname == "settingsProperties":
            continue  # composite — covered by individual subtypes
        # Schema fragment is usually under "properties" within the definition
        props = (defspec.get("properties") or {})
        out[defname] = {
            "type": defspec.get("type"),
            "property_count": len(props),
            "properties": sorted(props.keys()),
        }
    return out


def extract_wp_runtime_defaults(wp_json):
    """Extract WP core's runtime default values for each settings.X subtree."""
    s = wp_json.get("settings", {})
    out = {}
    for k, v in s.items():
        if isinstance(v, dict):
            out[k] = {
                "key_count": len(v),
                "keys": sorted(v.keys()),
            }
            # If there are presets, count them
            for sub, subv in v.items():
                if isinstance(subv, list):
                    out[k][f"{sub}_preset_count"] = len(subv)
                elif isinstance(subv, dict) and sub == "defaultPalette":
                    out[k][f"{sub}_value"] = subv
        else:
            out[k] = v
    return out


def extract_atlas_rule_id(fp):
    """Read atlas .md frontmatter and extract rule_id."""
    text = fp.read_text()
    for line in text.split("\n")[:30]:
        if line.startswith("rule_id:"):
            return line.split(":", 1)[1].strip()
    return None


def extract_atlas_metadata(fp):
    """Read atlas frontmatter — rule_id, related, field_cluster, status."""
    text = fp.read_text()
    in_front = False
    fm = {}
    related = []
    in_related = False
    for line in text.split("\n"):
        if line.strip() == "---":
            if not in_front:
                in_front = True
                continue
            else:
                break
        if not in_front:
            continue
        if line.startswith("rule_id:"):
            fm["rule_id"] = line.split(":", 1)[1].strip()
            in_related = False
        elif line.startswith("status:"):
            fm["status"] = line.split(":", 1)[1].strip()
            in_related = False
        elif line.startswith("field_cluster:"):
            fm["field_cluster"] = line.split(":", 1)[1].strip()
            in_related = False
        elif line.startswith("language:"):
            fm["language"] = line.split(":", 1)[1].strip()
            in_related = False
        elif line.startswith("related:"):
            in_related = True
        elif in_related and line.strip().startswith("- "):
            rel = line.strip().lstrip("- ").split("#")[0].strip()
            if rel:
                related.append(rel)
        elif in_related and line and not line.startswith(" "):
            in_related = False
    if related:
        fm["related"] = related
    return fm


def main():
    schema_json = json.loads(SCHEMA.read_text())
    wp_runtime = json.loads(WP_RUNTIME.read_text())
    docs_text = DOCS.read_text()

    corpus_files = load_corpus_settings()
    atlas_files = load_atlas_settings()
    schema_defs = extract_schema_settings_keys(schema_json)
    wp_runtime_defaults = extract_wp_runtime_defaults(wp_runtime)

    docs_h2 = extract_h2(docs_text)

    # Build per-category 5-way matrix
    # Categories: 12 (corpus) + atlas may have extras
    all_slugs = set(corpus_files.keys()) | set(atlas_files.keys())
    # Also pull from schema definition names
    schema_slugs_from_defs = set()
    slug_map_from_schema = {
        "settingsAppearanceToolsProperties": "appearanceTools",
        "settingsBackgroundProperties": "background",
        "settingsBlocksPropertiesComplete": "blocks",
        "settingsBorderProperties": "border",
        "settingsColorProperties": "color",
        "settingsCustomProperties": "custom",
        "settingsDimensionsProperties": "dimensions",
        "settingsLayoutProperties": "layout",
        "settingsLightboxProperties": "lightbox",
        "settingsPositionProperties": "position",
        "settingsShadowProperties": "shadow",
        "settingsSpacingProperties": "spacing",
        "settingsTypographyProperties": "typography",
    }
    for defname in schema_defs:
        if defname in slug_map_from_schema:
            schema_slugs_from_defs.add(slug_map_from_schema[defname])
    all_slugs |= schema_slugs_from_defs

    # Normalize: corpus uses "appearance-tools", atlas uses "appearanceTools"
    # Build canonical slug map
    def canonicalize(slug):
        return slug.replace("-", "").lower()

    canon_to_slug_set = {}
    for slug in all_slugs:
        canon = canonicalize(slug)
        canon_to_slug_set.setdefault(canon, set()).add(slug)

    # 5-way per category
    categories = []
    for canon, slug_variants in sorted(canon_to_slug_set.items()):
        # Pick canonical display name (prefer atlas form, then corpus form)
        atlas_match = [s for s in slug_variants if s in atlas_files]
        corpus_match = [s for s in slug_variants if s in corpus_files]
        schema_match = [s for s in slug_variants if s in schema_slugs_from_defs]

        display = atlas_match[0] if atlas_match else (corpus_match[0] if corpus_match else (schema_match[0] if schema_match else list(slug_variants)[0]))

        in_corpus = bool(corpus_match)
        in_atlas = bool(atlas_match)
        in_schema = bool(schema_match)
        in_wp_runtime = display in wp_runtime_defaults or canonicalize(display) in {canonicalize(k) for k in wp_runtime_defaults}
        # docs: check if display appears as h2 or as keyword
        in_docs = any(canonicalize(display) in canonicalize(h) for h in docs_h2) or canonicalize(display) in canonicalize(docs_text[:50000])

        sources_present = sum([in_corpus, in_docs, in_schema, in_wp_runtime, in_atlas])

        cat = {
            "canonical": canon,
            "display": display,
            "slug_variants": sorted(slug_variants),
            "in_corpus": in_corpus,
            "in_docs": in_docs,
            "in_schema": in_schema,
            "in_wp_runtime": in_wp_runtime,
            "in_atlas": in_atlas,
            "n_sources": sources_present,
        }

        # Add details if in atlas
        if atlas_match:
            atlas_fp = atlas_files[atlas_match[0]]
            cat["atlas_metadata"] = extract_atlas_metadata(atlas_fp)

        # Add schema details
        if schema_match:
            defname = next(d for d, s in slug_map_from_schema.items() if s == schema_match[0])
            cat["schema_def_name"] = defname
            cat["schema_property_count"] = schema_defs[defname]["property_count"]
            cat["schema_properties"] = schema_defs[defname]["properties"]

        # Add WP runtime details
        if in_wp_runtime:
            rt_key = display if display in wp_runtime_defaults else next(
                (k for k in wp_runtime_defaults if canonicalize(k) == canonicalize(display)),
                None,
            )
            if rt_key:
                cat["wp_runtime_key"] = rt_key
                cat["wp_runtime_details"] = wp_runtime_defaults[rt_key]

        categories.append(cat)

    # Ontology slot extraction
    ontology_slots = {
        "ThemeToken": {
            "tier": 1,
            "description": "A named design value registered in theme.json settings — color/font/spacing/shadow preset that becomes a CSS custom property and inserter option.",
            "instance_count": 0,  # to be populated
            "atlas_rule_id_pattern": "theme-config.settings.*",
        },
        "GlobalSetting": {
            "tier": 1,
            "description": "A boolean/scalar opt-in toggle in theme.json settings (e.g., color.custom=false locks down user palette extension).",
            "tier_note": "Distinct from ThemeToken (which is presets); GlobalSetting is configuration policy.",
        },
        "Preset": {
            "tier": 1,
            "description": "A user-selectable named value within a ThemeToken category (color palette item, font size step, spacing step).",
            "atlas_rule_id_pattern": "theme-config.settings.color/typography/spacing",
        },
        "CustomToken": {
            "tier": 1,
            "description": "Theme-author-defined arbitrary CSS variable via settings.custom (escape hatch for non-standard tokens).",
            "atlas_rule_id_pattern": "theme-config.settings.custom (residual-governance)",
        },
        "AppearanceTool": {
            "tier": 1,
            "description": "appearanceTools is a meta-toggle that bulk-enables UI controls (border, link color, padding, margin) across blocks. Bridge between BlockSupport and ThemeToken.",
            "atlas_rule_id_pattern": "theme-config.settings.appearanceTools",
            "bridge_to_block_supports": True,
        },
        "ColorPalette": {
            "tier": 2,
            "description": "Ordered list of named color Presets exposed in color picker UI.",
            "parent_token": "ThemeToken.color",
        },
        "TypographyScale": {
            "tier": 2,
            "description": "Ordered list of named font-size Presets + fluid scaling rules.",
            "parent_token": "ThemeToken.typography",
        },
        "SpacingScale": {
            "tier": 2,
            "description": "Ordered list of named spacing Presets (margin/padding/blockGap).",
            "parent_token": "ThemeToken.spacing",
        },
        "ShadowPreset": {
            "tier": 2,
            "description": "Named shadow tokens for shadow picker UI.",
            "parent_token": "ThemeToken.shadow",
        },
        "LayoutConstraint": {
            "tier": 2,
            "description": "contentSize / wideSize root-level constraints + custom layout rules.",
            "parent_token": "ThemeToken.layout",
        },
        "PresetOrigin": {
            "tier": 3,
            "description": "Where a preset comes from — core defaults / theme.json / user style preferences. Critical for cascade order.",
            "enum_values": ["core", "theme", "user"],
        },
        "InheritanceBoundary": {
            "tier": 3,
            "description": "Theme inheritance rules — block-level settings override theme-level, user overrides theme.",
        },
    }

    # Cross-axis bridges (Block ↔ Theme)
    bridges = {
        "BlockSupport.spacing ↔ ThemeToken.spacing": {
            "block_support": "wp:BlockSupport.spacing",
            "theme_token": "ThemeToken.spacing",
            "atlas_block_rule": "block.supports.spacing",
            "atlas_theme_rule": "theme-config.settings.spacing",
            "binding": "When a block declares supports.spacing, theme.json spacing tokens become available as UI options for that block.",
        },
        "BlockSupport.typography ↔ ThemeToken.typography": {
            "block_support": "wp:BlockSupport.typography",
            "theme_token": "ThemeToken.typography",
            "atlas_block_rule": "block.supports.typography",
            "atlas_theme_rule": "theme-config.settings.typography",
        },
        "BlockSupport.color ↔ ThemeToken.color": {
            "block_support": "wp:BlockSupport.color",
            "theme_token": "ThemeToken.color",
            "atlas_block_rule": "block.supports.color",
            "atlas_theme_rule": "theme-config.settings.color",
        },
        "BlockSupport.dimensions ↔ ThemeToken.dimensions": {
            "block_support": "wp:BlockSupport.dimensions",
            "theme_token": "ThemeToken.dimensions",
        },
        "BlockSupport.shadow ↔ ThemeToken.shadow": {
            "block_support": "wp:BlockSupport.shadow",
            "theme_token": "ThemeToken.shadow",
            "atlas_theme_rule": "theme-config.settings.shadow",
        },
        "BlockSupport.layout ↔ ThemeToken.layout": {
            "block_support": "wp:BlockSupport.layout",
            "theme_token": "ThemeToken.layout",
            "atlas_theme_rule": "theme-config.settings.layout",
        },
        "BlockSupport.border ↔ ThemeToken.border": {
            "block_support": "wp:BlockSupport.border",  # if present
            "theme_token": "ThemeToken.border",
        },
        "appearanceTools ↔ multiple BlockSupports": {
            "theme_token": "AppearanceTool",
            "block_supports": ["border.color", "border.radius", "border.style", "border.width",
                               "color.link", "dimensions.aspectRatio", "dimensions.minHeight",
                               "position.sticky", "spacing.blockGap", "spacing.margin", "spacing.padding",
                               "typography.lineHeight"],
            "binding": "appearanceTools=true is a bulk-enable for 12 specific BlockSupport sub-properties.",
        },
    }

    # Gaps
    gaps = []

    # G4.1 — schema vs runtime divergence
    schema_categories = set(schema_slugs_from_defs)
    runtime_categories = {canonicalize(k) for k in wp_runtime_defaults}
    schema_canon = {canonicalize(s) for s in schema_categories}
    schema_only = schema_canon - runtime_categories
    runtime_only = runtime_categories - schema_canon
    if schema_only or runtime_only:
        gaps.append({
            "id": "G4.1",
            "category": "schema-runtime-divergence",
            "description": f"theme.json schema defines categories not in WP runtime defaults: {sorted(schema_only)}; runtime has: {sorted(runtime_only)}",
            "schema_only": sorted(schema_only),
            "runtime_only": sorted(runtime_only),
        })

    # G4.2 — atlas vs schema coverage
    atlas_canon = {canonicalize(s) for s in atlas_files.keys()}
    schema_not_in_atlas = schema_canon - atlas_canon
    atlas_not_in_schema = atlas_canon - schema_canon
    if schema_not_in_atlas:
        gaps.append({
            "id": "G4.2",
            "category": "atlas-coverage",
            "description": f"Schema settings categories not yet covered by atlas: {sorted(schema_not_in_atlas)}",
            "missing_in_atlas": sorted(schema_not_in_atlas),
            "extra_in_atlas": sorted(atlas_not_in_schema),
        })

    # G4.3 — appearanceTools bridge
    gaps.append({
        "id": "G4.3",
        "category": "block-theme-bridge",
        "description": "appearanceTools is a meta-toggle bridging theme.json settings and block supports. It bulk-enables 12+ block support sub-properties. This is the most important Block↔Theme binding surface in the ontology.",
        "atlas_anchor": "theme-config.settings.appearanceTools",
        "impact": "AppearanceTool slot must explicitly enumerate the 12 BlockSupport sub-properties it controls.",
    })

    # G4.4 — slug naming divergence
    naming_divergence = []
    for canon, variants in canon_to_slug_set.items():
        if len(variants) > 1:
            naming_divergence.append({"canonical": canon, "variants": sorted(variants)})
    if naming_divergence:
        gaps.append({
            "id": "G4.4",
            "category": "naming-divergence",
            "description": "Slug naming differs across sources (corpus kebab-case vs schema/atlas camelCase).",
            "instances": naming_divergence,
        })

    report = {
        "schema_version": "v2.0-pilot-theme-settings-2026-05-12",
        "generated_at": "2026-05-12",
        "pilot": "P4-settings",
        "sources": {
            "1_corpus": str(CORPUS_DIR),
            "2_docs": str(DOCS),
            "3_schema": str(SCHEMA),
            "4_wp_runtime": str(WP_RUNTIME),
            "5_atlas": str(ATLAS_DIR),
        },
        "baseline": {
            "wordpress": "6.9.4 @ 97b7f62a",
            "gutenberg": "v23.1.1 @ 12c6c76e",
            "atlas": "knowledge/wordpress/theme-config/settings/ (6 rules)",
        },
        "categories": categories,
        "categories_summary": {
            "total_distinct": len(categories),
            "in_all_5_sources": sum(1 for c in categories if c["n_sources"] == 5),
            "in_4_sources": sum(1 for c in categories if c["n_sources"] == 4),
            "in_3_sources": sum(1 for c in categories if c["n_sources"] == 3),
            "in_2_sources": sum(1 for c in categories if c["n_sources"] == 2),
            "in_1_source": sum(1 for c in categories if c["n_sources"] == 1),
        },
        "schema_definitions_count": len(schema_defs),
        "wp_runtime_settings": list(wp_runtime_defaults.keys()),
        "ontology_slots": ontology_slots,
        "bridges_block_to_theme": bridges,
        "gaps": gaps,
    }

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT}\n")

    # Summary print
    print("=== Source inventory ===")
    print(f"  corpus settings files: {len(corpus_files)}")
    print(f"  atlas settings rules:  {len(atlas_files)}")
    print(f"  schema definitions:    {len(schema_defs)}")
    print(f"  wp runtime settings:   {len(wp_runtime_defaults)}")
    print()
    print("=== 5-way agreement per category ===")
    print(f"  {'category':25s} {'C':2s} {'D':2s} {'S':2s} {'R':2s} {'A':2s}  n_sources")
    for c in categories:
        marks = lambda b: "✓" if b else "·"
        print(f"  {c['display']:25s} {marks(c['in_corpus']):2s} {marks(c['in_docs']):2s} {marks(c['in_schema']):2s} {marks(c['in_wp_runtime']):2s} {marks(c['in_atlas']):2s}  {c['n_sources']}/5")
    print()
    print(f"=== Summary ===")
    for k, v in report["categories_summary"].items():
        print(f"  {k}: {v}")
    print()
    print(f"=== Ontology slots (P4 candidates) ===")
    for slot, defn in ontology_slots.items():
        print(f"  {slot:25s} tier={defn['tier']}")
    print()
    print(f"=== Bridges (Block ontology ↔ Theme ontology) ===")
    for bridge_name in bridges:
        print(f"  {bridge_name}")
    print()
    print(f"=== Gaps ===")
    for g in gaps:
        print(f"  {g['id']} [{g['category']}]: {g['description'][:120]}")


if __name__ == "__main__":
    main()
