#!/usr/bin/env python3
"""
link_pass.py — Convert WP.org dev links to internal relative paths.

Policy (DECISIONS.md, Phase 6):
  Internal (same handbook or cross handbook within corpus):
    https://developer.wordpress.org/<handbook>/<path>/[#anchor]
    → relative path to corresponding .md, preserving anchor

  External (outside the 5-handbook corpus, including other WP.org subpaths):
    → absolute URL preserved unchanged

  GitHub / W3C / RFC / other domains:
    → absolute URL preserved unchanged

Anchor (#section) is ALWAYS preserved.

Outputs:
  - Rewrites .md files in-place (idempotent)
  - broken_links.json — links that look internal but have no resolvable target

Usage:
  python link_pass.py --target-root dev_handbook_clean \\
      --broken-output broken_links.json [--dry-run]
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path


# Markdown link: [text](url) or [text](url "title")
RE_MD_LINK = re.compile(r"\[([^\]\n]*)\]\(([^)\n]+)\)")
# WP.org developer URL
RE_WP_DEV_URL = re.compile(r"^https?://developer\.wordpress\.org/")
# WP.org handbook URL maps to handbook slug
WP_PATH_TO_HANDBOOK = {
    "plugins": "plugin",
    "themes": "theme",
    "block-editor": "block-editor",
    "rest-api": "rest-api",
    "common-apis": "common-apis",
    "apis": "common-apis",  # alt path
}


def parse_frontmatter_url(text: str) -> str | None:
    """Pull source_url from frontmatter."""
    lines = text.split("\n")
    if not lines or lines[0].strip() != "---":
        return None
    for line in lines[1:30]:
        if line.strip() == "---":
            break
        m = re.match(r"^source_url:\s*(\S+)", line)
        if m:
            return m.group(1).strip()
    return None


def normalize_wp_url(url: str) -> str:
    """Normalize a WP.org URL: lowercase, no trailing slash on path, no anchor or query."""
    # Strip fragment and query
    url = url.split("#", 1)[0].split("?", 1)[0]
    # Strip trailing slash (we'll add back on lookup if needed)
    url = url.rstrip("/")
    return url.lower()


def split_url_anchor(url: str) -> tuple[str, str]:
    """Return (base_url, anchor_with_hash) — anchor includes leading #."""
    if "#" in url:
        base, anchor = url.split("#", 1)
        return base, "#" + anchor
    return url, ""


def build_url_index(target: Path) -> dict[str, Path]:
    """
    Build map: normalized source_url → file Path.
    Also includes plural/singular alternates if present in source_url.
    """
    idx: dict[str, Path] = {}
    for md in target.rglob("*.md"):
        text = md.read_text(encoding="utf-8")
        url = parse_frontmatter_url(text)
        if not url:
            continue
        norm = normalize_wp_url(url)
        idx[norm] = md
        # Also strip leading "www." just in case
        if norm.startswith("https://www."):
            idx[norm.replace("https://www.", "https://")] = md
        elif "://" in norm:
            scheme, rest = norm.split("://", 1)
            idx[f"{scheme}://www.{rest}"] = md
    return idx


def is_corpus_internal(url: str) -> bool:
    """
    Return True if the URL points into one of our 5 handbooks (corpus internal).
    Outside developer.wordpress.org or in other paths -> False.
    """
    if not RE_WP_DEV_URL.match(url):
        return False
    # Path after domain
    m = re.match(r"^https?://developer\.wordpress\.org/([^/?#]+)", url)
    if not m:
        return False
    first_seg = m.group(1).lower()
    return first_seg in WP_PATH_TO_HANDBOOK


def rewrite_links_in_text(
    text: str,
    src_path: Path,
    url_index: dict[str, Path],
    target_root: Path,
    broken: list,
) -> tuple[str, int, int]:
    """
    Rewrite markdown links in `text`. Returns (new_text, rewritten, skipped_external).
    """
    rewritten = 0
    skipped_external = 0

    def repl(m: re.Match) -> str:
        nonlocal rewritten, skipped_external
        link_text = m.group(1)
        raw_target = m.group(2).strip()
        # Extract optional title — split first whitespace
        url_part = raw_target
        title_part = ""
        sm = re.match(r'^(\S+)(\s+".*")$', raw_target)
        if sm:
            url_part = sm.group(1)
            title_part = sm.group(2)

        # Only act on WP dev URLs that look corpus-internal
        if not is_corpus_internal(url_part):
            skipped_external += 1
            return m.group(0)

        base, anchor = split_url_anchor(url_part)
        norm_base = normalize_wp_url(base)
        dst_path = url_index.get(norm_base)

        if not dst_path:
            # Try with/without trailing slash, hyphen normalizations
            for variant in (
                norm_base + "/",
                norm_base.replace("--", "-"),
            ):
                if variant in url_index:
                    dst_path = url_index[variant]
                    break

        if not dst_path:
            broken.append({
                "src_file": str(src_path.relative_to(target_root)),
                "original_url": url_part,
                "link_text": link_text,
                "normalized": norm_base,
            })
            skipped_external += 1
            return m.group(0)

        # Compute relative path from src_path's directory to dst_path
        rel = os.path.relpath(dst_path, start=src_path.parent)
        rel = rel.replace("\\", "/")
        new_link = f"[{link_text}]({rel}{anchor}{title_part})"
        rewritten += 1
        return new_link

    new_text = RE_MD_LINK.sub(repl, text)
    return new_text, rewritten, skipped_external


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--target-root", required=True)
    ap.add_argument("--broken-output", required=True)
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    target = Path(args.target_root)

    print("Building URL index…")
    url_index = build_url_index(target)
    print(f"  indexed {len(url_index)} URLs across {sum(1 for _ in target.rglob('*.md'))} files")

    broken: list = []
    total_rewritten = 0
    total_skipped = 0
    files_changed = 0
    files_total = 0

    for md in sorted(target.rglob("*.md")):
        files_total += 1
        original = md.read_text(encoding="utf-8")
        new_text, rewritten, skipped = rewrite_links_in_text(
            original, md, url_index, target, broken
        )
        total_rewritten += rewritten
        total_skipped += skipped
        if new_text != original:
            files_changed += 1
            if not args.dry_run:
                md.write_text(new_text, encoding="utf-8")

    # Build broken summary
    broken_by_handbook: dict[str, int] = Counter()
    for b in broken:
        hb_match = re.match(r"^https?://developer\.wordpress\.org/([^/]+)", b["original_url"])
        if hb_match:
            broken_by_handbook[hb_match.group(1)] += 1

    output = {
        "schema_version": 1,
        "summary": {
            "files_total": files_total,
            "files_changed": files_changed,
            "links_rewritten": total_rewritten,
            "links_skipped_external_or_unresolved": total_skipped,
            "broken_internal_links": len(broken),
            "broken_by_wp_path": dict(broken_by_handbook),
        },
        "broken_links": broken,
    }

    Path(args.broken_output).write_text(
        json.dumps(output, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    print()
    print("=== Link Pass Summary ===")
    print(f"Files total:                          {files_total}")
    print(f"Files changed:                        {files_changed}")
    print(f"Links rewritten (corpus internal):    {total_rewritten}")
    print(f"Links skipped (external/unresolved):  {total_skipped}")
    print(f"Broken internal (no target found):    {len(broken)}")
    if broken_by_handbook:
        print("Broken by WP path segment:")
        for k, n in sorted(broken_by_handbook.items(), key=lambda x: -x[1])[:10]:
            print(f"  {n:4}  {k}")
    if args.dry_run:
        print("(DRY RUN — no files modified)")


if __name__ == "__main__":
    main()
