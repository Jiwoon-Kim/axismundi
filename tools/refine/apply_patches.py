#!/usr/bin/env python3
"""
apply_patches.py — Apply manual high-value patches on top of pipeline output.

Patches live in patches/<handbook>/<file>.patches.json as a list of
{"find": "...", "replace": "..."} entries. Each entry is a literal string
replacement applied once. Idempotent: skips if `find` not present.

Run AFTER clean_handbook.py.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path


def apply_patches_to_file(target: Path, patches: list[dict]) -> tuple[int, int]:
    """Returns (applied_count, skipped_count)."""
    if not target.exists():
        return 0, 0
    text = target.read_text(encoding="utf-8")
    applied = 0
    skipped = 0
    for p in patches:
        find = p["find"]
        replace = p["replace"]
        if find in text:
            text = text.replace(find, replace, 1)
            applied += 1
        else:
            skipped += 1
    target.write_text(text, encoding="utf-8")
    return applied, skipped


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--patches-root", required=True, help="Directory of patch JSON files")
    ap.add_argument("--target-root", required=True, help="Pipeline output directory")
    args = ap.parse_args()

    patches_root = Path(args.patches_root)
    target_root = Path(args.target_root)

    total_files = 0
    total_applied = 0
    total_skipped = 0
    report = []

    for patch_file in sorted(patches_root.rglob("*.patches.json")):
        rel = patch_file.relative_to(patches_root)
        # patch path: <handbook>/<chapter>/<file>.md.patches.json
        target_rel = str(rel).replace(".patches.json", "")
        target = target_root / target_rel
        patches = json.loads(patch_file.read_text(encoding="utf-8"))
        applied, skipped = apply_patches_to_file(target, patches)
        total_files += 1
        total_applied += applied
        total_skipped += skipped
        report.append({
            "patch_file": str(rel),
            "target": str(target_rel),
            "applied": applied,
            "skipped": skipped,
        })

    print(json.dumps({
        "summary": {
            "patch_files": total_files,
            "patches_applied": total_applied,
            "patches_skipped": total_skipped,
        },
        "details": report,
    }, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
