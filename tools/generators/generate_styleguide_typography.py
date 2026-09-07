#!/usr/bin/env python3
"""
generate_styleguide_typography.py — build the style guide's type scale CSS.

    source  products/styleguide/_data/typography.yml
    output  products/styleguide/assets/css/tokens.sys.typography.css

The output is committed even though it is generated, so a pull request shows
the typography change itself rather than a data diff the reader has to compile
in their head. `--check` guards that: it regenerates in memory and compares,
failing if the committed file was hand-edited or the data moved without a
rebuild.

That is a different question from the one the validator answers. This asks
whether the file equals what the generator produces; the validator asks whether
the file's values equal the published spec. A bug in this script passes the
first and fails the second.

Units: the spec publishes pt and its own web mapping is 1sp = 0.0625rem, so
sizes, tracking and line heights are the published value over rem_divisor.
Every number in the output is computed here, never transcribed.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

try:
    import yaml
except ImportError:  # pragma: no cover - environment problem, not a data problem
    print("PyYAML is required: pip install pyyaml", file=sys.stderr)
    raise SystemExit(2)

UTF8 = "utf-8"
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding=UTF8)
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding=UTF8)

ROOT = Path(__file__).resolve().parent.parent.parent
STYLEGUIDE = ROOT / "products/styleguide"
DATA = STYLEGUIDE / "_data/typography.yml"
OUT = STYLEGUIDE / "assets/css/tokens.sys.typography.css"

GROUPS = ("display", "headline", "title", "body", "label")
PREFIX = "--md-sys-typescale"

HEADER = """/* ============================================================
 * Style guide — type scale
 *
 * GENERATED from _data/typography.yml. Do not edit.
 *   build   python tools/generators/generate_styleguide_typography.py
 *   verify  python tools/validators/validate_styleguide_typography.py
 *
 * Committed on purpose: a pull request should show the typography change, not
 * a data diff the reader has to compile in their head. The generator's --check
 * mode is what keeps this file honest.
 *
 * The tokens carry M3's canonical names rather than a parallel `--sg-` set, so
 * there is one name per concept across the project. The cost is that a
 * specimen page which also loads the theme's or the lab's token CSS will have
 * two definitions in play; scope the specimen when that day comes.
 *
 * No role emits font-variation-settings. The spec assigns each an `opsz` equal
 * to its font size, which `font-optical-sizing: auto` already does on a
 * variable font, and every other axis it lists (GRAD 0, wdth 100, ROND 0,
 * CRSV 0, slnt 0, FILL 0, HEXP 0) is constant across roles and already the
 * @font-face default.
 *
 * Emphasized styles differ from their baseline role in weight alone, so only
 * the weight is emitted; font, size, tracking and line height are shared.
 *
 * Line height is declared in the language-height blocks below rather than
 * beside the rest of each role, because M3 publishes four values per role -
 * one per set - and raises them for scripts that need more vertical room. The
 * layout picks the set from the document language.
 * ============================================================ */
"""


def rem(value: float, divisor: int) -> str:
    """Published value -> rem, trimmed. Unitless zero, since 0rem reads odd."""
    v = value / divisor
    if v == 0:
        return "0"
    text = f"{v:.6f}".rstrip("0").rstrip(".")
    return f"{text}rem"


def weight_name(weights: dict, value: int, where: str) -> str:
    for name, published in weights.items():
        if published == value:
            return name
    raise SystemExit(f"{where}: weight {value} is not in the weights table")


def render(data: dict) -> str:
    divisor = data["meta"]["rem_divisor"]
    weights = data["weights"]
    roles = data["roles"]
    sets = data["language_height_sets"]

    ordered = []
    for group in GROUPS:
        members = [name for name in roles if name.startswith(group + "-")]
        if not members:
            raise SystemExit(f"typography.yml has no {group}-* roles")
        ordered.append((group, members))

    unknown = set(roles) - {name for _, members in ordered for name in members}
    if unknown:
        raise SystemExit(f"typography.yml has roles outside the known groups: {sorted(unknown)}")

    lines = [HEADER, ":root {"]

    for group, members in ordered:
        lines.append(f"\t/* {group.capitalize()} */")
        for name in members:
            role = roles[name]
            w = weight_name(weights, role["weight"], name)
            lines.append(f"\t{PREFIX}-{name}-font: var(--md-ref-typeface-{role['typeface']});")
            lines.append(f"\t{PREFIX}-{name}-size: {rem(role['size'], divisor)};")
            lines.append(f"\t{PREFIX}-{name}-weight: var(--md-ref-typeface-weight-{w});")
            lines.append(f"\t{PREFIX}-{name}-tracking: {rem(role['tracking'], divisor)};")
        lines.append("")

    lines.append("\t/* Emphasized — weight only; everything else is the baseline role's. */")
    for _, members in ordered:
        for name in members:
            role = roles[name]
            w = weight_name(weights, role["emphasized_weight"], f"{name} emphasized")
            note = ""
            if "emphasized_weight_static" in role:
                note = f"  /* spec also publishes weight {role['emphasized_weight_static']} for static Roboto */"
            lines.append(f"\t{PREFIX}-{name}-emphasized-weight: var(--md-ref-typeface-weight-{w});{note}")
    lines.append("}")
    lines.append("")

    lines.append(
        "/* Language height sets. `small` is the baseline every role falls back to;\n"
        " * set data-language-height on the root to pick another. */"
    )
    for set_name in sets:
        selector = (
            ':root,\n:root[data-language-height="small"]'
            if set_name == "small"
            else f':root[data-language-height="{set_name}"]'
        )
        lines.append(f"{selector} {{")
        for _, members in ordered:
            for name in members:
                heights = roles[name]["line_height"]
                if set_name not in heights:
                    raise SystemExit(f"{name}: no line height for the {set_name} set")
                lines.append(f"\t{PREFIX}-{name}-line-height: {rem(heights[set_name], divisor)};")
        lines.append("}")
        lines.append("")

    return "\n".join(lines).rstrip() + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--check",
        action="store_true",
        help="fail if the committed CSS differs from what this would write",
    )
    args = parser.parse_args()

    if not DATA.is_file():
        print(f"missing source: {DATA.relative_to(ROOT).as_posix()}")
        return 1

    css = render(yaml.safe_load(DATA.read_text(encoding=UTF8)))
    rel = OUT.relative_to(ROOT).as_posix()

    if args.check:
        if not OUT.is_file():
            print(f"FAIL - {rel} does not exist; run the generator")
            return 1
        current = OUT.read_text(encoding=UTF8)
        if current != css:
            current_lines = current.splitlines()
            new_lines = css.splitlines()
            for i, (a, b) in enumerate(zip(current_lines, new_lines), 1):
                if a != b:
                    print(f"FAIL - {rel} differs from the generator at line {i}")
                    print(f"  committed: {a.strip()}")
                    print(f"  generated: {b.strip()}")
                    break
            else:
                print(
                    f"FAIL - {rel} differs from the generator in length "
                    f"({len(current_lines)} committed, {len(new_lines)} generated)"
                )
            print("  Edit _data/typography.yml and rebuild, do not edit the CSS.")
            return 1
        print(f"  {rel} matches the generator")
        return 0

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(css, encoding=UTF8, newline="\n")
    role_count = len(yaml.safe_load(DATA.read_text(encoding=UTF8))["roles"])
    print(f"  wrote {rel} ({role_count} roles)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
