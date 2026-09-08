#!/usr/bin/env python3
"""
validate_styleguide_layout.py — check the layout CSS against the spec, and
against the theme.

Three questions, and they are not the same question.

  1. Do the generated CSS values match _data/measurement.yml and
     _data/layout.yml? That catches a hand-edit or a generator bug - the
     generator's own --check only proves the file is what the generator
     produces, which a buggy generator satisfies happily.

  2. Do the media queries carry the breakpoint values the data declares? They
     have to be literals, because a media query cannot evaluate var(), so
     nothing but a check keeps them in step with the custom properties beside
     them.

  3. Does the spacing scale still match the theme's? The style guide declares
     it because WordPress builds --wp--preset--spacing--* at runtime and leaves
     no stylesheet to copy - but the theme.json those presets come from carries
     the same scale, so the two are one fact stored twice. This is the check
     that notices when they stop being one fact.
"""

from __future__ import annotations

import json
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
THEME_JSON = ROOT / "products/wordpress/themes/axismundi/theme.json"

MEASUREMENT_DATA = STYLEGUIDE / "_data/measurement.yml"
LAYOUT_DATA = STYLEGUIDE / "_data/layout.yml"
MEASUREMENT_CSS = STYLEGUIDE / "assets/css/tokens.sys.measurement.css"
LAYOUT_CSS = STYLEGUIDE / "assets/css/tokens.sys.layout.css"


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


def as_px(value: int | float) -> str:
    return "0" if value == 0 else f"{value}px"


def declarations(text: str) -> dict[str, str]:
    return {m.group(1): m.group(2).strip()
            for m in re.finditer(r"(--[\w-]+):\s*([^;]+);", text)}


def root_block(css: str, path: Path, report: Report) -> dict[str, str]:
    match = re.search(r"^:root \{\n(.*?)^\}", css, re.S | re.M)
    if match is None:
        report.fail(f"{path.name}: no :root block")
        return {}
    return declarations(match.group(1))


def main() -> int:
    for required in (MEASUREMENT_DATA, LAYOUT_DATA, MEASUREMENT_CSS, LAYOUT_CSS, THEME_JSON):
        if not required.is_file():
            print(f"missing: {required.relative_to(ROOT).as_posix()}")
            return 1

    measurement = yaml.safe_load(MEASUREMENT_DATA.read_text(encoding=UTF8))
    layout = yaml.safe_load(LAYOUT_DATA.read_text(encoding=UTF8))
    m_css = MEASUREMENT_CSS.read_text(encoding=UTF8)
    l_css = LAYOUT_CSS.read_text(encoding=UTF8)
    report = Report()

    # 1. spacing scale, CSS against data
    m_root = root_block(m_css, MEASUREMENT_CSS, report)
    for step, value in measurement["scale"].items():
        got = m_root.get(f"--md-sys-measurement-space{step}")
        if got is None:
            report.fail(f"space{step}: no token")
            continue
        report.check(got == as_px(value), f"space{step}: css {got} != spec {as_px(value)}")

    extra = [k for k in m_root if k.startswith("--md-sys-measurement-space")
             and k.rsplit("space", 1)[1] not in {str(s) for s in measurement["scale"]}]
    report.check(not extra, f"tokens with no entry in the scale: {extra}")

    # 2. breakpoints, custom properties and media queries both
    l_root = root_block(l_css, LAYOUT_CSS, report)
    classes = layout["breakpoints"]["classes"]
    for entry in classes:
        token = f"--md-sys-layout-breakpoint-{entry['name']}"
        got = l_root.get(token)
        if got is None:
            report.fail(f"{entry['name']}: no breakpoint token")
            continue
        report.check(got == as_px(entry["min"]),
                     f"{entry['name']}: css {got} != spec {as_px(entry['min'])}")

    queried = [int(m.group(1)) for m in re.finditer(r"@media \(min-width: (\d+)px\)", l_css)]
    expected = [e["min"] for e in classes[1:]]
    report.check(queried == expected,
                 f"media query minima {queried} != spec {expected}")

    # A var() in a media query is silently inert, so make sure none crept in.
    report.check("@media (min-width: var(" not in l_css,
                 "a media query uses var(), which never matches")

    # 3. panes and content widths
    for pane in layout["panes"]:
        prefix = "md" if pane["source"] == "m3" else "ax"
        token = f"--{prefix}-sys-layout-pane-{pane['name']}"
        got = l_root.get(token)
        if got is None:
            report.fail(f"{pane['name']} pane: no token {token}")
            continue
        report.check(got == as_px(pane["width"]),
                     f"{pane['name']} pane: css {got} != spec {as_px(pane['width'])}")

    widths = layout["content_widths"]
    for key, token in (("content", "--ax-sys-layout-content-max"),
                       ("wide", "--ax-sys-layout-wide-max")):
        got = l_root.get(token)
        report.check(got == as_px(widths[key]),
                     f"{token}: css {got} != spec {as_px(widths[key])}")

    # 4. grid, at every breakpoint
    grid = layout["grid"]
    blocks = {classes[0]["name"]: l_root}
    for entry in classes[1:]:
        match = re.search(
            r"@media \(min-width: " + str(entry["min"]) + r"px\) \{\n\t:root \{\n(.*?)\n\t\}",
            l_css, re.S)
        if match is None:
            report.fail(f"{entry['name']}: no media block")
            continue
        blocks[entry["name"]] = declarations(match.group(1))

    for name, block in blocks.items():
        report.check(block.get("--ax-sys-layout-columns") == str(grid["columns"][name]),
                     f"{name} columns: css {block.get('--ax-sys-layout-columns')} "
                     f"!= spec {grid['columns'][name]}")
        for field in ("margin", "gap"):
            want = f"var(--md-sys-measurement-space{grid[field][name]})"
            got = block.get(f"--ax-sys-layout-{field}")
            report.check(got == want, f"{name} {field}: css {got} != spec {want}")

    # 5. the spacing scale against the theme it came from
    theme = json.loads(THEME_JSON.read_text(encoding=UTF8))
    theme_sizes = {s["slug"]: s["size"]
                   for s in theme["settings"]["spacing"]["spacingSizes"]}
    for step, value in measurement["scale"].items():
        key = str(step)
        if key not in theme_sizes:
            # space0 is legitimately absent: WordPress has no use for a zero preset.
            report.check(value == 0,
                         f"space{step} ({value}dp) is in the scale but not in theme.json")
            continue
        report.check(theme_sizes[key] == as_px(value),
                     f"space{step}: theme.json {theme_sizes[key]} != spec {as_px(value)}")

    unknown = set(theme_sizes) - {str(s) for s in measurement["scale"]}
    report.check(not unknown,
                 f"theme.json declares spacing sizes the M3 scale does not: {sorted(unknown)}")

    settings_layout = theme["settings"].get("layout", {})
    for key, wp_key in (("content", "contentSize"), ("wide", "wideSize")):
        report.check(settings_layout.get(wp_key) == as_px(widths[key]),
                     f"{wp_key}: theme.json {settings_layout.get(wp_key)} "
                     f"!= layout.yml {as_px(widths[key])}")

    print(f"  checked {report.checked} values")
    if report.problems:
        print(f"FAIL - {len(report.problems)} problem(s)")
        for problem in report.problems:
            print(f"  - {problem}")
        return 1
    print("  layout and spacing agree with the spec and with the theme")
    return 0


if __name__ == "__main__":
    sys.exit(main())
