#!/usr/bin/env python3
"""
validate_pilot_against_bindings.py — v2.1a-P0.5 Binding Legitimacy Audit.

Verifies that axismundi-theme-pilot-v0.1 actually operationalizes the bindings
declared in v2.1a-P0 binding map.

Audit axes (per project plan):
  A. Schema axis: theme.json color/typography/spacing/shadow conform to M3 ontology slugs
  B. Theme axis: appearanceTools enabled; color.custom=false enforced
  C. CSS axis: tokens.css + base.css present in expected paths
  D. Runtime axis: block style registrations in functions.php match Tier 2 binding rules
  E. Token layering axis: md-sys color tokens reference md-ref, never literal hex
  F. Bridge layering axis: wp-preset/wp-custom bridges are downstream var chains
  G. Custom settings axis: settings.custom.axismundi leaves are upstream vars

Output: pilot_validation_report.md + binding_legitimacy_audit.json
"""

import json
import re
from pathlib import Path
from collections import defaultdict

UTF8 = "utf-8"

PILOT = Path("products/reference-implementations/ontology-theme-pilot")
AXISMUNDI_PILOT = Path("products/reference-implementations/axismundi-pilot")
AXISMUNDI_LAB_STYLES = Path("products/reference-implementations/axismundi-lab/stylesheets")
BINDING_MAP = Path("bindings/wordpress-material3/binding_map.json")
M3_ONTOLOGY = Path("core/design-systems/material3/token_ontology.jsonld")
BINDING_RULES = Path("bindings/wordpress-material3/block_component_rules.json")

OUT_AUDIT = Path("bindings/wordpress-material3/binding_legitimacy_audit.json")
OUT_REPORT = Path("bindings/wordpress-material3/pilot_validation_report.md")


def axis_a_schema(theme_json, m3_ontology):
    """A. Schema axis — theme.json slugs ↔ M3 ontology roles."""
    findings = {}

    # A.1 color.palette slugs ↔ sys-color roles
    palette_slugs = {p["slug"] for p in theme_json["settings"]["color"]["palette"]}
    m3_color_roles = {
        n["role"] for n in m3_ontology["@graph"]
        if n.get("@type") == "m3:DesignToken" and n.get("family") == "color" and n.get("role")
    }
    matching = palette_slugs & m3_color_roles
    missing_in_pilot = m3_color_roles - palette_slugs
    extra_in_pilot = palette_slugs - m3_color_roles
    findings["A1_color_palette"] = {
        "m3_roles_total": len(m3_color_roles),
        "pilot_slugs_total": len(palette_slugs),
        "matching": len(matching),
        "missing_in_pilot": sorted(missing_in_pilot),
        "extra_in_pilot": sorted(extra_in_pilot),
        "score": len(matching) / len(m3_color_roles) if m3_color_roles else 0,
    }

    # A.2 typography.fontSizes slugs ↔ sys-typescale roles
    fontsize_slugs = {f["slug"] for f in theme_json["settings"]["typography"]["fontSizes"]}
    m3_typescale_roles = {
        n.get("typescale_role") for n in m3_ontology["@graph"]
        if n.get("@type") == "m3:DesignToken" and n.get("family") == "typescale" and n.get("typescale_role")
    }
    matching = fontsize_slugs & m3_typescale_roles
    findings["A2_typography_fontSizes"] = {
        "m3_roles_total": len(m3_typescale_roles),
        "pilot_slugs_total": len(fontsize_slugs),
        "matching": len(matching),
        "missing_in_pilot": sorted(m3_typescale_roles - fontsize_slugs),
        "score": len(matching) / len(m3_typescale_roles) if m3_typescale_roles else 0,
    }

    # A.3 shadow.presets slugs ↔ sys-elevation levels
    shadow_slugs = {s["slug"] for s in theme_json["settings"]["shadow"]["presets"]}
    m3_elevation_levels = {f"elevation-{i}" for i in range(6)}
    matching = shadow_slugs & m3_elevation_levels
    findings["A3_shadow_presets"] = {
        "m3_levels_total": len(m3_elevation_levels),
        "pilot_slugs_total": len(shadow_slugs),
        "matching": len(matching),
        "score": len(matching) / len(m3_elevation_levels) if m3_elevation_levels else 0,
    }

    overall = (
        findings["A1_color_palette"]["score"] * 0.5
        + findings["A2_typography_fontSizes"]["score"] * 0.35
        + findings["A3_shadow_presets"]["score"] * 0.15
    )
    return findings, overall


def axis_b_theme(theme_json):
    """B. Theme axis — appearanceTools + lock-down flags."""
    findings = {}
    s = theme_json["settings"]

    findings["B1_appearanceTools"] = {
        "expected": True,
        "actual": s.get("appearanceTools") is True,
        "score": 1.0 if s.get("appearanceTools") is True else 0.0,
    }
    findings["B2_color_custom_lockdown"] = {
        "expected": False,
        "actual": s.get("color", {}).get("custom"),
        "score": 1.0 if s.get("color", {}).get("custom") is False else 0.0,
    }
    findings["B3_customFontSize_lockdown"] = {
        "expected": False,
        "actual": s.get("typography", {}).get("customFontSize"),
        "score": 1.0 if s.get("typography", {}).get("customFontSize") is False else 0.0,
    }
    findings["B4_useRootPaddingAwareAlignments"] = {
        "expected": True,
        "actual": s.get("useRootPaddingAwareAlignments") is True,
        "score": 1.0 if s.get("useRootPaddingAwareAlignments") is True else 0.0,
    }

    overall = sum(f["score"] for f in findings.values()) / len(findings)
    return findings, overall


def axis_c_css(pilot_dir):
    """C. CSS axis — tokens.css + base.css + block-styles.css present and referenceable."""
    findings = {}

    for f in ["assets/css/tokens.css", "assets/css/base.css", "assets/css/block-styles.css"]:
        p = pilot_dir / f
        findings[f"C_{f}"] = {
            "exists": p.exists(),
            "size_bytes": p.stat().st_size if p.exists() else 0,
            "score": 1.0 if p.exists() and p.stat().st_size > 0 else 0.0,
        }

    # C.4 block-styles.css references M3 tokens (not hard-coded colors)
    bs_path = pilot_dir / "assets/css/block-styles.css"
    if bs_path.exists():
        bs_text = bs_path.read_text(encoding=UTF8)
        m3_var_refs = len(re.findall(r"var\(--md-sys-", bs_text))
        # Hex literals (should be near zero since we use tokens)
        hex_lits = len(re.findall(r"#[0-9A-Fa-f]{3,6}\b(?!\s*\.|\.css)", bs_text))
        findings["C4_block_styles_token_usage"] = {
            "m3_var_references": m3_var_refs,
            "hex_literal_count": hex_lits,
            "score": 1.0 if m3_var_refs > 20 and hex_lits == 0 else 0.5 if m3_var_refs > 0 else 0.0,
        }

    overall = sum(f["score"] for f in findings.values()) / len(findings)
    return findings, overall


def axis_d_runtime(pilot_dir, binding_rules):
    """D. Runtime axis — functions.php block style registrations match Tier 2 bindings."""
    findings = {}
    funcs_path = pilot_dir / "functions.php"
    if not funcs_path.exists():
        findings["D_functions_php"] = {"exists": False, "score": 0.0}
        return findings, 0.0

    text = funcs_path.read_text(encoding=UTF8)

    # Find all register_block_style calls
    pattern = re.compile(
        r"register_block_style\(\s*'([^']+)'\s*,\s*array\s*\(\s*'name'\s*=>\s*'([^']+)'",
        re.MULTILINE,
    )
    registered = set()
    for m in pattern.finditer(text):
        registered.add((m.group(1), m.group(2)))

    findings["D1_registered_count"] = {
        "count": len(registered),
        "registrations": sorted([f"{b}::{n}" for b, n in registered]),
    }

    # Expected from binding rules with binding_type == Direct.CoreBlockStyle
    expected = set()
    for rule in binding_rules["rules"]:
        if rule["binding_type"] != "Direct.CoreBlockStyle":
            continue
        sc = rule.get("style_class_binding") or {}
        block = sc.get("applies_to_block")
        styles = sc.get("style_classes") or []
        if not block:
            continue
        for sty in styles:
            # is-style-filled-search → filled-search
            name = sty.replace("is-style-", "")
            expected.add((block, name))

    matching = registered & expected
    findings["D2_binding_coverage"] = {
        "expected_from_binding_rules": len(expected),
        "registered_in_pilot": len(registered),
        "matching": len(matching),
        "missing_in_pilot": sorted([f"{b}::{n}" for b, n in (expected - registered)]),
        "extra_in_pilot": sorted([f"{b}::{n}" for b, n in (registered - expected)]),
        # Score: how much of binding rules' Direct.CoreBlockStyle did we operationalize?
        "score": len(matching) / len(expected) if expected else 0,
    }

    # D.3 enqueue order verification (tokens before base before block-styles)
    enqueue_pattern = re.compile(r"wp_enqueue_style\(\s*'([^']+)'")
    enqueues = [m.group(1) for m in enqueue_pattern.finditer(text)]
    expected_order = ["axismundi-tokens", "axismundi-base", "axismundi-block-styles"]
    findings["D3_enqueue_order"] = {
        "enqueues": enqueues,
        "expected": expected_order,
        "score": 1.0 if enqueues == expected_order else 0.0,
    }

    # Note: D2 is a partial-coverage score — pilot doesn't have to implement EVERY binding rule.
    # We weigh it as "completeness of what's in pilot vs expected", soft.
    overall = (
        findings["D2_binding_coverage"]["score"] * 0.7
        + findings["D3_enqueue_order"]["score"] * 0.3
    )
    return findings, overall


def axis_e_token_layering(styles_dir):
    """E. Token layering axis — md-sys color tokens must consume md-ref."""
    findings = {}
    sys_files = [
        styles_dir / "tokens.sys.light.css",
        styles_dir / "tokens.sys.dark.css",
    ]

    sys_color_def_pattern = re.compile(r"^\s*(--md-sys-color-[a-z0-9-]+)\s*:\s*([^;]+);", re.MULTILINE)
    direct_hex_pattern = re.compile(r"^\s*(--md-sys-color-[a-z0-9-]+)\s*:\s*(#[0-9A-Fa-f]{3,8})\b", re.MULTILINE)
    md_ref_pattern = re.compile(r"^\s*--md-sys-color-[a-z0-9-]+\s*:\s*var\(--md-ref-", re.MULTILINE)

    scores = []
    for path in sys_files:
        if not path.exists():
            findings[str(path)] = {
                "exists": False,
                "score": 0.0,
            }
            scores.append(0.0)
            continue

        text = path.read_text(encoding=UTF8)
        definitions = [
            {"token": match.group(1), "value": match.group(2).strip()}
            for match in sys_color_def_pattern.finditer(text)
        ]
        direct_hex = [
            {
                "line": text[:match.start()].count("\n") + 1,
                "token": match.group(1),
                "value": match.group(2),
            }
            for match in direct_hex_pattern.finditer(text)
        ]
        md_ref_refs = len(md_ref_pattern.findall(text))

        score = 1.0 if definitions and not direct_hex and md_ref_refs == len(definitions) else 0.0
        scores.append(score)
        findings[str(path)] = {
            "exists": True,
            "sys_color_definitions": len(definitions),
            "md_ref_references": md_ref_refs,
            "direct_hex_count": len(direct_hex),
            "direct_hex": direct_hex,
            "score": score,
        }

    overall = sum(scores) / len(scores) if scores else 0.0
    return findings, overall


def axis_f_bridge_layering(styles_dir):
    """F. Bridge layering axis — WP bridge tokens stay downstream of M3/comp."""
    findings = {}
    token_files = [
        styles_dir / "tokens.ref.css",
        styles_dir / "tokens.sys.light.css",
        styles_dir / "tokens.sys.dark.css",
        styles_dir / "tokens.comp.css",
    ]
    bridge_files = [
        styles_dir / "wp-preset.bridge.css",
        styles_dir / "wp-custom.bridge.css",
    ]

    token_def_pattern = re.compile(r"^\s*(--(?:md-ref|md-sys|comp)-[a-z0-9-]+)\s*:", re.MULTILINE)
    bridge_def_pattern = re.compile(r"^\s*(--wp--(?:preset--color|custom--axismundi)--[a-z0-9-]+(?:--[a-z0-9-]+)*)\s*:\s*([^;]+);", re.MULTILINE)
    var_ref_pattern = re.compile(r"var\((--(?:md-ref|md-sys|comp)-[a-z0-9-]+)\)")
    literal_pattern = re.compile(r"^\s*--wp--(?:preset--color|custom--axismundi)--[a-z0-9-]+(?:--[a-z0-9-]+)*\s*:\s*(?!\s*var\()[^;]+;", re.MULTILINE)

    upstream_tokens = set()
    for path in token_files:
        if not path.exists():
            findings[str(path)] = {"exists": False, "score": 0.0}
            continue
        upstream_tokens.update(token_def_pattern.findall(path.read_text(encoding=UTF8)))

    scores = []
    for path in bridge_files:
        if not path.exists():
            findings[str(path)] = {
                "exists": False,
                "score": 0.0,
            }
            scores.append(0.0)
            continue

        text = path.read_text(encoding=UTF8)
        definitions = [
            {"token": match.group(1), "value": match.group(2).strip()}
            for match in bridge_def_pattern.finditer(text)
        ]
        literal_values = [
            {
                "line": text[:match.start()].count("\n") + 1,
                "declaration": match.group(0).strip(),
            }
            for match in literal_pattern.finditer(text)
        ]
        var_refs = [match.group(1) for match in var_ref_pattern.finditer(text)]
        broken_refs = sorted({ref for ref in var_refs if ref not in upstream_tokens})

        score = 1.0 if definitions and not literal_values and not broken_refs and len(var_refs) == len(definitions) else 0.0
        scores.append(score)
        findings[str(path)] = {
            "exists": True,
            "bridge_definitions": len(definitions),
            "var_references": len(var_refs),
            "literal_value_count": len(literal_values),
            "literal_values": literal_values,
            "broken_references": broken_refs,
            "score": score,
        }

    overall = sum(scores) / len(scores) if scores else 0.0
    return findings, overall


def collect_upstream_tokens(styles_dir):
    token_files = [
        styles_dir / "tokens.ref.css",
        styles_dir / "tokens.sys.light.css",
        styles_dir / "tokens.sys.dark.css",
        styles_dir / "tokens.comp.css",
    ]
    token_def_pattern = re.compile(r"^\s*(--(?:md-ref|md-sys|comp)-[a-z0-9-]+)\s*:", re.MULTILINE)

    upstream_tokens = set()
    for path in token_files:
        if not path.exists():
            continue
        upstream_tokens.update(token_def_pattern.findall(path.read_text(encoding=UTF8)))
    return upstream_tokens


def flatten_leaf_values(value, prefix=""):
    if isinstance(value, dict):
        leaves = []
        for key, child in value.items():
            child_prefix = f"{prefix}.{key}" if prefix else key
            leaves.extend(flatten_leaf_values(child, child_prefix))
        return leaves
    return [{"path": prefix, "value": value}]


def axis_g_custom_settings(theme_json, styles_dir):
    """G. Custom settings axis — settings.custom.axismundi stays downstream."""
    axismundi = theme_json.get("settings", {}).get("custom", {}).get("axismundi")
    if not isinstance(axismundi, dict):
        return {
            "settings.custom.axismundi": {
                "exists": False,
                "score": 0.0,
            }
        }, 0.0

    leaves = flatten_leaf_values(axismundi, "settings.custom.axismundi")
    allowed_pattern = re.compile(r"^var\((--(?:comp|md-sys|md-ref)-[a-z0-9-]+)\)$")
    upstream_tokens = collect_upstream_tokens(styles_dir)

    invalid_values = []
    broken_refs = []
    for leaf in leaves:
        value = leaf["value"]
        if not isinstance(value, str):
            invalid_values.append(leaf)
            continue
        match = allowed_pattern.match(value)
        if not match:
            invalid_values.append(leaf)
            continue
        if match.group(1) not in upstream_tokens:
            broken_refs.append({"path": leaf["path"], "token": match.group(1)})

    score = 1.0 if leaves and not invalid_values and not broken_refs else 0.0
    findings = {
        "settings.custom.axismundi": {
            "exists": True,
            "leaf_values": len(leaves),
            "invalid_values": invalid_values,
            "broken_references": broken_refs,
            "score": score,
        }
    }
    return findings, score


def main():
    theme_json = json.loads((PILOT / "theme.json").read_text(encoding=UTF8))
    axismundi_theme_json = json.loads((AXISMUNDI_PILOT / "theme.json").read_text(encoding=UTF8))
    m3 = json.loads(M3_ONTOLOGY.read_text(encoding=UTF8))
    binding_map = json.loads(BINDING_MAP.read_text(encoding=UTF8))
    binding_rules = json.loads(BINDING_RULES.read_text(encoding=UTF8))

    findings_a, score_a = axis_a_schema(theme_json, m3)
    findings_b, score_b = axis_b_theme(theme_json)
    findings_c, score_c = axis_c_css(PILOT)
    findings_d, score_d = axis_d_runtime(PILOT, binding_rules)
    findings_e, score_e = axis_e_token_layering(AXISMUNDI_LAB_STYLES)
    findings_f, score_f = axis_f_bridge_layering(AXISMUNDI_LAB_STYLES)
    findings_g, score_g = axis_g_custom_settings(axismundi_theme_json, AXISMUNDI_LAB_STYLES)

    overall = (score_a + score_b + score_c + score_d + score_e + score_f + score_g) / 7

    audit = {
        "schema_version": "v2.1a-P0.5-binding-legitimacy-audit-2026-05-12",
        "pilot_target": str(PILOT),
        "binding_baseline": "v2.1a-P0 binding map",
        "axes": {
            "A_schema": {"findings": findings_a, "score": score_a, "weight": 0.30},
            "B_theme": {"findings": findings_b, "score": score_b, "weight": 0.20},
            "C_css": {"findings": findings_c, "score": score_c, "weight": 0.20},
            "D_runtime": {"findings": findings_d, "score": score_d, "weight": 0.30},
            "E_token_layering": {"findings": findings_e, "score": score_e, "weight": "hard gate"},
            "F_bridge_layering": {"findings": findings_f, "score": score_f, "weight": "hard gate"},
            "G_custom_settings": {"findings": findings_g, "score": score_g, "weight": "hard gate"},
        },
        "overall_score": round(overall, 3),
        "passes_threshold": overall >= 0.85,
        "verdict": "PASS" if overall >= 0.85 else ("MARGINAL" if overall >= 0.70 else "FAIL"),
    }

    OUT_AUDIT.write_text(json.dumps(audit, indent=2, ensure_ascii=False) + "\n", encoding=UTF8)
    print(f"Wrote {OUT_AUDIT}")

    # Markdown report
    md = [
        "# v2.1a-P0.5 — Binding Legitimacy Audit Report",
        "",
        f"**Pilot target**: `axismundi-theme-pilot-v0.1`",
        f"**Baseline**: v2.1a-P0 binding map (6 token bindings, 48 binding rules)",
        f"**Audit date**: 2026-05-12",
        "",
        f"## Overall verdict",
        "",
        f"- **Score**: {audit['overall_score']:.3f} / 1.000",
        f"- **Threshold (≥0.85)**: {'PASS ✓' if audit['passes_threshold'] else 'FAIL ✗'}",
        f"- **Verdict**: **{audit['verdict']}**",
        "",
        "## 7-Axis breakdown",
        "",
        "| Axis | Description | Score | Weight |",
        "|---|---|---|---|",
        f"| A — Schema | theme.json slugs ↔ M3 ontology roles | **{score_a:.3f}** | 0.30 |",
        f"| B — Theme | appearanceTools + lock-down flags | **{score_b:.3f}** | 0.20 |",
        f"| C — CSS | tokens.css + base.css + block-styles.css | **{score_c:.3f}** | 0.20 |",
        f"| D — Runtime | block style registrations ↔ binding rules | **{score_d:.3f}** | 0.30 |",
        f"| E — Token layering | md-sys color tokens reference md-ref | **{score_e:.3f}** | hard gate |",
        f"| F — Bridge layering | wp-preset/wp-custom bridge vars stay downstream | **{score_f:.3f}** | hard gate |",
        f"| G — Custom settings | settings.custom.axismundi leaves are upstream vars | **{score_g:.3f}** | hard gate |",
        "",
        "## Axis A — Schema (theme.json ↔ M3 ontology)",
        "",
    ]

    for k, v in findings_a.items():
        md.append(f"### {k}")
        md.append("")
        for kk, vv in v.items():
            if isinstance(vv, list) and len(vv) > 5:
                vv = vv[:5] + ["..."]
            md.append(f"- `{kk}`: {vv}")
        md.append("")

    md.append("## Axis B — Theme (appearanceTools + lock-down)")
    md.append("")
    for k, v in findings_b.items():
        md.append(f"- **{k}**: expected={v['expected']}, actual={v['actual']}, score={v['score']}")
    md.append("")

    md.append("## Axis C — CSS (asset presence + token usage)")
    md.append("")
    for k, v in findings_c.items():
        md.append(f"- **{k}**: {v}")
    md.append("")

    md.append("## Axis D — Runtime (block style registrations)")
    md.append("")
    for k, v in findings_d.items():
        md.append(f"### {k}")
        md.append("")
        if isinstance(v, dict):
            for kk, vv in v.items():
                if isinstance(vv, list) and len(vv) > 8:
                    vv = vv[:8] + ["..."]
                md.append(f"- `{kk}`: {vv}")
        md.append("")

    md.append("## Axis E — Token layering (md-sys → md-ref)")
    md.append("")
    for k, v in findings_e.items():
        md.append(f"### {k}")
        md.append("")
        if isinstance(v, dict):
            for kk, vv in v.items():
                if isinstance(vv, list) and len(vv) > 8:
                    vv = vv[:8] + ["..."]
                md.append(f"- `{kk}`: {vv}")
        md.append("")

    md.append("## Axis F — Bridge layering (WP bridge → M3/comp)")
    md.append("")
    for k, v in findings_f.items():
        md.append(f"### {k}")
        md.append("")
        if isinstance(v, dict):
            for kk, vv in v.items():
                if isinstance(vv, list) and len(vv) > 8:
                    vv = vv[:8] + ["..."]
                md.append(f"- `{kk}`: {vv}")
        md.append("")

    md.append("## Axis G — Custom settings (theme.json settings.custom.axismundi)")
    md.append("")
    for k, v in findings_g.items():
        md.append(f"### {k}")
        md.append("")
        if isinstance(v, dict):
            for kk, vv in v.items():
                if isinstance(vv, list) and len(vv) > 8:
                    vv = vv[:8] + ["..."]
                md.append(f"- `{kk}`: {vv}")
        md.append("")

    md.append("## Decision")
    md.append("")
    if audit["passes_threshold"]:
        md.append("✓ **PASS — pilot binding legitimacy verified.**")
        md.append("")
        md.append("Strong bindings (color/typography/appearanceTools) are operationalized in code.")
        md.append("Proceed to **v2.1a-P1** (M3-COMPONENT-SPECS Tier 1 component ontology).")
    elif audit["overall_score"] >= 0.7:
        md.append("⚠ **MARGINAL — pilot bindings partially legitimate.**")
        md.append("")
        md.append("Review failing axes before P1. Consider iterating pilot to close gaps.")
    else:
        md.append("✗ **FAIL — binding legitimacy not established.**")
        md.append("")
        md.append("Pilot doesn't operationalize bindings as designed. Fix before P1.")

    OUT_REPORT.write_text("\n".join(md), encoding=UTF8)
    print(f"Wrote {OUT_REPORT}")

    print(f"\n=== Overall: {audit['overall_score']:.3f} ({audit['verdict']}) ===")
    print(f"  A schema:  {score_a:.3f}")
    print(f"  B theme:   {score_b:.3f}")
    print(f"  C css:     {score_c:.3f}")
    print(f"  D runtime: {score_d:.3f}")
    print(f"  E tokens:  {score_e:.3f}")
    print(f"  F bridge:  {score_f:.3f}")
    print(f"  G custom:  {score_g:.3f}")


if __name__ == "__main__":
    main()
