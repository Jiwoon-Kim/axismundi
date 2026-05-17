#!/usr/bin/env python3
"""
summary_gen.py — Generate SUMMARY.md indexes for each handbook + corpus root.

Reads frontmatter from every cleaned MD to build an ordered nested list,
sorted by (parent_order, sub_order or 0, sub_sub_order or 0, page_order).

Produces:
  dev_handbook_clean/SUMMARY.md                 # corpus-level index
  dev_handbook_clean/<handbook>/SUMMARY.md      # per-handbook index

Idempotent: regenerates from current frontmatter every run.
"""

from __future__ import annotations

import argparse
import os
import re
from collections import defaultdict
from pathlib import Path


RE_FM_LINE = re.compile(r"^([a-z_][a-z0-9_]*):\s*(.*?)\s*$")

HANDBOOK_LABELS = {
    "common-apis": "Common APIs Handbook",
    "rest-api": "REST API Handbook",
    "block-editor": "Block Editor Handbook",
    "plugin": "Plugin Handbook",
    "theme": "Theme Handbook",
}


def parse_frontmatter(text: str) -> dict:
    lines = text.split("\n")
    if not lines or lines[0].strip() != "---":
        return {}
    fields: dict = {}
    for line in lines[1:30]:
        if line.strip() == "---":
            break
        m = RE_FM_LINE.match(line)
        if not m:
            continue
        key, raw = m.group(1), m.group(2).strip()
        if raw.startswith('"') and raw.endswith('"'):
            raw = raw[1:-1].replace('\\"', '"')
        if raw.lstrip("-").isdigit():
            try:
                fields[key] = int(raw)
                continue
            except ValueError:
                pass
        fields[key] = raw
    return fields


def humanize(slug: str) -> str:
    """Convert kebab-case slug to Title Case."""
    return " ".join(w.capitalize() for w in slug.replace("_", "-").split("-"))


def sort_key(entry: dict) -> tuple:
    return (
        entry.get("parent_order", 0),
        entry.get("sub_order") or 0,
        entry.get("sub_sub_order") or 0,
        entry.get("page_order", 0),
    )


def build_handbook_summary(handbook_root: Path, hb_slug: str) -> str:
    """Build SUMMARY.md text for one handbook."""
    entries = []
    for md in handbook_root.rglob("*.md"):
        if md.name == "SUMMARY.md":
            continue
        text = md.read_text(encoding="utf-8")
        fm = parse_frontmatter(text)
        if not fm:
            continue
        if fm.get("handbook") != hb_slug:
            continue
        rel = md.relative_to(handbook_root)
        entries.append({
            "rel_path": str(rel).replace("\\", "/"),
            "title": fm.get("title", md.stem),
            "chapter": fm.get("chapter", ""),
            "sub_chapter": fm.get("sub_chapter"),
            "sub_sub_chapter": fm.get("sub_sub_chapter"),
            "slug": fm.get("slug"),
            "parent_order": fm.get("parent_order", 0),
            "sub_order": fm.get("sub_order"),
            "sub_sub_order": fm.get("sub_sub_order"),
            "page_order": fm.get("page_order", 0),
            "degraded": fm.get("code_quality") == "degraded",
        })

    entries.sort(key=sort_key)

    lines: list[str] = []
    lines.append(f"# {HANDBOOK_LABELS.get(hb_slug, hb_slug.title())}")
    lines.append("")
    lines.append(f"_Auto-generated index. {len(entries)} pages._")
    lines.append("")

    # Group: by chapter > sub_chapter > sub_sub_chapter
    current_chapter = None
    current_sub = None
    current_sub_sub = None

    for e in entries:
        chap = e["chapter"]
        sub = e["sub_chapter"]
        sub_sub = e["sub_sub_chapter"]

        if chap != current_chapter:
            current_chapter = chap
            current_sub = None
            current_sub_sub = None
            label = humanize(chap) if chap else "Overview"
            lines.append(f"## {e['parent_order']}. {label}")
            lines.append("")

        if sub and sub != current_sub:
            current_sub = sub
            current_sub_sub = None
            lines.append(f"### {e['parent_order']}.{e.get('sub_order','?')}. {humanize(sub)}")
            lines.append("")
        elif not sub and current_sub is not None:
            current_sub = None
            current_sub_sub = None

        if sub_sub and sub_sub != current_sub_sub:
            current_sub_sub = sub_sub
            lines.append(f"#### {e['parent_order']}.{e.get('sub_order','?')}.{e.get('sub_sub_order','?')}. {humanize(sub_sub)}")
            lines.append("")
        elif not sub_sub and current_sub_sub is not None:
            current_sub_sub = None

        title = e["title"]
        marker = " ⚠" if e["degraded"] else ""
        lines.append(f"- [{title}](./{e['rel_path']}){marker}")

    lines.append("")
    return "\n".join(lines)


def build_corpus_summary(corpus_root: Path) -> str:
    """Build top-level SUMMARY.md across all handbooks."""
    lines: list[str] = []
    lines.append("# WordPress Handbooks Corpus")
    lines.append("")
    lines.append("_Auto-generated index across 5 WordPress developer handbooks._")
    lines.append("")
    lines.append("## Handbooks")
    lines.append("")

    handbook_dirs = sorted([d for d in corpus_root.iterdir() if d.is_dir()])
    for hb_dir in handbook_dirs:
        # Strip "-handbook" suffix to get the canonical slug
        hb_slug = hb_dir.name.removesuffix("-handbook")
        label = HANDBOOK_LABELS.get(hb_slug, hb_slug.title())
        page_count = sum(1 for _ in hb_dir.rglob("*.md") if _.name != "SUMMARY.md")
        lines.append(f"- [{label}](./{hb_dir.name}/SUMMARY.md) ({page_count} pages)")

    lines.append("")
    lines.append("## Reference")
    lines.append("")
    lines.append("- `../CHANGELOG.md` — version history across phases")
    lines.append("- `../CONTEXT.md` — current state snapshot")
    lines.append("- `../DECISIONS.md` — architecture decision record (v1.0 freeze)")
    lines.append("- `../_meta/fence_lang_audit.json` — code fence language distribution")
    lines.append("- `../_meta/structural_audit.json` — metadata integrity report")
    lines.append("- `../_meta/broken_links.json` — unresolved internal links")
    lines.append("- `../_meta/bridge_index.json` — ontology bridge index (anchors, lang profiles)")
    lines.append("")
    return "\n".join(lines)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--corpus-root", required=True, help="dev_handbook_clean root")
    args = ap.parse_args()

    corpus = Path(args.corpus_root)

    # Per-handbook SUMMARY.md
    for hb_dir in sorted(corpus.iterdir()):
        if not hb_dir.is_dir():
            continue
        hb_slug = hb_dir.name.removesuffix("-handbook")
        summary_text = build_handbook_summary(hb_dir, hb_slug)
        summary_path = hb_dir / "SUMMARY.md"
        summary_path.write_text(summary_text + "\n", encoding="utf-8")
        print(f"Wrote {summary_path.relative_to(corpus.parent)}")

    # Top-level SUMMARY.md
    corpus_summary = build_corpus_summary(corpus)
    top_summary = corpus / "SUMMARY.md"
    top_summary.write_text(corpus_summary + "\n", encoding="utf-8")
    print(f"Wrote {top_summary.relative_to(corpus.parent)}")


if __name__ == "__main__":
    main()
