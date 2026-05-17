#!/usr/bin/env python3
"""
v2_1_build_wp_to_m3_binding.py — Construct binding map between WordPress ontology v0.2
and M3 token + component ontology.

This is the CORE v2.1a deliverable: a typed translation layer between two
ontologies — NOT an "equivalence" mapping. Per GPT's guidance:
  "WordPress = CMS / block / token authority
   Material  = design system / UI semantics
   Binding   = translation layer"

Inputs:
  core/wordpress/ontology_core_draft_v0_2.jsonld   (WP, 114 entities)
  bindings/wordpress-material3/m3_token_ontology.jsonld           (M3, 273 entities)
  bindings/wordpress-material3/block_component_binding_rules.json (48 binding rules)

Outputs:
  bindings/wordpress-material3/wp_to_m3_binding_map.json
  bindings/wordpress-material3/binding_confidence_matrix.json
  bindings/wordpress-material3/wp_to_m3_binding_summary.md
"""

import json
from pathlib import Path
from collections import Counter, defaultdict

WP = Path("core/wordpress/ontology_core_draft_v0_2.jsonld")
M3 = Path("bindings/wordpress-material3/m3_token_ontology.jsonld")
BINDING_RULES = Path("bindings/wordpress-material3/block_component_binding_rules.json")

OUT_MAP = Path("bindings/wordpress-material3/wp_to_m3_binding_map.json")
OUT_CONFIDENCE = Path("bindings/wordpress-material3/binding_confidence_matrix.json")
OUT_SUMMARY = Path("bindings/wordpress-material3/wp_to_m3_binding_summary.md")


# Token-level bindings (Tier 1) — WP ThemeToken ↔ M3 family
TOKEN_BINDINGS = [
    {
        "wp_anchor": "wp:ThemeToken.color",
        "m3_anchor": "m3:Family.sys.color",
        "confidence": 0.95,
        "p4_agreement": "5/5",
        "translation_rule": (
            "WP theme.json settings.color.palette[].slug ↔ M3 sys-color role. "
            "Mapping is NOT 1:1 because M3 has fixed 38 sys roles "
            "(primary/secondary/tertiary/error × on-/-container/on--container + surface/outline/inverse/scrim/shadow), "
            "while WP palette is open string-keyed. "
            "Binding policy: theme.json declares a palette entry per M3 role using M3 slugs."
        ),
        "binding_pattern": "role_to_slug",
        "atlas_anchor_wp": "theme-config.json-settings-color",
        "axismundi_implementation": "tokens.css §2 sys-color (36 tokens, dual light/dark)",
    },
    {
        "wp_anchor": "wp:ThemeToken.typography",
        "m3_anchor": "m3:Family.sys.typescale",
        "confidence": 0.85,
        "p4_agreement": "5/5",
        "translation_rule": (
            "WP theme.json settings.typography.fontSizes ↔ M3 sys-typescale roles "
            "(display-large/medium/small, headline-*, title-*, body-*, label-*). "
            "Each role decomposes into 5 CSS properties (font/size/weight/lineHeight/tracking). "
            "Material is more granular than WP fontSize (only size). "
            "Binding policy: each WP fontSize slug maps to one M3 role, "
            "and base.css §6.5 .t-{role} utility classes provide the missing properties."
        ),
        "binding_pattern": "role_to_slug_plus_utility_class",
        "atlas_anchor_wp": "theme-config.json-settings-typography",
        "axismundi_implementation": "tokens.css §3 sys-typescale (75 tokens) + base.css §6.5 .t-{role} utilities",
    },
    {
        "wp_anchor": "wp:ThemeToken.spacing",
        "m3_anchor": None,
        "confidence": 0.7,
        "p4_agreement": "5/5",
        "translation_rule": (
            "M3 does NOT define a canonical spacing scale in baseline tokens (unlike color/typescale). "
            "WP theme.json settings.spacing.spacingSizes is the authority. "
            "Axismundi can ship a recommended spacing scale (e.g. 4px base, 4-8-12-16-24-32-48-64) "
            "but this is a design decision, not an M3 standard mapping."
        ),
        "binding_pattern": "wp_authoritative_with_axismundi_recommendation",
        "atlas_anchor_wp": "theme-config.json-settings-spacing",
        "axismundi_implementation": "(no spacing tokens in tokens.css yet; theme.json is source of truth)",
        "binding_gap": "M3 baseline has no spacing token spec",
    },
    {
        "wp_anchor": "wp:ThemeToken.shadow",
        "m3_anchor": "m3:Family.sys.elevation",
        "confidence": 0.7,
        "p4_agreement": "4/5",
        "translation_rule": (
            "WP theme.json settings.shadow.presets ↔ M3 sys-elevation levels (level0..level5). "
            "M3 elevation expresses surface depth via tonal containers + shadow; "
            "WP shadow is open-list shadow presets. "
            "Binding policy: 6 elevation levels map to 6 WP shadow presets with specific x/y/blur/spread."
        ),
        "binding_pattern": "level_to_preset",
        "axismundi_implementation": "tokens.css §4 sys-elevation (12 tokens, light-mode shadow + dark-mode tonal containers)",
    },
    {
        "wp_anchor": "wp:ThemeToken.border",
        "m3_anchor": "m3:Family.sys.shape",
        "confidence": 0.6,
        "p4_agreement": "4/5",
        "translation_rule": (
            "M3 sys-shape defines corner radius tokens (none/extra-small/small/medium/large/extra-large/full). "
            "WP theme.json settings.border has radius/style/width/color. "
            "Binding policy: M3 corner tokens become recommended WP border.radius values; "
            "the WP border subprops (style/width/color) are not M3-equivalent (M3 has no border-style/width baseline)."
        ),
        "binding_pattern": "radius_token_subset",
        "axismundi_implementation": "tokens.css §5 sys-shape (13 corner tokens)",
        "binding_gap": "border-style + border-width are WP-only; no M3 equivalent",
    },
    {
        "wp_anchor": "wp:AppearanceTool",
        "m3_anchor": "(meta-flag)",
        "confidence": 0.9,
        "p4_agreement": "5/5",
        "translation_rule": (
            "appearanceTools=true bulk-enables 12+ BlockSupport sub-properties. "
            "In Material design system terms, this is equivalent to enabling "
            "design-tool exposure for: border (color/radius/style/width), color.link, "
            "dimensions (aspectRatio/minHeight), position.sticky, spacing (blockGap/margin/padding), typography.lineHeight. "
            "M3 equivalent: 'Component design-tool capability flag' (M3 doesn't have a single meta-flag, but the bundle "
            "matches the M3 component-level design freedoms typically exposed in Component Inspector tools)."
        ),
        "binding_pattern": "meta_flag_to_capability_bundle",
        "atlas_anchor_wp": "theme-config.json-appearanceTools",
    },
]

# Component-level bindings (Tier 2) — synthesized from binding rules
def build_component_bindings(rules):
    """Group binding rules by binding_type and emit component-level binding entries."""
    bindings = []
    for rule in rules:
        if rule["binding_type"] not in ("Direct.CoreBlockStyle", "Direct.CustomBlock", "Composite.TemplatePart"):
            continue  # skip OutOfScope, RuntimeOnly
        block_refs = rule.get("block_refs", [])
        style_classes = rule.get("style_classes", [])
        binding = {
            "m3_component": rule["m3_component"],
            "binding_type": rule["binding_type"],
            "wp_block_anchors": block_refs,
            "wp_style_anchors": style_classes,
            "wp_ontology_links": [],
            "confidence": confidence_for_type(rule["binding_type"]),
            "deferred": rule.get("deferred", False),
            "implementation_path": rule["implementation_path"],
        }
        # If block_refs are core/* blocks, link to WP ontology
        for br in block_refs:
            if br.startswith("core/"):
                binding["wp_ontology_links"].append(f"wp:BlockName[{br}]")
        bindings.append(binding)
    return bindings


def confidence_for_type(binding_type):
    return {
        "Direct.CoreBlockStyle": 0.9,
        "Direct.CustomBlock": 0.85,
        "Compositional.BlockPattern": 0.7,
        "Composite.TemplatePart": 0.75,
    }.get(binding_type, 0.5)


def build_confidence_matrix(token_bindings, component_bindings):
    """3-axis confidence: WP anchor × M3 anchor × P4-agreement."""
    matrix = []

    # Token-level
    for tb in token_bindings:
        matrix.append({
            "axis": "token",
            "wp_anchor": tb["wp_anchor"],
            "m3_anchor": tb["m3_anchor"],
            "confidence": tb["confidence"],
            "p4_agreement": tb.get("p4_agreement"),
            "binding_pattern": tb["binding_pattern"],
            "has_axismundi_implementation": "axismundi_implementation" in tb,
        })

    # Component-level — only top-confidence (>0.7)
    for cb in component_bindings:
        if cb["confidence"] < 0.7:
            continue
        matrix.append({
            "axis": "component",
            "wp_anchor": cb["wp_block_anchors"][0] if cb["wp_block_anchors"] else None,
            "m3_anchor": cb["m3_component"],
            "confidence": cb["confidence"],
            "binding_pattern": cb["binding_type"],
            "deferred": cb.get("deferred", False),
        })

    return matrix


def main():
    wp = json.loads(WP.read_text())
    m3 = json.loads(M3.read_text())
    rules = json.loads(BINDING_RULES.read_text())

    component_bindings = build_component_bindings(rules["rules"])
    confidence_matrix = build_confidence_matrix(TOKEN_BINDINGS, component_bindings)

    binding_map = {
        "schema_version": "v2.1a-wp-to-m3-binding-2026-05-12",
        "generated_at": "2026-05-12",
        "philosophy": (
            "Binding ontology — NOT equivalence. WordPress and Material are different "
            "authorities (CMS+block+token vs design-system+UI-semantics); binding is a "
            "typed translation layer with explicit confidence + binding_pattern."
        ),
        "baseline": {
            "wp_ontology": "v0.2 (114 entities)",
            "m3_token_ontology": "v0.1 (273 entities)",
            "binding_rules": f"{len(rules['rules'])} component bindings",
        },
        "summary": {
            "tier1_token_bindings": len(TOKEN_BINDINGS),
            "tier1_strong_bindings": sum(1 for tb in TOKEN_BINDINGS if tb["confidence"] >= 0.85),
            "tier2_component_bindings": len(component_bindings),
            "tier2_direct_bindings": sum(1 for cb in component_bindings if cb["binding_type"].startswith("Direct")),
            "tier2_composite_bindings": sum(1 for cb in component_bindings if cb["binding_type"] == "Composite.TemplatePart"),
            "deferred_component_bindings": sum(1 for cb in component_bindings if cb.get("deferred")),
            "out_of_scope": sum(1 for r in rules["rules"] if r["binding_type"].startswith("OutOfScope")),
            "runtime_only": sum(1 for r in rules["rules"] if r["binding_type"] == "RuntimeOnly.ThemeJS"),
        },
        "tier1_token_bindings": TOKEN_BINDINGS,
        "tier2_component_bindings": component_bindings,
    }

    OUT_MAP.parent.mkdir(parents=True, exist_ok=True)
    OUT_MAP.write_text(json.dumps(binding_map, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT_MAP}")

    confidence_doc = {
        "schema_version": "v2.1a-confidence-matrix-2026-05-12",
        "axis_keys": ["token", "component"],
        "confidence_taxonomy": {
            "0.85-1.0": "strong — proceed with implementation, low risk",
            "0.70-0.85": "moderate — proceed with documented binding pattern, some translation logic needed",
            "0.50-0.70": "weak — design decision required, M3 baseline gap or WP openness mismatch",
            "<0.50": "speculative — not recommended for v2.1a",
        },
        "matrix": confidence_matrix,
    }
    OUT_CONFIDENCE.write_text(json.dumps(confidence_doc, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT_CONFIDENCE}")

    # Summary md
    md = [
        "# WordPress ↔ M3 Binding Map (v2.1a v0.1)",
        "",
        "**Philosophy**: Translation layer, not equivalence. WordPress is CMS+block+token authority;",
        "Material is design-system+UI-semantics authority. Binding is **typed translation**.",
        "",
        "## Tier 1 — Token bindings (6)",
        "",
        "| WP anchor | M3 anchor | confidence | P4 agreement | binding_pattern |",
        "|---|---|---|---|---|",
    ]
    for tb in TOKEN_BINDINGS:
        m3a = tb["m3_anchor"] or "—"
        p4 = tb.get("p4_agreement", "—")
        md.append(f"| `{tb['wp_anchor']}` | `{m3a}` | {tb['confidence']} | {p4} | {tb['binding_pattern']} |")
    md.append("")

    # Strongest bindings highlight
    strong = [tb for tb in TOKEN_BINDINGS if tb["confidence"] >= 0.85]
    md += [
        "",
        f"## Strong bindings ({len(strong)} of {len(TOKEN_BINDINGS)})",
        "",
        "These are the immediate ROI for Axismundi block theme + token system:",
        "",
    ]
    for tb in strong:
        md.append(f"### {tb['wp_anchor']} ↔ {tb['m3_anchor']}")
        md.append("")
        md.append(f"**Confidence**: {tb['confidence']} | **P4 agreement**: {tb.get('p4_agreement', '—')}")
        md.append("")
        md.append(tb["translation_rule"])
        md.append("")
        if "axismundi_implementation" in tb:
            md.append(f"**Axismundi implementation**: {tb['axismundi_implementation']}")
            md.append("")

    md += [
        "## Tier 2 — Component bindings",
        "",
        f"From `block_component_binding_rules.json`: {len(component_bindings)} in-scope bindings.",
        "",
        "| binding_type | count | confidence |",
        "|---|---|---|",
    ]
    type_counts = Counter(cb["binding_type"] for cb in component_bindings)
    type_confidence = {t: next((cb["confidence"] for cb in component_bindings if cb["binding_type"] == t), 0) for t in type_counts}
    for t, c in type_counts.most_common():
        md.append(f"| {t} | {c} | {type_confidence[t]} |")
    md.append("")

    # Out-of-scope summary
    out_of_scope = binding_map["summary"]["out_of_scope"]
    runtime_only = binding_map["summary"]["runtime_only"]
    md += [
        "## Out-of-scope / Runtime-only",
        "",
        f"- **OutOfScope.Handoff** (form plugins, dropped components): {out_of_scope} rules",
        f"- **RuntimeOnly.ThemeJS** (snackbar, tooltip, ripple): {runtime_only} rules",
        "",
        "These are explicitly NOT in the WP↔M3 ontology binding. They are either:",
        "- Handed off to other plugins (form fields → CF7/WPForms/Gravity)",
        "- Pure JS runtime primitives (no block, no token equivalence)",
        "",
        "## Next iterations (v2.1a-P1+)",
        "",
        "- **P1**: M3-COMPONENT-SPECS Tier 1 (Button, Card, List, Divider) → component ontology nodes with structure breakdown",
        "- **P2**: base.css semantic policy extraction (§3 heading mapping, §6.5 type utilities, §11 code)",
        "- **P3**: BLOCK-COMPONENT-MAP bucket B (Block Pattern) — only 1 entry currently, expand with composition rules",
        "- **P4**: Axismundi block theme self-validation — does the actual theme implement these bindings?",
    ]

    OUT_SUMMARY.write_text("\n".join(md))
    print(f"Wrote {OUT_SUMMARY}")

    print()
    print("=== Binding Summary ===")
    for k, v in binding_map["summary"].items():
        print(f"  {k}: {v}")


if __name__ == "__main__":
    main()
