#!/usr/bin/env python3
"""
clean_handbook.py — WordPress Handbook scrape cleanup pipeline.

See DECISIONS.md for full spec. Applies (in order):

  1. CRLF -> LF
  2. Strip top-of-file bare URL line (extract source_url)
  3. Strip heading anchor links:  ## [Title](url)  ->  ## Title
  4. Remove [Copy](...) / [Expand code](...) stray lines
  5. Remove empty `` markers (single-line `` only)
  6. 4-space indented code blocks -> fenced (with language detection)
  7. Detect collapsed code (no newlines in PHP/JS) -> insert WARNING callout
     and mark frontmatter code_quality: degraded
  8. Write extended frontmatter (DECISIONS.md 2.3)

Pure stdlib. Idempotent on already-cleaned files (frontmatter detected).

Usage:
  python clean_handbook.py --manifest manifest_security.json \\
      --input-root /path/to/dev_handbook \\
      --output-root /path/to/dev_handbook_clean
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import dataclass
from pathlib import Path


# ---------------------------------------------------------------------------
# Frontmatter
# ---------------------------------------------------------------------------

@dataclass
class FileMeta:
    source_url: str
    synced: str
    handbook: str
    chapter: str
    slug: str
    parent_order: int
    page_order: int
    title: str
    # Optional deep-nesting fields (block-editor mostly)
    sub_chapter: str | None = None
    sub_order: int | None = None
    sub_sub_chapter: str | None = None
    sub_sub_order: int | None = None
    code_quality: str | None = None
    code_issue: str | None = None

    def to_yaml(self) -> str:
        lines = ["---"]
        lines.append(f"source_url: {self.source_url}")
        lines.append(f"synced: {self.synced}")
        lines.append(f"handbook: {self.handbook}")
        lines.append(f"chapter: {self.chapter}")
        if self.sub_chapter:
            lines.append(f"sub_chapter: {self.sub_chapter}")
        if self.sub_sub_chapter:
            lines.append(f"sub_sub_chapter: {self.sub_sub_chapter}")
        lines.append(f"slug: {self.slug}")
        lines.append(f"parent_order: {self.parent_order}")
        if self.sub_order is not None:
            lines.append(f"sub_order: {self.sub_order}")
        if self.sub_sub_order is not None:
            lines.append(f"sub_sub_order: {self.sub_sub_order}")
        lines.append(f"page_order: {self.page_order}")
        escaped_title = self.title.replace('"', '\\"')
        lines.append(f'title: "{escaped_title}"')
        if self.code_quality:
            lines.append(f"code_quality: {self.code_quality}")
        if self.code_issue:
            lines.append(f"code_issue: {self.code_issue}")
        lines.append("---")
        return "\n".join(lines)


# ---------------------------------------------------------------------------
# Regexes
# ---------------------------------------------------------------------------

# Top-of-file bare URL (developer.wordpress.org/...)
RE_TOP_URL = re.compile(r"^https?://developer\.wordpress\.org/\S+\s*$")

# Heading with anchor link: # Title or ## [Title](url)
# Captures the bracketed title text, preserving heading level
RE_HEADING_ANCHORED = re.compile(
    r"^(#{1,6})\s+\[([^\]]+)\]\(https?://developer\.wordpress\.org/[^)]+\)\s*$"
)

# Stray UI links from scrape
RE_COPY_LINK = re.compile(r"^\[Copy\]\(https?://[^)]+\)\s*$")
RE_EXPAND_LINK = re.compile(r"^\[Expand code\]\(https?://[^)]+\)\s*$")

# Empty/single-double backtick markers
RE_EMPTY_BACKTICKS = re.compile(r"^``\s*$")

# 4-space indented line (markdown code block convention)
RE_INDENTED = re.compile(r"^    (.*)$")

# Heuristic: PHP code that lost newlines  (e.g. "<?phpecho ...")
RE_PHP_COLLAPSED = re.compile(r"<\?php[a-zA-Z_]+\s*\(")
# Multiple statements jammed together inside an indented code line
RE_MULTI_STMT_COLLAPSED = re.compile(r";[a-zA-Z_\$]")
# Statement followed by // comment without newline
RE_STMT_THEN_COMMENT = re.compile(r";//")
# Very long single line with function-call structure (suggests collapse)
def is_long_collapsed_call(code: str, length_threshold: int = 140) -> bool:
    if "\n" in code:
        return False
    if len(code) < length_threshold:
        return False
    # has function-call structure and multiple commas/=>/array
    has_call = re.search(r"\w+\s*\(", code) is not None
    has_structure = code.count(",") >= 3 or code.count("=>") >= 2 or code.count("array(") >= 1
    return has_call and has_structure


# ---------------------------------------------------------------------------
# Language detection
# ---------------------------------------------------------------------------

def detect_lang(code_block: str) -> str:
    """
    Detect language for a code block in WordPress handbook context.
    Strategy: In WP handbook context, PHP is the default; JS only when
    strong JS signals dominate.
    """
    s = code_block.lstrip()
    head = s[:300]

    # ---- Strongest signal: PHP open tag ----
    if s.startswith("<?php") or "<?php" in head:
        return "php"

    # ---- gettext PO file ----
    # Pattern: msgid "..." + msgstr "..." (possibly with #: file:line headers)
    # Collapsed scrape may put them on one line, so check whole block.
    if "msgid" in code_block and "msgstr" in code_block:
        return "po"

    # ---- HTTP request example ----
    # Pattern: METHOD /path  (optionally followed by HTTP/x.y or headers)
    # Distinguish from plain endpoint paths (which have no leading method).
    if re.match(r"^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\s+/", s):
        return "http"

    # ---- HTTP response ----
    # Pattern: starts with "HTTP/x.y NNN " (status line)
    if re.match(r"^HTTP/\d", s):
        return "http"

    # ---- JSON detection (objects, arrays, fragments) ----
    # Full JSON object: starts with `{` and has quoted keys
    if s.startswith("{"):
        # Distinguish JSON from JS object literal: JSON has quoted keys, no `$`, no `=>`, no imports
        if (re.search(r'"[\w-]+"\s*:', head)
            and "$" not in code_block
            and "=>" not in code_block
            and "from '" not in code_block
            and 'from "' not in code_block):
            return "json"
    # JSON property fragment: `"key": value` (common in block.json examples)
    if re.match(r'^"[\w-]+"\s*:\s*', s):
        if ("$" not in code_block
            and "<?" not in code_block
            and "from '" not in code_block
            and 'from "' not in code_block):
            return "json"
    # JSON arrays
    if s.startswith("["):
        if (re.search(r'\[\s*(?:"[^"]*"|\d|\[|\{)', s)
            and "$" not in code_block
            and "=>" not in code_block):
            return "json"

    # ---- Strong JS-only signals (rarely overlap with PHP) ----
    # NOTE: `var` deliberately excluded — PHP OOP uses `var $member` (legacy class
    # property syntax), so `var ` would cause PHP false negatives.
    js_strong_markers = (
        "import ", "export ", "const ", "let ",
        "async function", "await ",
        "() =>", ") =>", " => {", " => (",
    )
    js_strong = any(m in head for m in js_strong_markers)

    # ---- PHP signals ----
    php_signals = sum([
        bool(re.search(r"\$\w+", code_block)),                     # any $var
        "<?php" in code_block,
        "array(" in code_block,
        bool(re.search(r"\b(wp_|add_|get_|the_|register_|esc_|do_|sanitize_|apply_|check_|is_|has_|update_|delete_|insert_|current_user_)\w+\s*\(", code_block)),
        bool(re.search(r"\$\w+\s*->", code_block)),                # $obj->prop
        "::" in code_block and not "://" in code_block,            # static call (not URL)
        bool(re.search(r"=>\s*", code_block)),                     # array key arrow
        bool(re.search(r":\s*(bool|int|string|float|array|void|self|static)\b", code_block)),  # PHP type hints
        bool(re.search(r"^echo ", s)),                              # PHP echo
        # PHP-only built-in functions / constructs
        bool(re.search(r"\b(define|defined|isset|empty|unset|function_exists|class_exists|method_exists|property_exists|require_once|include_once|require|include|namespace|use)\s*[(\\\s]", code_block)),
        # PHPDoc-style comment block opener
        "/**" in code_block and ("@param" in code_block or "@return" in code_block or "@var" in code_block),
        # Chained string concatenation with PHP operator `.`
        bool(re.search(r"\.\s*['\"]", code_block)) and bool(re.search(r"['\"]\s*\.", code_block)),
        # PHP @-suppression operator before function call
        bool(re.search(r"@\w+\s*\(", code_block)),
    ])

    if js_strong and php_signals < 2:
        # React-or-WP environment signals (any indicates JSX is plausible)
        react_or_wp_signals = (
            "import React" in code_block
            or "useState" in code_block
            or "useEffect" in code_block
            or "useSelect" in code_block
            or "useDispatch" in code_block
            or "useBlockProps" in code_block       # block-editor JSX hook
            or "useInnerBlocksProps" in code_block
            or "useBlockEditingMode" in code_block
            or "props." in code_block
            or "from 'react'" in code_block
            or 'from "react"' in code_block
            or "from '@wordpress/element'" in code_block
            or 'from "@wordpress/element"' in code_block
            or "from '@wordpress/components'" in code_block
            or 'from "@wordpress/components"' in code_block
            or "from '@wordpress/block-editor'" in code_block
            or 'from "@wordpress/block-editor"' in code_block
        )

        # JSX-markup signals (any of these indicates JSX syntax present)
        jsx_markup_signals = bool(
            re.search(r"<[A-Z]\w*", head)                          # capitalized component <Button>
            or re.search(r"<\w+\s+\{\s*\.\.\.", code_block)        # spread attr <div { ...props }>
            or re.search(r"\w+\s*=\s*\{[^}]+\}\s*/?>", code_block) # prop={...} JSX attribute
            or re.search(r"return\s*\(?\s*<\w+", code_block)       # return <tag or return ( <tag
            or re.search(r"<\w+[^>]*>\s*\{[\w.]+\s*}", code_block) # children expression
        )

        if react_or_wp_signals and jsx_markup_signals:
            return "jsx"
        # Capitalized JSX tag alone (no React signals) — likely not JSX (Apache <Files>, etc.)
        return "js"

    # ---- HTML / JSX (lowercase tag start, no JS markers) ----
    if s.startswith("<") and not s.startswith("<?"):
        # JSX-specific markup signals (HTML doesn't allow `={...}` attributes)
        has_jsx_attr = bool(re.search(r"\w+\s*=\s*\{[^}]+\}", code_block))   # prop={expr}
        has_jsx_spread = bool(re.search(r"<\w+\s+\{\s*\.\.\.", code_block))  # <tag {...}>
        # Capitalized tag = JSX component
        if re.match(r"<[A-Z]", s):
            react_or_wp_signals = (
                "import React" in code_block
                or "useState" in code_block
                or "useBlockProps" in code_block
                or "props." in code_block
                or "from 'react'" in code_block
                or 'from "react"' in code_block
                or "from '@wordpress/element'" in code_block
                or 'from "@wordpress/element"' in code_block
                or "from '@wordpress/components'" in code_block
                or 'from "@wordpress/components"' in code_block
            )
            # Capitalized tag alone is enough if JSX-only syntax present
            if has_jsx_attr or has_jsx_spread or react_or_wp_signals:
                return "jsx"
        # Lowercase tag with JSX-only syntax = JSX
        if has_jsx_attr or has_jsx_spread:
            return "jsx"
        # If it has php-style template inside, still call it php
        if "<?" in code_block or php_signals >= 2:
            return "php"
        return "html"

    # ---- CSS ----
    if re.search(r"^[\.#]?[\w-]+\s*\{", head) and ":" in head and ";" in head and php_signals == 0:
        return "css"

    # ---- Shell ----
    shell_starts = ("$ ", "# ", "npm ", "npx ", "yarn ", "pnpm ", "git ",
                    "cd ", "ls ", "wp ", "wp-scripts ", "wp-env ", "composer ",
                    "mkdir ", "curl ", "rm ", "svn ",
                    "pecl ", "pear ", "php ", "apt ", "brew ", "sudo ", "ssh ")
    if any(s.startswith(p) for p in shell_starts):
        return "bash"

    # ---- INI / config file ----
    # Pattern: lines (or collapsed forms) of `key = value` without function calls,
    # no PHP signals, no leading function-style structure.
    # Excludes: markdown bullets (- `?key=` ...), query strings (`?key=`),
    # HTTP headers (`Header-Name:`), URLs.
    if php_signals == 0 and not s.startswith("<"):
        # Exclude markdown bullet/list lines
        starts_with_bullet = bool(re.match(r"^\s*-\s+", s))
        # Exclude query-string-looking content
        contains_query_string = "?" in s and "=" in s and bool(re.search(r"\?\w+=", s))
        # Exclude HTTP header lines (`Word-Word: value` at start)
        starts_with_http_header = bool(re.match(r"^[A-Z][\w-]*:\s+\S", s))
        # Exclude URL-like content at start
        starts_with_url = s.startswith(("http://", "https://", "ftp://"))

        if (not starts_with_bullet
            and not contains_query_string
            and not starts_with_http_header
            and not starts_with_url):
            kv_matches = re.findall(r"^\s*\w[\w.-]*\s*=\s*(?:[A-Za-z0-9_./\-]+|\"[^\"]*\")",
                                    code_block, re.MULTILINE)
            # Avoid PHP function-call style: no `(...)` calls
            has_calls = bool(re.search(r"\w+\s*\(", code_block))
            if len(kv_matches) >= 2 and not has_calls:
                return "ini"
            # Single-line case: starts with "key = value" and has no calls
            if len(kv_matches) >= 1 and not has_calls and re.match(r"^\s*\w[\w.-]*\s*=\s*\S", s):
                # Additional guard: more than just a single short k=v (could be markdown text)
                if len(s) >= 15 or s.count("=") >= 1:
                    return "ini"

    # ---- PHP (default in WP handbook context) ----
    if php_signals >= 1:
        return "php"

    # ---- JS object fragment ----
    # Pattern: `identifier: { ... }` (unquoted key — JS object property,
    # often a block.json `supports`/`attributes` fragment shown as JS literal)
    if re.match(r"^\w+\s*:\s*[\{\[]", s) and "=>" not in code_block and "$" not in code_block:
        return "js"

    # ---- Lone "function name() {" without PHP signals — JS ----
    if "function " in head:
        return "js"

    return "text"


# ---------------------------------------------------------------------------
# Pipeline stages
# ---------------------------------------------------------------------------

def normalize_line_endings(text: str) -> str:
    return text.replace("\r\n", "\n").replace("\r", "\n")


def strip_top_url(lines: list[str]) -> tuple[list[str], str | None]:
    """Find and remove the top bare-URL line. Returns (lines, url-or-none)."""
    url = None
    # Allow up to 3 leading blank lines
    for i, line in enumerate(lines[:5]):
        if RE_TOP_URL.match(line):
            url = line.strip()
            lines = lines[:i] + lines[i + 1:]
            break
    # Remove any leading blank lines after removal
    while lines and lines[0].strip() == "":
        lines.pop(0)
    return lines, url


def strip_heading_anchors(lines: list[str]) -> list[str]:
    out = []
    for line in lines:
        m = RE_HEADING_ANCHORED.match(line)
        if m:
            level, title = m.group(1), m.group(2).strip()
            out.append(f"{level} {title}")
        else:
            out.append(line)
    return out


def strip_scrape_artifacts(lines: list[str]) -> list[str]:
    out = []
    for line in lines:
        if RE_COPY_LINK.match(line):
            continue
        if RE_EXPAND_LINK.match(line):
            continue
        if RE_EMPTY_BACKTICKS.match(line):
            continue
        out.append(line)
    return out


def collapse_blank_runs(lines: list[str], max_blank: int = 2) -> list[str]:
    out = []
    blank = 0
    for line in lines:
        if line.strip() == "":
            blank += 1
            if blank <= max_blank:
                out.append(line)
        else:
            blank = 0
            out.append(line)
    return out


def fence_indented_blocks(lines: list[str]) -> tuple[list[str], bool]:
    """
    Convert runs of 4-space indented lines into fenced code blocks.
    Returns (new_lines, degraded_detected).
    """
    out: list[str] = []
    i = 0
    degraded = False
    while i < len(lines):
        line = lines[i]
        m = RE_INDENTED.match(line)
        if m and line.strip() != "":
            # Start of an indented block. Gather.
            block: list[str] = []
            j = i
            # Allow blank lines INSIDE the indented block (common in markdown).
            while j < len(lines):
                lj = lines[j]
                mj = RE_INDENTED.match(lj)
                if mj:
                    block.append(mj.group(1))
                    j += 1
                elif lj.strip() == "":
                    # Peek ahead: blank then more indented = still block
                    k = j + 1
                    while k < len(lines) and lines[k].strip() == "":
                        k += 1
                    if k < len(lines) and RE_INDENTED.match(lines[k]):
                        block.append("")
                        j += 1
                    else:
                        break
                else:
                    break

            code = "\n".join(block).rstrip()

            # Skip if block is empty/whitespace-only
            if not code.strip():
                out.append(line)
                i += 1
                continue

            # Detect degraded (collapsed) code
            is_degraded = bool(
                RE_PHP_COLLAPSED.search(code)
                or RE_STMT_THEN_COMMENT.search(code)
                or is_long_collapsed_call(code)
                or (RE_MULTI_STMT_COLLAPSED.search(code) and "\n" not in code and len(code) > 80)
            )
            if is_degraded:
                degraded = True
                out.append("> [!WARNING]")
                out.append("> Code block appears degraded due to lost newlines during scraping.")
                out.append("")

            lang = detect_lang(code)
            out.append(f"```{lang}")
            out.extend(code.split("\n"))
            out.append("```")

            i = j
        else:
            out.append(line)
            i += 1
    return out, degraded


# ---------------------------------------------------------------------------
# Main processing
# ---------------------------------------------------------------------------

def already_has_frontmatter(text: str) -> bool:
    """
    True if text starts with a valid YAML frontmatter block (---, key: value..., ---).
    Multi-section separators (--- alone followed by prose or URL) do NOT qualify.
    """
    stripped = text.lstrip()
    if not (stripped.startswith("---\n") or stripped.startswith("---\r\n")):
        return False
    # Check that next non-blank line looks like a YAML key (`word: value`),
    # not a URL (`https:...`) and not a multi-section separator (`---`).
    lines = stripped.split("\n")
    for line in lines[1:6]:
        s = line.strip()
        if s == "":
            continue
        if s == "---":
            return False
        # URL — not frontmatter
        if s.startswith(("http://", "https://", "ftp://")):
            return False
        # Looks like YAML key
        if re.match(r"^[a-z_][a-z0-9_]{0,30}:\s*\S", s):
            return True
        return False
    return False


def extract_h1_title(lines: list[str]) -> str | None:
    for line in lines[:30]:
        m = re.match(r"^#\s+(.+?)\s*$", line)
        if m:
            return normalize_title(m.group(1).strip())
    return None


def normalize_title(title: str) -> str:
    """
    Strip common markdown escapes from a title.
    Frontmatter title is machine metadata, so we want the raw display name.
    Body H1 is preserved untouched.
    """
    # Remove backslash before _, *, ., ~, ` (markdown escapes)
    return re.sub(r"\\([_*.~`])", r"\1", title)


def process_file(
    src_path: Path,
    dst_path: Path,
    manifest_entry: dict,
    synced_date: str,
) -> dict:
    """Process one file. Returns report dict for changelog."""
    raw = src_path.read_text(encoding="utf-8")
    raw = normalize_line_endings(raw)

    if already_has_frontmatter(raw):
        # Idempotent skip
        dst_path.parent.mkdir(parents=True, exist_ok=True)
        dst_path.write_text(raw, encoding="utf-8")
        return {"file": str(src_path.name), "status": "already-clean"}

    lines = raw.split("\n")
    lines, top_url = strip_top_url(lines)

    # source_url priority: manifest > top-of-file URL > error
    source_url = manifest_entry.get("source_url") or top_url
    if not source_url:
        return {"file": str(src_path.name), "status": "error", "error": "no source_url"}

    lines = strip_heading_anchors(lines)
    lines = strip_scrape_artifacts(lines)
    lines, degraded = fence_indented_blocks(lines)
    lines = collapse_blank_runs(lines)

    # Title: manifest takes precedence, else extract from H1
    title = manifest_entry.get("title") or extract_h1_title(lines) or src_path.stem
    title = normalize_title(title)

    meta = FileMeta(
        source_url=source_url,
        synced=synced_date,
        handbook=manifest_entry["handbook"],
        chapter=manifest_entry["chapter"],
        slug=manifest_entry["slug"],
        parent_order=manifest_entry["parent_order"],
        page_order=manifest_entry["page_order"],
        title=title,
        sub_chapter=manifest_entry.get("sub_chapter"),
        sub_order=manifest_entry.get("sub_order"),
        sub_sub_chapter=manifest_entry.get("sub_sub_chapter"),
        sub_sub_order=manifest_entry.get("sub_sub_order"),
        code_quality="degraded" if degraded else None,
        code_issue="pre_newline_loss" if degraded else None,
    )

    # Strip leading blanks before body
    while lines and lines[0].strip() == "":
        lines.pop(0)

    body = "\n".join(lines).rstrip() + "\n"
    output = meta.to_yaml() + "\n\n" + body

    dst_path.parent.mkdir(parents=True, exist_ok=True)
    dst_path.write_text(output, encoding="utf-8")

    return {
        "file": str(src_path.name),
        "status": "cleaned",
        "degraded": degraded,
        "title": title,
    }


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--manifest", required=True, help="JSON file mapping relative paths to FileMeta dicts")
    ap.add_argument("--input-root", required=True)
    ap.add_argument("--output-root", required=True)
    ap.add_argument("--synced", default="2026-05-12")
    args = ap.parse_args()

    manifest = json.loads(Path(args.manifest).read_text(encoding="utf-8"))
    in_root = Path(args.input_root)
    out_root = Path(args.output_root)

    reports = []
    for rel_path, entry in manifest.items():
        src = in_root / rel_path
        dst = out_root / rel_path
        if not src.exists():
            reports.append({"file": rel_path, "status": "missing"})
            continue
        rep = process_file(src, dst, entry, args.synced)
        rep["path"] = rel_path
        reports.append(rep)

    # Summary
    print(json.dumps({"reports": reports}, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
