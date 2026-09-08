#!/usr/bin/env python3
"""
generate_styleguide_layout.py — build the measurement and layout token CSS.

    sources  products/styleguide/_data/measurement.yml
             products/styleguide/_data/layout.yml
    outputs  products/styleguide/assets/css/tokens.sys.measurement.css
             products/styleguide/assets/css/tokens.sys.layout.css

Both outputs are committed, and `--check` regenerates in memory and compares so
a hand-edit or an un-rebuilt data change fails rather than passing quietly.
That is the same contract the typography generator carries, and for the same
reason: the file a reader opens should be the file a pull request shows.

Breakpoints are why a generator is not optional here. A media query cannot
evaluate `var()`, so their values have to be written into the CSS text at build
time. Emitting them as custom properties as well - for JavaScript, and for a
page that wants to print them - keeps the query and the documentation reading
from one source instead of two that drift.

Token prefixes follow the `source` field in the data. M3 publishes the
breakpoints and the supporting pane width, so those are `--md-sys-*`. Column
counts, the space around and between them, and WordPress's content and wide
widths are this project's decisions, so those are `--ax-sys-*`.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

try:
    import yaml
except ImportError:  # pragma: no cover
    print("PyYAML is required: pip install pyyaml", file=sys.stderr)
    raise SystemExit(2)

UTF8 = "utf-8"
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding=UTF8)
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding=UTF8)

ROOT = Path(__file__).resolve().parent.parent.parent
STYLEGUIDE = ROOT / "products/styleguide"
MEASUREMENT_DATA = STYLEGUIDE / "_data/measurement.yml"
LAYOUT_DATA = STYLEGUIDE / "_data/layout.yml"
MEASUREMENT_OUT = STYLEGUIDE / "assets/css/tokens.sys.measurement.css"
LAYOUT_OUT = STYLEGUIDE / "assets/css/tokens.sys.layout.css"

MEASUREMENT_HEADER = """/* ============================================================
 * Style guide — spacing scale
 *
 * GENERATED from _data/measurement.yml. Do not edit.
 *   build   python tools/generators/generate_styleguide_layout.py
 *   verify  python tools/validators/validate_styleguide_layout.py
 *
 * M3's md.sys.measurement.space* scale, published in dp and written here in
 * px, which is the same number on the web.
 *
 * The theme already ships this exact scale as theme.json spacingSizes, down to
 * the slugs, so nothing here is a parallel invention - it is the published
 * scale, finally written where CSS can reach it. WordPress generates
 * --wp--preset--spacing--* from those settings at runtime, which is why the
 * theme has no spacing stylesheet to copy and this file exists at all.
 * ============================================================ */
"""

LAYOUT_HEADER = """/* ============================================================
 * Style guide — layout
 *
 * GENERATED from _data/layout.yml. Do not edit.
 *   build   python tools/generators/generate_styleguide_layout.py
 *   verify  python tools/validators/validate_styleguide_layout.py
 *
 * Breakpoint values appear twice on purpose. A media query cannot evaluate
 * var(), so each one is written as a literal in the query and again as a
 * custom property for anything that can read one. Both come from the same
 * data, so the query and the number a page prints cannot disagree.
 *
 * --md-sys-* is M3's: the breakpoints and the 360dp supporting pane.
 * --ax-sys-* is this project's: column counts, the margin and gap around and
 * between them, and WordPress's content and wide widths - which are a separate
 * axis from the breakpoints, since one decides how panes are arranged and the
 * other how wide a line of text is allowed to get.
 * ============================================================ */
"""


def px(value: int | float) -> str:
    return "0" if value == 0 else f"{value}px"


def render_measurement(data: dict) -> str:
    scale = data["scale"]
    nested = set(data["meta"].get("nested") or [])
    lines = [MEASUREMENT_HEADER, ":root {"]
    for step, value in scale.items():
        suffix = "  /* nested unit */" if step in nested else ""
        lines.append(f"\t--md-sys-measurement-space{step}: {px(value)};{suffix}")
    lines.append("}")
    return "\n".join(lines) + "\n"


def render_layout(data: dict) -> str:
    classes = data["breakpoints"]["classes"]
    grid = data["grid"]
    widths = data["content_widths"]

    by_name = {c["name"]: c for c in classes}
    for field in ("columns", "margin", "gap"):
        missing = set(by_name) - set(grid[field])
        if missing:
            raise SystemExit(f"layout.yml: grid.{field} has no value for {sorted(missing)}")

    lines = [LAYOUT_HEADER, ":root {"]

    lines.append("\t/* Breakpoint minima. Readable by script; the queries below")
    lines.append("\t * carry the same numbers as literals because they must. */")
    for entry in classes:
        lines.append(f"\t--md-sys-layout-breakpoint-{entry['name']}: {px(entry['min'])};")
    lines.append("")

    for pane in data["panes"]:
        prefix = "md" if pane["source"] == "m3" else "ax"
        lines.append(f"\t--{prefix}-sys-layout-pane-{pane['name']}: {px(pane['width'])};")
    lines.append("")

    lines.append("\t/* WordPress's own axis, not a breakpoint. */")
    lines.append(f"\t--ax-sys-layout-content-max: {px(widths['content'])};")
    lines.append(f"\t--ax-sys-layout-wide-max: {px(widths['wide'])};")
    lines.append("")

    first = classes[0]["name"]
    lines.append(f"\t/* Grid, at {first}. Each query below restates all three. */")
    lines.append(f"\t--ax-sys-layout-columns: {grid['columns'][first]};")
    lines.append(f"\t--ax-sys-layout-margin: var(--md-sys-measurement-space{grid['margin'][first]});")
    lines.append(f"\t--ax-sys-layout-gap: var(--md-sys-measurement-space{grid['gap'][first]});")
    lines.append("}")
    lines.append("")

    for entry in classes[1:]:
        name = entry["name"]
        lines.append(f"/* {name} — {entry['min']}dp and up */")
        lines.append(f"@media (min-width: {px(entry['min'])}) {{")
        lines.append("\t:root {")
        lines.append(f"\t\t--ax-sys-layout-columns: {grid['columns'][name]};")
        lines.append(
            f"\t\t--ax-sys-layout-margin: var(--md-sys-measurement-space{grid['margin'][name]});"
        )
        lines.append(
            f"\t\t--ax-sys-layout-gap: var(--md-sys-measurement-space{grid['gap'][name]});"
        )
        lines.append("\t}")
        lines.append("}")
        lines.append("")

    return "\n".join(lines).rstrip() + "\n"


def emit(path: Path, text: str, check: bool) -> int:
    rel = path.relative_to(ROOT).as_posix()
    if not check:
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text, encoding=UTF8, newline="\n")
        print(f"  wrote {rel}")
        return 0

    if not path.is_file():
        print(f"FAIL - {rel} does not exist; run the generator")
        return 1
    current = path.read_text(encoding=UTF8)
    if current == text:
        print(f"  {rel} matches the generator")
        return 0

    for i, (a, b) in enumerate(zip(current.splitlines(), text.splitlines()), 1):
        if a != b:
            print(f"FAIL - {rel} differs from the generator at line {i}")
            print(f"  committed: {a.strip()}")
            print(f"  generated: {b.strip()}")
            break
    else:
        print(f"FAIL - {rel} differs from the generator in length")
    print("  Edit the _data file and rebuild, do not edit the CSS.")
    return 1


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true",
                        help="fail if the committed CSS differs from what this would write")
    args = parser.parse_args()

    for required in (MEASUREMENT_DATA, LAYOUT_DATA):
        if not required.is_file():
            print(f"missing source: {required.relative_to(ROOT).as_posix()}")
            return 1

    measurement = render_measurement(yaml.safe_load(MEASUREMENT_DATA.read_text(encoding=UTF8)))
    layout = render_layout(yaml.safe_load(LAYOUT_DATA.read_text(encoding=UTF8)))

    status = emit(MEASUREMENT_OUT, measurement, args.check)
    status |= emit(LAYOUT_OUT, layout, args.check)
    return status


if __name__ == "__main__":
    sys.exit(main())
