#!/usr/bin/env python3
"""
v2_0_p3_tier_regroup.py — Restructure P3 pilot output with GPT's tier proposal
+ instance-grounded provenance correction.

Input:  core/wordpress/pilot_registration.json
Output: core/wordpress/pilot_registration_tiered.json
        + appendable section for gap_report.md
"""

import json
from pathlib import Path

PILOT = Path("core/wordpress/pilot_registration.json")
OUT = Path("core/wordpress/pilot_registration_tiered.json")


# GPT-proposed tiering, validated against instance evidence
TIER_DEFINITIONS = {
    "Tier1_Identity": {
        "description": "What a BlockType IS — name, label, version, taxonomy. Mostly schema-direct, mostly required or near-100% instance grounding.",
        "keys": ["name", "title", "apiVersion", "category", "description",
                 "icon", "keywords", "version", "textdomain", "$schema",
                 "__experimental"],
    },
    "Tier2_Composition": {
        "description": "How a BlockType relates to OTHER blocks — containment, inserter rules, child/parent semantics.",
        "keys": ["parent", "ancestor", "allowedBlocks", "styles", "variations",
                 "example"],
    },
    "Tier3_DataModel": {
        "description": "How a BlockType stores DATA — attribute shape, support flags, selectors. Connects to P1/P2 pilots.",
        "keys": ["attributes", "supports", "selectors"],
    },
    "Tier4_Runtime": {
        "description": "Asset loading + render. Most core blocks use PHP register_block_type for these; block.json adoption is partial.",
        "keys": ["render", "script", "style", "editorScript", "editorStyle",
                 "viewScript", "viewStyle", "viewScriptModule"],
    },
    "Tier5_Context": {
        "description": "Block-to-block runtime communication: context provision/consumption + hook insertion.",
        "keys": ["providesContext", "usesContext", "blockHooks"],
    },
}


def main():
    pilot = json.loads(PILOT.read_text())
    instances = pilot["instances"]
    key_usage = instances["key_usage"]
    total = instances["total_blocks"]
    schema = pilot["schema_summary"]

    def usage_pct(k):
        return (key_usage.get(k, 0) / total * 100) if total else 0.0

    # Provenance classification:
    #   schema+instance:       schema-defined AND used in core block.json instances
    #   schema_only:           schema-defined, never used in core block.json
    #   schema+php_runtime:    schema-defined, but instances populate via PHP register_block_type
    #   schema+js_runtime:     schema-defined, but instances populate via JS register*
    PROVENANCE_OVERRIDES = {
        "icon": "schema+php_runtime",       # 3/121 in block.json; rest via PHP
        "variations": "schema+js_runtime",  # 0/121 in block.json; JS API
        "render": "schema+php_runtime",     # core uses PHP register_block_type
        "script": "schema+php_runtime",
        "viewScript": "schema+php_runtime",
        "viewStyle": "schema+php_runtime",
    }

    def derive_provenance(k):
        if k in PROVENANCE_OVERRIDES:
            return PROVENANCE_OVERRIDES[k]
        if k not in schema["all_keys"]:
            return "instance_only"
        if key_usage.get(k, 0) == 0:
            return "schema_only"
        return "schema+instance"

    # Build tiered output
    tiered = {}
    for tier_name, defn in TIER_DEFINITIONS.items():
        tier_keys = []
        for k in defn["keys"]:
            if k not in schema["all_keys"]:
                # not in schema; skip but record
                tier_keys.append({
                    "key": k,
                    "in_schema": False,
                    "instance_usage": key_usage.get(k, 0),
                    "instance_pct": usage_pct(k),
                    "provenance": derive_provenance(k),
                    "required": False,
                })
                continue
            tier_keys.append({
                "key": k,
                "in_schema": True,
                "required": k in schema["required"],
                "instance_usage": key_usage.get(k, 0),
                "instance_pct": round(usage_pct(k), 1),
                "provenance": derive_provenance(k),
            })
        tiered[tier_name] = {
            "description": defn["description"],
            "key_count": len(defn["keys"]),
            "keys": tier_keys,
        }

    # Verify coverage
    all_tiered_keys = set()
    for tname, t in tiered.items():
        for k in t["keys"]:
            all_tiered_keys.add(k["key"])
    schema_keys = set(schema["all_keys"])
    uncovered = schema_keys - all_tiered_keys
    extra = all_tiered_keys - schema_keys

    # Cross-tier indicators (Axismundi production filter)
    axismundi_safe_filter = {
        "description": "Filter rules for production block themes (e.g., Axismundi) — avoid experimental, FSE-only, plugin-territory features.",
        "exclude_when": {
            "__experimental in {true, 'fse'}": "experimental marker",
            "blockHooks": "new API, not yet in core",
            "render via block.json": "use PHP if needed",
        },
        "tolerable": {
            "ancestor": "rare but stable",
            "selectors": "style engine, stable",
            "variations (JS API)": "stable, but bind via JS not block.json",
        },
    }

    # Composability surface — composability rules synthesized from 3 keys
    composability_synthesis = {
        "ParentConstraint": {
            "source": "parent",
            "semantics": "direct child only",
            "instance_count": instances.get("parent_blocks", 0),
            "example": instances.get("parent_examples", []),
        },
        "AncestorConstraint": {
            "source": "ancestor",
            "semantics": "recursive descendant",
            "instance_count": instances.get("ancestor_blocks", 0),
            "example": instances.get("ancestor_examples", []),
        },
        "AllowedBlocks": {
            "source": "allowedBlocks",
            "semantics": "child whitelist (host-side rule)",
            "instance_count": instances.get("allowedBlocks_used", 0),
        },
    }

    # Asset loading surface (Tier 4 synthesized)
    asset_surface = {
        "EditorAssets": ["editorScript", "editorStyle"],
        "FrontendAssets": ["style", "viewScript", "viewStyle", "viewScriptModule"],
        "BothAssets": ["script"],
        "ServerRender": ["render"],
    }

    # Gaps refinement
    refined_gaps = {
        "G3.9_icon_provenance": {
            "description": "icon is Identity-tier but 118/121 core blocks set it via PHP register_block_type, not block.json. Instance grounding for icon is outside block.json corpus.",
            "impact": "Ontology Icon slot has provenance=schema+php_runtime; instance scan must include PHP files for completeness.",
        },
        "G3.10_runtime_php_dominance": {
            "description": "Tier 4 (Runtime) keys mostly absent in block.json: render/script/viewScript/viewStyle all 0%. Core blocks register render callbacks via PHP. Plugin block.json patterns may differ.",
            "impact": "Ontology Runtime slots are schema-valid but their instance corpus is in PHP, not block.json.",
        },
        "G3.11_apiVersion_as_ontology_boundary": {
            "description": "All core blocks pin to apiVersion=3. Pre-v3 blocks use different attribute parsing semantics (entity vs HTML). Ontology must support multi-version BlockType analysis for plugin block compatibility.",
            "impact": "Ontology slot ApiVersion is a discrete-axis enum; ontology validation rules can differ per version.",
        },
        "G3.12_blockHooks_emerging": {
            "description": "blockHooks (WP 6.4+) is composability hook surface (after/before/firstChild/lastChild) — 0 core usage. Plugin territory. Strategically important for Axismundi/ActivityPub binding.",
            "impact": "Ontology slot PatternAffinity / HookInsertion is currently aspirational; revisit when plugin instance corpus available.",
        },
    }

    out = {
        "schema_version": "v2.0-p3-tier-regroup-2026-05-12",
        "generated_at": "2026-05-12",
        "source_pilot": str(PILOT),
        "tier_definitions": TIER_DEFINITIONS,
        "tiered_keys": tiered,
        "coverage_check": {
            "schema_total_keys": len(schema_keys),
            "tiered_total_keys": len(all_tiered_keys),
            "uncovered_by_tiering": sorted(uncovered),
            "extra_in_tiers_not_in_schema": sorted(extra),
        },
        "composability_synthesis": composability_synthesis,
        "asset_surface": asset_surface,
        "axismundi_safe_filter": axismundi_safe_filter,
        "refined_gaps_p3_supplement": refined_gaps,
    }

    OUT.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT}\n")

    # Summary print
    print("=== 5-Tier Coverage Check ===")
    print(f"  schema total keys:  {len(schema_keys)}")
    print(f"  tiered keys:        {len(all_tiered_keys)}")
    if uncovered:
        print(f"  uncovered:          {sorted(uncovered)}")
    if extra:
        print(f"  extra (not schema): {sorted(extra)}")
    print()
    for tname, t in tiered.items():
        print(f"\n{tname}  ({t['key_count']} keys)")
        for kinfo in t["keys"]:
            marker = "*" if kinfo.get("required") else " "
            prov = kinfo["provenance"]
            pct = kinfo.get("instance_pct", 0)
            print(f"  {marker} {kinfo['key']:20s} {pct:5.1f}%  [{prov}]")


if __name__ == "__main__":
    main()
