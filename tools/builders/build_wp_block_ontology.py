#!/usr/bin/env python3
"""
build_ontology_core_v0_1.py — Unify P1 (supports) + P2 (attributes) + P3 (registration)
into a single JSON-LD ontology core draft, with provenance + thematic_domain.

Inputs:
  core/wordpress/pilot_supports.json
  core/wordpress/pilot_attributes.json
  core/wordpress/pilot_registration.json
  core/wordpress/pilot_registration_tiered.json   (5-tier classification + provenance)

Outputs:
  core/wordpress/ontology_core_draft_v0_1.jsonld
  core/wordpress/ontology_core_summary_v0_1.md     (human-readable)
"""

import json
from pathlib import Path
from datetime import datetime


P1 = Path("core/wordpress/pilot_supports.json")
P2 = Path("core/wordpress/pilot_attributes.json")
P3 = Path("core/wordpress/pilot_registration.json")
P3T = Path("core/wordpress/pilot_registration_tiered.json")

OUT_JSONLD = Path("core/wordpress/ontology_core_draft_v0_1.jsonld")
OUT_SUMMARY = Path("core/wordpress/ontology_core_summary_v0_1.md")


# Atlas 11 bounded contexts (from knowledge/wordpress/_meta/dsl-spec.md)
THEMATIC_DOMAINS = {
    "block-authoring",
    "theme-config",
    "style-engine",
    "editor-customization",
    "data-layer",
    "site-building",
    "interactivity",
    "plugin-dev",
    "i18n",
    "build-tooling",
    "admin-ui",
}


def build_atlas_index():
    """Index atlas rules by rule_id for cross-reference validation."""
    # Read from in-place if available
    import os
    candidates = [
        Path("atlas/wordpress"),
        Path("knowledge/wordpress"),
    ]
    root = next((p for p in candidates if p.exists()), None)
    if not root:
        return set()
    ids = set()
    for fp in root.rglob("*.md"):
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


ATLAS_RULES = None  # populated in main()


def atlas_link(rule_id):
    """Return rule_id only if it exists in atlas; else None."""
    if ATLAS_RULES and rule_id in ATLAS_RULES:
        return rule_id
    return None


def make_context():
    """JSON-LD @context — vocabulary mapping for the WordPress ontology core."""
    return {
        # Primary vocabulary namespace
        "wp": "https://anthropic.tld/v2.0/wp-ontology#",   # placeholder
        # Schema.org / RDF / SKOS for standard predicates
        "rdfs": "http://www.w3.org/2000/01/rdf-schema#",
        "skos": "http://www.w3.org/2004/02/skos/core#",
        "xsd": "http://www.w3.org/2001/XMLSchema#",
        # Custom predicates for ontology metadata
        "tier": "wp:tier",
        "provenance": "wp:provenance",
        "thematic_domain": "wp:thematicDomain",
        "atlas_rule_id": "wp:atlasRuleId",
        "schema_field": "wp:schemaField",
        "schema_path": "wp:schemaPath",
        "binding_candidates": "wp:bindingCandidates",
        "instance_count": "wp:instanceCount",
        "instance_percent": "wp:instancePercent",
        "enum_values": "wp:enumValues",
        "subproperties": "wp:subproperties",
        "has_property": "wp:hasProperty",
        "has_attribute": "wp:hasAttribute",
        "has_support": "wp:hasSupport",
        "required": "wp:required",
        "availability": "wp:availability",
        # Standard predicates
        "label": "rdfs:label",
        "comment": "rdfs:comment",
        "altLabel": "skos:altLabel",
        "broader": "skos:broader",
        "narrower": "skos:narrower",
    }


def build_blocktype(p3, p3t):
    """Root BlockType + its 5-tier property structure (P3)."""
    schema = p3["schema_summary"]
    instances = p3["instances"]
    tiered = p3t["tiered_keys"]

    # Map block.json properties → atlas rule IDs (manual curation based on atlas inventory)
    PROPERTY_TO_ATLAS = {
        "name": "block.json-basic-metadata",
        "title": "block.json-basic-metadata",
        "description": "block.json-basic-metadata",
        "icon": "block.json-basic-metadata",
        "category": "block.json-basic-metadata",
        "keywords": "block.json-basic-metadata",
        "textdomain": "block.json-basic-metadata",
        "apiVersion": "block.api-version-and-block-evolution",
        "version": "block.json-residual-fields",
        "$schema": "block.json-residual-fields",
        "__experimental": "block.json-residual-fields",
        "parent": "block.json-hierarchy-constraints",
        "ancestor": "block.json-hierarchy-constraints",
        "allowedBlocks": "block.json-hierarchy-constraints",
        "attributes": "block.json-attributes-core",
        "supports": "block.json-supports-field",
        "selectors": "block.json-selectors",
        "providesContext": "block.json-context",
        "usesContext": "block.json-context",
        "blockHooks": None,  # no atlas rule yet (new API)
        "styles": "block.block-styles-registration",
        "variations": "block.variations",
        "example": None,
        "render": "block.dynamic-rendering",
        "script": "block.json-assets",
        "style": "block.json-assets",
        "editorScript": "block.json-assets",
        "editorStyle": "block.json-assets",
        "viewScript": "block.json-assets",
        "viewStyle": "block.json-assets",
        "viewScriptModule": "block.json-assets",
    }

    # Build property nodes per tier
    property_nodes = []
    for tier_name, tier_def in tiered.items():
        for kinfo in tier_def["keys"]:
            k = kinfo["key"]
            node = {
                "@id": f"wp:BlockType.{k}",
                "@type": "wp:BlockTypeProperty",
                "label": k,
                "tier": tier_name,
                "required": kinfo.get("required", False),
                "provenance": kinfo["provenance"],
                "instance_count": kinfo.get("instance_usage", 0),
                "instance_percent": kinfo.get("instance_pct", 0.0),
                "schema_field": k,
                "thematic_domain": "block-authoring",
            }
            # Atlas link
            rid_candidate = PROPERTY_TO_ATLAS.get(k)
            if rid_candidate:
                linked = atlas_link(rid_candidate)
                if linked:
                    node["atlas_rule_id"] = linked
            property_nodes.append(node)

    # Identity sub-entities with enums (Category, ApiVersion, ExperimentalMarker)
    category_enum = sorted(instances["category_usage"].keys())
    apiVersion_enum = sorted(int(k) for k in instances["api_version_usage"].keys())

    sub_entities = [
        {
            "@id": "wp:Category",
            "@type": "wp:Enumeration",
            "label": "Category",
            "tier": "Tier1_Identity",
            "comment": "Top-level grouping for inserter UI. Enum derived from 121 core block instances (schema is open string).",
            "provenance": "instance_derived_enum",
            "enum_values": category_enum,
            "schema_field": "category",
            "thematic_domain": "block-authoring",
            "atlas_rule_id": atlas_link("block.json-basic-metadata"),  # category is part of basic-metadata
        },
        {
            "@id": "wp:ApiVersion",
            "@type": "wp:Enumeration",
            "label": "ApiVersion",
            "tier": "Tier1_Identity",
            "comment": "Block API version. Core is exclusively v3 (post-migration). Plugin/theme blocks may pin to 1/2/3 for compatibility.",
            "provenance": "schema+instance",
            "enum_values": [1, 2, 3],
            "instance_observed": apiVersion_enum,
            "schema_field": "apiVersion",
            "required": True,
            "thematic_domain": "block-authoring",
        },
        {
            "@id": "wp:ExperimentalMarker",
            "@type": "wp:Enumeration",
            "label": "ExperimentalMarker",
            "tier": "Tier1_Identity",
            "comment": "Block lifecycle status. `true` = API may change; `'fse'` = FSE-only experimental.",
            "provenance": "schema+instance",
            "enum_values": [True, "fse"],
            "schema_field": "__experimental",
            "instance_count": 14,
            "availability": "filter:exclude_for_production",
            "thematic_domain": "block-authoring",
        },
        {
            "@id": "wp:BlockName",
            "@type": "wp:Identifier",
            "label": "BlockName",
            "tier": "Tier1_Identity",
            "comment": "Globally unique block identifier with pattern `namespace/slug`. Core namespace reserved; plugin/theme use own namespace.",
            "provenance": "schema+instance",
            "pattern": r"^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$",
            "schema_field": "name",
            "required": True,
            "instance_count": 121,
            "core_namespace_usage": "121/121",
            "thematic_domain": "block-authoring",
        },
        # Composition slots
        {
            "@id": "wp:ParentConstraint",
            "@type": "wp:ContainmentRule",
            "label": "ParentConstraint",
            "tier": "Tier2_Composition",
            "comment": "Direct-child containment: block must be a direct child of one of listed parent BlockTypes.",
            "semantics": "directChildOf",
            "schema_field": "parent",
            "provenance": "schema+instance",
            "instance_count": 25,
            "instance_percent": 20.7,
            "examples": p3.get("tier1_slots", {}).get("ParentConstraint", {}).get("examples", []),
            "binding_candidates": {
                "material": "composite_child (Button → ButtonGroup)",
                "activitypub": "partOf (single Collection)",
            },
            "thematic_domain": "block-authoring",
            "atlas_rule_id": atlas_link("block.json-hierarchy-constraints"),
        },
        {
            "@id": "wp:AncestorConstraint",
            "@type": "wp:ContainmentRule",
            "label": "AncestorConstraint",
            "tier": "Tier2_Composition",
            "comment": "Recursive descendant containment: block must be nested anywhere within one of listed ancestor BlockTypes.",
            "semantics": "descendantOf",
            "schema_field": "ancestor",
            "provenance": "schema+instance",
            "instance_count": 15,
            "instance_percent": 12.4,
            "binding_candidates": {
                "material": "slot_template (Card.Content anywhere in Card)",
                "activitypub": "partOf (transitive)",
            },
            "thematic_domain": "block-authoring",
            "atlas_rule_id": atlas_link("block.json-hierarchy-constraints"),
        },
        {
            "@id": "wp:AllowedBlocks",
            "@type": "wp:ContainmentRule",
            "label": "AllowedBlocks",
            "tier": "Tier2_Composition",
            "comment": "Child whitelist (host-side rule): only listed BlockTypes can be inserted inside this block.",
            "semantics": "childWhitelist",
            "schema_field": "allowedBlocks",
            "provenance": "schema+instance",
            "instance_count": 20,
            "instance_percent": 16.5,
            "thematic_domain": "block-authoring",
            "atlas_rule_id": atlas_link("block.json-hierarchy-constraints"),
        },
        # Context I/O
        {
            "@id": "wp:ContextProvider",
            "@type": "wp:ContextIO",
            "label": "ContextProvider",
            "tier": "Tier5_Context",
            "comment": "Block exposes named context values to descendant blocks.",
            "schema_field": "providesContext",
            "provenance": "schema+instance",
            "instance_count": 14,
            "instance_percent": 11.6,
            "binding_candidates": {"material": "context_provider (React)", "activitypub": None},
            "thematic_domain": "block-authoring",
            "atlas_rule_id": atlas_link("block.json-context"),
        },
        {
            "@id": "wp:ContextConsumer",
            "@type": "wp:ContextIO",
            "label": "ContextConsumer",
            "tier": "Tier5_Context",
            "comment": "Block reads named context values from an ancestor provider.",
            "schema_field": "usesContext",
            "provenance": "schema+instance",
            "instance_count": 63,
            "instance_percent": 52.1,
            "binding_candidates": {"material": "context_consumer (React)", "activitypub": "context_field"},
            "thematic_domain": "block-authoring",
            "atlas_rule_id": atlas_link("block.json-context"),
        },
        {
            "@id": "wp:PatternAffinity",
            "@type": "wp:HookSurface",
            "label": "PatternAffinity",
            "tier": "Tier5_Context",
            "comment": "blockHooks API: automatic insertion of block relative to others (before/after/firstChild/lastChild).",
            "schema_field": "blockHooks",
            "provenance": "schema_only",
            "instance_count": 0,
            "comment_provenance": "WP 6.4+ API; no core blocks adopted yet. Plugin/theme territory.",
            "thematic_domain": "block-authoring",
        },
    ]

    # BlockType root
    root = {
        "@id": "wp:BlockType",
        "@type": "wp:RootEntity",
        "label": "BlockType",
        "comment": "A registered Gutenberg block type. Identity + Composition + DataModel + Runtime + Context.",
        "tier": "Root",
        "schema_path": "schemas/json/block.json",
        "required_properties": schema["required"],
        "total_top_level_properties": len(schema["all_keys"]),
        "instance_population": 121,
        "instance_namespace": "core/*",
        "tier_groups": [
            {"tier": "Tier1_Identity", "key_count": 11, "instance_avg_pct": 65.3},
            {"tier": "Tier2_Composition", "key_count": 6, "instance_avg_pct": 11.9},
            {"tier": "Tier3_DataModel", "key_count": 3, "instance_avg_pct": 65.6},
            {"tier": "Tier4_Runtime", "key_count": 8, "instance_avg_pct": 15.9},
            {"tier": "Tier5_Context", "key_count": 3, "instance_avg_pct": 21.2},
        ],
        "has_property": [n["@id"] for n in property_nodes],
        "has_attribute_model": "wp:BlockAttribute",
        "has_support_model": "wp:BlockSupport",
        "thematic_domain": "block-authoring",
    }

    return [root] + sub_entities + property_nodes


def build_blocksupport(p1):
    """BlockSupport entities (P1 — 28 supports)."""
    slots = p1["slots"]
    nodes = []

    # Root BlockSupport class
    nodes.append({
        "@id": "wp:BlockSupport",
        "@type": "wp:CapabilityClass",
        "label": "BlockSupport",
        "comment": "A named feature flag a block declares to opt into editor UI / attribute injection.",
        "tier": "Tier3_DataModel",
        "schema_path": "schemas/json/block.json#/properties/supports",
        "instance_count": 28,
        "agreement": {
            "in_all_3_sources": 27,
            "in_2_sources": 1,
            "schema_only": 1,
        },
        "thematic_domain": "block-authoring",
        "atlas_rule_id": atlas_link("block.json-supports-field") or atlas_link("block.json.supports-field"),
        "atlas_related": [r for r in (atlas_link(f"block.supports.{x}") for x in ["background","color","dimensions","filter","layout","position","shadow","spacing","typography"]) if r],
    })

    # Each support
    for s in slots:
        canon = s["canonical_name"]
        # Normalize provenance to canonical taxonomy
        prov_set = set(s["provenance"])
        if prov_set == {"corpus", "docs", "schema"}:
            prov_str = "schema+instance"
        elif prov_set == {"corpus", "docs"}:
            prov_str = "instance_only"  # docs counts as instance evidence for handbook
        elif prov_set == {"schema"}:
            prov_str = "schema_only"
        elif "schema" in prov_set:
            prov_str = "schema+instance_partial"
        else:
            prov_str = "instance_only"
        node = {
            "@id": f"wp:BlockSupport.{canon}",
            "@type": "wp:BlockSupportInstance",
            "label": canon,
            "broader": "wp:BlockSupport",
            "tier": "Tier3_DataModel",
            "schema_field": f"supports.{canon}",
            "provenance": prov_str,
            "thematic_domain": "block-authoring",
        }
        # Atlas mapping — atlas uses `block.supports.X` (short prefix)
        atlas_id = atlas_link(f"block.supports.{canon}")
        if atlas_id:
            node["atlas_rule_id"] = atlas_id
        # Schema details
        if s.get("schema_type"):
            node["schema_type"] = s["schema_type"]
        if s.get("schema_default") is not None:
            node["schema_default"] = s["schema_default"]
        if s.get("schema_subprops"):
            node["subproperties"] = [
                f"wp:BlockSupport.{canon}.{sp}" for sp in s["schema_subprops"]
            ]
        # Naming alias (G1.1)
        if s.get("corpus_name") and s.get("docs_name") and s["corpus_name"] != s["docs_name"]:
            node["altLabel"] = [s["corpus_name"], s["docs_name"]]
            node["comment"] = f"Naming alias: corpus uses `{s['corpus_name']}`, docs uses `{s['docs_name']}`."
        # Availability dual-track (G1.4)
        if canon in {"contentRole", "listView", "visibility", "customCSS"}:
            node["availability"] = ["gutenberg_plugin_23_1_1"]
        elif canon in {"autoRegister", "auto_register"}:
            node["availability"] = ["core_6_9_4", "gutenberg_plugin_23_1_1"]
        nodes.append(node)

    return nodes


def build_blockattribute(p2):
    """BlockAttribute entities (P2 — 9 schema keys × 473 instance defs)."""
    schema = p2["schema"]
    inst = p2["instance_stats"]
    nodes = []

    # Root BlockAttribute class
    nodes.append({
        "@id": "wp:BlockAttribute",
        "@type": "wp:DataModelClass",
        "label": "BlockAttribute",
        "comment": "A named attribute providing structured data for a BlockType. Has 9 schema-defined facets.",
        "tier": "Tier3_DataModel",
        "schema_path": "schemas/json/block.json#/properties/attributes/patternProperties/[a-zA-Z]",
        "schema_keys": schema["keys"],
        "instance_count": inst["total_attribute_definitions"],
        "instance_blocks_using": inst["blocks_with_attributes"],
        "thematic_domain": "block-authoring",
        "atlas_rule_id": atlas_link("block.json-attributes-core"),
        "atlas_related": [r for r in [
            atlas_link("block.json-attributes-html-sources"),
            atlas_link("block.json-attributes-query-source"),
        ] if r],
    })

    # AttributeType enum (8 primitives)
    nodes.append({
        "@id": "wp:AttributeType",
        "@type": "wp:Enumeration",
        "label": "AttributeType",
        "broader": "wp:BlockAttribute",
        "comment": "Data-type primitive for attribute value. Note: `rich-text` is a TYPE primitive distinct from `source: rich-text`.",
        "tier": "Tier3_DataModel",
        "schema_field": "type",
        "enum_values": schema["type_enum"],
        "provenance": "schema+instance",
        "instance_usage": inst["type_usage"],
        "binding_candidates": {
            "material": {
                "rich-text": "RichTextField",
                "string": "TextField",
                "number": "NumberField",
                "boolean": "ToggleField",
                "object": "structured_props",
                "array": "list_field",
            },
        },
        "thematic_domain": "block-authoring",
    })

    # AttributeSource enum (7, with 5 active + 2 dormant)
    nodes.append({
        "@id": "wp:AttributeSource",
        "@type": "wp:Enumeration",
        "label": "AttributeSource",
        "broader": "wp:BlockAttribute",
        "comment": "Extraction strategy: how attribute value is read from saved markup.",
        "tier": "Tier3_DataModel",
        "schema_field": "source",
        "enum_values": schema["source_enum"],
        "active_sources": ["attribute", "rich-text", "query", "raw", "html"],
        "dormant_sources": ["text", "meta"],
        "provenance": "schema+instance_partial",
        "instance_usage": inst["source_usage"],
        "comment_dormant": "`text` superseded by `rich-text`; `meta` is postmeta bridge (plugin territory).",
        "binding_candidates": {
            "material": {
                "attribute": "propBinding",
                "rich-text": "RichTextField.value",
                "html": "innerHTML_binding",
                "query": "list_field_binding",
            },
        },
        "thematic_domain": "block-authoring",
    })

    # Role (P2 G2.2 — instance-derived closed enum)
    nodes.append({
        "@id": "wp:Role",
        "@type": "wp:Enumeration",
        "label": "Role",
        "broader": "wp:BlockAttribute",
        "comment": "Semantic role of attribute. Schema defines as open string; instances reveal closed enum {content, local}.",
        "tier": "Tier3_DataModel",
        "schema_field": "role",
        "enum_values": ["content", "local"],
        "provenance": "instance_derived_enum",  # schema under-specified
        "instance_usage": {"content": 90, "local": 6},
        "schema_under_specification": True,
        "binding_candidates": {
            "material": {
                "content": "Component.contentSlot",
                "local": "Component.localState",
            },
            "activitypub": {
                "content": "object.content (federated)",
                "local": None,
            },
        },
        "thematic_domain": "block-authoring",
    })

    # Other facets
    other_facets = [
        ("DefaultValue", "default",
         "Fallback value when attribute is unset. Type varies by attribute.",
         "schema+instance", 224, 47.4),
        ("EnumConstraint", "enum",
         "Closed set of allowed values for an attribute.",
         "schema+instance", 16, 3.4),
        ("Selector", "selector",
         "CSS selector to locate source DOM element. Applies when source ∈ {attribute, text, html, rich-text, raw}.",
         "schema+instance", 66, 13.9),
        ("AttributeRef", "attribute",
         "Name of the HTML attribute to read (paired with source='attribute').",
         "schema+instance", 39, 8.2),
        ("QueryShape", "query",
         "Recursive attribute shape: each item in matched DOM uses sub-attribute spec.",
         "schema+instance", 5, 1.1),
        ("MetaBinding", "meta",
         "wp_postmeta storage bridge. Schema-only in core; used in plugin meta-bound blocks.",
         "schema_only", 0, 0.0),
    ]
    for slot, field, comment, prov, cnt, pct in other_facets:
        node = {
            "@id": f"wp:{slot}",
            "@type": "wp:AttributeFacet",
            "label": slot,
            "broader": "wp:BlockAttribute",
            "comment": comment,
            "tier": "Tier3_DataModel",
            "schema_field": field,
            "provenance": prov,
            "instance_count": cnt,
            "instance_percent": pct,
            "thematic_domain": "block-authoring",
        }
        nodes.append(node)

    # Tier 3 derived ontology slots (binding-relevant)
    derived = [
        {
            "@id": "wp:SerializationBoundary",
            "@type": "wp:DerivedSlot",
            "label": "SerializationBoundary",
            "comment": "Whether an attribute participates in serialized HTML output. Derived from source: html/rich-text/attribute/raw/text → DOM; meta → external; query → recursive; null source → comment metadata.",
            "tier": "Tier3_DataModel_Derived",
            "derived_from": ["wp:AttributeSource"],
            "thematic_domain": "block-authoring",
        },
        {
            "@id": "wp:DOMBinding",
            "@type": "wp:DerivedSlot",
            "label": "DOMBinding",
            "comment": "Tuple (Selector, AttributeSource, AttributeRef) describing how an attribute extracts from the DOM.",
            "tier": "Tier3_DataModel_Derived",
            "derived_from": ["wp:Selector", "wp:AttributeSource", "wp:AttributeRef"],
            "thematic_domain": "block-authoring",
        },
    ]
    nodes.extend(derived)

    return nodes


def build_ontology():
    p1 = json.loads(P1.read_text())
    p2 = json.loads(P2.read_text())
    p3 = json.loads(P3.read_text())
    p3t = json.loads(P3T.read_text())

    graph = []
    graph.extend(build_blocktype(p3, p3t))
    graph.extend(build_blocksupport(p1))
    graph.extend(build_blockattribute(p2))

    ontology = {
        "@context": make_context(),
        "@id": "wp:WordPressBlockOntologyCore",
        "@type": "wp:OntologyCore",
        "label": "WordPress Block Ontology Core Draft v0.1",
        "comment": (
            "Unified ontology skeleton from P1 (supports) + P2 (attributes) + "
            "P3 (registration) pilots. Provenance-aware, atlas-linked, "
            "binding-ready. Tier classification: Root / Tier1_Identity / "
            "Tier2_Composition / Tier3_DataModel / Tier4_Runtime / Tier5_Context."
        ),
        "version": "0.1.0",
        "generated_at": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"),
        "baseline": {
            "wordpress": "6.9.4 @ 97b7f62a (2026-03-11)",
            "gutenberg": "v23.1.1 @ 12c6c76e (2026-05-08)",
            "corpus_sync": "2026-05-12",
        },
        "thematic_domains_total": list(sorted(THEMATIC_DOMAINS)),
        "provenance_taxonomy": [
            "schema+instance",
            "schema+php_runtime",
            "schema+js_runtime",
            "schema_only",
            "instance_only",
            "instance_derived_enum",
            "schema+instance_partial",
        ],
        "atlas_integration": {
            "atlas_root": "knowledge/wordpress/",
            "bounded_contexts": 11,
            "atlas_rule_links": "ontology entities carry atlas_rule_id when 1:1 mapping exists",
            "directionality": "atlas (B) is source-of-truth for rules; ontology (C) is typed projection",
        },
        "@graph": graph,
    }
    return ontology


def write_summary(ont):
    """Human-readable summary alongside the JSON-LD."""
    g = ont["@graph"]
    from collections import Counter
    by_type = Counter(node["@type"] for node in g)
    by_tier = Counter(node.get("tier", "(none)") for node in g)
    by_prov = Counter(node.get("provenance", "(none)") for node in g if "provenance" in node)
    has_atlas = sum(1 for n in g if n.get("atlas_rule_id"))
    has_binding = sum(1 for n in g if n.get("binding_candidates"))

    md = []
    md.append(f"# {ont['label']}\n")
    md.append(f"Generated: {ont['generated_at']}\n")
    md.append(f"Baseline: WP {ont['baseline']['wordpress']}, Gutenberg {ont['baseline']['gutenberg']}\n")
    md.append("")
    md.append(f"## Graph composition\n")
    md.append(f"- **Total entities**: {len(g)}\n")
    md.append(f"- **By @type**:\n")
    for t, c in by_type.most_common():
        md.append(f"  - {t}: {c}")
    md.append("")
    md.append(f"## Tier distribution\n")
    for tier, c in by_tier.most_common():
        md.append(f"- {tier}: {c}")
    md.append("")
    md.append(f"## Provenance distribution\n")
    for prov, c in by_prov.most_common():
        md.append(f"- {prov}: {c}")
    md.append("")
    md.append(f"## Atlas + Binding readiness\n")
    md.append(f"- Entities with `atlas_rule_id` (linked to knowledge/wordpress/): {has_atlas}\n")
    md.append(f"- Entities with `binding_candidates` (Material/ActivityPub-ready): {has_binding}\n")
    md.append("")
    md.append(f"## Root entities\n")
    for n in g:
        if n.get("@type") in {"wp:RootEntity", "wp:CapabilityClass", "wp:DataModelClass"}:
            md.append(f"- **{n['@id']}** — {n['label']}")
            if n.get("comment"):
                md.append(f"  - {n['comment']}")
    md.append("")
    md.append("## Next steps\n")
    md.append("- P4 theme.json settings → Token ontology slot (Material binding 다른 한 축)\n")
    md.append("- P5 data-core-block-editor → Store/Selector ontology\n")
    md.append("- v2.1 binding layer: WordPress ontology ↔ Material binding rules + ↔ ActivityPub binding\n")
    return "\n".join(md)


def main():
    global ATLAS_RULES
    ATLAS_RULES = build_atlas_index()
    print(f"Loaded {len(ATLAS_RULES)} atlas rule IDs")
    ont = build_ontology()

    OUT_JSONLD.parent.mkdir(parents=True, exist_ok=True)
    OUT_JSONLD.write_text(json.dumps(ont, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {OUT_JSONLD}")

    OUT_SUMMARY.write_text(write_summary(ont), encoding="utf-8")
    print(f"Wrote {OUT_SUMMARY}")

    # Verify JSON-LD parses
    parsed = json.loads(OUT_JSONLD.read_text())
    print()
    print(f"=== Verification ===")
    print(f"  Total @graph entities: {len(parsed['@graph'])}")
    from collections import Counter
    types = Counter(n["@type"] for n in parsed["@graph"])
    print(f"  @types: {dict(types)}")
    tiers = Counter(n.get("tier", "(none)") for n in parsed["@graph"])
    print(f"  Tiers: {dict(tiers)}")
    provs = Counter(n.get("provenance", "(none)") for n in parsed["@graph"] if "provenance" in n)
    print(f"  Provenance: {dict(provs)}")
    print(f"  with atlas_rule_id: {sum(1 for n in parsed['@graph'] if n.get('atlas_rule_id'))}")
    print(f"  with binding_candidates: {sum(1 for n in parsed['@graph'] if n.get('binding_candidates'))}")


if __name__ == "__main__":
    main()
