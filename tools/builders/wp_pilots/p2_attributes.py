#!/usr/bin/env python3
"""
v2_0_attributes_pilot.py — 4-way validation for the attributes anchor.

Sources:
  1. corpus:    dev_handbook_clean/.../attributes.md
  2. docs:      GB-23.1.1/docs/reference-guides/block-api/block-attributes.md
  3. schema:    GB-23.1.1/schemas/json/block.json#/properties/attributes
  4. instances: GB-23.1.1/packages/block-library/src/*/block.json (121 files)

Outputs:
  core/wordpress/pilot_attributes.json — 4-way comparison + ontology slot extraction
"""

import json
import re
from pathlib import Path
from collections import Counter, OrderedDict


CORPUS = Path("dev_handbook_clean/block-editor-handbook/03-reference-guides/01-block-api-reference/attributes.md")
DOCS = Path("${GUTENBERG_ROOT}/docs/reference-guides/block-api/block-attributes.md")
SCHEMA = Path("${GUTENBERG_ROOT}/schemas/json/block.json")
INSTANCES_GLOB = Path("${GUTENBERG_ROOT}/packages/block-library/src")
OUT = Path("core/wordpress/pilot_attributes.json")


def extract_h2(text):
    """Extract H2 headings."""
    return [m.group(1) for m in re.finditer(r"^## ([^\n]+)$", text, re.MULTILINE)]


def extract_schema_attribute_def():
    """Extract the BlockAttribute schema (patternProperties[a-zA-Z])."""
    schema = json.loads(SCHEMA.read_text())
    attr_def = schema["properties"]["attributes"]["patternProperties"]["[a-zA-Z]"]
    props = attr_def["properties"]

    out = {
        "keys": sorted(props.keys()),
        "type_enum": [],
        "source_enum": [],
        "key_details": {},
    }

    # type enum
    type_def = props.get("type", {})
    if "oneOf" in type_def:
        for variant in type_def["oneOf"]:
            if "enum" in variant:
                out["type_enum"].extend(variant["enum"])
            elif variant.get("type") == "array" and "items" in variant:
                items_enum = variant["items"].get("enum", [])
                for v in items_enum:
                    if v not in out["type_enum"]:
                        pass  # array variant subset

    # source enum
    source_def = props.get("source", {})
    if "enum" in source_def:
        out["source_enum"] = list(source_def["enum"])

    # role enum (if exists)
    role_def = props.get("role", {})
    if "enum" in role_def:
        out["role_enum"] = list(role_def["enum"])

    # Description for each key
    for k, v in props.items():
        out["key_details"][k] = {
            "type": v.get("type", "?"),
            "description_first_line": (v.get("description", "") or "").split("\n")[0][:120],
        }

    # query nested shape
    query_def = props.get("query", {})
    if "patternProperties" in query_def:
        out["query_nested_shape"] = "patternProperties — same shape as parent attribute (recursive)"

    return out, set(out["keys"])


def scan_instances():
    """Walk block-library and aggregate attribute statistics."""
    instances = sorted(INSTANCES_GLOB.glob("*/block.json"))
    total_blocks = 0
    blocks_with_attributes = 0
    total_attributes = 0
    key_usage = Counter()
    type_usage = Counter()
    source_usage = Counter()
    role_usage = Counter()
    type_source_combos = Counter()

    # Per-block sample for serialization
    per_block_summary = []

    for fp in instances:
        try:
            data = json.loads(fp.read_text())
        except Exception:
            continue
        total_blocks += 1
        attrs = data.get("attributes", {}) or {}
        if not attrs:
            continue
        blocks_with_attributes += 1

        block_attr_count = 0
        block_sources = Counter()
        for attr_name, attr_def in attrs.items():
            if not isinstance(attr_def, dict):
                continue
            block_attr_count += 1
            total_attributes += 1
            for k in attr_def.keys():
                key_usage[k] += 1
            t = attr_def.get("type")
            if isinstance(t, list):
                t = "|".join(t)
            if t:
                type_usage[t] += 1
            s = attr_def.get("source")
            if s:
                source_usage[s] += 1
                block_sources[s] += 1
                if t:
                    type_source_combos[f"{t}+{s}"] += 1
            r = attr_def.get("role")
            if r:
                role_usage[r] += 1

        per_block_summary.append({
            "name": data.get("name", fp.parent.name),
            "attribute_count": block_attr_count,
            "sources_used": dict(block_sources),
        })

    return {
        "total_blocks": total_blocks,
        "blocks_with_attributes": blocks_with_attributes,
        "total_attribute_definitions": total_attributes,
        "key_usage": dict(key_usage.most_common()),
        "type_usage": dict(type_usage.most_common()),
        "source_usage": dict(source_usage.most_common()),
        "role_usage": dict(role_usage.most_common()),
        "type_source_combos_top10": dict(type_source_combos.most_common(10)),
        "per_block_sample": per_block_summary[:10],  # first 10 only
    }


def extract_corpus_keys(text):
    """Find which schema keys are mentioned as code spans `key`."""
    schema_keys = ["type", "source", "selector", "attribute", "meta",
                   "query", "enum", "default", "role"]
    found = {}
    for k in schema_keys:
        # Count occurrences of `key` (backticks)
        pattern = re.compile(rf"`{re.escape(k)}`")
        matches = pattern.findall(text)
        found[k] = len(matches)
    return found


def main():
    corpus_text = CORPUS.read_text()
    docs_text = DOCS.read_text()

    # 1. H2 comparison
    corpus_h2 = extract_h2(corpus_text)
    docs_h2 = extract_h2(docs_text)

    # 2. schema
    schema, schema_keys = extract_schema_attribute_def()

    # 3. corpus/docs key mentions
    corpus_key_mentions = extract_corpus_keys(corpus_text)
    docs_key_mentions = extract_corpus_keys(docs_text)

    # 4. instances
    print("Scanning 121 block-library instances...")
    instance_stats = scan_instances()

    # 5. 4-way agreement on attribute keys
    keys_4way = {}
    for k in schema_keys:
        keys_4way[k] = {
            "in_schema": True,
            "in_corpus": corpus_key_mentions[k] > 0,
            "in_docs": docs_key_mentions[k] > 0,
            "in_instances": k in instance_stats["key_usage"],
            "instance_usage_count": instance_stats["key_usage"].get(k, 0),
            "corpus_mention_count": corpus_key_mentions[k],
            "docs_mention_count": docs_key_mentions[k],
        }
        keys_4way[k]["in_n_sources"] = sum([
            keys_4way[k]["in_schema"],
            keys_4way[k]["in_corpus"],
            keys_4way[k]["in_docs"],
            keys_4way[k]["in_instances"],
        ])

    # 6. Ontology slot candidates (GPT's list)
    ontology_slots = {
        "BlockAttribute": {
            "description": "A named attribute attached to a BlockType, providing structured data per block.",
            "schema_keys": list(schema_keys),
            "schema_key_count": len(schema_keys),
            "evidence_tier": 2,
        },
        "AttributeType": {
            "description": "The data-type primitive for an attribute value.",
            "schema_enum": schema["type_enum"],
            "schema_enum_count": len(schema["type_enum"]),
            "instance_usage": instance_stats["type_usage"],
            "evidence_tier": 1,
        },
        "AttributeSource": {
            "description": "Where the attribute value is extracted from in saved block markup (storage strategy).",
            "schema_enum": schema["source_enum"],
            "schema_enum_count": len(schema["source_enum"]),
            "instance_usage": instance_stats["source_usage"],
            "evidence_tier": 1,
        },
        "DefaultValue": {
            "description": "Fallback value when an attribute is unset.",
            "evidence_in_instances": "extracted from per-attribute 'default' field; type varies",
            "evidence_tier": 2,
        },
        "EnumConstraint": {
            "description": "Closed set of allowed values for an attribute.",
            "schema_field": "enum (array of bool/number/string)",
            "evidence_tier": 2,
        },
        "Selector": {
            "description": "CSS selector used to locate the source DOM element for attribute extraction.",
            "schema_field": "selector (string)",
            "applies_when_source_in": ["attribute", "text", "html", "rich-text", "raw"],
            "evidence_tier": 2,
        },
        "QueryShape": {
            "description": "Recursive attribute shape: when source=query, each item in matched DOM uses a sub-attribute spec.",
            "schema_field": "query (object with patternProperties matching parent attribute schema)",
            "notable_property": "self-recursive schema",
            "evidence_tier": 1,
        },
        "Role": {
            "description": "Semantic role of an attribute (e.g., 'content' for serialization boundary).",
            "schema_field": "role (string)",
            "instance_usage": instance_stats["role_usage"],
            "evidence_tier": 1,
        },
        # Tier 2 (binding-relevant)
        "SerializationBoundary": {
            "description": "Whether an attribute is part of serialized HTML output (source-dependent).",
            "derivation": "function of AttributeSource: text/html/rich-text/attribute/raw → DOM; meta → external; query → recursive; null source → comment metadata",
            "evidence_tier": 3,  # derived ontology
        },
        "DOMBinding": {
            "description": "Mapping (selector + source-method) that connects an attribute to a DOM extraction strategy.",
            "evidence_tier": 3,
        },
        "MetaBinding": {
            "description": "Bridge to wp_postmeta storage via source='meta'.",
            "instance_usage": instance_stats["source_usage"].get("meta", 0),
            "evidence_tier": 3,
        },
    }

    # 7. Gap candidates (early signals)
    gaps = []
    # rich-text source vs type
    if "rich-text" in schema["type_enum"] and "rich-text" in schema["source_enum"]:
        gaps.append({
            "id": "G2.1",
            "category": "schema-naming",
            "description": "`rich-text` appears in both type enum and source enum — distinct concepts: type='rich-text' is data-shape, source='rich-text' is extraction strategy. Potential ontology confusion.",
        })
    # Role usage in instances
    if instance_stats["role_usage"]:
        gaps.append({
            "id": "G2.2",
            "category": "schema-instances",
            "description": f"`role` field used in {sum(instance_stats['role_usage'].values())} attribute definitions across instances. Roles found: {list(instance_stats['role_usage'].keys())}",
        })
    else:
        gaps.append({
            "id": "G2.2",
            "category": "schema-instances",
            "description": "`role` field defined in schema but not used in any core block instance. Possibly deprecated or experimental.",
        })
    # Source coverage gap
    unused_sources = [s for s in schema["source_enum"] if s not in instance_stats["source_usage"]]
    if unused_sources:
        gaps.append({
            "id": "G2.3",
            "category": "instance-coverage",
            "description": f"Schema-allowed sources not used in core instances: {unused_sources}",
        })

    # H2 alignment
    h2_match = corpus_h2 == docs_h2

    report = {
        "schema_version": "v2.0-pilot-attributes-2026-05-12",
        "generated_at": "2026-05-12",
        "anchor": "block-editor-handbook/.../attributes.md",
        "sources": {
            "corpus": str(CORPUS),
            "docs": str(DOCS),
            "schema": str(SCHEMA),
            "instances": f"{INSTANCES_GLOB}/*/block.json",
        },
        "baseline": {
            "wordpress": "6.9.4 @ 97b7f62a",
            "gutenberg": "v23.1.1 @ 12c6c76e",
        },
        "h2_alignment": {
            "corpus": corpus_h2,
            "docs": docs_h2,
            "match": h2_match,
        },
        "schema": schema,
        "instance_stats": instance_stats,
        "keys_4way": keys_4way,
        "ontology_slots": ontology_slots,
        "gaps": gaps,
    }

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT}\n")

    # ==== Print summary ====
    print("=== H2 alignment ===")
    print(f"  corpus: {corpus_h2}")
    print(f"  docs:   {docs_h2}")
    print(f"  match:  {h2_match}")
    print()
    print(f"=== Schema: BlockAttribute keys ({len(schema_keys)}) ===")
    print(f"  {', '.join(sorted(schema_keys))}")
    print(f"  type enum:   {schema['type_enum']}")
    print(f"  source enum: {schema['source_enum']}")
    print()
    print(f"=== Instances: {instance_stats['total_blocks']} blocks, {instance_stats['blocks_with_attributes']} with attrs, {instance_stats['total_attribute_definitions']} attribute defs total ===")
    print("  key_usage (top 10):")
    for k, v in list(instance_stats["key_usage"].items())[:10]:
        print(f"    {k:20s}: {v}")
    print()
    print("  source_usage:")
    for k, v in instance_stats["source_usage"].items():
        print(f"    {k:20s}: {v}")
    print()
    print("=== 4-way key agreement ===")
    for k, info in sorted(keys_4way.items()):
        marks = "".join([
            "S" if info["in_schema"] else "-",
            "D" if info["in_docs"] else "-",
            "C" if info["in_corpus"] else "-",
            "I" if info["in_instances"] else "-",
        ])
        print(f"  {k:20s} [{marks}]  corpus={info['corpus_mention_count']:3d}  docs={info['docs_mention_count']:3d}  instances={info['instance_usage_count']}")
    print()
    print("=== Ontology slot candidates ===")
    for slot_name, defn in ontology_slots.items():
        print(f"  {slot_name:25s} tier={defn.get('evidence_tier', '?')}")
    print()
    print("=== Gaps ===")
    for g in gaps:
        print(f"  {g['id']} [{g['category']}]: {g['description'][:120]}")


if __name__ == "__main__":
    main()
