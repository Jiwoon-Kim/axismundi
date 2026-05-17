#!/usr/bin/env python3
"""
global_audit.py — Final structural audit before v1.0 freeze.

Walks dev_handbook_clean/, checks every cleaned MD for:

  Metadata completeness:
    - Frontmatter presence
    - Required fields (source_url, synced, handbook, chapter, slug,
      parent_order, page_order, title)
    - Optional fields (sub_chapter, sub_order, sub_sub_chapter, sub_sub_order,
      code_quality, code_issue)

  Structural integrity:
    - page_order collisions within same (handbook, chapter, sub_chapter,
      sub_sub_chapter) tuple
    - fallback page_order (>=1000) inventory
    - Title escape leftovers (markdown \\_, \\*, etc.)
    - degraded flag statistics

  Anomalies:
    - Files without frontmatter
    - Files with malformed YAML
    - Duplicate (handbook, chapter, slug) combinations
    - Body H1 vs frontmatter title mismatches (info, not error)

Writes structural_audit.json with full results.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path


RE_FM_LINE = re.compile(r"^([a-z_][a-z0-9_]*):\s*(.*?)\s*$")
RE_H1 = re.compile(r"^#\s+(.+?)\s*$")
RE_TITLE_ESCAPE = re.compile(r"\\([_*.~`])")


REQUIRED_FIELDS = (
    "source_url", "synced", "handbook", "chapter", "slug",
    "parent_order", "page_order", "title",
)
OPTIONAL_FIELDS = (
    "sub_chapter", "sub_order", "sub_sub_chapter", "sub_sub_order",
    "code_quality", "code_issue",
)
KNOWN_FIELDS = set(REQUIRED_FIELDS) | set(OPTIONAL_FIELDS)


def parse_frontmatter(text: str) -> tuple[dict, list[str], str]:
    """
    Return (fields, errors, body).
    errors is a list of YAML parse anomalies.
    body is the text after the closing ---.
    """
    errors: list[str] = []
    lines = text.split("\n")

    if not lines or lines[0].strip() != "---":
        errors.append("no_frontmatter_open")
        return {}, errors, text

    fields: dict = {}
    close_idx = -1
    for i, line in enumerate(lines[1:], start=1):
        if line.strip() == "---":
            close_idx = i
            break
        m = RE_FM_LINE.match(line)
        if not m:
            if line.strip():
                errors.append(f"malformed_yaml_line_{i}: {line[:60]!r}")
            continue
        key, raw = m.group(1), m.group(2)
        # Strip surrounding quotes from value
        val: str = raw.strip()
        if val.startswith('"') and val.endswith('"'):
            val = val[1:-1].replace('\\"', '"')
        # Numeric coercion
        if val.isdigit() or (val.startswith("-") and val[1:].isdigit()):
            try:
                fields[key] = int(val)
                continue
            except ValueError:
                pass
        fields[key] = val

    if close_idx == -1:
        errors.append("no_frontmatter_close")
        return fields, errors, ""

    body = "\n".join(lines[close_idx + 1:])
    return fields, errors, body


def audit_file(md_path: Path) -> dict:
    """Return per-file audit dict."""
    text = md_path.read_text(encoding="utf-8")
    fm, errors, body = parse_frontmatter(text)

    # Required field presence
    missing_required = [f for f in REQUIRED_FIELDS if f not in fm]
    # Unknown fields (would indicate schema drift)
    unknown_fields = [k for k in fm if k not in KNOWN_FIELDS]
    # Title escape leftovers
    title = fm.get("title", "")
    title_escape_leftover = bool(RE_TITLE_ESCAPE.search(title) if isinstance(title, str) else False)
    # Body H1 vs frontmatter title (informational)
    body_h1 = None
    for line in body.split("\n")[:15]:
        m = RE_H1.match(line)
        if m:
            body_h1 = m.group(1).strip()
            break

    return {
        "path": str(md_path),
        "fm": fm,
        "errors": errors,
        "missing_required": missing_required,
        "unknown_fields": unknown_fields,
        "title_escape_leftover": title_escape_leftover,
        "body_h1": body_h1,
    }


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--target-root", required=True, help="dev_handbook_clean root")
    ap.add_argument("--output", required=True, help="structural_audit.json path")
    args = ap.parse_args()

    target = Path(args.target_root)
    md_files = sorted(p for p in target.rglob("*.md") if p.name != "SUMMARY.md")

    files_info: list[dict] = []
    parse_errors: list[dict] = []
    missing_field_files: list[dict] = []
    title_escape_files: list[dict] = []
    unknown_field_files: list[dict] = []
    fallback_entries: list[dict] = []
    degraded_files: list[dict] = []
    body_h1_mismatches: list[dict] = []

    # Group by (handbook, chapter, sub_chapter, sub_sub_chapter) for collision detection
    group_map: dict[tuple, list[dict]] = defaultdict(list)
    # Detect duplicate (handbook, chapter, slug) (looser key for slug-uniqueness within chapter)
    slug_group: dict[tuple, list[str]] = defaultdict(list)
    # Per-handbook stats
    handbook_stats: dict[str, dict] = defaultdict(lambda: {
        "total": 0, "degraded": 0, "fallback": 0, "missing_fields": 0,
        "title_escape": 0, "parse_errors": 0,
    })

    for md in md_files:
        info = audit_file(md)
        files_info.append(info)
        fm = info["fm"]
        rel = str(md.relative_to(target))

        hb = fm.get("handbook", "?")
        handbook_stats[hb]["total"] += 1

        if info["errors"]:
            parse_errors.append({"path": rel, "errors": info["errors"]})
            handbook_stats[hb]["parse_errors"] += 1
        if info["missing_required"]:
            missing_field_files.append({"path": rel, "missing": info["missing_required"]})
            handbook_stats[hb]["missing_fields"] += 1
        if info["title_escape_leftover"]:
            title_escape_files.append({"path": rel, "title": fm.get("title")})
            handbook_stats[hb]["title_escape"] += 1
        if info["unknown_fields"]:
            unknown_field_files.append({"path": rel, "unknown": info["unknown_fields"]})

        page_order = fm.get("page_order", 0)
        if isinstance(page_order, int) and page_order >= 1000:
            fallback_entries.append({
                "path": rel, "page_order": page_order,
                "chapter": fm.get("chapter"), "sub_chapter": fm.get("sub_chapter"),
                "slug": fm.get("slug"),
            })
            handbook_stats[hb]["fallback"] += 1
        if fm.get("code_quality") == "degraded":
            degraded_files.append({"path": rel, "code_issue": fm.get("code_issue")})
            handbook_stats[hb]["degraded"] += 1

        # Collision detection key
        group_key = (
            fm.get("handbook"), fm.get("chapter"),
            fm.get("sub_chapter") or "", fm.get("sub_sub_chapter") or "",
        )
        group_map[group_key].append({
            "path": rel, "page_order": page_order, "slug": fm.get("slug"),
        })

        # Slug uniqueness within full hierarchy (5-tuple)
        slug_key = (
            fm.get("handbook"), fm.get("chapter"),
            fm.get("sub_chapter") or "", fm.get("sub_sub_chapter") or "",
            fm.get("slug"),
        )
        slug_group[slug_key].append(rel)

        # Body H1 vs title (informational)
        body_h1 = info["body_h1"]
        title = fm.get("title")
        if body_h1 and title and isinstance(title, str):
            # Normalize both for comparison
            norm_h1 = re.sub(r"\\([_*.~`])", r"\1", body_h1).strip()
            if norm_h1 != title.strip():
                body_h1_mismatches.append({
                    "path": rel, "frontmatter_title": title, "body_h1": body_h1,
                })

    # Detect page_order collisions within group (page_order < 1000 only — fallback >=1000 OK)
    collisions: list[dict] = []
    for gk, items in group_map.items():
        po_counts: dict[int, list[str]] = defaultdict(list)
        for it in items:
            po = it["page_order"] if isinstance(it["page_order"], int) else -1
            if po < 1000:
                po_counts[po].append(it["slug"])
        for po, slugs in po_counts.items():
            if len(slugs) > 1:
                collisions.append({
                    "handbook": gk[0], "chapter": gk[1],
                    "sub_chapter": gk[2], "sub_sub_chapter": gk[3],
                    "page_order": po, "slugs": slugs,
                })

    # Slug duplicates (same handbook + chapter + slug)
    slug_duplicates = [
        {"key": list(k), "paths": v}
        for k, v in slug_group.items() if len(v) > 1
    ]

    output = {
        "schema_version": 1,
        "generated_at_input": args.target_root,
        "summary": {
            "files_total": len(md_files),
            "files_with_parse_errors": len(parse_errors),
            "files_missing_required_fields": len(missing_field_files),
            "files_title_escape_leftover": len(title_escape_files),
            "files_unknown_fields": len(unknown_field_files),
            "files_degraded": len(degraded_files),
            "files_fallback_page_order": len(fallback_entries),
            "page_order_collisions": len(collisions),
            "slug_duplicates": len(slug_duplicates),
            "body_h1_vs_title_mismatches": len(body_h1_mismatches),
        },
        "by_handbook": dict(handbook_stats),
        "parse_errors": parse_errors,
        "missing_field_files": missing_field_files,
        "title_escape_files": title_escape_files,
        "unknown_field_files": unknown_field_files,
        "fallback_entries": fallback_entries,
        "page_order_collisions": collisions,
        "slug_duplicates": slug_duplicates,
        "body_h1_mismatches_count": len(body_h1_mismatches),
        # Don't dump all body h1 mismatches (could be many); just first 30 as sample
        "body_h1_mismatches_sample": body_h1_mismatches[:30],
    }

    Path(args.output).write_text(
        json.dumps(output, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    # Console summary
    s = output["summary"]
    print(f"=== Global Structural Audit ===")
    print(f"Files: {s['files_total']}")
    print(f"  parse errors:           {s['files_with_parse_errors']}")
    print(f"  missing required:       {s['files_missing_required_fields']}")
    print(f"  title escape leftover:  {s['files_title_escape_leftover']}")
    print(f"  unknown fields:         {s['files_unknown_fields']}")
    print(f"  degraded:               {s['files_degraded']}")
    print(f"  fallback page_order:    {s['files_fallback_page_order']}")
    print(f"  page_order collisions:  {s['page_order_collisions']}")
    print(f"  slug duplicates:        {s['slug_duplicates']}")
    print(f"  H1 vs title mismatches: {s['body_h1_vs_title_mismatches']}")
    print()
    print("By handbook:")
    for hb, st in handbook_stats.items():
        print(f"  {hb:14}  total={st['total']:3}  degraded={st['degraded']:3}  "
              f"fallback={st['fallback']:2}  missing={st['missing_fields']:2}  "
              f"escape={st['title_escape']:2}  parse_err={st['parse_errors']:2}")


if __name__ == "__main__":
    main()
