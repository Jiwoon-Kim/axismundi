#!/usr/bin/env python3
"""
v1_1a_markdown_scan.py — Scan P1 degraded anchor pages for repair candidates.

Identifies suspicious code blocks that may have been mis-classified during
scraping (markdown list/prose → text fence, JSON fragment → text fence, etc.).
Output is a structured candidate log; NO automatic repairs.

Heuristic categories (each block tagged with one):
  - LIKELY_MARKDOWN_LIST   : text block where most lines start with `- ` or `* `
  - LIKELY_JSON_FRAGMENT    : text block with quoted-key `"foo":` pattern
  - LIKELY_JS_OBJECT        : text block with unquoted-key `foo: { ... }` pattern
  - LIKELY_TABLE            : text block with multiple `|` separators
  - LIKELY_PROSE            : text block reading as continuous prose
  - DEGRADED_CODE           : block already marked degraded (collapsed newlines)
  - UNKNOWN                 : doesn't fit above

Each candidate enters the log with default status=repair_candidate.
Reviewer decides confirmed_repair / deferred manually.
"""

from __future__ import annotations

import argparse
import json
import re
from collections import Counter
from pathlib import Path


RE_FENCE_OPEN = re.compile(r"^```([a-z][a-z0-9]*)\s*$")
RE_FENCE_CLOSE = re.compile(r"^```\s*$")
RE_WARNING = re.compile(r"^>\s*\[!WARNING\]")
RE_MD_LINK = re.compile(r"\[[^\]\n]*\]\([^)\n]+\)")


def classify_block(lang: str, lines: list[str], preceded_by_warning: bool) -> str:
    """Return heuristic category for a code block."""
    if preceded_by_warning:
        return "DEGRADED_CODE"
    if lang != "text":
        return "OK_NOT_TEXT"  # not a candidate

    non_empty = [l for l in lines if l.strip()]
    if not non_empty:
        return "UNKNOWN"
    n = len(non_empty)
    body = "\n".join(non_empty)

    # LIKELY_MARKDOWN_LIST: most lines start with `- ` or `* `
    bullet_lines = sum(1 for l in non_empty
                       if re.match(r"^\s*[-*]\s+", l))
    if bullet_lines >= 2 and bullet_lines / n >= 0.5:
        return "LIKELY_MARKDOWN_LIST"

    # LIKELY_TABLE: pipe-separated columns
    pipe_lines = sum(1 for l in non_empty if l.count("|") >= 2)
    if pipe_lines >= 2 and pipe_lines / n >= 0.5:
        return "LIKELY_TABLE"

    # LIKELY_JSON_FRAGMENT: quoted-key pattern dominant
    json_key_lines = len(re.findall(r'"\w[\w-]*"\s*:', body))
    if json_key_lines >= 2 and "$" not in body and "<?" not in body:
        return "LIKELY_JSON_FRAGMENT"

    # LIKELY_JS_OBJECT: unquoted-key pattern + { } structure
    if re.search(r"\b\w+\s*:\s*[\{\[]", body) and "{" in body and "$" not in body:
        # Distinguish from prose with colon (e.g., "Note: text")
        unquoted_keys = len(re.findall(r"^\s*\w+\s*:\s*[\{\[]", body, re.MULTILINE))
        if unquoted_keys >= 1:
            return "LIKELY_JS_OBJECT"

    # LIKELY_PROSE: long sentences, mostly natural language
    word_count = len(body.split())
    avg_line_len = sum(len(l) for l in non_empty) / n
    has_sentence_punct = bool(re.search(r"[.!?]\s", body))
    has_md_link = bool(RE_MD_LINK.search(body))
    if word_count >= 8 and (has_sentence_punct or has_md_link) and avg_line_len >= 30:
        return "LIKELY_PROSE"

    return "UNKNOWN"


def extract_blocks(text: str) -> list[dict]:
    """
    Parse text and return list of code blocks with metadata.
    Each block: {lang, line_start, line_end, content_preview, content_full,
                 preceded_by_warning, prev_heading}.
    """
    lines = text.split("\n")
    blocks = []
    current_heading = "(top of file)"
    in_fence = False
    fence_start = -1
    fence_lang = ""
    fence_content: list[str] = []
    prev_was_warning = False
    last_warning_line = -1

    for i, line in enumerate(lines):
        if not in_fence:
            m_h = re.match(r"^(#{1,6})\s+(.+?)\s*$", line)
            if m_h:
                current_heading = m_h.group(2).strip()

            m_w = RE_WARNING.match(line)
            if m_w:
                last_warning_line = i

            m_o = RE_FENCE_OPEN.match(line)
            if m_o:
                in_fence = True
                fence_start = i
                fence_lang = m_o.group(1)
                fence_content = []
                # `preceded_by_warning` = WARNING within last 3 lines
                prev_was_warning = (i - last_warning_line <= 3)
        else:
            if RE_FENCE_CLOSE.match(line):
                content_full = "\n".join(fence_content)
                # Preview: first 5 lines, each line truncated to 200 chars
                preview_lines = []
                for pl in fence_content[:5]:
                    if len(pl) > 200:
                        preview_lines.append(pl[:200] + f" … (+{len(pl) - 200} chars)")
                    else:
                        preview_lines.append(pl)
                preview = "\n".join(preview_lines)
                if len(fence_content) > 5:
                    preview += f"\n… (+{len(fence_content) - 5} more lines)"
                blocks.append({
                    "lang": fence_lang,
                    "line_start": fence_start + 1,  # 1-indexed
                    "line_end": i + 1,
                    "preceded_by_warning": prev_was_warning,
                    "prev_heading": current_heading,
                    "content_preview": preview,
                    "content_full": content_full,
                    "line_count": len(fence_content),
                    "max_line_chars": max((len(l) for l in fence_content), default=0),
                })
                in_fence = False
                fence_content = []
            else:
                fence_content.append(line)

    return blocks


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--corpus-root", required=True)
    ap.add_argument("--anchor-pages", required=True,
                    help="_meta/v1_1a/anchor_critical_pages.json")
    ap.add_argument("--output", required=True,
                    help="_meta/v1_1a/markdown_repairs.md")
    args = ap.parse_args()

    corpus = Path(args.corpus_root)
    anchors_data = json.loads(Path(args.anchor_pages).read_text())

    # Filter P1 degraded pages
    p1_degraded = [p for p in anchors_data["pages"]
                   if p["priority"] == 1 and p["degraded"]]
    print(f"P1 degraded anchor pages: {len(p1_degraded)}")

    # For first pass, also apply user's stated sub-priorities:
    #   1. REST schema / route / global-parameters
    #   2. block-editor supports / block.json / data-store
    #   3. theme.json settings/styles
    #   4. interactivity / template anchor
    def sort_key(p):
        hb = p["handbook"]
        sub = p.get("sub_chapter") or ""
        slug = p["slug"]
        # Priority tiers within P1
        if hb == "rest-api":
            return (0, p["chapter"], slug)
        if hb == "block-editor" and sub in ("block-api-reference", "data-module-reference"):
            return (1, sub, slug)
        if hb == "theme" and p["chapter"] == "theme-json":
            return (2, sub, slug)
        if hb == "block-editor" and sub == "interactivity-api-reference":
            return (3, sub, slug)
        if hb == "theme" and p["chapter"] == "templates":
            return (4, sub, slug)
        return (5, hb, slug)

    p1_degraded.sort(key=sort_key)

    # Build markdown log
    md_lines: list[str] = []
    md_lines.append("# v1.1a — Markdown Repair Candidates (P1 First Pass)")
    md_lines.append("")
    md_lines.append("Scope: 47 anchor candidates, priority 1, degraded only.")
    md_lines.append("Status (initial): all `repair_candidate`.")
    md_lines.append("Reviewer assigns `confirmed_repair` or `deferred` per block.")
    md_lines.append("")
    md_lines.append("**Judgment guide** (Ji-woon):")
    md_lines.append("- `confirmed_repair` = 원본/문맥상 명백히 리스트·표·JSON·JS 등으로 복원 가능")
    md_lines.append("- `deferred` = 원본 확인 필요하거나 코드/문서 경계가 애매함")
    md_lines.append("- `repair_candidate` = 아직 미판정 (default)")
    md_lines.append("")
    md_lines.append("**Block categories**:")
    md_lines.append("- `LIKELY_MARKDOWN_LIST` — text fence with bullet lines (most likely repair target)")
    md_lines.append("- `LIKELY_JSON_FRAGMENT` — text fence with quoted-key pattern (JSON sample mis-classified)")
    md_lines.append("- `LIKELY_JS_OBJECT` — text fence with unquoted-key pattern (JS object literal)")
    md_lines.append("- `LIKELY_TABLE` — text fence with pipe-separated columns")
    md_lines.append("- `LIKELY_PROSE` — text fence reading as prose (likely should not be a code block)")
    md_lines.append("- `UNKNOWN` — needs manual classification")
    md_lines.append("- `DEGRADED_CODE` — already flagged with `> [!WARNING]` (collapse-affected, separate section)")
    md_lines.append("")

    # Sections: main repair candidates, then degraded code inventory
    main_section: list[str] = []
    degraded_section: list[str] = []

    total_candidates = 0
    overall_categories: Counter = Counter()
    degraded_per_page: Counter = Counter()

    for page in p1_degraded:
        rel = page["path"]
        md_path = corpus / rel
        text = md_path.read_text(encoding="utf-8")
        blocks = extract_blocks(text)

        # Split: repair candidates vs degraded code
        repair_blocks = []
        degraded_blocks = []
        for b in blocks:
            cat = classify_block(b["lang"], b["content_full"].split("\n"),
                                  b["preceded_by_warning"])
            if cat == "OK_NOT_TEXT" and not b["preceded_by_warning"]:
                continue
            tagged = {**b, "category": cat}
            if cat == "DEGRADED_CODE":
                degraded_blocks.append(tagged)
                degraded_per_page[rel] += 1
            else:
                repair_blocks.append(tagged)

        if repair_blocks:
            # Page header in main section
            sub = f" / `{page['sub_chapter']}`" if page.get("sub_chapter") else ""
            main_section.append(f"## {page['handbook']} / `{page['chapter']}`{sub} / `{page['slug']}`")
            main_section.append("")
            main_section.append(f"- **Path**: `{rel}`")
            main_section.append(f"- **Title**: {page.get('title', '?')}")
            main_section.append(f"- **Repair candidates**: {len(repair_blocks)}  "
                                f"(degraded code blocks: {len(degraded_blocks)}, see appendix)")
            m_src = re.search(r"^source_url:\s*(\S+)", text, re.MULTILINE)
            if m_src:
                main_section.append(f"- **Source URL**: {m_src.group(1)}")
            main_section.append("")

            for idx, b in enumerate(repair_blocks, start=1):
                cat = b["category"]
                overall_categories[cat] += 1
                total_candidates += 1
                main_section.append(f"### Block #{idx} — {cat} (lang=`{b['lang']}`)")
                main_section.append("")
                main_section.append(f"- **Lines**: {b['line_start']}–{b['line_end']} "
                                    f"({b['line_count']} lines, max line {b['max_line_chars']} chars)")
                main_section.append(f"- **Section**: _{b['prev_heading']}_")
                main_section.append(f"- **Disposition**: `repair_candidate`  "
                                    f"`[ ] confirmed_repair`  `[ ] deferred`")
                main_section.append("")
                main_section.append("```")
                for line in b["content_preview"].split("\n"):
                    main_section.append(line)
                main_section.append("```")
                main_section.append("")
        elif degraded_blocks:
            # No repair candidates but still degraded — appendix-only mention
            pass

    # Degraded section: inventory only, no per-block dump (it's noise for review)
    if degraded_per_page:
        degraded_section.append("# Appendix — DEGRADED_CODE Inventory")
        degraded_section.append("")
        degraded_section.append("These blocks already carry a `> [!WARNING]` callout from the "
                                "pipeline (collapse-affected). Listed for completeness; out of "
                                "scope for v1.1a markdown repair (separate v1.1 track).")
        degraded_section.append("")
        for rel, count in sorted(degraded_per_page.items(), key=lambda x: -x[1]):
            degraded_section.append(f"- `{rel}` — {count} degraded blocks")
        degraded_section.append("")

    # Summary
    md_lines.append("## Summary")
    md_lines.append("")
    md_lines.append(f"- **P1 degraded pages reviewed**: {len(p1_degraded)}")
    md_lines.append(f"- **Repair candidates (main section)**: {total_candidates}")
    md_lines.append("- **By category**:")
    for cat, n in sorted(overall_categories.items(), key=lambda x: -x[1]):
        md_lines.append(f"  - `{cat}`: {n}")
    md_lines.append(f"- **DEGRADED_CODE blocks (appendix)**: {sum(degraded_per_page.values())}")
    md_lines.append("")
    md_lines.append("---")
    md_lines.append("")

    md_lines.extend(main_section)
    md_lines.append("---")
    md_lines.append("")
    md_lines.extend(degraded_section)

    Path(args.output).write_text("\n".join(md_lines) + "\n", encoding="utf-8")

    print(f"\nTotal candidates: {total_candidates}")
    print("By category:")
    for cat, n in overall_categories.most_common():
        print(f"  {cat:25}  {n}")
    print(f"\nWrote {args.output}")


if __name__ == "__main__":
    main()
