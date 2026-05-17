#!/usr/bin/env python3
"""
parse_chapters.py — Build handbook manifest by parsing Chapters.md + filesystem walk.

Strategy:
  1. Parse Chapters.md to extract per-handbook ordered chapter and subpage titles.
  2. Walk dev_handbook/<handbook>/ for actual MD files.
  3. For each file:
     - Derive handbook from path
     - Derive parent_order from directory or file numeric prefix
     - Derive chapter slug from directory name (or self for standalone)
     - Extract source_url from top of file
     - Extract H1 title from file
     - For subpage files: match H1 to Chapters.md title list → page_order
     - For chapter index files: page_order = 0

Outputs JSON manifest in the format expected by clean_handbook.py.

Usage:
  python parse_chapters.py --chapters dev_handbook/Chapters.md \\
      --handbook common-apis --handbook-dir dev_handbook/common-apis-handbook \\
      --output scripts/manifest_common_apis.json
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path


# Mapping from filesystem handbook directory name -> Chapters.md H1
HANDBOOK_H1 = {
    "common-apis": "Common APIs Handbook",
    "rest-api": "REST API Handbook",
    "block-editor": "Block Editor Handbook",
    "plugin": "Plugin Handbook",
    "theme": "Theme Handbook",
}

# Reverse for slug -> dir suffix
HANDBOOK_DIR_SUFFIX = "-handbook"


# ---------------------------------------------------------------------------
# Chapters.md parser
# ---------------------------------------------------------------------------

def parse_chapters(chapters_path: Path) -> dict:
    """
    Parse Chapters.md into:
      {handbook_slug: [
          {"title": "Hooks", "subpages": ["Action Reference", "Filter Reference"]},
          ...
      ]}
    """
    text = chapters_path.read_text(encoding="utf-8").replace("\r\n", "\n")
    result: dict[str, list[dict]] = {}

    # Split on `# Title` H1 sections
    # Each handbook section ends at next `# Title` or EOF
    h1_pattern = re.compile(r"^# (.+)$", re.MULTILINE)
    h1_matches = list(h1_pattern.finditer(text))

    # Build slug-from-H1 reverse lookup
    h1_to_slug = {v: k for k, v in HANDBOOK_H1.items()}

    for i, m in enumerate(h1_matches):
        h1_title = m.group(1).strip()
        if h1_title not in h1_to_slug:
            continue
        slug = h1_to_slug[h1_title]

        # Section body: from after H1 to next H1 (or EOF)
        start = m.end()
        end = h1_matches[i + 1].start() if i + 1 < len(h1_matches) else len(text)
        body = text[start:end]

        # Find `## Chapters` and parse list below it
        chapters_match = re.search(r"^## Chapters\s*$", body, re.MULTILINE)
        if not chapters_match:
            result[slug] = []
            continue

        list_body = body[chapters_match.end():]
        chapters = _parse_bullet_list(list_body, h1_title)
        result[slug] = chapters

    return result


def _parse_bullet_list(text: str, handbook_h1: str) -> list[dict]:
    """
    Parse a multi-depth bullet list into a tree structure.

    Indent convention (Chapters.md):
      `- Title`             — depth 0 (top-level chapter)
      `    - Title`         — depth 1 (sub-chapter / subpage)
      `        - Title`     — depth 2 (page under sub-chapter)
      `            - Title` — depth 3 (rare, e.g., interactivity core concepts)

    Returns ordered list of top-level dicts with nested `children`:
        [
          {"title": "Getting Started", "depth": 0, "children": [
            {"title": "Block Development Environment", "depth": 1, "children": [
              {"title": "Node.js development environment", "depth": 2, "children": []},
              ...
            ]},
            ...
          ]},
          ...
        ]
    """
    chapters: list[dict] = []
    # stop at next `---` horizontal rule
    stop_match = re.search(r"^---\s*$", text, re.MULTILINE)
    if stop_match:
        text = text[:stop_match.start()]

    # Stack: list of (depth, children_list_to_append_to)
    stack: list[tuple[int, list[dict]]] = [(-1, chapters)]

    for raw_line in text.split("\n"):
        line = raw_line.rstrip()
        if not line.strip():
            continue
        # Skip handbook self-title bullet
        if line.strip() in (
            f"- {handbook_h1}",
            f"- {handbook_h1.replace(' Handbook', '')}",
            handbook_h1,
            handbook_h1.replace(" Handbook", ""),
        ):
            continue

        m = re.match(r"^( *)- (.+?)\s*$", line)
        if not m:
            continue
        indent = len(m.group(1))
        title = m.group(2).strip()
        # depth: 0 = top-level (0 spaces), 1 = 4 spaces, 2 = 8 spaces, ...
        depth = indent // 4

        node = {"title": title, "depth": depth, "children": []}

        # Pop stack until we find parent (depth - 1)
        while stack and stack[-1][0] >= depth:
            stack.pop()
        if stack:
            stack[-1][1].append(node)
        else:
            chapters.append(node)
        stack.append((depth, node["children"]))

    return chapters


# ---------------------------------------------------------------------------
# Filesystem walker + manifest builder
# ---------------------------------------------------------------------------

RE_TOP_URL = re.compile(r"^https?://developer\.wordpress\.org/\S+\s*$")
RE_H1 = re.compile(r"^#\s+(.+?)\s*$")


def extract_url_and_title(md_path: Path) -> tuple[str | None, str | None]:
    """Return (source_url, h1_title) by reading top of file."""
    text = md_path.read_text(encoding="utf-8").replace("\r\n", "\n")
    lines = text.split("\n")

    url = None
    title = None
    for line in lines[:30]:
        if url is None and RE_TOP_URL.match(line.strip()):
            url = line.strip()
        if title is None:
            m = RE_H1.match(line)
            if m:
                title = m.group(1).strip()
        if url and title:
            break
    return url, title


def slugify(title: str) -> str:
    """Approximate slug for title (kebab-case, lowercase, alphanumeric only)."""
    return re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")


def derive_path_components(rel_path: str) -> dict:
    """
    Given a relative path inside <handbook>-handbook/, return path-derived info.

    Wrapping directories (without numeric prefix, e.g., `components/`, `packages/`)
    are flattened — they exist as filesystem grouping but are not IA levels.
    Example: `03-reference-guides/08-component-reference/components/button.md`
    becomes effectively a 3-deep IA path:
      chapter=reference-guides, sub_chapter=component-reference, slug=button.
    """
    parts = rel_path.split("/")
    # Pre-filter: drop intermediate "wrapping" dirs (those without N- prefix and not the leaf file)
    filtered_parts: list[str] = []
    for i, p in enumerate(parts):
        is_last = (i == len(parts) - 1)
        if is_last:
            filtered_parts.append(p)
            continue
        # Skip intermediate dirs without numeric prefix (wrapping dirs)
        if re.match(r"^\d+-", p):
            filtered_parts.append(p)
        # else: skip silently
    parts = filtered_parts

    depth = len(parts)
    result = {
        "depth": depth,
        "chapter": "",
        "sub_chapter": "",
        "sub_sub_chapter": "",
        "slug": "",
        "parent_order": 0,
        "sub_order": None,
        "sub_sub_order": None,
        "is_index": False,
        "is_chapter_root": False,
    }

    def split_numbered(name: str) -> tuple[int | None, str]:
        m = re.match(r"^(\d+)-(.+)$", name)
        if m:
            return int(m.group(1)), m.group(2)
        return None, name

    if depth == 1:
        # Standalone top-level file
        stem = parts[0].removesuffix(".md")
        order, slug = split_numbered(stem)
        if order is not None:
            result["parent_order"] = order
            if slug.endswith("-handbook"):
                result["slug"] = slug
            else:
                result["chapter"] = slug
                result["slug"] = slug
                result["is_chapter_root"] = True
        else:
            result["slug"] = stem
        return result

    # First component is chapter dir
    chap_order, chap_slug = split_numbered(parts[0])
    if chap_order is not None:
        result["parent_order"] = chap_order
        result["chapter"] = chap_slug

    if depth == 2:
        # XX-chapter/page.md
        stem = parts[1].removesuffix(".md")
        _, slug = split_numbered(stem)
        result["slug"] = slug
        result["is_index"] = (stem == "index")
        return result

    # depth >= 3: sub-chapter present
    sub_order, sub_slug = split_numbered(parts[1])
    if sub_order is not None:
        result["sub_order"] = sub_order
        result["sub_chapter"] = sub_slug
    else:
        result["sub_chapter"] = parts[1]

    if depth == 3:
        stem = parts[2].removesuffix(".md")
        _, slug = split_numbered(stem)
        result["slug"] = slug
        result["is_index"] = (stem == "index")
        return result

    # depth == 4 (truly 4-deep IA, e.g., interactivity-api/01-core-concepts/...)
    sub_sub_order, sub_sub_slug = split_numbered(parts[2])
    if sub_sub_order is not None:
        result["sub_sub_order"] = sub_sub_order
        result["sub_sub_chapter"] = sub_sub_slug
    else:
        result["sub_sub_chapter"] = parts[2]

    stem = parts[-1].removesuffix(".md")
    _, slug = split_numbered(stem)
    result["slug"] = slug
    result["is_index"] = (stem == "index")
    return result


def title_match_score(file_title: str, chapter_title: str) -> int:
    """
    Score how well file_title matches chapter_title. Higher = better.
    """
    if file_title is None or chapter_title is None:
        return 0
    a = file_title.lower().strip()
    b = chapter_title.lower().strip()
    if a == b:
        return 1000
    a_norm = re.sub(r"['\u2019]", "", a)
    b_norm = re.sub(r"['\u2019]", "", b)
    if a_norm == b_norm:
        return 990
    a_slug = slugify(a)
    b_slug = slugify(b)
    if a_slug == b_slug:
        return 950
    if a_slug in b_slug or b_slug in a_slug:
        return 500
    return 0


def find_node_by_title(nodes: list[dict], title: str, min_score: int = 500) -> int:
    """Return index of best-matching node in `nodes`, or -1 if no good match."""
    best_idx = -1
    best_score = 0
    for i, n in enumerate(nodes):
        score = title_match_score(title, n["title"])
        if score > best_score:
            best_score = score
            best_idx = i
    return best_idx if best_score >= min_score else -1


def find_node_by_slug(nodes: list[dict], target_slug: str, min_score: int = 500) -> int:
    """Match by slugified title."""
    best_idx = -1
    best_score = 0
    for i, n in enumerate(nodes):
        node_slug = slugify(n["title"])
        if node_slug == target_slug:
            return i
        if target_slug in node_slug or node_slug in target_slug:
            score = 500
            if score > best_score:
                best_score = score
                best_idx = i
    return best_idx if best_score >= min_score else -1


def build_manifest(
    handbook_slug: str,
    handbook_dir: Path,
    chapters_ia: list[dict],
) -> dict:
    """
    Walk handbook_dir for *.md files; produce manifest entries keyed by
    relative path (relative to dev_handbook/).
    """
    manifest: dict[str, dict] = {}
    md_files = sorted(handbook_dir.rglob("*.md"))

    for md_path in md_files:
        rel_to_handbook = md_path.relative_to(handbook_dir)
        rel_str = str(rel_to_handbook).replace("\\", "/")

        if md_path.name in ("README.md", "Chapters.md"):
            continue
        if rel_str.startswith(("meta/", "templates/", "assets/")):
            continue

        url, file_title = extract_url_and_title(md_path)
        comp = derive_path_components(rel_str)

        # ---- Resolve page_order by walking Chapters.md tree ----
        page_order = None

        if comp["depth"] == 1:
            if comp["slug"].endswith("-handbook"):
                # Handbook root
                page_order = 0
                comp["chapter"] = ""
            else:
                # Standalone chapter
                page_order = 0
        else:
            # Need to find chapter node in chapters_ia
            chap_idx = find_node_by_slug(chapters_ia, comp["chapter"])
            if chap_idx == -1 and file_title:
                chap_idx = find_node_by_title(chapters_ia, file_title)

            if chap_idx >= 0:
                chap_node = chapters_ia[chap_idx]

                if comp["depth"] == 2:
                    # XX-chapter/page.md — page is a child of chapter
                    if comp["is_index"]:
                        page_order = 0
                    elif chap_node["children"]:
                        # Match by file_title or slug
                        match_titles = []
                        if file_title:
                            match_titles.append(file_title)
                        if url:
                            last_seg = url.rstrip("/").split("/")[-1]
                            match_titles.append(last_seg.replace("-", " "))
                        match_titles.append(comp["slug"].replace("-", " "))
                        # Try each candidate; first hit wins
                        for mt in match_titles:
                            idx = find_node_by_title(chap_node["children"], mt)
                            if idx >= 0:
                                page_order = idx + 1
                                break

                elif comp["depth"] >= 3:
                    # Find sub-chapter node
                    sub_idx = find_node_by_slug(chap_node["children"], comp["sub_chapter"])
                    if sub_idx >= 0:
                        sub_node = chap_node["children"][sub_idx]
                        # If sub_order wasn't in path, derive from tree position
                        if comp["sub_order"] is None:
                            comp["sub_order"] = sub_idx + 1

                        if comp["depth"] == 3:
                            if comp["is_index"]:
                                page_order = 0
                            elif sub_node["children"]:
                                match_titles = []
                                if file_title:
                                    match_titles.append(file_title)
                                if url:
                                    last_seg = url.rstrip("/").split("/")[-1]
                                    match_titles.append(last_seg.replace("-", " "))
                                match_titles.append(comp["slug"].replace("-", " "))
                                for mt in match_titles:
                                    idx = find_node_by_title(sub_node["children"], mt)
                                    if idx >= 0:
                                        page_order = idx + 1
                                        break
                        elif comp["depth"] == 4:
                            sub_sub_idx = find_node_by_slug(sub_node["children"], comp["sub_sub_chapter"])
                            if sub_sub_idx >= 0:
                                ss_node = sub_node["children"][sub_sub_idx]
                                if comp["is_index"]:
                                    page_order = 0
                                elif ss_node["children"]:
                                    match_titles = [file_title] if file_title else []
                                    match_titles.append(comp["slug"].replace("-", " "))
                                    for mt in match_titles:
                                        idx = find_node_by_title(ss_node["children"], mt)
                                        if idx >= 0:
                                            page_order = idx + 1
                                            break

        if page_order is None:
            # Fallback: alphabetical position within file's own directory,
            # offset by 1000 to avoid colliding with Chapters.md-matched entries.
            siblings = sorted([p.name for p in md_path.parent.glob("*.md")])
            page_order = 1000 + siblings.index(md_path.name) + 1
            print(f"WARN: fallback alphabetical page_order for {rel_str}", file=sys.stderr)

        # ---- Build manifest entry ----
        manifest_key = f"{handbook_slug}-handbook/{rel_str}"
        entry: dict = {
            "handbook": handbook_slug,
            "chapter": comp["chapter"],
            "slug": comp["slug"],
            "parent_order": comp["parent_order"],
            "page_order": page_order,
        }
        if comp["sub_chapter"]:
            entry["sub_chapter"] = comp["sub_chapter"]
            if comp["sub_order"] is not None:
                entry["sub_order"] = comp["sub_order"]
        if comp.get("sub_sub_chapter"):
            entry["sub_sub_chapter"] = comp["sub_sub_chapter"]
            if comp.get("sub_sub_order") is not None:
                entry["sub_sub_order"] = comp["sub_sub_order"]
        if url:
            entry["source_url"] = url
        if file_title:
            entry["title"] = file_title

        manifest[manifest_key] = entry

    return manifest


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--chapters", required=True, help="Path to Chapters.md")
    ap.add_argument("--handbook", required=True, choices=list(HANDBOOK_H1.keys()))
    ap.add_argument("--handbook-dir", required=True, help="Path to dev_handbook/<handbook>-handbook/")
    ap.add_argument("--output", required=True, help="Manifest JSON output path")
    args = ap.parse_args()

    chapters_path = Path(args.chapters)
    handbook_dir = Path(args.handbook_dir)

    all_chapters_ia = parse_chapters(chapters_path)
    chapters_ia = all_chapters_ia.get(args.handbook, [])
    if not chapters_ia:
        print(f"WARN: no chapters parsed for handbook '{args.handbook}'", file=sys.stderr)

    manifest = build_manifest(args.handbook, handbook_dir, chapters_ia)

    out_path = Path(args.output)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    print(f"Wrote {len(manifest)} entries to {out_path}")


if __name__ == "__main__":
    main()
