#!/usr/bin/env python3
"""
audit_fences.py — Audit fence language distribution across cleaned handbooks.

Walks dev_handbook_clean/, counts ```lang fences per:
  - handbook
  - handbook/chapter
  - handbook/file (top 20)
  - degraded vs clean files

Outputs a JSON snapshot (fence_lang_audit.json by default) that accumulates
across phases. Useful for spotting drift as new handbooks are processed.

Usage:
  python audit_fences.py --target-root dev_handbook_clean \\
      --output fence_lang_audit.json
"""

from __future__ import annotations

import argparse
import json
import re
from collections import Counter, defaultdict
from datetime import date
from pathlib import Path


RE_FENCE = re.compile(r"^```([a-z][a-z0-9]*)\s*$")
RE_FRONTMATTER_FIELD = re.compile(r"^([a-z_]+):\s*(.+?)\s*$")


def parse_frontmatter(text: str) -> dict:
    """Return frontmatter fields, or {} if none."""
    lines = text.split("\n")
    if not lines or lines[0].strip() != "---":
        return {}
    fields = {}
    for line in lines[1:]:
        if line.strip() == "---":
            break
        m = RE_FRONTMATTER_FIELD.match(line)
        if m:
            fields[m.group(1)] = m.group(2).strip().strip('"')
    return fields


def audit_file(md_path: Path) -> dict:
    """Return per-file audit info: handbook, chapter, fences Counter, degraded flag."""
    text = md_path.read_text(encoding="utf-8")
    fm = parse_frontmatter(text)
    fences = Counter()
    for line in text.split("\n"):
        m = RE_FENCE.match(line)
        if m:
            fences[m.group(1)] += 1
    return {
        "path": str(md_path),
        "handbook": fm.get("handbook", "?"),
        "chapter": fm.get("chapter", ""),
        "slug": fm.get("slug", md_path.stem),
        "parent_order": int(fm.get("parent_order", -1)),
        "page_order": int(fm.get("page_order", -1)),
        "degraded": fm.get("code_quality") == "degraded",
        "fences": fences,
    }


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--target-root", required=True, help="dev_handbook_clean root")
    ap.add_argument("--output", required=True, help="fence_lang_audit.json path")
    ap.add_argument("--snapshot-date", default=None, help="ISO date (default: today)")
    ap.add_argument("--reset", action="store_true", help="Drop previous snapshots; keep only this one")
    args = ap.parse_args()

    target = Path(args.target_root)
    out_path = Path(args.output)

    # Load existing audit if present (accumulate snapshots)
    history: list = []
    if out_path.exists() and not args.reset:
        try:
            existing = json.loads(out_path.read_text(encoding="utf-8"))
            history = existing.get("snapshots", [])
        except Exception:
            history = []

    # Walk and audit
    per_handbook: dict[str, Counter] = defaultdict(Counter)
    per_chapter: dict[str, Counter] = defaultdict(Counter)
    per_file: list[dict] = []
    degraded_per_handbook: dict[str, dict] = defaultdict(lambda: {"degraded": 0, "total": 0})

    for md in sorted(target.rglob("*.md")):
        if md.name == "SUMMARY.md":
            continue
        info = audit_file(md)
        handbook = info["handbook"]
        chapter = info["chapter"]
        chap_key = f"{handbook}/{chapter}" if chapter else f"{handbook}/(root)"

        for lang, count in info["fences"].items():
            per_handbook[handbook][lang] += count
            per_chapter[chap_key][lang] += count

        degraded_per_handbook[handbook]["total"] += 1
        if info["degraded"]:
            degraded_per_handbook[handbook]["degraded"] += 1

        per_file.append({
            "handbook": handbook,
            "chapter": chapter,
            "slug": info["slug"],
            "degraded": info["degraded"],
            "fences": dict(info["fences"]),
            "fence_total": sum(info["fences"].values()),
        })

    # Build snapshot
    snapshot = {
        "date": args.snapshot_date or date.today().isoformat(),
        "target_root": str(target),
        "files_total": len(per_file),
        "by_handbook": {h: dict(c.most_common()) for h, c in sorted(per_handbook.items())},
        "by_chapter": {k: dict(v.most_common()) for k, v in sorted(per_chapter.items())},
        "degraded_by_handbook": dict(degraded_per_handbook),
        "top_files_by_fence_count": sorted(
            per_file, key=lambda x: x["fence_total"], reverse=True
        )[:20],
    }

    history.append(snapshot)

    output = {
        "schema_version": 1,
        "description": "Fence language distribution across cleaned WordPress handbooks. Snapshots accumulate per phase.",
        "snapshots": history,
    }

    out_path.write_text(
        json.dumps(output, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    # Console summary
    print(f"=== Snapshot {snapshot['date']} ===")
    print(f"Files audited: {snapshot['files_total']}")
    print()
    print("By handbook:")
    for hb, counts in snapshot["by_handbook"].items():
        deg = degraded_per_handbook[hb]
        print(f"  {hb:20}  total fences: {sum(counts.values()):5}  files: {deg['total']}  degraded: {deg['degraded']}")
        for lang, n in list(counts.items())[:8]:
            print(f"    {lang:8}  {n}")
    print()
    print(f"Wrote audit to {out_path}")


if __name__ == "__main__":
    main()
