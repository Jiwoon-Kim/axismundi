#!/usr/bin/env python3
"""Check the theme's shipped separator styling against the published M3 divider data.

`products/styleguide/_data/divider.yml` restates M3's divider specification, and
`products/styleguide/AGENTS.md` requires a restatement to come with a validator.
The subject is what the theme already ships: one baseline rule in `style.css`
driving both a raw `<hr>` and `core/separator`, plus the `inset` and
`middle-inset` block style variations.

That pairing is the useful check. The theme reached three variants independently
of this data file, so the two agreeing is evidence rather than tautology -- and if
either side drifts, the published figures are where the disagreement shows.
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


ROOT = Path(__file__).resolve().parent.parent.parent
STYLEGUIDE = ROOT / "products/styleguide"
THEME = ROOT / "products/wordpress/themes/axismundi"
DATA = STYLEGUIDE / "_data/divider.yml"
THEME_STYLE = THEME / "style.css"
THEME_JSON = THEME / "theme.json"
VARIATIONS = {
    "inset": THEME / "styles/blocks/separator-inset.json",
    "middle-inset": THEME / "styles/blocks/separator-middle-inset.json",
}


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


def spacing_presets() -> dict[str, str]:
    theme = json.loads(THEME_JSON.read_text(encoding="utf-8"))
    sizes = theme.get("settings", {}).get("spacing", {}).get("spacingSizes", [])
    return {str(size.get("slug")): str(size.get("size")) for size in sizes}


def preset_for(presets: dict[str, str], dp: int) -> str | None:
    """The spacing preset slug whose value is this many pixels, if one exists."""
    for slug, size in presets.items():
        if f"{dp}px" == size:
            return slug
    return None


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    report = Report()

    meta = data["meta"]
    variants = {variant["name"]: variant for variant in data["variants"]}
    style = THEME_STYLE.read_text(encoding="utf-8")
    presets = spacing_presets()

    # The baseline rule: a 1dp line in outline-variant, driving `<hr>` and the
    # separator block from one place.
    baseline = re.search(
        r"^hr,\s*\n\.wp-block-separator:not\(\s*\.is-style-dots\s*\)\s*\{(.+?)\}",
        style,
        re.DOTALL | re.MULTILINE,
    )
    report.check(baseline is not None, "style.css no longer has one baseline rule for `hr` and `core/separator`")
    body = baseline.group(1) if baseline else ""

    report.check(
        f"block-size: {meta['thickness']}px;" in body,
        f"the theme's divider line is not the published {meta['thickness']}dp thick",
    )
    report.check(
        f"background-color: var(--wp--preset--color--{meta['color_role']});" in body,
        f"the theme's divider line is not the published {meta['color_role']}",
    )
    # The line has to be paintable at less than full width for an inset to show.
    # The theme does that with a content-box-clipped background rather than a
    # border, and its own comment records why: the block editor's centring
    # swallows a margin- or width-based inset.
    report.check(
        "background-clip: content-box;" in body,
        "the theme's divider line is no longer a content-box-clipped background, so padding can no longer inset it",
    )
    report.check(
        "border: 0;" in body,
        "the theme's divider still carries a border, which core sets to 2px and which padding cannot inset",
    )

    # The variations, against the published inset figures.
    for name, path in VARIATIONS.items():
        variant = variants.get(name)
        if variant is None:
            report.problems.append(f"{name}: no entry in divider.yml")
            continue

        variation = json.loads(path.read_text(encoding="utf-8"))
        css = variation.get("styles", {}).get("css", "")
        label = path.name

        report.check(
            ["core/separator"] == variation.get("blockTypes"),
            f"{label}: a divider variation belongs to core/separator, found {variation.get('blockTypes')}",
        )

        start_preset = preset_for(presets, variant["inset_start"])
        report.check(
            start_preset is not None,
            f"{name}: no theme spacing preset equals the published {variant['inset_start']}dp inset",
        )
        if start_preset is None:
            continue

        expected = (
            f"padding-inline:var(--wp--preset--spacing--{start_preset});"
            if variant["inset_end"] == variant["inset_start"]
            else f"padding-inline-start:var(--wp--preset--spacing--{start_preset});"
        )
        report.check(
            expected in css.replace(" ", ""),
            f"{label}: css {css!r} does not spend the published inset ({variant['inset_start']}dp start, {variant['inset_end']}dp end)",
        )

        # Logical properties, so the inset follows the writing direction. A
        # left/right pair here would be a published figure applied to the wrong
        # side in RTL.
        report.check(
            "left" not in css and "right" not in css,
            f"{label}: an inset must use logical properties, not left/right",
        )

    # Three variants, because the measurements table publishes three even though
    # the prose says two. The discrepancy entry has to keep saying so.
    report.check(
        3 == len(variants) and {"full", "inset", "middle-inset"} == set(variants),
        f"divider.yml must publish full, inset and middle-inset; found {sorted(variants)}",
    )
    report.check(
        any("two ways" in str(entry.get("prose", "")) for entry in data.get("discrepancies", [])),
        "divider.yml must keep recording that M3's prose says two ways while the table publishes three",
    )

    # The spacing around a divider is published in the divider's own measurements
    # table and is still not the divider's. If these ever migrate into `variants`
    # or `meta`, a Divider starts injecting margins its callers cannot remove.
    report.check(
        {"supporting_text_gap", "right_margin", "bottom_margin"} == set(data["spacing_owned_by_consumer"]),
        "the three surrounding-space figures stay recorded as the consumer's",
    )
    report.check(
        meta["states"] is False,
        "a divider is not interactive and publishes no states",
    )

    print(f"divider: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
