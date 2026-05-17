#!/usr/bin/env python3
"""
bridge_gen.py — Build bridge_index.json for future ontology/RAG/DSL use.

For each cleaned MD, emit one entry with:
  - handbook / chapter / sub_chapter / slug / title / source_url
  - path (relative to corpus root)
  - anchors  (H2 + H3 headings as slugified IDs)
  - lang_profile (fence language counts)
  - is_anchor_candidate (DECISIONS.md 6.12 ontology anchor priority)
  - degraded

This is the C-layer prep: even without ontology core, the index can drive
retrieval, link resolution, or DSL mapping immediately.
"""

from __future__ import annotations

import argparse
import json
import re
from collections import Counter
from pathlib import Path


RE_FM_LINE = re.compile(r"^([a-z_][a-z0-9_]*):\s*(.*?)\s*$")
RE_FENCE = re.compile(r"^```([a-z][a-z0-9]*)\s*$")
RE_HEADING = re.compile(r"^(#{2,3})\s+(.+?)\s*$")


# Ontology anchor priority (DECISIONS.md 6.12)
ANCHOR_CANDIDATES: set[tuple[str, str, str | None, str]] = {
    # rest-api (8 chapters from DECISIONS.md 5.5)
    ("rest-api", "using-the-rest-api", None, "authentication"),
    ("rest-api", "using-the-rest-api", None, "global-parameters"),
    ("rest-api", "using-the-rest-api", None, "linking-and-embedding"),
    ("rest-api", "using-the-rest-api", None, "pagination"),
    ("rest-api", "extending-the-rest-api", None, "controller-classes"),
    ("rest-api", "extending-the-rest-api", None, "routes-and-endpoints"),
    ("rest-api", "extending-the-rest-api", None, "schema"),
    ("rest-api", "reference", None, "application-passwords"),
    # block-editor reference anchors
    ("block-editor", "reference-guides", "block-api-reference", "attributes"),
    ("block-editor", "reference-guides", "block-api-reference", "supports"),
    ("block-editor", "reference-guides", "block-api-reference", "deprecation"),
    ("block-editor", "reference-guides", "data-module-reference", "data-core-block-editor"),
    ("block-editor", "reference-guides", "interactivity-api-reference", "directives-and-store"),
    # plugin core anchors
    ("plugin", "hooks", None, "actions"),
    ("plugin", "hooks", None, "filters"),
    ("plugin", "shortcodes", None, "basic-shortcodes"),
    ("plugin", "javascript", None, "ajax"),
    # theme (all theme-json + templates are bridge-critical)
}


def parse_frontmatter(text: str) -> tuple[dict, str]:
    """Return (fields, body)."""
    lines = text.split("\n")
    if not lines or lines[0].strip() != "---":
        return {}, text
    fm: dict = {}
    close = -1
    for i, line in enumerate(lines[1:], start=1):
        if line.strip() == "---":
            close = i
            break
        m = RE_FM_LINE.match(line)
        if not m:
            continue
        k, raw = m.group(1), m.group(2).strip()
        if raw.startswith('"') and raw.endswith('"'):
            raw = raw[1:-1].replace('\\"', '"')
        if raw.lstrip("-").isdigit():
            try:
                fm[k] = int(raw)
                continue
            except ValueError:
                pass
        fm[k] = raw
    body = "\n".join(lines[close + 1:]) if close >= 0 else ""
    return fm, body


def slugify(text: str) -> str:
    """Heading text → slug (matches WP-style anchor IDs)."""
    s = text.lower()
    # Strip code backticks and markdown emphasis
    s = re.sub(r"[`*_~]", "", s)
    # Strip backslash escapes
    s = re.sub(r"\\([_*.~`])", r"\1", s)
    # Strip parenthetical content
    s = re.sub(r"\([^)]*\)", "", s)
    # Replace non-alnum with dash
    s = re.sub(r"[^a-z0-9]+", "-", s).strip("-")
    return s


def extract_anchors_and_langs(body: str) -> tuple[list[str], dict[str, int]]:
    """Walk body once, collecting headings (H2+H3) and fence languages."""
    anchors: list[str] = []
    seen: set[str] = set()
    langs: Counter = Counter()
    in_fence = False
    fence_lang: str | None = None

    for line in body.split("\n"):
        m_fence = RE_FENCE.match(line)
        if m_fence:
            if not in_fence:
                in_fence = True
                fence_lang = m_fence.group(1)
                langs[fence_lang] += 1
            # closing fence (```) handled below
            continue
        if line.strip() == "```":
            in_fence = False
            fence_lang = None
            continue
        if in_fence:
            continue
        m_h = RE_HEADING.match(line)
        if m_h:
            slug = slugify(m_h.group(2))
            if slug and slug not in seen:
                seen.add(slug)
                anchors.append(slug)

    return anchors, dict(langs)


def is_anchor_candidate(fm: dict) -> bool:
    """Check if this page is in the priority anchor list."""
    key = (
        fm.get("handbook"),
        fm.get("chapter"),
        fm.get("sub_chapter") or None,
        fm.get("slug"),
    )
    if key in ANCHOR_CANDIDATES:
        return True
    # Theme: all theme-json + templates pages are anchor candidates
    if fm.get("handbook") == "theme":
        if fm.get("chapter") in ("theme-json", "templates"):
            return True
    return False


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--corpus-root", required=True)
    ap.add_argument("--output", required=True)
    args = ap.parse_args()

    corpus = Path(args.corpus_root)
    entries: list[dict] = []

    for md in sorted(corpus.rglob("*.md")):
        if md.name == "SUMMARY.md":
            continue
        text = md.read_text(encoding="utf-8")
        fm, body = parse_frontmatter(text)
        if not fm:
            continue
        anchors, lang_profile = extract_anchors_and_langs(body)

        entry: dict = {
            "handbook": fm.get("handbook"),
            "chapter": fm.get("chapter"),
            "slug": fm.get("slug"),
            "title": fm.get("title"),
            "source_url": fm.get("source_url"),
            "path": str(md.relative_to(corpus)).replace("\\", "/"),
            "parent_order": fm.get("parent_order"),
            "page_order": fm.get("page_order"),
            "anchors": anchors,
            "lang_profile": lang_profile,
            "is_anchor_candidate": is_anchor_candidate(fm),
            "degraded": fm.get("code_quality") == "degraded",
        }
        # Optional fields
        for opt in ("sub_chapter", "sub_order", "sub_sub_chapter", "sub_sub_order"):
            val = fm.get(opt)
            if val is not None and val != "":
                entry[opt] = val

        entries.append(entry)

    # Summary
    by_handbook: Counter = Counter()
    anchor_candidate_count = 0
    total_anchors = 0
    for e in entries:
        by_handbook[e["handbook"]] += 1
        total_anchors += len(e["anchors"])
        if e["is_anchor_candidate"]:
            anchor_candidate_count += 1

    output = {
        "schema_version": 1,
        "generated_from": str(corpus),
        "summary": {
            "entries_total": len(entries),
            "anchor_candidates": anchor_candidate_count,
            "total_extracted_anchors": total_anchors,
            "by_handbook": dict(by_handbook),
        },
        "entries": entries,
    }

    Path(args.output).write_text(
        json.dumps(output, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    print(f"=== Bridge Index ===")
    print(f"Entries: {len(entries)}")
    print(f"Anchor candidates: {anchor_candidate_count}")
    print(f"Total extracted anchors: {total_anchors}")
    print()
    print("By handbook:")
    for hb, n in sorted(by_handbook.items()):
        print(f"  {hb:14}  {n} entries")
    print()
    print(f"Wrote {args.output}")


if __name__ == "__main__":
    main()
