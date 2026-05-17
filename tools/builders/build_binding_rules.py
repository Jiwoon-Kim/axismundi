#!/usr/bin/env python3
"""
build_block_component_binding_ontology.py — Convert Axismundi BLOCK-COMPONENT-MAP.md
from intuitive lookup table to rule-based binding ontology.

Per GPT's analysis: existing 6-bucket (A/B/C/D/E/F) is already ontology-aware,
NOT pure intuition. We formalize the 6-bucket into BindingType enum and add
supports profile conditions where applicable.

6-bucket → ontology classification:
  A — Direct (core block + Block Style class)
  B — Compositional (Block Pattern)
  C — Direct (custom block, plugin)
  D — Composite (template-part level)
  E — OutOfScope (handoff to other plugins / discard)
  F — RuntimeOnly (pure JS, no ontology entity)

Inputs:
  bindings/wordpress-material3/_source/BLOCK-COMPONENT-MAP.md
  core/wordpress/ontology.jsonld

Outputs:
  bindings/wordpress-material3/block_component_binding_rules.json
  bindings/wordpress-material3/binding_taxonomy.md
"""

import json
import re
from pathlib import Path

MAP_FILE = Path("bindings/wordpress-material3/_source/BLOCK-COMPONENT-MAP.md")
WP_ONTOLOGY = Path("core/wordpress/ontology_core_draft_v0_2.jsonld")
OUT_RULES = Path("bindings/wordpress-material3/block_component_binding_rules.json")
OUT_TAXONOMY = Path("bindings/wordpress-material3/binding_taxonomy.md")


BUCKET_TAXONOMY = {
    "A": {
        "binding_type": "Direct.CoreBlockStyle",
        "description": "Core Gutenberg block exists with semantic match; M3 variant realized as registered Block Style class.",
        "ontology_strength": "strong",
        "implementation_path": "block_style_registration",
    },
    "B": {
        "binding_type": "Compositional.BlockPattern",
        "description": "M3 component realized as composition of multiple core blocks (e.g. List item with leading + body + trailing).",
        "ontology_strength": "conditional",
        "implementation_path": "block_pattern_authoring",
        "requires_supports_profile": True,
    },
    "C": {
        "binding_type": "Direct.CustomBlock",
        "description": "No reasonable core equivalent; ship as custom block (m3/* or axismundi/* plugin).",
        "ontology_strength": "strong",
        "implementation_path": "custom_block_plugin",
    },
    "D": {
        "binding_type": "Composite.TemplatePart",
        "description": "Site-chrome element; lives at FSE template-part level, not content flow.",
        "ontology_strength": "strong",
        "implementation_path": "fse_template_part",
    },
    "E": {
        "binding_type": "OutOfScope.Handoff",
        "description": "Doesn't fit WP authoring paradigm; either handed off to a form/3rd-party plugin or dropped for v1.",
        "ontology_strength": "out_of_scope",
        "implementation_path": "external_plugin_styling_only",
    },
    "F": {
        "binding_type": "RuntimeOnly.ThemeJS",
        "description": "Pure interaction primitive; no block representation, runtime-only.",
        "ontology_strength": "runtime_only",
        "implementation_path": "theme_js_api",
    },
}


def load_wp_ontology_blocksupports():
    """Get the BlockSupport canonical names from WP ontology v0.2."""
    wp = json.loads(WP_ONTOLOGY.read_text())
    supports = set()
    for n in wp["@graph"]:
        if n.get("@type") == "wp:BlockSupportInstance":
            supports.add(n.get("label"))
    return supports


def parse_mapping_tables(text):
    """Extract M3-component → block-mapping rows from §1 tables (1.1 ~ 1.7).
       Plus §2 (FSE template-part level) and §3 (gaps)."""
    rows = []
    in_section_1 = False
    in_section_2 = False
    current_subsection = None
    for line in text.split("\n"):
        # Section detection
        if re.match(r"^## 1\.", line):
            in_section_1 = True
            in_section_2 = False
            continue
        elif re.match(r"^## 2\.", line):
            in_section_1 = False
            in_section_2 = True
            current_subsection = "fse-template-parts"
            continue
        elif re.match(r"^## [3-9]", line):
            in_section_1 = False
            in_section_2 = False

        # Subsection detection inside §1
        if in_section_1:
            m = re.match(r"^### 1\.\d+ (.+)", line)
            if m:
                current_subsection = m.group(1).strip()
                continue

        if not (in_section_1 or in_section_2):
            continue

        # Table row parsing: | M3 Component | Bucket | Mapping |
        if line.startswith("|") and "|" in line[1:]:
            # Skip header row + separator row
            if "---" in line or "M3 Component" in line or line.count("|") < 3:
                continue
            cols = [c.strip() for c in line.split("|")[1:-1]]
            if len(cols) >= 3:
                m3_component, bucket, mapping = cols[0], cols[1], cols[2]
                # extract bucket letter (e.g. "A" or "C (deferred)")
                bucket_letter = bucket.strip().split()[0].strip("`")
                if bucket_letter in BUCKET_TAXONOMY:
                    rows.append({
                        "m3_component": m3_component,
                        "bucket": bucket_letter,
                        "bucket_raw": bucket,
                        "mapping": mapping,
                        "subsection": current_subsection,
                        "deferred": "deferred" in bucket.lower(),
                    })
    return rows


def extract_block_refs(mapping_text):
    """Extract block references from mapping text, e.g. `core/button` → ['core/button']."""
    return re.findall(r"`([a-z][a-z0-9-]*\/[a-z][a-z0-9-]*)`", mapping_text)


def extract_style_classes(mapping_text):
    """Extract is-style-* class names."""
    return re.findall(r"`(is-style-[a-z][a-z0-9-]*)`", mapping_text)


def extract_supports_profile(mapping_text):
    """Heuristically extract supports required by mapping (looking for keywords)."""
    keywords = []
    if "elevation" in mapping_text.lower() or "shadow" in mapping_text.lower():
        keywords.append("supports.shadow")
    if "padding" in mapping_text.lower() or "spacing" in mapping_text.lower():
        keywords.append("supports.spacing")
    if "background" in mapping_text.lower():
        keywords.append("supports.background")
    if "border" in mapping_text.lower():
        keywords.append("supports.border")
    if "color" in mapping_text.lower():
        keywords.append("supports.color")
    if "typography" in mapping_text.lower() or "font" in mapping_text.lower():
        keywords.append("supports.typography")
    return keywords


def build_binding_rule(row, idx):
    """Build a single binding rule from a mapping row."""
    bucket = row["bucket"]
    taxonomy = BUCKET_TAXONOMY[bucket]

    block_refs = extract_block_refs(row["mapping"])
    style_classes = extract_style_classes(row["mapping"])

    rule_id = f"binding.{slug(row['m3_component'])}"

    rule = {
        "@id": f"ax:Binding.{slug(row['m3_component'])}",
        "@type": "ax:BlockComponentBinding",
        "rule_id": rule_id,
        "m3_component": row["m3_component"],
        "binding_type": taxonomy["binding_type"],
        "ontology_strength": taxonomy["ontology_strength"],
        "implementation_path": taxonomy["implementation_path"],
        "bucket_legacy": bucket,
        "subsection": row["subsection"],
        "deferred": row["deferred"],
        "block_refs": block_refs,
        "style_classes": style_classes,
        "raw_mapping": row["mapping"],
    }

    # For bucket B (Compositional), add supports_profile_required
    if bucket == "B":
        supports = extract_supports_profile(row["mapping"])
        rule["composition_requires_supports"] = supports
        rule["evaluation_rule"] = (
            f"WHEN block participates in pattern AND its BlockType.supports profile "
            f"includes {supports} THEN this binding is applicable."
        )

    # For bucket A with Block Style, add style class binding spec
    if bucket == "A" and style_classes:
        rule["style_class_binding"] = {
            "applies_to_block": block_refs[0] if block_refs else None,
            "style_classes": style_classes,
            "registration_method": "register_block_style()",
        }

    # For bucket C (custom block), add plugin spec
    if bucket == "C":
        # Extract custom block names: m3/* or axismundi/*
        custom_blocks = [b for b in block_refs if b.startswith(("m3/", "axismundi/"))]
        rule["custom_block_spec"] = {
            "block_names": custom_blocks,
            "plugin": "m3-blocks" if any(b.startswith("m3/") for b in custom_blocks) else "axismundi-blocks",
        }

    # For bucket D (template part), add template-part spec
    if bucket == "D":
        rule["template_part_spec"] = {
            "template_parts": [p for p in re.findall(r"`(parts\/[a-z0-9-]+\.html)`", row["mapping"])],
            "level": "fse_chrome",
        }

    # For bucket E, mark out-of-scope reason
    if bucket == "E":
        rule["out_of_scope_reason"] = "form_plugin_handoff" if "Form" in row["mapping"] or "form" in row["mapping"] else "deferred_for_v1"

    return rule


def slug(s):
    """Convert M3 component name to a stable slug."""
    s = s.lower()
    s = re.sub(r"[—–]+", "-", s)
    s = re.sub(r"[^\w\s-]", "", s)
    s = re.sub(r"\s+", "-", s).strip("-")
    return s


def write_taxonomy_md(rules):
    """Human-readable binding taxonomy + 4-class breakdown."""
    from collections import Counter
    by_type = Counter(r["binding_type"] for r in rules)
    by_bucket = Counter(r["bucket_legacy"] for r in rules)
    deferred = sum(1 for r in rules if r.get("deferred"))

    lines = [
        "# Block ↔ Component Binding Taxonomy",
        "",
        "v2.1a normalization of `BLOCK-COMPONENT-MAP.md` from intuitive lookup table to ",
        "rule-based binding ontology. Each M3 component binding is classified by:",
        "",
        "- **binding_type** — ontology category (Direct / Compositional / Composite / OutOfScope / RuntimeOnly)",
        "- **ontology_strength** — strong / conditional / out_of_scope / runtime_only",
        "- **implementation_path** — concrete realization mechanism",
        "",
        "## Distribution by binding_type",
        "",
        "| binding_type | count |",
        "|---|---|",
    ]
    for t, c in by_type.most_common():
        lines.append(f"| {t} | {c} |")
    lines.append("")

    lines += [
        "## Distribution by legacy bucket",
        "",
        "| bucket | count | meaning |",
        "|---|---|---|",
    ]
    for b, c in by_bucket.most_common():
        lines.append(f"| {b} | {c} | {BUCKET_TAXONOMY[b]['binding_type']} |")
    lines.append("")
    lines.append(f"**Total rules**: {len(rules)} | **Deferred** (Tier 3+): {deferred}")
    lines.append("")

    lines += [
        "## Rule samples by type",
        "",
    ]

    # One sample per type
    seen_types = set()
    for r in rules:
        bt = r["binding_type"]
        if bt in seen_types:
            continue
        seen_types.add(bt)
        lines.append(f"### {r['m3_component']} ({bt})")
        lines.append("")
        lines.append(f"- **rule_id**: `{r['rule_id']}`")
        lines.append(f"- **bucket**: {r['bucket_legacy']}")
        lines.append(f"- **ontology_strength**: {r['ontology_strength']}")
        lines.append(f"- **implementation_path**: {r['implementation_path']}")
        if r["block_refs"]:
            lines.append(f"- **block_refs**: {', '.join('`'+b+'`' for b in r['block_refs'])}")
        if r["style_classes"]:
            lines.append(f"- **style_classes**: {', '.join('`'+c+'`' for c in r['style_classes'])}")
        if r.get("composition_requires_supports"):
            lines.append(f"- **requires_supports**: {', '.join(r['composition_requires_supports'])}")
        if r.get("evaluation_rule"):
            lines.append(f"- **evaluation_rule**: {r['evaluation_rule']}")
        lines.append("")

    return "\n".join(lines)


def main():
    text = MAP_FILE.read_text()
    rows = parse_mapping_tables(text)
    print(f"Parsed {len(rows)} mapping rows from §1 + §2")

    # Build binding rules
    rules = [build_binding_rule(r, i) for i, r in enumerate(rows)]

    wp_supports = load_wp_ontology_blocksupports()
    print(f"Loaded {len(wp_supports)} BlockSupports from WP ontology v0.2")

    # Validate: composition rules reference supports that exist in WP ontology
    unknown_supports = set()
    for r in rules:
        for sup in r.get("composition_requires_supports", []):
            sup_name = sup.replace("supports.", "")
            if sup_name not in wp_supports:
                unknown_supports.add(sup_name)
    if unknown_supports:
        print(f"  Note: composition rules reference these BlockSupports not in WP ontology: {unknown_supports}")

    # Write rules JSON
    output = {
        "schema_version": "v2.1a-block-component-binding-2026-05-12",
        "generated_at": "2026-05-12",
        "source": str(MAP_FILE),
        "wp_ontology_baseline": "v0.2",
        "bucket_taxonomy": BUCKET_TAXONOMY,
        "summary": {
            "total_rules": len(rules),
            "by_binding_type": {},
            "by_bucket": {},
            "deferred_count": sum(1 for r in rules if r.get("deferred")),
        },
        "rules": rules,
    }
    from collections import Counter
    bt = Counter(r["binding_type"] for r in rules)
    bk = Counter(r["bucket_legacy"] for r in rules)
    output["summary"]["by_binding_type"] = dict(bt.most_common())
    output["summary"]["by_bucket"] = dict(bk.most_common())

    OUT_RULES.parent.mkdir(parents=True, exist_ok=True)
    OUT_RULES.write_text(json.dumps(output, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT_RULES}")

    OUT_TAXONOMY.write_text(write_taxonomy_md(rules))
    print(f"Wrote {OUT_TAXONOMY}")

    print()
    print("=== Distribution ===")
    print(f"Total rules: {len(rules)}")
    print(f"By binding_type:")
    for t, c in bt.most_common():
        print(f"  {t}: {c}")
    print(f"By bucket:")
    for b, c in bk.most_common():
        print(f"  {b} ({BUCKET_TAXONOMY[b]['binding_type'][:30]:30s}): {c}")


if __name__ == "__main__":
    main()
