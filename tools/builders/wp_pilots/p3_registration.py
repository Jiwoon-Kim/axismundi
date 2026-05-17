#!/usr/bin/env python3
"""
v2_0_registration_pilot.py — 4-way validation for BlockType registration (P3).

Sources:
  1. corpus:    dev_handbook_clean/.../registration.md
  2. docs:      GB-23.1.1/docs/reference-guides/block-api/block-registration.md
  3. schema:    GB-23.1.1/schemas/json/block.json (top-level properties)
  4. instances: GB-23.1.1/packages/block-library/src/*/block.json (121 core blocks)

Outputs:
  core/wordpress/pilot_registration.json
"""

import json
import re
from pathlib import Path
from collections import Counter, defaultdict


CORPUS = Path("dev_handbook_clean/block-editor-handbook/03-reference-guides/01-block-api-reference/registration.md")
DOCS = Path("${GUTENBERG_ROOT}/docs/reference-guides/block-api/block-registration.md")
SCHEMA = Path("${GUTENBERG_ROOT}/schemas/json/block.json")
INSTANCES_GLOB = Path("${GUTENBERG_ROOT}/packages/block-library/src")
OUT = Path("core/wordpress/pilot_registration.json")


# Ontology classification of block.json top-level properties
ONTOLOGY_GROUPS = {
    "Identity": ["name", "title", "description", "icon", "apiVersion",
                 "version", "textdomain", "$schema", "__experimental"],
    "Taxonomy": ["category", "keywords"],
    "Containment": ["parent", "ancestor", "allowedBlocks"],
    "Context": ["providesContext", "usesContext"],
    "DataModel": ["attributes", "supports", "selectors"],
    "Composability": ["blockHooks", "variations", "styles", "example"],
    "Runtime": ["render", "editorScript", "editorStyle", "script", "style",
                "viewScript", "viewScriptModule", "viewStyle"],
}


def extract_schema_properties():
    schema = json.loads(SCHEMA.read_text())
    props = schema["properties"]
    required = set(schema.get("required", []))

    out = {
        "all_keys": sorted(props.keys()),
        "required_keys": sorted(required),
        "key_details": {},
        "category_enum": None,
        "icon_type": None,
    }

    for k, v in props.items():
        out["key_details"][k] = {
            "type": v.get("type"),
            "required": k in required,
            "description_first": (v.get("description") or "").split("\n")[0][:140],
            "has_enum": "enum" in v,
            "is_array": v.get("type") == "array",
            "is_object": v.get("type") == "object",
        }

    # Category enum
    cat = props.get("category", {})
    if "oneOf" in cat:
        for variant in cat["oneOf"]:
            if "enum" in variant:
                out["category_enum"] = list(variant["enum"])
                break
    elif "enum" in cat:
        out["category_enum"] = list(cat["enum"])

    return out


def extract_h2(text):
    return [m.group(1).strip("`") for m in re.finditer(r"^## (.+)$", text, re.MULTILINE)]


def extract_key_mentions(text, keys):
    """Count `key` backtick mentions for each schema key."""
    out = {}
    for k in keys:
        pattern = re.compile(rf"`{re.escape(k)}`")
        out[k] = len(pattern.findall(text))
    return out


def scan_instances(schema_keys):
    """Aggregate BlockType identity stats across 121 core blocks."""
    files = sorted(INSTANCES_GLOB.glob("*/block.json"))
    total = 0
    key_usage = Counter()
    name_namespaces = Counter()
    category_usage = Counter()
    api_version_usage = Counter()
    parent_used = 0
    ancestor_used = 0
    parent_examples = []
    ancestor_examples = []
    variations_count = 0
    variations_blocks = []
    styles_used = 0
    example_used = 0
    keywords_total = Counter()
    keywords_per_block = []
    blockHooks_used = 0
    allowedBlocks_used = 0
    providesContext_used = 0
    usesContext_used = 0
    selectors_used = 0
    __experimental_used = 0
    icon_types = Counter()
    name_collisions = Counter()

    for fp in files:
        try:
            d = json.loads(fp.read_text())
        except Exception:
            continue
        total += 1

        name = d.get("name", "")
        name_collisions[name] += 1
        if "/" in name:
            ns, _ = name.split("/", 1)
            name_namespaces[ns] += 1

        for k in d.keys():
            if k in schema_keys:
                key_usage[k] += 1

        cat = d.get("category")
        if cat:
            category_usage[cat] += 1
        av = d.get("apiVersion")
        if av is not None:
            api_version_usage[av] += 1

        if "parent" in d:
            parent_used += 1
            if len(parent_examples) < 5:
                parent_examples.append({"block": name, "parent": d["parent"]})
        if "ancestor" in d:
            ancestor_used += 1
            if len(ancestor_examples) < 5:
                ancestor_examples.append({"block": name, "ancestor": d["ancestor"]})
        if "variations" in d:
            v = d["variations"]
            if isinstance(v, list):
                variations_count += len(v)
                variations_blocks.append({"block": name, "variation_count": len(v)})
        if "styles" in d:
            styles_used += 1
        if "example" in d:
            example_used += 1
        if "keywords" in d:
            kw = d["keywords"] or []
            keywords_per_block.append(len(kw))
            for k_word in kw:
                keywords_total[k_word] += 1
        if "blockHooks" in d:
            blockHooks_used += 1
        if "allowedBlocks" in d:
            allowedBlocks_used += 1
        if "providesContext" in d:
            providesContext_used += 1
        if "usesContext" in d:
            usesContext_used += 1
        if "selectors" in d:
            selectors_used += 1
        if "__experimental" in d:
            __experimental_used += 1
        icon = d.get("icon")
        if icon:
            icon_types[type(icon).__name__] += 1

    return {
        "total_blocks": total,
        "key_usage": dict(key_usage.most_common()),
        "name_namespaces": dict(name_namespaces.most_common()),
        "name_collisions": {n: c for n, c in name_collisions.items() if c > 1},
        "category_usage": dict(category_usage.most_common()),
        "api_version_usage": dict(api_version_usage.most_common()),
        "parent_blocks": parent_used,
        "parent_examples": parent_examples,
        "ancestor_blocks": ancestor_used,
        "ancestor_examples": ancestor_examples,
        "total_variations": variations_count,
        "variation_blocks_top10": sorted(variations_blocks, key=lambda x: -x["variation_count"])[:10],
        "styles_used": styles_used,
        "example_used": example_used,
        "keywords_total_unique": len(keywords_total),
        "keywords_top20": dict(keywords_total.most_common(20)),
        "blockHooks_used": blockHooks_used,
        "allowedBlocks_used": allowedBlocks_used,
        "providesContext_used": providesContext_used,
        "usesContext_used": usesContext_used,
        "selectors_used": selectors_used,
        "__experimental_used": __experimental_used,
        "icon_types": dict(icon_types),
    }


def main():
    corpus_text = CORPUS.read_text()
    docs_text = DOCS.read_text()
    schema = extract_schema_properties()
    schema_keys = set(schema["all_keys"])

    corpus_h2 = extract_h2(corpus_text)
    docs_h2 = extract_h2(docs_text)

    corpus_mentions = extract_key_mentions(corpus_text, schema_keys)
    docs_mentions = extract_key_mentions(docs_text, schema_keys)

    print("Scanning 121 block-library instances...")
    instances = scan_instances(schema_keys)

    # 4-way agreement matrix
    keys_4way = {}
    for k in sorted(schema_keys):
        keys_4way[k] = {
            "in_schema": True,
            "in_corpus": corpus_mentions[k] > 0,
            "in_docs": docs_mentions[k] > 0,
            "in_instances": instances["key_usage"].get(k, 0) > 0,
            "corpus_mentions": corpus_mentions[k],
            "docs_mentions": docs_mentions[k],
            "instance_usage": instances["key_usage"].get(k, 0),
            "required": k in schema["required_keys"],
        }
        keys_4way[k]["n_sources"] = sum([
            True,
            keys_4way[k]["in_corpus"],
            keys_4way[k]["in_docs"],
            keys_4way[k]["in_instances"],
        ])

    # Ontology slots
    slots = {}
    # Group keys into ontology buckets
    for group, keys in ONTOLOGY_GROUPS.items():
        slots[f"BlockType.{group}"] = {
            "description": f"{group} aspect of BlockType registration",
            "keys": [k for k in keys if k in schema_keys],
            "instance_usage": {k: instances["key_usage"].get(k, 0) for k in keys if k in schema_keys},
        }

    # Tier 1 specific slots
    tier1_slots = {
        "BlockType": {
            "tier": 1,
            "description": "Root: a registered block type; identified by global `name` (namespace/slug).",
            "required_keys": schema["required_keys"],
            "total_top_level_keys": len(schema["all_keys"]),
            "instance_population": instances["total_blocks"],
        },
        "BlockName": {
            "tier": 1,
            "description": "Globally unique identifier; pattern `namespace/slug`.",
            "namespaces_in_instances": instances["name_namespaces"],
            "name_collisions": instances["name_collisions"],
        },
        "Category": {
            "tier": 1,
            "description": "Top-level grouping for inserter UI.",
            "schema_enum": schema["category_enum"],
            "instance_usage": instances["category_usage"],
        },
        "ParentConstraint": {
            "tier": 1,
            "description": "Direct-containment constraint: block must be a direct child of one of listed parent types.",
            "instance_count": instances["parent_blocks"],
            "examples": instances["parent_examples"],
        },
        "AncestorConstraint": {
            "tier": 1,
            "description": "Recursive-containment constraint: block must be nested anywhere within one of listed ancestor types.",
            "instance_count": instances["ancestor_blocks"],
            "examples": instances["ancestor_examples"],
        },
        "Keyword": {
            "tier": 1,
            "description": "Search-discovery aliases for the inserter.",
            "unique_keywords": instances["keywords_total_unique"],
            "top20": instances["keywords_top20"],
        },
        "StyleVariation": {
            "tier": 1,
            "description": "Named visual variant registered via `styles[]`; user-selectable in editor.",
            "instance_count": instances["styles_used"],
        },
        "Variation": {
            "tier": 1,
            "description": "Pre-configured block instance variation with default attribute presets.",
            "instance_blocks_using_variations": len([b for b in instances["variation_blocks_top10"] if b["variation_count"] > 0]),
            "total_variations_across_corpus": instances["total_variations"],
            "top10": instances["variation_blocks_top10"],
        },
        "Example": {
            "tier": 1,
            "description": "Static preview definition rendered in the inserter and inspector preview surfaces.",
            "instance_count": instances["example_used"],
        },
    }

    # Tier 2 binding-relevant slots
    tier2_slots = {
        "Discoverability": {
            "tier": 2,
            "description": "Derived: which blocks appear in the inserter (function of category, keywords, parent/ancestor, allowedBlocks).",
            "evidence_inputs": ["Category", "Keyword", "ParentConstraint", "AncestorConstraint", "allowedBlocks"],
        },
        "InserterSurface": {
            "tier": 2,
            "description": "Where a block can be inserted: derived from parent + ancestor + allowedBlocks (of would-be host).",
            "evidence_inputs": ["parent", "ancestor", "allowedBlocks"],
        },
        "ComposableConstraint": {
            "tier": 2,
            "description": "Full composability rule set for a block (parent + ancestor + allowedBlocks + supports).",
        },
        "PatternAffinity": {
            "tier": 2,
            "description": "Block's relationship to pattern system (blockHooks targets).",
            "instance_count": instances["blockHooks_used"],
        },
        "ContextProvider": {
            "tier": 2,
            "description": "Block exposes context downward.",
            "instance_count": instances["providesContext_used"],
        },
        "ContextConsumer": {
            "tier": 2,
            "description": "Block reads context from ancestor.",
            "instance_count": instances["usesContext_used"],
        },
    }

    # Gaps
    gaps = []

    # G3.1 Name collisions across core
    if instances["name_collisions"]:
        gaps.append({
            "id": "G3.1",
            "category": "instance-uniqueness",
            "description": f"Name collisions in core block-library: {instances['name_collisions']}",
        })

    # G3.2 Category enum coverage
    used_cats = set(instances["category_usage"].keys())
    schema_cats = set(schema["category_enum"] or [])
    cats_only_in_instance = used_cats - schema_cats
    cats_only_in_schema = schema_cats - used_cats
    if schema_cats:
        gaps.append({
            "id": "G3.2",
            "category": "taxonomy-coverage",
            "description": f"Schema category enum: {sorted(schema_cats)}; instances use: {sorted(used_cats)}",
            "instance_only": sorted(cats_only_in_instance),
            "schema_only_unused": sorted(cats_only_in_schema),
        })

    # G3.3 parent vs ancestor relationship
    gaps.append({
        "id": "G3.3",
        "category": "containment-model",
        "description": f"parent (direct) used by {instances['parent_blocks']} blocks; ancestor (recursive) used by {instances['ancestor_blocks']} blocks. Asymmetric usage suggests parent is well-understood, ancestor is sparsely adopted.",
        "parent_examples": instances["parent_examples"],
        "ancestor_examples": instances["ancestor_examples"],
    })

    # G3.4 apiVersion evolution
    if len(instances["api_version_usage"]) > 1:
        gaps.append({
            "id": "G3.4",
            "category": "version-evolution",
            "description": f"Mixed apiVersion in core: {instances['api_version_usage']}. Indicates ongoing migration; ontology must support apiVersion as evolving property.",
        })

    # G3.5 example schema underuse
    example_pct = (instances["example_used"] / instances["total_blocks"]) * 100 if instances["total_blocks"] else 0
    if example_pct < 50:
        gaps.append({
            "id": "G3.5",
            "category": "schema-instances",
            "description": f"`example` defined in schema as preview source-of-truth, but only {instances['example_used']}/{instances['total_blocks']} ({example_pct:.0f}%) of core blocks provide one.",
        })

    # G3.6 variations distribution
    if instances["total_variations"] > 0:
        gaps.append({
            "id": "G3.6",
            "category": "schema-instances",
            "description": f"{instances['total_variations']} total variations across core blocks. Top users: {instances['variation_blocks_top10'][:3]}",
        })

    # G3.7 __experimental marker
    if instances["__experimental_used"] > 0:
        gaps.append({
            "id": "G3.7",
            "category": "lifecycle",
            "description": f"{instances['__experimental_used']} blocks marked __experimental. These should be flagged in ontology with availability=experimental.",
        })

    report = {
        "schema_version": "v2.0-pilot-registration-2026-05-12",
        "generated_at": "2026-05-12",
        "anchor": "block-editor-handbook/.../registration.md",
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
            "match": corpus_h2 == docs_h2,
        },
        "schema_summary": {
            "total_keys": len(schema["all_keys"]),
            "required": schema["required_keys"],
            "all_keys": schema["all_keys"],
            "category_enum": schema["category_enum"],
        },
        "instances": instances,
        "keys_4way": keys_4way,
        "ontology_groups": slots,
        "tier1_slots": tier1_slots,
        "tier2_slots": tier2_slots,
        "gaps": gaps,
    }

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT}\n")

    # ==== Summary ====
    print("=== H2 alignment ===")
    print(f"  match: {corpus_h2 == docs_h2}")
    print(f"  corpus: {corpus_h2}")
    print(f"  docs:   {docs_h2}")
    print()
    print(f"=== Schema BlockType keys: {len(schema['all_keys'])} (required: {schema['required_keys']}) ===")
    print()
    print(f"=== Instances: {instances['total_blocks']} core blocks ===")
    print(f"  apiVersion: {instances['api_version_usage']}")
    print(f"  namespaces: {instances['name_namespaces']}")
    print(f"  categories: {instances['category_usage']}")
    print(f"  parent (direct):    {instances['parent_blocks']} blocks")
    print(f"  ancestor (nested):  {instances['ancestor_blocks']} blocks")
    print(f"  variations:         {instances['total_variations']} total")
    print(f"  styles:             {instances['styles_used']} blocks")
    print(f"  example:            {instances['example_used']} blocks")
    print(f"  blockHooks:         {instances['blockHooks_used']}")
    print(f"  __experimental:     {instances['__experimental_used']}")
    print()
    print("=== 4-way key agreement (top 15 by instance usage) ===")
    by_usage = sorted(keys_4way.items(), key=lambda x: -x[1]['instance_usage'])
    for k, info in by_usage[:15]:
        marks = "".join(["S",
                         "D" if info["in_docs"] else "-",
                         "C" if info["in_corpus"] else "-",
                         "I" if info["in_instances"] else "-"])
        req = "*" if info["required"] else " "
        print(f"  {k:22s}{req}[{marks}]  corpus={info['corpus_mentions']:3d} docs={info['docs_mentions']:3d} inst={info['instance_usage']:3d}")
    print()
    print("=== Schema keys NOT used in any core instance ===")
    unused = [k for k, info in keys_4way.items() if not info["in_instances"]]
    for k in unused:
        info = keys_4way[k]
        print(f"  {k}  corpus_mentions={info['corpus_mentions']}, docs_mentions={info['docs_mentions']}")
    print()
    print("=== Gaps ===")
    for g in gaps:
        print(f"  {g['id']} [{g['category']}]: {g['description'][:130]}")


if __name__ == "__main__":
    main()
