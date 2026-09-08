#!/usr/bin/env python3
"""Check the static Button adapter against its M3 data and theme sources."""

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


ROOT = Path(__file__).resolve().parent.parent.parent
STYLEGUIDE = ROOT / "products/styleguide"
THEME = ROOT / "products/wordpress/themes/axismundi"
DATA = STYLEGUIDE / "_data/button.yml"
ADAPTER = STYLEGUIDE / "assets/css/components/button.css"
THEME_JSON = THEME / "theme.json"
PARTIALS = {
	"connected": THEME / "styles/blocks/buttons-connected.json",
	"elevated": THEME / "styles/blocks/button-elevated.json",
    "tonal": THEME / "styles/blocks/button-tonal.json",
    "text": THEME / "styles/blocks/button-text.json",
}


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


def role(value: str | None) -> str:
    return "transparent" if value is None else f"var:preset|color|{value}"


def css_role(value: str | None) -> str:
    return "transparent" if value is None else f"var(--md-sys-color-{value})"


def block(css: str, selector: str) -> str | None:
    match = re.search(re.escape(selector) + r"\s*\{(.*?)\n\}", css, re.S)
    return match.group(1) if match else None


def declaration(css: str, name: str) -> str | None:
    match = re.search(r"(?m)^\s*" + re.escape(name) + r":\s*([^;]+);", css)
    return match.group(1).strip() if match else None


def theme_button(style: dict) -> dict:
    return style["styles"]["elements"]["button"]


def main() -> int:
    required = [DATA, ADAPTER, THEME_JSON, *PARTIALS.values()]
    missing = [path.relative_to(ROOT).as_posix() for path in required if not path.is_file()]
    if missing:
        print("missing:")
        print("\n".join(f"  - {path}" for path in missing))
        return 1

    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    css = ADAPTER.read_text(encoding="utf-8")
    theme = json.loads(THEME_JSON.read_text(encoding="utf-8"))
    partials = {
        name: json.loads(path.read_text(encoding="utf-8"))
        for name, path in PARTIALS.items()
    }
    report = Report()

    # The bound M3 Small row is the only size the deployed theme currently owns.
    small = next(row for row in data["sizes"] if row["name"] == "small")
    theme_button_style = theme["styles"]["elements"]["button"]
    spacing = theme_button_style["spacing"]["padding"]
    typography = theme_button_style["typography"]
    report.check(theme_button_style["dimensions"]["height"] == f"{small['height']}px",
                 "theme default button height differs from M3 Small")
    report.check(spacing["left"] == "var:preset|spacing|200" and spacing["right"] == "var:preset|spacing|200",
                 "theme default button padding differs from M3 Small")
    report.check(theme_button_style["border"]["radius"] == "20px",
                 "theme default button is not a 40px Small pill")
    report.check(theme_button_style[":active"]["border"]["radius"] == "8px",
                 "theme default pressed shape differs from M3 Small")
    report.check(typography["fontSize"] == "var:preset|font-size|label-large",
                 "theme default button no longer uses label-large")

    control = block(css, ".wp-block-button__link")
    report.check(control is not None, "adapter has no base button control rule")
    if control is not None:
        expected = {
            "block-size": f"var(--ax-button-height, {small['height']}px)",
            "padding-inline": "var(--ax-button-space, var(--md-sys-measurement-space200))",
            "border-radius": "var(--ax-button-shape, 20px)",
            "font-size": f"var(--ax-button-label-size, {small['type']['size']}px)",
            "font-weight": f"var(--ax-button-label-weight, {small['type']['weight']})",
        }
        for name, want in expected.items():
            report.check(declaration(control, name) == want,
                         f"adapter base {name} differs from M3 Small ({want})")

    # Each published colour style must agree in the spec data, static adapter,
    # and theme.json/registered partial that delivers it to the editor.
    for color in data["colors"]:
        name = color["name"]
        # M3 calls the style "outlined" while core/button registers the
        # variation as `outline`. The data owns that translation.
        selector = (
            ".wp-block-button"
            if name == "filled"
            else f".wp-block-button.{color['wp_style']}"
        )
        adapter_style = block(css, selector)
        report.check(adapter_style is not None, f"adapter has no {name} style rule")
        if adapter_style is not None:
            adapter_container = (
                "transparent"
                if color.get("container_is_outline")
                else css_role(color["container"])
            )
            report.check(declaration(adapter_style, "--ax-button-container") == adapter_container,
                         f"adapter {name} container differs from button.yml")
            report.check(declaration(adapter_style, "--ax-button-content") == css_role(color["content"]),
                         f"adapter {name} content differs from button.yml")

        if name == "filled":
            source = theme_button_style
        elif name == "outlined":
            source = theme["styles"]["blocks"]["core/button"]["variations"]["outline"]
        else:
            source = theme_button(partials[name])

        colors = source.get("color", {})
        if color["container"] is None:
            report.check(colors.get("background") == "transparent",
                         f"theme {name} background is not transparent")
        elif color.get("container_is_outline"):
            report.check(source.get("border", {}).get("color") == role(color["container"]),
                         f"theme {name} outline differs from button.yml")
        else:
            report.check(colors.get("background") == role(color["container"]),
                         f"theme {name} container differs from button.yml")
        report.check(colors.get("text") == role(color["content"]),
                     f"theme {name} content differs from button.yml")

    # These are shared contracts, not colour-style decisions.
    for token in (
        "--md-sys-state-hover-state-layer-opacity",
        "--md-sys-state-focus-state-layer-opacity",
        "--md-sys-state-pressed-state-layer-opacity",
        "--md-focus-ring-width",
        "--md-focus-ring-outward-offset",
    ):
        report.check(token in css, f"adapter does not consume {token}")

    # core/buttons Connected is a visual variation, not an M3 Button group. It
    # joins child geometry but preserves every child's independent size and style.
    connected_container = block(css, ".wp-block-buttons.is-style-connected")
    connected_child = block(css, ".wp-block-buttons.is-style-connected > .wp-block-button")
    report.check(connected_container is not None, "adapter has no Connected Buttons container rule")
    if connected_container is not None:
        report.check(declaration(connected_container, "flex-wrap") == "nowrap",
                     "Connected Buttons must remain on one visual row")
        report.check(declaration(connected_container, "gap") == "var(--md-sys-measurement-space25)",
                     "Connected Buttons gap differs from the theme variation")
    report.check(connected_child is not None, "adapter has no Connected Buttons child rule")
    if connected_child is not None:
        report.check(declaration(connected_child, "margin") == "0",
                     "Connected Buttons child margin differs from the theme variation")
        for name in ("--ax-button-container", "--ax-button-content", "--ax-button-state-role", "--ax-button-height"):
            report.check(declaration(connected_child, name) is None,
                         f"Connected Buttons must not override child {name}")

    connected_theme_css = partials["connected"]["styles"]["css"]
    for source in (
        "&{flex-wrap:nowrap;}",
        "& > .wp-block-button{margin:0;}",
        "& > .wp-block-button > .wp-block-button__link{border-radius:8px;}",
    ):
        report.check(source in connected_theme_css,
                     f"theme Connected Buttons variation no longer contains {source}")

    print(f"  checked {report.checked} button contracts")
    if report.problems:
        print(f"FAIL - {len(report.problems)} problem(s)")
        for problem in report.problems:
            print(f"  - {problem}")
        return 1
    print("  Button adapter agrees with button.yml and theme sources")
    return 0


if __name__ == "__main__":
    sys.exit(main())
