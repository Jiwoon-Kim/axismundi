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
GROUP_ADAPTER = STYLEGUIDE / "assets/css/components/button-group.css"
THEME_JSON = THEME / "theme.json"
THEME_COMPONENT_CSS = THEME / "assets/styles/components.button.css"
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
    # A style rule may serve core/button and the proposed Button group adapter
    # through a comma-separated selector list. Match selectors structurally
    # rather than requiring the requested selector to stand immediately before
    # the opening brace, otherwise valid shared contracts look absent.
    stylesheet = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    for match in re.finditer(r"([^{}]+)\{([^{}]*)\}", stylesheet, re.S):
        selectors = [part.strip() for part in match.group(1).split(",")]
        if selector in selectors:
            return match.group(2)
    return None


def declaration(css: str, name: str) -> str | None:
    match = re.search(r"(?m)^\s*" + re.escape(name) + r":\s*([^;]+);", css)
    return match.group(1).strip() if match else None


def theme_button(style: dict) -> dict:
    return style["styles"]["elements"]["button"]


def main() -> int:
    required = [DATA, ADAPTER, GROUP_ADAPTER, THEME_JSON, THEME_COMPONENT_CSS, *PARTIALS.values()]
    missing = [path.relative_to(ROOT).as_posix() for path in required if not path.is_file()]
    if missing:
        print("missing:")
        print("\n".join(f"  - {path}" for path in missing))
        return 1

    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    css = ADAPTER.read_text(encoding="utf-8")
    group_css = GROUP_ADAPTER.read_text(encoding="utf-8")
    theme_component_css = THEME_COMPONENT_CSS.read_text(encoding="utf-8")
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
            "gap": "var(--ax-button-icon-gap, var(--md-sys-measurement-space100))",
            "block-size": f"var(--ax-button-height, {small['height']}px)",
            "padding-inline": "var(--ax-button-space, var(--md-sys-measurement-space200))",
            "border-radius": "var(--ax-button-shape, 20px)",
            "font-size": f"var(--ax-button-label-size, {small['type']['size']}px)",
            "font-weight": f"var(--ax-button-label-weight, {small['type']['weight']})",
        }
        for name, want in expected.items():
            report.check(declaration(control, name) == want,
                         f"adapter base {name} differs from M3 Small ({want})")

        # M3 guidelines: the width follows the label, and a width narrower than
        # the label is the documented Don't. Declaring no width is not enough -
        # core/buttons is a flex parent whose children shrink by default, and a
        # squeezed label wraps into a container whose height is fixed per size,
        # so the overflow is clipped instead of visible. nowrap makes the label
        # the min-content floor, which is what actually holds the width.
        report.check(declaration(control, "white-space") == "nowrap",
                     "Button label must not wrap: its width follows the label")
        for name in ("width", "inline-size", "min-inline-size", "max-inline-size"):
            report.check(declaration(control, name) is None,
                         f"Button must not declare {name}: the label sets the width")

    # The slot class is the contract, not what fills it: a 7.1 icon-registry
    # reference arrives as <svg>, a typed Material Symbols name as a ligature.
    # Keying the geometry on the glyph font would bind the rule to one source.
    icon = block(css, ".wp-block-button__link > .wp-block-button__icon")
    report.check(icon is not None, "adapter has no Button icon geometry rule")
    report.check(".wp-block-button__link > .material-symbols-outlined" not in css,
                 "Button icon geometry must not key on the glyph font")
    svg = block(css, ".wp-block-button__link > .wp-block-button__icon > svg")
    report.check(svg is not None,
                 "adapter has no rule sizing a registry <svg> to the icon slot")
    if svg is not None:
        for name, want in {"inline-size": "100%", "block-size": "100%",
                           "fill": "currentColor"}.items():
            report.check(declaration(svg, name) == want,
                         f"adapter icon svg {name} does not fill the slot")
    # The Small default belongs in the var() fallback, never as a declaration on
    # the link. Declared there it is the icon's own value and outranks the one
    # inherited from .wp-block-button[data-size], so every size renders a 20px
    # glyph while its height, padding and gap scale correctly - which is exactly
    # how this was found, by measuring the rendered page rather than reading it.
    if control is not None:
        report.check(declaration(control, "--ax-button-icon-size") is None,
                     "icon size must not be declared on the link: it would "
                     "outrank the per-size value inherited from the button")
    if icon is not None:
        for name, want in {
            "flex": f"0 0 var(--ax-button-icon-size, {small['icon']}px)",
            "inline-size": f"var(--ax-button-icon-size, {small['icon']}px)",
            "block-size": f"var(--ax-button-icon-size, {small['icon']}px)",
            "font-size": f"var(--ax-button-icon-size, {small['icon']}px)",
            "line-height": "1",
        }.items():
            report.check(declaration(icon, name) == want,
                         f"adapter Button icon {name} differs from the size contract")

    # Colour is the one WordPress block-style axis. Size is a distinct block
    # attribute rendered as data-size, so selecting Tonal does not erase Medium.
    report.check(not re.search(r"\.is-style-(?:xsmall|small|medium|large|xlarge)\b", css),
                 "Button size must not consume the is-style-* variation axis")
    report.check(".is-size-" not in css,
                 "Button size must not use an additional CSS class")
    # Shape is orthogonal to colour for the same reason size is.
    report.check(".is-shape-" not in css,
                 "Button shape must not use an additional CSS class")
    report.check('[data-shape="square"]' in css,
                 "adapter has no data-shape square rule")
    for size in data["sizes"]:
        if size["name"] == "small":
            continue
        size_rule = block(css, f'.wp-block-button[data-size="{size["name"]}"]')
        report.check(size_rule is not None,
                     f"adapter has no {size['name']} data-size rule")
        if size_rule is not None:
            report.check(declaration(size_rule, "--ax-button-icon-size") == f"{size['icon']}px",
                         f"adapter {size['name']} icon size differs from button.yml")
            report.check(declaration(size_rule, "--ax-button-icon-gap") ==
                         f"var(--md-sys-measurement-space{int(size['icon_gap'] * 12.5)})",
                         f"adapter {size['name']} icon gap differs from button.yml")

    # Prose gives ordinary links a primary colour. The block editor's real
    # parent-child relationship must therefore restate the Button label colour
    # so an anchor Button can still render on-primary for the Filled default.
    linked_control = block(css, ".wp-block-button > .wp-block-button__link")
    report.check(linked_control is not None,
                 "adapter has no WordPress DOM-specific Button label rule")
    if linked_control is not None:
        report.check(declaration(linked_control, "color") == "var(--ax-button-content)",
                     "anchor Button label does not preserve its style content role")

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

        # Connected Button groups do not choose a separate palette. Their
        # wrapper consumes this same Button style, then segments read the
        # published toggle colours for resting and selected states. Text has
        # no toggle form in M3, so it deliberately has no group counterpart.
        if color["toggle_unselected"] is not None:
            group_selector = (
                ".wp-block-axismundi-button-group"
                if name == "filled"
                else f".wp-block-axismundi-button-group.{color['wp_style']}"
            )
            group_style = block(css, group_selector)
            report.check(group_style is not None,
                         f"adapter has no {name} Button group style rule")
            if group_style is not None:
                for state in ("unselected", "selected"):
                    toggle = color[f"toggle_{state}"]
                    for role_name, role_value in toggle.items():
                        property_name = f"--ax-button-toggle-{state}-{role_name}"
                        expected_value = (
                            "transparent"
                            if color.get("container_is_outline") and state == "unselected" and role_name == "container"
                            else css_role(role_value)
                        )
                        report.check(declaration(group_style, property_name) == expected_value,
                                     f"Button group {name} {state} {role_name} differs from button.yml")

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

    group_item = block(group_css, ".wp-block-axismundi-button-group__item")
    report.check(group_item is not None, "Button group adapter has no segment rule")
    if group_item is not None:
        expected_group_surface = {
            "background-color": "var(--ax-button-toggle-unselected-container)",
            "color": "var(--ax-button-toggle-unselected-content)",
        }
        for name, want in expected_group_surface.items():
            report.check(declaration(group_item, name) == want,
                         f"Button group segment {name} does not consume Button toggle roles")

    selected_group = block(
        group_css,
        ".wp-block-axismundi-button-group__item[aria-pressed=\"true\"]",
    )
    report.check(selected_group is not None,
                 "Button group adapter has no selected segment rule")
    if selected_group is not None:
        expected_selected_surface = {
            "background-color": "var(--ax-button-toggle-selected-container)",
            "color": "var(--ax-button-toggle-selected-content)",
        }
        for name, want in expected_selected_surface.items():
            report.check(declaration(selected_group, name) == want,
                         f"Button group selected segment {name} does not consume Button toggle roles")

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

    connected_link = block(
        css,
        ".wp-block-buttons.is-style-connected > .wp-block-button > .wp-block-button__link",
    )
    connected_active_link = block(
        css,
        ".wp-block-buttons.is-style-connected > .wp-block-button > .wp-block-button__link:active",
    )
    report.check(connected_link is not None, "adapter has no Connected Buttons inner-corner rule")
    if connected_link is not None:
        report.check(declaration(connected_link, "border-radius") == "var(--md-sys-shape-corner-value-small)",
                     "Connected Buttons resting inner corners differ from the theme variation")
    report.check(connected_active_link is not None, "adapter has no Connected Buttons pressed inner-corner rule")
    if connected_active_link is not None:
        report.check(declaration(connected_active_link, "border-radius") == "var(--md-sys-shape-corner-value-extra-small)",
                     "Connected Buttons pressed inner corners differ from the theme variation")

    connected_theme_css = partials["connected"]["styles"]["css"]
    for source in (
        "&{flex-wrap:nowrap;}",
        "& > .wp-block-button{margin:0;}",
        "& > .wp-block-button > .wp-block-button__link{border-radius:8px;}",
        "& > .wp-block-button > .wp-block-button__link:active{border-radius:4px;}",
    ):
        report.check(source in connected_theme_css,
                     f"theme Connected Buttons variation no longer contains {source}")

    # `is-style-outline` is core/button's canonical variation. The theme still
    # has a multi-block `outlined` partial for Dialog and Sheet, and old posts
    # may carry that legacy class. Core emits its element rule as an impossible
    # descendant selector, so the direct public-class shim is a front-end as
    # well as editor-parity contract.
    legacy_outline = block(
        theme_component_css,
        ".wp-block-button.is-style-outlined .wp-block-button__link:not(.has-background)",
    )
    report.check(legacy_outline is not None,
                 "theme has no legacy is-style-outlined compatibility shim")
    if legacy_outline is not None:
        expected_legacy_outline = {
            "background-color": "transparent",
            "background-image": "none",
            "border": "1px solid var(--wp--preset--color--outline-variant)",
            "color": "var(--wp--preset--color--on-surface-variant)",
        }
        for name, want in expected_legacy_outline.items():
            report.check(declaration(legacy_outline, name) == want,
                         f"legacy is-style-outlined {name} differs from core outline")
    for state, opacity in (("hover", "hover"), ("focus", "focus"), ("active", "pressed")):
        state_rule = block(
            theme_component_css,
            f".wp-block-button.is-style-outlined .wp-block-button__link:{state}",
        )
        report.check(state_rule is not None,
                     f"theme legacy is-style-outlined has no {state} state rule")
        if state_rule is not None:
            expected = (
                "color-mix(in srgb, var(--wp--preset--color--on-surface-variant) "
                f"calc(var(--md-sys-state-{opacity}-state-layer-opacity) * 100%), transparent)"
            )
            report.check(declaration(state_rule, "background-color") == expected,
                         f"legacy is-style-outlined {state} state differs from core outline")

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
