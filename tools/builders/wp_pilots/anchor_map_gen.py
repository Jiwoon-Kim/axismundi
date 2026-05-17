#!/usr/bin/env python3
"""
v2_0_anchor_map_gen.py — Map 47 anchor-critical pages to repo source paths.

For each anchor page in _meta/v1_1a/anchor_critical_pages.json:
  - Try Gutenberg docs/ first (1순위, block-editor handbook)
  - Try Gutenberg schemas/ if it's a schema-related page
  - Try WP wp-includes/ if it's runtime/registration
  - Mark developer_url fallback if no repo source found

Output: core/wordpress/anchor_to_repo_map.json
"""

import json
import re
from pathlib import Path

ANCHOR_PAGES = Path("_meta/v1_1a/anchor_critical_pages.json")
GB_ROOT = Path("${GUTENBERG_ROOT}")
WP_ROOT = Path("${WORDPRESS_ROOT}")
OUT_PATH = Path("core/wordpress/anchor_to_repo_map.json")


# Hand-curated mappings for block-editor handbook → Gutenberg docs
# corpus path pattern → relative gutenberg/docs path
BLOCK_EDITOR_DOCS_MAP = {
    "01-block-api-reference/attributes.md": "docs/reference-guides/block-api/block-attributes.md",
    "01-block-api-reference/deprecation.md": "docs/reference-guides/block-api/block-deprecation.md",
    "01-block-api-reference/supports.md": "docs/reference-guides/block-api/block-supports.md",
    "01-block-api-reference/registration.md": "docs/reference-guides/block-api/block-registration.md",
    "01-block-api-reference/metadata.md": "docs/reference-guides/block-api/block-metadata.md",
    "01-block-api-reference/edit-and-save.md": "docs/reference-guides/block-api/block-edit-save.md",
    "01-block-api-reference/transforms.md": "docs/reference-guides/block-api/block-transforms.md",
    "01-block-api-reference/templates.md": "docs/reference-guides/block-api/block-templates.md",
    "01-block-api-reference/patterns.md": "docs/reference-guides/block-api/block-patterns.md",
    "01-block-api-reference/styles.md": "docs/reference-guides/block-api/block-styles.md",
    "01-block-api-reference/variations.md": "docs/reference-guides/block-api/block-variations.md",
    "01-block-api-reference/context.md": "docs/reference-guides/block-api/block-context.md",
    "01-block-api-reference/selectors.md": "docs/reference-guides/block-api/block-selectors.md",
    "01-block-api-reference/bindings.md": "docs/reference-guides/block-api/block-bindings.md",
    "01-block-api-reference/annotations.md": "docs/reference-guides/block-api/block-annotations.md",
    "01-block-api-reference/api-versions.md": "docs/reference-guides/block-api/block-api-versions.md",
    "04-interactivity-api-reference/directives-and-store.md": "docs/reference-guides/interactivity-api/directives-and-store.md",
    "04-interactivity-api-reference/iapi-about.md": "docs/reference-guides/interactivity-api/iapi-about.md",
    "04-interactivity-api-reference/core-concepts/client-side-navigation.md": None,  # may not exist
    "10-data-module-reference/data-core-block-editor.md": "docs/reference-guides/data/data-core-block-editor.md",
    "06-theme-json-reference/theme-json-living.md": None,  # generated, see schemas/json/theme.json
}

# theme handbook 일부는 gutenberg/docs/how-to-guides/themes/ 와 cross-reference 가능
# (단, 1:1 source는 아니고 보조 reference)
THEME_HANDBOOK_TO_GB_DOCS = {
    "03-theme-json/01-introduction/introduction-to-theme-json.md": "docs/how-to-guides/themes/global-settings-and-styles.md",
    "03-theme-json/03-styles/applying-styles.md": "docs/how-to-guides/themes/global-settings-and-styles.md",
}

# theme.json schema is direct ground truth for theme-handbook theme.json pages
THEME_HANDBOOK_TO_SCHEMA = {
    "03-styles/styles-reference.md": "schemas/json/theme.json",
    "02-settings/settings-reference.md": "schemas/json/theme.json",
    "02-settings/typography.md": "schemas/json/theme.json#properties/settings/properties/typography",
    "02-settings/color.md": "schemas/json/theme.json#properties/settings/properties/color",
    "02-settings/spacing.md": "schemas/json/theme.json#properties/settings/properties/spacing",
    "02-settings/border.md": "schemas/json/theme.json#properties/settings/properties/border",
    "02-settings/dimensions.md": "schemas/json/theme.json#properties/settings/properties/dimensions",
    "02-settings/layout.md": "schemas/json/theme.json#properties/settings/properties/layout",
    "02-settings/position.md": "schemas/json/theme.json#properties/settings/properties/position",
    "02-settings/shadow.md": "schemas/json/theme.json#properties/settings/properties/shadow",
    "02-settings/lightbox.md": "schemas/json/theme.json#properties/settings/properties/lightbox",
    "02-settings/appearance-tools.md": "schemas/json/theme.json#properties/settings/properties/appearanceTools",
    "02-settings/blocks.md": "schemas/json/theme.json#properties/settings/properties/blocks",
}


def resolve_repo_source(page):
    """Given an anchor page dict, return repo_source mapping(s)."""
    handbook = page["handbook"]
    path = page["path"]
    slug = page["slug"]
    sources = []

    if handbook == "block-editor":
        # Strip the handbook + chapter prefix to get a relative key
        # e.g. block-editor-handbook/03-reference-guides/01-block-api-reference/attributes.md
        # → 01-block-api-reference/attributes.md
        # Also: block-editor-handbook/03-reference-guides/10-data-module-reference/...
        rel_path = path.replace("block-editor-handbook/03-reference-guides/", "")

        if rel_path in BLOCK_EDITOR_DOCS_MAP:
            docs_path = BLOCK_EDITOR_DOCS_MAP[rel_path]
            if docs_path is not None:
                exists = (GB_ROOT / docs_path).exists()
                sources.append({
                    "tier": 1,
                    "type": "gutenberg_docs",
                    "path": docs_path,
                    "exists": exists,
                })

        # block.json schema applies to all block-api pages
        if "block-api-reference" in path:
            sources.append({
                "tier": 2,
                "type": "gutenberg_schema",
                "path": "schemas/json/block.json",
                "exists": (GB_ROOT / "schemas/json/block.json").exists(),
                "note": "block supports/attributes canonical schema",
            })
            # Also instance examples
            sources.append({
                "tier": 3,
                "type": "gutenberg_packages",
                "path": "packages/block-library/src/*/block.json",
                "exists": True,
                "note": "112 core block instances",
            })

        # PHP runtime for supports/registration
        if slug in ("supports", "registration"):
            sources.append({
                "tier": 4,
                "type": "wp_core",
                "path": "wp-includes/class-wp-block-supports.php" if slug == "supports" else "wp-includes/class-wp-block-type.php",
                "exists": (WP_ROOT / ("wp-includes/class-wp-block-supports.php" if slug == "supports" else "wp-includes/class-wp-block-type.php")).exists(),
                "note": "server-side implementation",
            })

    elif handbook == "theme":
        # theme.json schema mapping
        rel_path = path.replace("theme-handbook/03-theme-json/", "")
        if rel_path in THEME_HANDBOOK_TO_SCHEMA:
            sources.append({
                "tier": 2,
                "type": "gutenberg_schema",
                "path": THEME_HANDBOOK_TO_SCHEMA[rel_path],
                "exists": (GB_ROOT / "schemas/json/theme.json").exists(),
                "note": "theme.json canonical schema (with property path)",
            })

        # Some theme handbook pages have cross-reference in gutenberg/docs
        rel_full = path.replace("theme-handbook/", "")
        if rel_full in THEME_HANDBOOK_TO_GB_DOCS:
            docs_path = THEME_HANDBOOK_TO_GB_DOCS[rel_full]
            exists = (GB_ROOT / docs_path).exists()
            sources.append({
                "tier": 1,
                "type": "gutenberg_docs",
                "path": docs_path,
                "exists": exists,
                "note": "cross-reference (not strict 1:1 source; supplementary)",
            })

        # WP core runtime theme.json
        sources.append({
            "tier": 4,
            "type": "wp_core",
            "path": "wp-includes/theme.json",
            "exists": (WP_ROOT / "wp-includes/theme.json").exists(),
            "note": "runtime default theme.json",
        })

    # rest-api / plugin / common-apis: docs not in Gutenberg repo
    # (separate repo: WP-API/docs for REST API, in-tree PHP docblocks for plugin/common-apis)
    # → developer_url fallback as primary
    if not sources:
        sources.append({
            "tier": 5,
            "type": "developer_url",
            "path": None,
            "note": f"No Gutenberg/WP repo source identified; use developer.wordpress.org HTML as primary",
        })

    return sources


def main():
    data = json.loads(ANCHOR_PAGES.read_text())
    pages = data["pages"]
    print(f"Loaded {len(pages)} anchor pages")

    mappings = []
    stats = {
        "tier_1_docs": 0,
        "tier_2_schema": 0,
        "tier_3_packages": 0,
        "tier_4_wp_core": 0,
        "tier_5_developer_url": 0,
        "missing": 0,
    }

    for page in pages:
        sources = resolve_repo_source(page)

        # Tally
        for s in sources:
            key = f"tier_{s['tier']}_{s['type'].replace('gutenberg_', '').replace('wp_', '')}"
            if key not in stats:
                stats[key] = 0
            stats[key] = stats.get(key, 0) + 1
            if s.get("exists") is False and s.get("path"):
                stats["missing"] += 1

        # Determine primary source tier
        primary_tier = min((s["tier"] for s in sources), default=6)

        # source_priority value
        priority_map = {1: "repo_markdown", 2: "repo_schema", 3: "repo_packages",
                        4: "wp_core_php", 5: "developer_url", 6: "corpus_only"}
        priority = priority_map.get(primary_tier, "unknown")

        mappings.append({
            "handbook_path": page["path"],
            "handbook": page["handbook"],
            "slug": page["slug"],
            "title": page["title"],
            "priority": page["priority"],
            "anchors_count": page["anchors_count"],
            "inbound_links": page["inbound_links"],
            "source_priority": priority,
            "repo_sources": sources,
        })

    out = {
        "schema_version": "v2.0-anchor-map-2026-05-12",
        "generated_at": "2026-05-12",
        "baseline": {
            "wordpress": "6.9.4 @ 97b7f62a",
            "gutenberg": "v21.9.0 @ ccb651bc",
        },
        "summary": {
            "total_anchors": len(pages),
            **stats,
        },
        "mappings": mappings,
    }

    OUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUT_PATH.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n")
    print(f"Wrote {OUT_PATH}")
    print()
    print("=== Source priority distribution ===")
    by_priority = {}
    for m in mappings:
        by_priority[m["source_priority"]] = by_priority.get(m["source_priority"], 0) + 1
    for p, c in sorted(by_priority.items(), key=lambda x: -x[1]):
        print(f"  {p:20s}: {c}")
    print()
    print("=== Stats ===")
    for k, v in stats.items():
        print(f"  {k}: {v}")


if __name__ == "__main__":
    main()
