#!/usr/bin/env python3
"""
v2_0_supports_pilot.py — 3-way validation for the supports anchor.

Sources:
  1. corpus:  dev_handbook_clean/.../supports.md
  2. docs:    GB-23.1.1/docs/reference-guides/block-api/block-supports.md
  3. schema:  GB-23.1.1/schemas/json/block.json (properties.supports)

Outputs:
  core/wordpress/pilot_supports.json — 3-way comparison report
  ontology slot candidates with provenance per slot
"""

import json
import re
from pathlib import Path
from collections import OrderedDict


CORPUS = Path("dev_handbook_clean/block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md")
DOCS = Path("${GUTENBERG_ROOT}/docs/reference-guides/block-api/block-supports.md")
SCHEMA = Path("${GUTENBERG_ROOT}/schemas/json/block.json")
OUT = Path("core/wordpress/pilot_supports.json")


def extract_h2(md_text):
    """Extract H2 headings (## name) as ordered dict."""
    out = OrderedDict()
    for line in md_text.split("\n"):
        m = re.match(r"^## \[?([a-zA-Z_][a-zA-Z0-9_]*)\]?", line)
        if m:
            out[m.group(1)] = True
    return out


def extract_subprops_from_md(md_text, anchor):
    """For a given H2 anchor, find subproperties listed under it
       (looking for `- Subproperties:` or similar markers)."""
    lines = md_text.split("\n")
    in_section = False
    subprops = []
    for i, line in enumerate(lines):
        m = re.match(r"^## \[?(\w+)\]?", line)
        if m:
            if m.group(1) == anchor:
                in_section = True
                continue
            elif in_section:
                break  # next H2, stop
        if not in_section:
            continue
        # Look for sub-bullets like "  - `name`: type ..."
        m_sub = re.match(r"^\s+-\s+`(\w+)`:?\s*(.*)$", line)
        if m_sub:
            subprops.append({"name": m_sub.group(1), "desc": m_sub.group(2)[:80]})
    return subprops


def extract_schema_supports():
    """Extract supports.properties from block.json schema."""
    schema = json.loads(SCHEMA.read_text())
    sup = schema["properties"]["supports"]
    props = sup.get("properties", {})
    out = {}
    for name, defn in props.items():
        t = defn.get("type")
        if isinstance(t, list):
            t = "/".join(t)
        out[name] = {
            "type": t,
            "default": defn.get("default"),
            "description": defn.get("description", "")[:120],
            "subprops": list(defn.get("properties", {}).keys()) if isinstance(defn.get("properties"), dict) else [],
        }
    return out


def main():
    corpus_text = CORPUS.read_text(encoding="utf-8")
    docs_text = DOCS.read_text(encoding="utf-8")

    corpus_h2 = extract_h2(corpus_text)
    docs_h2 = extract_h2(docs_text)
    schema_sup = extract_schema_supports()

    # Normalize: corpus uses 'autoRegister', docs uses 'auto_register'
    # Build canonical set
    def canonicalize(name):
        # Map snake_case to camelCase for cross-source comparison
        if "_" in name:
            parts = name.split("_")
            return parts[0] + "".join(p.capitalize() for p in parts[1:])
        return name

    corpus_c = {canonicalize(n): n for n in corpus_h2}
    docs_c = {canonicalize(n): n for n in docs_h2}
    schema_c = {canonicalize(n): n for n in schema_sup}

    all_canonical = sorted(set(corpus_c) | set(docs_c) | set(schema_c))

    # 3-way comparison
    slots = []
    for canon in all_canonical:
        in_corpus = canon in corpus_c
        in_docs = canon in docs_c
        in_schema = canon in schema_c
        provenance = []
        if in_corpus: provenance.append("corpus")
        if in_docs: provenance.append("docs")
        if in_schema: provenance.append("schema")

        slot = {
            "canonical_name": canon,
            "corpus_name": corpus_c.get(canon),
            "docs_name": docs_c.get(canon),
            "schema_name": schema_c.get(canon),
            "provenance": provenance,
            "in_n_sources": len(provenance),
        }
        if in_schema:
            slot["schema_type"] = schema_sup[schema_c[canon]]["type"]
            slot["schema_default"] = schema_sup[schema_c[canon]]["default"]
            slot["schema_subprops"] = schema_sup[schema_c[canon]]["subprops"]
        slots.append(slot)

    # Ontology slot candidates per GPT's list:
    # BlockSupport, SupportProperty, AttributeInjection, DefaultValue,
    # StylePath, UIControlExposure, SchemaConstraint
    ontology_slots = {
        "BlockSupport": {
            "description": "A named feature flag a block can declare to opt into editor UI / attribute injection.",
            "instances": [s["canonical_name"] for s in slots if "schema" in s["provenance"]],
            "instance_count": sum(1 for s in slots if "schema" in s["provenance"]),
            "evidence_tier": 2,  # schema-grounded
        },
        "SupportProperty": {
            "description": "Sub-property of a BlockSupport (e.g. color.background, dimensions.minHeight).",
            "instances": [],  # populated below
        },
        "AttributeInjection": {
            "description": "Block attribute(s) auto-added when a BlockSupport is declared (e.g. color → backgroundColor + style).",
            "instances": [],  # extracted from prose; pilot-only stub
        },
        "DefaultValue": {
            "description": "Schema-declared default for a BlockSupport (false/true/null).",
            "instances": [],
        },
        "StylePath": {
            "description": "Path within style attribute where user-set values are stored (e.g. style.color.background).",
            "instances": [],
        },
        "UIControlExposure": {
            "description": "Mapping from BlockSupport opt-in to specific editor UI control visibility.",
            "instances": [],
        },
        "SchemaConstraint": {
            "description": "JSON-schema-level constraint on a BlockSupport (type/enum/oneOf).",
            "instances": [],
        },
    }

    # Populate SupportProperty + DefaultValue from schema
    sp_instances = []
    dv_instances = []
    for canon in sorted(schema_c):
        sup_name = schema_c[canon]
        s = schema_sup[sup_name]
        for sub in s["subprops"]:
            sp_instances.append(f"{canon}.{sub}")
        if s["default"] is not None:
            dv_instances.append({"support": canon, "default": s["default"]})
    ontology_slots["SupportProperty"]["instances"] = sp_instances
    ontology_slots["SupportProperty"]["instance_count"] = len(sp_instances)
    ontology_slots["DefaultValue"]["instances"] = dv_instances
    ontology_slots["DefaultValue"]["instance_count"] = len(dv_instances)

    # Cross-source agreement summary
    agreement = {
        "in_all_3": sum(1 for s in slots if s["in_n_sources"] == 3),
        "in_2_sources": sum(1 for s in slots if s["in_n_sources"] == 2),
        "in_1_source_only": sum(1 for s in slots if s["in_n_sources"] == 1),
        "corpus_only": [s["canonical_name"] for s in slots if s["provenance"] == ["corpus"]],
        "docs_only": [s["canonical_name"] for s in slots if s["provenance"] == ["docs"]],
        "schema_only": [s["canonical_name"] for s in slots if s["provenance"] == ["schema"]],
    }

    report = {
        "schema_version": "v2.0-pilot-supports-2026-05-12",
        "generated_at": "2026-05-12",
        "anchor": "block-editor-handbook/.../supports.md",
        "sources": {
            "corpus": str(CORPUS),
            "docs": str(DOCS),
            "schema": str(SCHEMA),
        },
        "baseline": {
            "wordpress": "6.9.4 @ 97b7f62a",
            "gutenberg": "v23.1.1 @ 12c6c76e",
        },
        "agreement": agreement,
        "ontology_slots": ontology_slots,
        "slots": slots,
    }

    OUT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {OUT}\n")
    print("=== 3-way agreement ===")
    for k, v in agreement.items():
        if isinstance(v, int):
            print(f"  {k}: {v}")
        elif v:
            print(f"  {k}: {v}")
    print()
    print("=== Ontology slot candidates (instance counts) ===")
    for slot_name, slot_def in ontology_slots.items():
        cnt = slot_def.get("instance_count", len(slot_def.get("instances", [])))
        print(f"  {slot_name:20s} : {cnt} instances")


if __name__ == "__main__":
    main()
