#!/usr/bin/env python3
"""
v1_1a_inventory.py — Ontology-critical input sanitation inventory.

Produces two artifacts in _meta/v1_1a/:

  anchor_critical_pages.json
    - For each of the 47 anchor candidates (bridge_index.is_anchor_candidate):
      path, handbook, chapter, slug, title, priority tier (1/2/3),
      lang_profile, degraded flag, outbound_links (count + broken count),
      inbound_links (count from other pages in the corpus).

  broken_links_critical.json
    - Subset of broken_links.json where EITHER:
        (a) source file is one of the 47 anchor pages, OR
        (b) target URL (if resolved) would land in a 47 anchor page.
    - For (b) we use a slug-name heuristic since the URL didn't resolve.

Priority tiers (per Ji-woon's v1.1a decision):
  P1 = REST / Block / Theme JSON / Interactivity / Data Store
  P2 = Plugin hooks / settings / i18n
  P3 = 단순 설명성 anchor (everything else in the 47)
"""

from __future__ import annotations

import argparse
import json
import re
from collections import Counter, defaultdict
from pathlib import Path


# Priority tier rules. Order matters: first match wins.
PRIORITY_RULES = [
    # P1 — REST / Block / Theme JSON / Interactivity / Data Store
    (1, lambda e: e["handbook"] == "rest-api"),
    (1, lambda e: e["handbook"] == "block-editor"
        and e.get("sub_chapter") == "block-api-reference"),
    (1, lambda e: e["handbook"] == "block-editor"
        and e.get("sub_chapter") == "interactivity-api-reference"),
    (1, lambda e: e["handbook"] == "block-editor"
        and e.get("sub_chapter") == "data-module-reference"),
    (1, lambda e: e["handbook"] == "theme"
        and e["chapter"] in ("theme-json", "templates")),
    # P2 — Plugin hooks / settings / i18n
    (2, lambda e: e["handbook"] == "plugin"
        and e["chapter"] in ("hooks", "settings", "internationalization", "shortcodes")),
    (2, lambda e: e["handbook"] == "plugin"
        and e["chapter"] == "javascript" and e["slug"] == "ajax"),
    # P3 — fallback for any anchor candidate not matched above
]


def classify_priority(entry: dict) -> int:
    for tier, rule in PRIORITY_RULES:
        if rule(entry):
            return tier
    return 3


# Markdown link parser
RE_MD_LINK = re.compile(r"\[([^\]\n]*)\]\(([^)\n]+)\)")
# Relative .md link (post link_pass)
RE_REL_MD = re.compile(r"^(\.\./)*[^/].*\.md(#[^)]*)?$")


def extract_outbound_links(text: str) -> list[dict]:
    """Return list of {url, anchor, is_relative_md} for each link in text."""
    out = []
    for m in RE_MD_LINK.finditer(text):
        url = m.group(2).strip()
        # Strip optional title
        sm = re.match(r'^(\S+)(\s+".*")?$', url)
        if sm:
            url = sm.group(1)
        anchor = ""
        if "#" in url:
            url_base, anchor = url.split("#", 1)
            anchor = "#" + anchor
        else:
            url_base = url
        out.append({
            "url": url_base,
            "anchor": anchor,
            "is_relative_md": bool(RE_REL_MD.match(url)),
        })
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--corpus-root", required=True)
    ap.add_argument("--bridge-index", required=True)
    ap.add_argument("--broken-links", required=True)
    ap.add_argument("--output-dir", required=True)
    args = ap.parse_args()

    corpus = Path(args.corpus_root)
    bridge = json.loads(Path(args.bridge_index).read_text())
    broken = json.loads(Path(args.broken_links).read_text())
    outdir = Path(args.output_dir)
    outdir.mkdir(parents=True, exist_ok=True)

    # ---- Step 1: identify the 47 anchor candidates ----
    all_entries = bridge["entries"]
    anchors = [e for e in all_entries if e.get("is_anchor_candidate")]
    print(f"Anchor candidates: {len(anchors)}")

    # Index anchors by path for fast lookup
    anchor_paths: set[str] = {e["path"] for e in anchors}
    # Index anchors by (handbook, slug) for target-side matching
    anchor_by_handbook_slug: dict[tuple, dict] = {
        (e["handbook"], e["slug"]): e for e in anchors
    }

    # ---- Step 2: build inbound link map ----
    # Walk all 630 pages and count outbound links targeting each anchor path
    inbound_counts: Counter = Counter()
    outbound_index: dict[str, list[dict]] = {}

    for md in corpus.rglob("*.md"):
        if md.name == "SUMMARY.md":
            continue
        rel = str(md.relative_to(corpus)).replace("\\", "/")
        text = md.read_text(encoding="utf-8")
        links = extract_outbound_links(text)
        outbound_index[rel] = links

        for ln in links:
            if not ln["is_relative_md"]:
                continue
            # Resolve relative .md target to absolute corpus path
            target = (md.parent / ln["url"]).resolve()
            try:
                target_rel = str(target.relative_to(corpus.resolve())).replace("\\", "/")
            except ValueError:
                continue
            if target_rel in anchor_paths:
                inbound_counts[target_rel] += 1

    # ---- Step 3: build anchor_critical_pages.json ----
    anchor_pages = []
    for e in anchors:
        rel = e["path"]
        outbound = outbound_index.get(rel, [])
        # broken outbound: any link whose URL was reported broken
        broken_in_this_file = [
            b for b in broken["broken_links"] if b["src_file"] == rel
        ]

        anchor_pages.append({
            "priority": classify_priority(e),
            "path": rel,
            "handbook": e["handbook"],
            "chapter": e["chapter"],
            "sub_chapter": e.get("sub_chapter"),
            "slug": e["slug"],
            "title": e.get("title"),
            "lang_profile": e["lang_profile"],
            "degraded": e["degraded"],
            "anchors_count": len(e["anchors"]),
            "outbound_links_total": len(outbound),
            "outbound_links_broken": len(broken_in_this_file),
            "broken_outbound_targets": [b["original_url"] for b in broken_in_this_file],
            "inbound_links": inbound_counts.get(rel, 0),
        })

    # Sort: priority asc, then handbook, then chapter
    anchor_pages.sort(key=lambda x: (x["priority"], x["handbook"],
                                       x["chapter"], x.get("sub_chapter") or "",
                                       x["slug"]))

    out_path1 = outdir / "anchor_critical_pages.json"
    out_path1.write_text(
        json.dumps({
            "schema_version": 1,
            "summary": {
                "total_anchor_pages": len(anchor_pages),
                "by_priority": dict(Counter(p["priority"] for p in anchor_pages)),
                "degraded_count": sum(1 for p in anchor_pages if p["degraded"]),
                "total_outbound_broken": sum(p["outbound_links_broken"] for p in anchor_pages),
            },
            "pages": anchor_pages,
        }, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    # ---- Step 4: build broken_links_critical.json ----
    # Two categories:
    #   (a) source = anchor page
    #   (b) target slug matches an anchor slug (heuristic — URL didn't resolve cleanly)

    # Heuristic for (b): extract trailing URL segment from broken URL, compare to anchor slugs
    def extract_target_slug(broken_url: str) -> str | None:
        # e.g. https://developer.wordpress.org/plugins/hooks/actions/#examples
        # → "actions"
        u = broken_url.split("#", 1)[0].rstrip("/")
        seg = u.rsplit("/", 1)[-1]
        return seg or None

    anchor_slugs: set[str] = {e["slug"] for e in anchors}

    critical_broken = []
    for b in broken["broken_links"]:
        src_is_anchor = b["src_file"] in anchor_paths
        target_slug = extract_target_slug(b["original_url"])
        target_is_anchor = target_slug in anchor_slugs

        if not (src_is_anchor or target_is_anchor):
            continue

        critical_broken.append({
            "src_file": b["src_file"],
            "src_is_anchor": src_is_anchor,
            "target_slug": target_slug,
            "target_is_anchor": target_is_anchor,
            "original_url": b["original_url"],
            "link_text": b.get("link_text", ""),
        })

    # Sort: src_is_anchor first, then target_is_anchor
    critical_broken.sort(key=lambda x: (
        not x["src_is_anchor"], not x["target_is_anchor"], x["src_file"]
    ))

    out_path2 = outdir / "broken_links_critical.json"
    out_path2.write_text(
        json.dumps({
            "schema_version": 1,
            "summary": {
                "total_broken_in_corpus": len(broken["broken_links"]),
                "total_critical": len(critical_broken),
                "src_is_anchor": sum(1 for c in critical_broken if c["src_is_anchor"]),
                "target_is_anchor": sum(1 for c in critical_broken if c["target_is_anchor"]),
                "both": sum(1 for c in critical_broken
                            if c["src_is_anchor"] and c["target_is_anchor"]),
            },
            "broken_links": critical_broken,
        }, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    # ---- Console summary ----
    print()
    print("=== v1.1a Inventory ===")
    print()
    print(f"anchor_critical_pages.json: {len(anchor_pages)} anchor pages")
    pri_counts = Counter(p["priority"] for p in anchor_pages)
    for pri in sorted(pri_counts):
        print(f"  P{pri}: {pri_counts[pri]}")
    print(f"  degraded: {sum(1 for p in anchor_pages if p['degraded'])}")
    print(f"  total outbound broken: {sum(p['outbound_links_broken'] for p in anchor_pages)}")
    print(f"  total inbound: {sum(p['inbound_links'] for p in anchor_pages)}")
    print()
    print(f"broken_links_critical.json: {len(critical_broken)} critical broken")
    print(f"  src is anchor:     {sum(1 for c in critical_broken if c['src_is_anchor'])}")
    print(f"  target is anchor:  {sum(1 for c in critical_broken if c['target_is_anchor'])}")
    print(f"  both:              {sum(1 for c in critical_broken if c['src_is_anchor'] and c['target_is_anchor'])}")
    print()
    print(f"Wrote {out_path1}")
    print(f"Wrote {out_path2}")


if __name__ == "__main__":
    main()
