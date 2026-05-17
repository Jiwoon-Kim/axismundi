#!/usr/bin/env python3
"""
v1_1a_apply_drafts.py — Convert confirmed draft repairs to patches and apply.

Reads _meta/v1_1a/draft_repairs/*.json (the staged before/after pairs),
builds find/replace entries, merges them into patches/v1_1a/<file>.patches.json,
then runs apply_patches.py.

Each draft block becomes:
  find:    before_fence (the exact ```text\\n...\\n``` block in current corpus)
  replace: after_markdown (raw markdown, no fence wrapping)
  reason:  v1.1a markdown_list: <note>

Existing link/lang_fix patches in patches/v1_1a/<file>.patches.json are
preserved — we append, dedup, and write back.
"""

from __future__ import annotations

import json
import subprocess
import sys
from collections import defaultdict
from pathlib import Path


STAGING = Path("_meta/v1_1a/draft_repairs")
PATCHES_ROOT = Path("patches/v1_1a")
CORPUS = Path("dev_handbook_clean")


def load_staged_blocks():
    """Return list of all staged blocks across all draft JSONs."""
    out = []
    for jp in sorted(STAGING.glob("*.json")):
        d = json.loads(jp.read_text())
        for b in d["blocks"]:
            out.append(b)
    return out


def build_patch_entry(block):
    """Convert a staged block to a {find, replace, reason} patch entry."""
    return {
        "find": block["before_fence"],
        "replace": block["after_markdown"],
        "reason": f"v1.1a markdown_list ({block['block_id']}): {block['note']}",
    }


def main():
    blocks = load_staged_blocks()
    print(f"Loaded {len(blocks)} staged blocks")

    # Group by file
    by_file = defaultdict(list)
    for b in blocks:
        by_file[b["file"]].append(b)

    # For each file, merge into existing patch file (or create)
    for file_rel, file_blocks in by_file.items():
        patch_path = PATCHES_ROOT / f"{file_rel}.patches.json"
        patch_path.parent.mkdir(parents=True, exist_ok=True)

        existing = []
        if patch_path.exists():
            existing = json.loads(patch_path.read_text())
            if not isinstance(existing, list):
                # legacy {patches: [...]} wrapper — flatten
                existing = existing.get("patches", [])

        # Build new entries
        new_entries = [build_patch_entry(b) for b in file_blocks]

        # Dedup by `find` string — keep existing first
        seen_finds = {e["find"] for e in existing}
        appended = 0
        for ne in new_entries:
            if ne["find"] not in seen_finds:
                existing.append(ne)
                seen_finds.add(ne["find"])
                appended += 1

        patch_path.write_text(
            json.dumps(existing, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )
        print(f"  {file_rel}: appended {appended} markdown_list patches "
              f"({len(existing)} total in file)")

    # Run apply_patches.py
    print()
    print("=== Running apply_patches.py ===")
    result = subprocess.run(
        ["python3", "scripts/apply_patches.py",
         "--patches-root", str(PATCHES_ROOT),
         "--target-root", str(CORPUS)],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        print("ERROR:", result.stderr)
        sys.exit(1)
    summary = json.loads(result.stdout)
    print(f"  patch files: {summary['summary']['patch_files']}")
    print(f"  applied:     {summary['summary']['patches_applied']}")
    print(f"  skipped:     {summary['summary']['patches_skipped']} (idempotent: already-applied)")


if __name__ == "__main__":
    main()
