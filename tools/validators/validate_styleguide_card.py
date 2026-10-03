#!/usr/bin/env python3
"""Check the theme's shipped Card block styles against the published M3 card data.

`products/styleguide/_data/card.yml` restates M3's card specification, and
`products/styleguide/AGENTS.md` requires a restatement to come with a validator.
The thing worth validating is not the restatement against itself: it is the three
block style variations the theme already ships for `core/group`, which are the
only card styling in the repository today.

There is no Social Card component and no styleguide card adapter yet. When either
arrives it gets checked here too; until then this file answers one question --
does what the theme ships still agree with what M3 publishes?
"""

from __future__ import annotations

import json
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
DATA = STYLEGUIDE / "_data/card.yml"
STATE_CSS = THEME / "assets/styles/tokens/tokens.sys.state.css"
BLOCK_STYLES = {
    "elevated": THEME / "styles/blocks/group-card-elevated.json",
    "filled": THEME / "styles/blocks/group-card-filled.json",
    "outlined": THEME / "styles/blocks/group-card-outlined.json",
}


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


def preset(role: str) -> str:
    return f"var:preset|color|{role}"


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    report = Report()

    measurements = data["measurements"]
    variants = {variant["name"]: variant for variant in data["variants"]}

    report.check(
        set(variants) == set(BLOCK_STYLES),
        f"card.yml variants {sorted(variants)} do not match the theme's card block styles {sorted(BLOCK_STYLES)}",
    )

    for name, path in BLOCK_STYLES.items():
        variant = variants.get(name)
        if variant is None:
            report.problems.append(f"{name}: no entry in card.yml")
            continue

        style = json.loads(path.read_text(encoding="utf-8"))
        styles = style.get("styles", {})
        color = styles.get("color", {})
        border = styles.get("border", {})
        label = path.name

        # The container role is the whole colour contract of a card: M3 gives the
        # container, and everything else inside brings its own.
        report.check(
            color.get("background") == preset(variant["container_role"]),
            f"{label}: background {color.get('background')!r} is not the published {variant['container_role']}",
        )

        # Shape is published once, not per variant, and is the same 12dp for all
        # three.
        report.check(
            border.get("radius") == f"{measurements['shape']}px",
            f"{label}: radius {border.get('radius')!r} is not the published {measurements['shape']}dp",
        )

        outline = variant["outline"]
        if outline:
            report.check(
                border.get("color") == preset(outline["color_role"]),
                f"{label}: outline colour {border.get('color')!r} is not the published {outline['color_role']}",
            )
            report.check(
                border.get("width") == f"{outline['width']}px",
                f"{label}: outline width {border.get('width')!r} is not the published {outline['width']}dp",
            )
            report.check(
                border.get("style") == "solid",
                f"{label}: an outlined card needs a solid border style, found {border.get('style')!r}",
            )
        else:
            # A card with no published outline must not grow one, or the three
            # styles stop being distinguishable by the thing that names them.
            report.check(
                "color" not in border and "width" not in border,
                f"{label}: {name} publishes no outline, but the block style declares one",
            )

        # Resting elevation. The theme spends WordPress shadow presets, which are
        # bound to `--md-sys-elevation-shadow-level*` in theme.json, so the preset
        # slug carries the level.
        level = variant["elevation"]["enabled"]
        shadow = styles.get("shadow")
        if level:
            report.check(
                shadow == f"var:preset|shadow|elevation-{level}",
                f"{label}: shadow {shadow!r} is not the published resting level {level}",
            )
        else:
            report.check(
                shadow is None,
                f"{label}: {name} rests at elevation 0, but the block style declares a shadow {shadow!r}",
            )

        report.check(
            color.get("text") == preset("on-surface"),
            f"{label}: text {color.get('text')!r} is not on-surface",
        )

    # The focus indicator figures are claimed to agree with the theme's focus
    # ring rather than being Card's own. Check the claim, since a theme change
    # would otherwise silently make the record wrong.
    focus = data["focus_indicator"]
    if focus.get("agrees_with_theme_focus_ring"):
        state_css = STATE_CSS.read_text(encoding="utf-8")
        for declaration, published in (
            ("--md-focus-ring-width", focus["thickness"]),
            ("--md-focus-ring-outward-offset", focus["outer_offset"]),
        ):
            report.check(
                f"{declaration}: {published}px;" in state_css,
                f"card.yml claims the theme focus ring matches, but {declaration} is not {published}px",
            )

    # A non-actionable card and a directly actionable one are mutually exclusive
    # in M3's accessibility section. The data has to keep saying so, because the
    # Social default depends on it.
    actionable = data["actionable"]
    report.check(
        actionable["exclusive_with_nested_actions"] is True,
        "card.yml must keep the actionable/nested-action exclusion M3's accessibility section states",
    )
    report.check(
        actionable["non_actionable"]["tab_stop"] is False
        and actionable["non_actionable"]["ripple"] is False
        and actionable["non_actionable"]["role"] is None,
        "a non-actionable card has no role, no tab stop and no ripple",
    )
    report.check(
        actionable["directly_actionable"]["tab_stop"] is True,
        "a directly actionable card is a tab stop",
    )

    # Card publishes no size axis. The absence is load-bearing: a size axis is
    # what Button, Icon button and Button group all have, and reading one into
    # Card would invent five sets of figures M3 does not publish.
    report.check(
        data["meta"]["sizes"] is False,
        "card.yml must keep recording that Card publishes no size axis",
    )
    report.check(
        data["meta"]["required_elements"] == ["container"],
        "the container is the only required element of a card",
    )

    print(f"card: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
