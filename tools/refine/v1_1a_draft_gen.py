#!/usr/bin/env python3
"""
v1_1a_draft_gen.py — Generate draft repair markdown for confirmed_repair markdown_list blocks.

For each confirmed block:
  1. Locate the current text-fenced block in dev_handbook_clean (by occurrence index)
  2. Capture exact `find` string (the text fence as currently present)
  3. The `replace` string is the human-curated markdown from source HTML —
     this script loads pre-supplied replacement payloads from a Python dict
     (constructed by Claude reading the fetched source HTML for each block).

Outputs per file:
  _meta/v1_1a/draft_repairs/<seq>-<basename>.md     — human review draft
  _meta/v1_1a/draft_repairs/<seq>-<basename>.json    — find/replace payload (used to build patches)

The .json files are NOT patches yet. They are pending-confirm staging.
Once user marks confirmed in the .md draft, run v1_1a_apply_drafts.py.
"""

from __future__ import annotations

import json
import re
from pathlib import Path


# ============================================================================
# REPLACEMENT PAYLOADS
# Each entry: {file_rel_path: {block_id: {find_snippet, replace, source_url, note}}}
#
# find_snippet: A short distinctive prefix that uniquely identifies the text fence
#               within the file. We then expand to the full fence at runtime.
# replace: Full restored markdown (multi-line allowed).
# ============================================================================

REPAIRS = {
    "rest-api-handbook/04-extending-the-rest-api/routes-and-endpoints.md": {
        "block_1": {
            "source_url": "https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/",
            "note": "nested UL: 3 endpoints under 'This route has 3 endpoints:'",
            # find: the text fence body (we'll wrap with ```text\n ... \n```)
            "fence_body": "\n".join([
                "- `GET` triggers a `get_item` method, returning the post data to the client.",
                "- `PUT` triggers an `update_item` method, taking the data to update, and returning the updated post data.",
                "- `DELETE` triggers a `delete_item` method, returning the now-deleted post data to the client.",
            ]),
            "preceding_line": "- This route has 3 endpoints:",
            "replace_inline": "\n".join([
                "  - `GET` triggers a `get_item` method, returning the post data to the client.",
                "  - `PUT` triggers an `update_item` method, taking the data to update, and returning the updated post data.",
                "  - `DELETE` triggers a `delete_item` method, returning the now-deleted post data to the client.",
            ]),
        },
    },
    "block-editor-handbook/03-reference-guides/01-block-api-reference/deprecation.md": {
        "block_1": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/",
            "note": "migrate's Parameters/Return — fence breaks parent `- migrate:` bullet; indent restored",
            "replace_inline": "\n".join([
                "  - *Parameters*",
                "    - `attributes`: The block's old attributes.",
                "    - `innerBlocks`: The block's old inner blocks.",
                "  - *Return*",
                "    - `Object | Array`: Either the updated block attributes or tuple array `[attributes, innerBlocks]`.",
            ]),
        },
        "block_2": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/",
            "note": "isEligible's Parameters/Return — fence breaks parent `- isEligible:` bullet; indent restored",
            "replace_inline": "\n".join([
                "  - *Parameters*",
                "    - `attributes`: The raw block attributes as parsed from the serialized HTML, and before the block type code is applied.",
                "    - `innerBlocks`: The block's current inner blocks.",
                "    - `data`: An object containing properties representing the block node and its resulting block object.",
                "      - `data.blockNode`: The raw form of the block as a result of parsing the serialized HTML.",
                "      - `data.block`: The block object, which is the result of applying the block type to the `blockNode`.",
                "  - *Return*",
                "    - `boolean`: Whether or not this otherwise valid block is eligible to be migrated by this deprecation.",
            ]),
        },
    },
    "block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md": {
        "block_2": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "background subproperties — convert flat fence to nested sub-list under existing 'Subproperties' bullet",
            "replace_inline": "\n".join([
                "  - `backgroundImage`: type `boolean`, default value `false`",
                "  - `backgroundSize`: type `boolean`, default value `false`",
            ]),
        },
        "block_3": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "background attribute tree — fence breaks parent `- style:` bullet; indent fence body to nest under style (existing indents become deeper)",
            "replace_inline": "\n".join([
                "  - `background`: an attribute of `object` type.",
                "      - `backgroundImage`: an attribute of `object` type, containing information about the selected image",
                "          - `url`: type `string`, URL to the image",
                "          - `id`: type `int`, media attachment ID",
                "          - `source`: type `string`, at the moment the only value is `file`",
                "          - `title`: type `string`, title of the media attachment",
                "      - `backgroundPosition`: an attribute of `string` type, defining the background images position, selected by FocalPointPicker and used in CSS as the [`background-position`](https://developer.mozilla.org/en-US/docs/Web/CSS/background-position) value.",
                "      - `backgroundSize`: an attribute of `string` type. defining the CSS [`background-size`](https://developer.mozilla.org/en-US/docs/Web/CSS/background-size) value.",
            ]),
        },
        "block_4": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "color subproperties — sub-list indent under existing 'Subproperties:' bullet",
            "replace_inline": "\n".join([
                "  - `background`: type `boolean`, default value `true`",
                "  - `button`: type `boolean`, default value `false`",
                "  - `enableContrastChecker`: type `boolean`, default value `true`",
                "  - `gradients`: type `boolean`, default value `false`",
                "  - `heading`: type `boolean`, default value `false`",
                "  - `link`: type `boolean`, default value `false`",
                "  - `text`: type `boolean`, default value `true`",
            ]),
        },
        "block_14": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "dimensions subproperties — sub-list indent",
            "replace_inline": "\n".join([
                "  - `height`: type `boolean`, default value `false`",
                "  - `minHeight`: type `boolean`, default value `false`",
                "  - `minWidth`: type `boolean`, default value `false`",
                "  - `width`: type `boolean`, default value `false`",
            ]),
        },
        "block_15": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "filter subproperties (single: duotone) — sub-list indent",
            "replace_inline": "\n".join([
                "  - `duotone`: type `boolean`, default value `false`",
            ]),
        },
        "block_17": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "interactivity subproperties — sub-list indent",
            "replace_inline": "\n".join([
                "  - `clientNavigation`: type `boolean`, default value `false`",
                "  - `interactive`: type `boolean`, default value `false`",
            ]),
        },
        "block_18": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "layout subproperties — sub-list indent",
            "replace_inline": "\n".join([
                "  - `default`: type `Object`, default value null",
                "  - `allowSwitching`: type `boolean`, default value `false`",
                "  - `allowEditing`: type `boolean`, default value `true`",
                "  - `allowInheriting`: type `boolean`, default value `true`",
                "  - `allowSizingOnChildren`: type `boolean`, default value `false`",
                "  - `allowVerticalAlignment`: type `boolean`, default value `true`",
                "  - `allowJustification`: type `boolean`, default value `true`",
                "  - `allowOrientation`: type `boolean`, default value `true`",
                "  - `allowWrap`: type `boolean`, default value `true`",
                "  - `allowCustomContentAndWideSize`: type `boolean`, default value `true`",
            ]),
        },
        "block_19": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "position subproperties — sub-list indent",
            "replace_inline": "\n".join([
                "  - `sticky`: type `boolean`, default value `false`",
            ]),
        },
        "block_21": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "spacing subproperties — sub-list indent",
            "replace_inline": "\n".join([
                "  - `margin`: type `boolean` or `array`, default value `false`",
                "  - `padding`: type `boolean` or `array`, default value `false`",
                "  - `blockGap`: type `boolean` or `array`, default value `false`",
            ]),
        },
        "block_22": {
            "source_url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/",
            "note": "typography subproperties — sub-list indent",
            "replace_inline": "\n".join([
                "  - `fontSize`: type `boolean`, default value `false`",
                "  - `lineHeight`: type `boolean`, default value `false`",
                "  - `textAlign`: type `boolean` or `array`, default value `false`",
            ]),
        },
    },
    "theme-handbook/03-theme-json/02-settings/typography.md": {
        "block_1": {
            "source_url": "https://developer.wordpress.org/themes/global-settings-and-styles/settings/typography/",
            "note": "/assets/fonts directory tree — fence breaks parent `- /assets` bullet; indent children (fonts at 4-space, font files preserve their 4-space which becomes 6-space relative)",
            "replace_inline": "\n".join([
                "    - `/fonts`",
                "        - `/open-sans.woff2`",
                "        - `/open-sans-italic.woff2`",
            ]),
        },
        "block_2": {
            "source_url": "https://developer.wordpress.org/themes/global-settings-and-styles/settings/typography/",
            "note": "fluid min/max — fence breaks parent `- **fluid**:` bullet; indent children to 4-space",
            "replace_inline": "\n".join([
                "    - **`min`:** The minimum value that the font size can scale down to. Must be a valid CSS size.",
                "    - **`max`:** The maximum value that the font size can scale up to. Must be a valid CSS size.",
            ]),
        },
    },
    "theme-handbook/04-templates/template-parts.md": {
        "block_1": {
            "source_url": "https://developer.wordpress.org/themes/templates/template-parts/",
            "note": "parts/ directory tree — fence breaks parent `- parts/` bullet; indent children to 4-space",
            "replace_inline": "\n".join([
                "    - `comments.html`",
                "    - `footer.html`",
                "    - `header.html`",
                "    - `sidebar.html`",
            ]),
        },
        "block_2": {
            "source_url": "https://developer.wordpress.org/themes/templates/template-parts/",
            "note": "area_tag's HTML tag options — fence breaks parent `- **area_tag**:` bullet; indent children to 4-space",
            "replace_inline": "\n".join([
                "    - `div`",
                "    - `article`",
                "    - `aside`",
                "    - `footer`",
                "    - `header`",
                "    - `main`",
                "    - `section`",
            ]),
        },
    },
}


# ============================================================================
# DRAFT GENERATION
# ============================================================================

CORPUS = Path("dev_handbook_clean")
OUTDIR = Path("_meta/v1_1a/draft_repairs")
RE_FENCE_OPEN = re.compile(r"^```([a-z][a-z0-9]*)\s*$")
RE_FENCE_CLOSE = re.compile(r"^```\s*$")


def extract_text_fence_blocks(file_path: Path):
    """Return list of {idx, lang, line_start, line_end, body, full_fence}
       for blocks that match candidate criteria (text fence)."""
    text = file_path.read_text(encoding="utf-8")
    lines = text.split("\n")
    blocks = []
    in_fence = False
    fence_lang = ""
    fence_start = -1
    fence_lines = []
    last_warning = -10
    for i, line in enumerate(lines):
        if not in_fence:
            if re.match(r"^>\s*\[!WARNING\]", line):
                last_warning = i
            m = RE_FENCE_OPEN.match(line)
            if m:
                in_fence = True
                fence_start = i
                fence_lang = m.group(1)
                fence_lines = []
        else:
            if RE_FENCE_CLOSE.match(line):
                preceded_by_warning = (fence_start - last_warning <= 3)
                if fence_lang == "text" or preceded_by_warning:
                    blocks.append({
                        "lang": fence_lang,
                        "line_start": fence_start + 1,
                        "line_end": i + 1,
                        "body": "\n".join(fence_lines),
                        "full_fence": "\n".join([f"```{fence_lang}"] + fence_lines + ["```"]),
                        "preceded_by_warning": preceded_by_warning,
                    })
                in_fence = False
                fence_lines = []
            else:
                fence_lines.append(line)
    return blocks


def generate_drafts():
    OUTDIR.mkdir(parents=True, exist_ok=True)
    sequence = 0
    total_blocks_staged = 0

    for file_rel, blocks_map in REPAIRS.items():
        sequence += 1
        file_path = CORPUS / file_rel
        if not file_path.exists():
            print(f"⚠ missing: {file_rel}")
            continue

        # All text-fence repair candidates in this file
        all_blocks = extract_text_fence_blocks(file_path)
        candidates = [b for b in all_blocks if b["lang"] == "text" and not b["preceded_by_warning"]]

        # Also load source file text to find section headings above each block
        text = file_path.read_text(encoding="utf-8")
        lines = text.split("\n")

        def section_for_line(line_no):
            """Find nearest heading above given 1-indexed line number."""
            heading = "(top of file)"
            for i in range(min(line_no - 1, len(lines))):
                m = re.match(r"^(#{1,6})\s+(.+?)\s*$", lines[i])
                if m:
                    heading = m.group(2).strip()
            return heading

        basename = file_rel.split("/")[-1].replace(".md", "")
        draft_md_path = OUTDIR / f"{sequence:02d}-{basename}.md"
        draft_json_path = OUTDIR / f"{sequence:02d}-{basename}.json"

        any_block = next(iter(blocks_map.values()))

        md_out = []
        md_out.append(f"# Draft Repair — {basename}.md")
        md_out.append("")
        md_out.append(f"**source_path**: `{file_rel}`")
        md_out.append(f"**source_url**: {any_block['source_url']}")
        md_out.append(f"**blocks**: {len(blocks_map)}")
        md_out.append("")
        md_out.append("**Status legend**: `confirmed` / `needs_adjustment` / `reject` (default: `pending`)")
        md_out.append("")
        md_out.append("---")
        md_out.append("")

        json_staging = []

        # Resolve block_N → candidates index, accounting for files where earlier
        # blocks have already been remediated (lang_fix in earlier batch).
        # Per dispositions.json, supports.md block_1 was a lang_fix (text → js)
        # already applied, so the current corpus has 21 text candidates instead
        # of 22. block_2 now maps to candidate[0].
        SUPPORTS_OFFSET = "block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md"

        for block_id, repair in blocks_map.items():
            block_num = int(re.match(r"block_(\d+)", block_id).group(1))
            if file_rel == SUPPORTS_OFFSET:
                idx = block_num - 2  # block_2 → 0
            else:
                idx = block_num - 1  # block_1 → 0
            if idx < 0 or idx >= len(candidates):
                print(f"⚠ {file_rel} {block_id}: index {idx} out of range ({len(candidates)} candidates)")
                continue
            actual = candidates[idx]
            section = section_for_line(actual["line_start"])

            md_out.append(f"## {block_id}")
            md_out.append("")
            md_out.append(f"- **candidate_id**: `{file_rel}::{block_id}`")
            md_out.append(f"- **section**: _{section}_  (lines {actual['line_start']}–{actual['line_end']})")
            md_out.append(f"- **notes**: {repair['note']}")
            md_out.append(f"- **status**: `pending`")
            md_out.append("")
            md_out.append("### before")
            md_out.append("")
            md_out.append("````")
            md_out.append(actual["full_fence"])
            md_out.append("````")
            md_out.append("")
            md_out.append("### after")
            md_out.append("")
            md_out.append("````")
            md_out.append(repair["replace_inline"])
            md_out.append("````")
            md_out.append("")
            md_out.append("---")
            md_out.append("")

            # Internal JSON for patch generator (NOT shown in draft)
            json_staging.append({
                "candidate_id": f"{file_rel}::{block_id}",
                "file": file_rel,
                "block_id": block_id,
                "section": section,
                "line_start": actual["line_start"],
                "line_end": actual["line_end"],
                "before_fence": actual["full_fence"],
                "after_markdown": repair["replace_inline"],
                "note": repair["note"],
                "source_url": repair["source_url"],
            })
            total_blocks_staged += 1

        draft_md_path.write_text("\n".join(md_out) + "\n", encoding="utf-8")
        draft_json_path.write_text(
            json.dumps({
                "file": file_rel,
                "source_url": any_block["source_url"],
                "blocks": json_staging,
            }, indent=2, ensure_ascii=False) + "\n",
            encoding="utf-8",
        )
        print(f"Wrote {draft_md_path.relative_to(Path('.'))} ({len(json_staging)} blocks)")

    print(f"\n총 {total_blocks_staged} blocks staged across {sequence} files")


if __name__ == "__main__":
    generate_drafts()
