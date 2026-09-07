#!/usr/bin/env python3
"""
validate_styleguide_typography.py — check the type scale CSS against the spec.

    spec  products/styleguide/_data/typography.yml
    css   products/styleguide/assets/css/tokens.sys.typography.css

Reads the CSS back, converts every rem to the unit the spec publishes, and
compares. Also checks that no role holds a literal family or weight where a
reference token belongs, and that the language-height blocks the layout can
select all exist.

This answers a different question from the generator's --check. That one asks
whether the file equals what the generator produces; this asks whether its
values equal the published spec. A bug in the generator passes the first and
fails here, which is the point of having both.
"""

from __future__ import annotations

import re
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
DATA = STYLEGUIDE / "_data/typography.yml"
CSS = STYLEGUIDE / "assets/css/tokens.sys.typography.css"

PREFIX = "--md-sys-typescale"
REF_WEIGHT = re.compile(r"^var\(--md-ref-typeface-weight-(\w+)\)$")
REF_FAMILY = re.compile(r"^var\(--md-ref-typeface-(\w+)\)$")


class Report:
    def __init__(self) -> None:
        self.problems: list[str] = []
        self.checked = 0

    def check(self, ok: bool, message: str) -> None:
        self.checked += 1
        if not ok:
            self.problems.append(message)

    def fail(self, message: str) -> None:
        self.checked += 1
        self.problems.append(message)


def declarations(block: str) -> dict[str, str]:
    return {
        m.group(1): m.group(2).strip()
        for m in re.finditer(r"(--[\w-]+):\s*([^;]+);", block)
    }


def published(value: str, divisor: int, where: str, report: Report):
    """rem back to the unit the spec publishes."""
    if value == "0":
        return 0.0
    m = re.fullmatch(r"(-?[\d.]+)rem", value)
    if not m:
        report.fail(f"{where}: not a rem value: {value}")
        return None
    return round(float(m.group(1)) * divisor, 6)


def main() -> int:
    for required in (DATA, CSS):
        if not required.is_file():
            print(f"missing: {required.relative_to(ROOT).as_posix()}")
            return 1

    data = yaml.safe_load(DATA.read_text(encoding=UTF8))
    css = CSS.read_text(encoding=UTF8)
    divisor = data["meta"]["rem_divisor"]
    weights = data["weights"]
    roles = data["roles"]
    sets = data["language_height_sets"]
    report = Report()

    root_match = re.search(r"^:root \{\n(.*?)^\}", css, re.S | re.M)
    if root_match is None:
        print("could not find the :root block")
        return 1
    root = declarations(root_match.group(1))

    for name, role in roles.items():
        base = f"{PREFIX}-{name}"

        family = root.get(f"{base}-font")
        if family is None:
            report.fail(f"{name}: no font token")
        else:
            m = REF_FAMILY.match(family)
            if m is None:
                report.fail(f"{name} font: literal instead of a reference token: {family}")
            else:
                report.check(
                    m.group(1) == role["typeface"],
                    f"{name} font: css {m.group(1)} != spec {role['typeface']}",
                )

        size = root.get(f"{base}-size")
        if size is None:
            report.fail(f"{name}: no size token")
        else:
            got = published(size, divisor, f"{name} size", report)
            if got is not None:
                report.check(got == role["size"], f"{name} size: css {got} != spec {role['size']}")

        track = root.get(f"{base}-tracking")
        if track is None:
            report.fail(f"{name}: no tracking token")
        else:
            got = published(track, divisor, f"{name} tracking", report)
            if got is not None:
                report.check(
                    abs(got - role["tracking"]) < 1e-9,
                    f"{name} tracking: css {got} != spec {role['tracking']}",
                )

        for suffix, want in (("weight", role["weight"]),
                             ("emphasized-weight", role["emphasized_weight"])):
            value = root.get(f"{base}-{suffix}")
            if value is None:
                report.fail(f"{name}: no {suffix} token")
                continue
            m = REF_WEIGHT.match(value)
            if m is None:
                report.fail(f"{name} {suffix}: literal instead of a reference token: {value}")
                continue
            named = weights.get(m.group(1))
            if named is None:
                report.fail(f"{name} {suffix}: {m.group(1)} is not in the weights table")
                continue
            report.check(named == want, f"{name} {suffix}: css {named} != spec {want}")

        report.check(
            f"{base}-line-height" not in root,
            f"{name}: line height belongs in a language-height block, not :root",
        )

    for set_name in sets:
        if set_name == "small":
            block = re.search(
                r'^:root,\n:root\[data-language-height="small"\] \{\n(.*?)^\}', css, re.S | re.M
            )
        else:
            block = re.search(
                r'^:root\[data-language-height="' + re.escape(set_name) + r'"\] \{\n(.*?)^\}',
                css, re.S | re.M,
            )
        if block is None:
            report.fail(f"no block for the {set_name} language height set")
            continue
        declared = declarations(block.group(1))
        for name, role in roles.items():
            token = f"{PREFIX}-{name}-line-height"
            value = declared.get(token)
            if value is None:
                report.fail(f"{set_name}/{name}: no line height")
                continue
            got = published(value, divisor, f"{set_name}/{name} line height", report)
            want = role["line_height"].get(set_name)
            if got is not None and want is not None:
                report.check(got == want, f"{set_name}/{name} line height: css {got} != spec {want}")

    print(f"  checked {report.checked} values against {DATA.relative_to(ROOT).as_posix()}")
    if report.problems:
        print(f"FAIL - {len(report.problems)} problem(s)")
        for problem in report.problems:
            print(f"  - {problem}")
        return 1
    print("  every value matches the published spec")
    return 0


if __name__ == "__main__":
    sys.exit(main())
