#!/usr/bin/env python3
"""Check `navigation_rail.yml` against the token tables committed beside it.

Same subject and reason as the item's and the bar's validators: the restated
figures are checked against the `md.comp.nav-rail.*` rows that
`tools/generators/fetch_m3_component_tokens.py` read out of the rail's own token
table, recorded in
`products/wordpress/plugins/axismundi/docs/SOURCE-M3-NAVIGATION-RAW.md`.

THE RAIL'S PARTICULAR RISK IS NOT TRANSCRIPTION, IT IS AMBIGUITY. Three rows
disagree about the space between items, the Color prose names a role no token
carries, and two of this component's figures are inferences about which variant
a row belongs to. So the checks here are weighted towards pinning the ambiguity:
every row in a conflict is asserted, so that an upstream change which resolves
the conflict fails loudly instead of leaving a guess in place that no longer has
a reason.

No network.
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
DATA = ROOT / "products/styleguide/_data/navigation_rail.yml"
ITEM = ROOT / "products/styleguide/_data/navigation_item.yml"
TABLES = ROOT / "products/wordpress/plugins/axismundi/docs/SOURCE-M3-NAVIGATION-RAW.md"
FRONTEND = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
RAIL_CSS = FRONTEND / "styles/components/nav-rail.css"
RAIL_JS = FRONTEND / "components/navigations/nav-rail.js"
VIEWPORT = FRONTEND / "foundations/layout/breakpoints/viewport.json"

ROW = re.compile(r"^ {2}(md\.[a-z0-9.\-]+) {2,}([A-Z_]+) {2,}(.+?)\s*$")
DEPRECATED = re.compile(r"^ {2}- (md\.[a-z0-9.\-]+)\s*$")


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)

    def equals(self, actual: object, expected: object, what: str) -> None:
        self.check(actual == expected, f"{what}: expected {expected!r}, table says {actual!r}")


class Tables:
    def __init__(self, text: str) -> None:
        self.rows: dict[str, tuple[str, str]] = {}
        self.deprecated: set[str] = set()
        for line in text.splitlines():
            row = ROW.match(line)
            if row:
                self.rows[row.group(1)] = (row.group(2), row.group(3))
                continue
            gone = DEPRECATED.match(line)
            if gone:
                self.deprecated.add(gone.group(1))

    def reference(self, token: str) -> str | None:
        value = self.rows.get(token)
        if not value:
            return None
        return value[1][3:].strip() if value[1].startswith("-> ") else None

    def dips(self, token: str) -> int | None:
        value = self.rows.get(token)
        if not value or value[1].startswith("-> "):
            return None
        try:
            carried = json.loads(value[1])
        except json.JSONDecodeError:
            return None
        return int(carried["value"]) if "DIPS" == carried.get("unit") else None

    def role(self, token: str) -> str | None:
        referenced = self.reference(token)
        return referenced.rsplit(".", 1)[-1] if referenced else None

    def shape_role(self, token: str) -> str | None:
        referenced = self.reference(token)
        prefix = "md.sys.shape."
        if not referenced or not referenced.startswith(prefix):
            return referenced
        return referenced[len(prefix):]

    def sys_role(self, token: str) -> str | None:
        """`md.sys.elevation.level0` keeps `elevation.level0`."""
        referenced = self.reference(token)
        prefix = "md.sys."
        if not referenced or not referenced.startswith(prefix):
            return referenced
        return referenced[len(prefix):]

    def matching(self, pattern: str) -> list[str]:
        return [name for name in self.rows if re.search(pattern, name)]


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    item_data = yaml.safe_load(ITEM.read_text(encoding="utf-8"))
    tables = Tables(TABLES.read_text(encoding="utf-8"))
    report = Report()

    report.check(
        len(tables.rows) > 100,
        f"the committed tables parsed as {len(tables.rows)} rows, which is too few to check against",
    )

    meta = data["meta"]
    report.equals(meta["namespace"], "md.comp.nav-rail", "the implemented namespace")
    report.check(
        bool(tables.matching(r"^md\.comp\.nav-rail\.")),
        "the flexible namespace is present in the committed table",
    )
    report.check(
        bool(tables.matching(r"^md\.comp\.navigation-rail\.")),
        "the deprecated baseline namespace is present, which is why the data file names it",
    )
    # The baseline container is 80dp wide, which is also the flexible rail's
    # NARROW width -- the one figure most likely to be mistaken for the other.
    report.equals(
        tables.dips("md.comp.navigation-rail.container.width"), 80, "the baseline rail's width"
    )
    report.check(
        tables.dips("md.comp.nav-rail.collapsed.container.width")
        != tables.dips("md.comp.navigation-rail.container.width"),
        "the baseline width now equals the collapsed width, so the namespaces are no longer distinguishable by it",
    )

    # --- the implementation's names say `nav-rail`, the live namespace ---

    report.check(RAIL_CSS.exists(), f"{RAIL_CSS.name} is missing; the stylesheet is not named after its namespace")
    report.check(RAIL_JS.exists(), f"{RAIL_JS.name} is missing; the component is not named after its namespace")
    report.check(
        not (FRONTEND / "styles/components/navigation-rail.css").exists()
        and not (FRONTEND / "components/navigations/navigation-rail.js").exists(),
        "a file named after the deprecated baseline namespace is back",
    )
    rail_css = RAIL_CSS.read_text(encoding="utf-8")
    report.check(
        ".ax-nav-rail {" in rail_css and ".ax-navigation-rail" not in rail_css,
        "the component class does not use the live namespace",
    )
    slots = set(re.findall(r"--md-comp-[a-z0-9-]+?(?=-container|-item|:)", rail_css))
    report.check(
        not any(slot.startswith("--md-comp-navigation-rail") for slot in sorted(slots)),
        f"nav-rail.css names the deprecated namespace in a slot: {sorted(slots)}",
    )
    for token in sorted(tables.deprecated):
        report.check(
            token.replace(".", "-") not in rail_css and token not in rail_css,
            f"nav-rail.css references the deprecated token {token}",
        )

    # --- TRANSCRIPTION IS NOT IMPLEMENTATION ---
    #
    # This validator passed, in full, while the published 64dp item height was not
    # applied anywhere: the items rendered at their content height, 52dp collapsed
    # and 56dp expanded -- and that 56dp looked like the published
    # `item.short.container.height` while being nothing but the indicator's own
    # height. Comparing the data file with the token table cannot catch a figure
    # that was never written down in CSS, so every figure this component is
    # responsible for is asserted to appear in its stylesheet.
    #
    # A substring check is a weak test of correctness and a strong test of
    # presence, and absence is the failure that actually happened.
    collapsed_container = data["variants"]["collapsed"]["container"]
    expanded_container = data["variants"]["expanded"]["container"]
    rail_item = data["item"]
    geometry = {
        "collapsed width": f'inline-size: {collapsed_container["width"]}px;',
        "collapsed narrow width": f'inline-size: {collapsed_container["narrow_width"]}px;',
        "expanded minimum width": f'min-inline-size: {expanded_container["width_minimum"]}px;',
        "expanded maximum width": f'max-inline-size: {expanded_container["width_maximum"]}px;',
        "top space": f'--md-comp-nav-rail-top-space: {collapsed_container["top_space"]}px;',
        "item minimum height": f'--md-comp-nav-rail-item-min-height: {rail_item["container_height"]}px;',
        "expanded indicator height": f'--md-comp-navigation-item-horizontal-indicator-height: {rail_item["horizontal"]["indicator_height"]}px;',
    }
    for what, declaration in geometry.items():
        report.check(
            declaration in rail_css,
            f"nav-rail.css does not declare the {what} -- expected `{declaration}`",
        )
    # The item height is a minimum in both sources, so it must not be a fixed box.
    report.check(
        rail_item["container_height_is_minimum"] is True,
        "the item container height is recorded as fixed; both sources call it a minimum",
    )
    report.check(
        "min-block-size: var( --md-comp-nav-rail-item-min-height );" in rail_css,
        "the item height is not applied as a minimum, so a scaled label cannot grow the item",
    )
    # `short` has no published selector, so it must not have quietly acquired one.
    report.check(
        rail_item["short_container_height_implemented"] is False,
        "the short item height is recorded as implemented; nothing published says what selects it",
    )
    report.check(
        f'{rail_item["short_container_height"]}px' not in rail_css.replace(
            f'{rail_item["horizontal"]["indicator_height"]}px', ""
        ),
        "nav-rail.css carries the short item height as a length; it has no published selector",
    )

    # --- the two variants' containers ---

    for name in ("collapsed", "expanded"):
        container = data["variants"][name]["container"]
        report.equals(
            tables.role(container["color_token"]), container["color_role"], f"{name} container colour"
        )
        report.equals(
            tables.sys_role(container["elevation_token"]),
            container["elevation_role"],
            f"{name} container elevation",
        )
        report.equals(
            tables.shape_role(container["shape_token"]), container["shape_role"], f"{name} container shape"
        )
        report.equals(
            tables.dips(container["top_space_token"]), container["top_space"], f"{name} top space"
        )

    collapsed = data["variants"]["collapsed"]["container"]
    report.equals(tables.dips(collapsed["width_token"]), collapsed["width"], "collapsed width")
    report.equals(
        tables.dips(collapsed["narrow_width_token"]), collapsed["narrow_width"], "collapsed narrow width"
    )
    expanded = data["variants"]["expanded"]["container"]
    report.equals(
        tables.dips(expanded["width_minimum_token"]), expanded["width_minimum"], "expanded minimum width"
    )
    report.equals(
        tables.dips(expanded["width_maximum_token"]), expanded["width_maximum"], "expanded maximum width"
    )
    report.check(
        expanded["width_minimum"] < expanded["width_maximum"],
        "the expanded rail's width range is inverted",
    )

    modal = data["variants"]["expanded"]["modal"]
    report.equals(tables.role(modal["color_token"]), modal["color_role"], "modal container colour")
    report.equals(
        tables.sys_role(modal["elevation_token"]), modal["elevation_role"], "modal container elevation"
    )
    report.equals(
        tables.shape_role(modal["shape_token"]), modal["shape_role"], "modal container shape"
    )
    # The modal configuration is the one thing here that differs from standard in
    # all three container rows. Asserted, because it is deferred and a deferred
    # figure is the easiest kind to let rot.
    report.check(
        modal["color_role"] != expanded["color_role"]
        and modal["elevation_role"] != expanded["elevation_role"]
        and modal["shape_role"] != expanded["shape_role"],
        "the modal container no longer differs from the standard one in all three rows",
    )

    # --- the rail's own item figures ---

    item = data["item"]
    report.equals(
        tables.dips(item["container_height_token"]), item["container_height"], "item container height"
    )
    report.equals(
        tables.dips(item["short_container_height_token"]),
        item["short_container_height"],
        "short item container height",
    )
    report.equals(
        tables.shape_role(item["container_shape_token"]),
        item["container_shape_role"],
        "item container shape",
    )
    report.equals(
        tables.role(item["header_space_minimum_token"]),
        item["header_space_minimum_role"],
        "header space minimum",
    )

    horizontal = item["horizontal"]
    report.equals(
        tables.dips(horizontal["indicator_height_token"]),
        horizontal["indicator_height"],
        "horizontal indicator height",
    )
    report.equals(
        tables.role(horizontal["icon_label_space_token"]),
        horizontal["icon_label_space_role"],
        "horizontal icon-label space",
    )
    report.equals(
        tables.role(horizontal["label_text_font_token"]),
        horizontal["label_typescale"],
        "horizontal label typescale",
    )
    for edge in ("full_width_leading_space_token", "full_width_trailing_space_token"):
        report.equals(
            tables.role(horizontal[edge]), horizontal["full_width_inline_space_role"], f"{edge}"
        )

    # The two files state the same three horizontal figures from opposite sides.
    mirror = next(
        entry for entry in item_data["orientations"] if "horizontal" == entry["name"]
    )["by_host"]["nav-rail"]
    report.equals(horizontal["indicator_height"], mirror["indicator_height"], "indicator height disagrees with navigation_item.yml")
    report.equals(horizontal["icon_label_space_role"], mirror["icon_label_space_role"], "icon-label space disagrees with navigation_item.yml")
    report.equals(horizontal["label_typescale"], mirror["label_typescale"], "label typescale disagrees with navigation_item.yml")

    # --- THE AMBIGUITIES, each pinned at every row that makes it one ---

    # 1. The space between items: three rows, two of them live.
    report.equals(
        tables.role("md.comp.nav-rail.collapsed.item.vertical-space"),
        data["variants"]["collapsed"]["item_between_space_role"],
        "the collapsed rail's item spacing",
    )
    report.equals(
        tables.role(item["container_vertical_space_token"]),
        item["container_vertical_space_role"],
        "the unqualified item container vertical space, which this file does not use",
    )
    report.check(
        "md.comp.nav-rail.expanded.between-item-space" in tables.deprecated,
        "the expanded between-item-space row is no longer deprecated; the expanded gap now has a live figure",
    )
    report.check(
        data["variants"]["expanded"]["item_between_space_role"] is None,
        "the expanded rail now records a between-item figure; no live row publishes one",
    )
    report.check(
        tables.role("md.comp.nav-rail.collapsed.item.vertical-space")
        != tables.role(item["container_vertical_space_token"]),
        "the two live spacing rows now agree, so the ambiguity this file records is gone and the note should go with it",
    )
    # The bar publishes the same figure under a third spelling. Asserted so the
    # note that says so cannot drift.
    report.equals(
        tables.role("md.comp.nav-bar.item.vertical.container.between-space"),
        item["container_vertical_space_role"],
        "the bar's third spelling of the same figure",
    )

    # 2. The Color prose names a role no token carries.
    report.equals(
        tables.role("md.comp.nav-rail.item.active.label-text.color"),
        "secondary",
        "the only published active label colour",
    )
    orientation_qualified = tables.matching(
        r"^md\.comp\.nav-rail\.item\.(vertical|horizontal)\..*label-text\.color$"
    )
    report.check(
        not orientation_qualified,
        f"the rail now publishes {orientation_qualified}; the active label colour is no longer shared with the bar and navigation_item.yml must change",
    )

    # 3. No token publishes a filled container, which is why the role is prose.
    filled = tables.matching(r"^md\.comp\.nav-rail\..*fill")
    report.check(
        not filled,
        f"the rail now publishes {filled}; the container fill role can stop being read from prose",
    )
    report.equals(
        data["configurations"]["container_fill"]["default"],
        False,
        "the container fill is off by default, which is what the published default colour looks like",
    )

    # --- host semantics and published bounds ---

    semantics = data["semantics"]
    report.equals(semantics["element"], "nav", "the rail is a navigation landmark")
    report.check(semantics["label_required"] is True, "a landmark needs a name")
    report.check(
        semantics["list_wrapper"] is False, "no list wrapper is invented: the anatomy has none"
    )
    keys = {row["keys"] for row in semantics["keyboard"]}
    report.equals(keys, {"Tab or Arrows", "Space or Enter"}, "the published keyboard rows")
    # The button group's lesson, applied: arrows are added to the tab order rather
    # than replacing it, so a roving tabindex would delete a published row.
    report.check(
        semantics["roving_tabindex"] is False,
        "a roving tabindex would remove the published `Tab` row in order to implement the `Arrows` one",
    )
    report.check(
        semantics["arrow_keys_move_focus_only"] is True,
        "arrows move focus; activation is published separately as Space or Enter",
    )
    report.check(
        semantics["focus_is_not_forced"] is True,
        "initial focus is a statement about document order, not something the rail takes",
    )

    report.equals(meta["destinations_minimum"], 3, "the published minimum")
    report.equals(meta["destinations_maximum"], 7, "the published maximum")
    report.equals(meta["modal_advised_above"], 5, "the threshold at which modal is advised")
    report.check(
        meta["modal_advised_above"] < meta["destinations_maximum"],
        "the modal advice threshold is not below the hard maximum, which would make it unreachable",
    )
    report.check(meta["exactly_one_active"] is True, "one destination is always active")
    report.check(
        meta["never_simultaneous_with_navigation_bar"] is True,
        "the published prohibition on showing both at once is recorded",
    )
    declared = set(json.loads(VIEWPORT.read_text(encoding="utf-8"))["windowSizeClasses"])
    report.check(
        set(meta["window_size_classes"]) <= declared,
        f"window size classes {meta['window_size_classes']} are not all declared in viewport.json {sorted(declared)}",
    )
    report.check(
        "compact" not in meta["window_size_classes"],
        "compact is published as a prohibition for the rail",
    )
    # The bar and the rail must not claim the same set, or the swap has no
    # meaning; they do overlap at medium, which is the published ambiguity.
    bar = yaml.safe_load((ROOT / "products/styleguide/_data/navigation_bar.yml").read_text(encoding="utf-8"))
    rail_classes = set(meta["window_size_classes"])
    bar_classes = set(bar["meta"]["window_size_classes"])
    report.check(
        rail_classes != bar_classes and {"medium"} == rail_classes & bar_classes,
        f"the bar and the rail no longer overlap at medium alone: bar {sorted(bar_classes)}, rail {sorted(rail_classes)}",
    )

    report.check(
        data["item"]["target_area_spans_full_width"] is True,
        "the full-width target area is published twice, as a rule rather than a measurement",
    )
    report.check(
        data["variants"]["collapsed"]["may_be_hidden"] is False,
        "the collapsed rail is published as never hidden",
    )

    # Each admitted inference is recorded as a discrepancy. Checked by subject, so
    # deleting one fails here.
    subjects = " ".join(str(entry.get("token", "")) for entry in data["discrepancies"])
    for required in (
        "the space between items",
        "label-text.color",
        "container.color",
        "item layout and variant",
        "label wrapping",
        "full-width",
    ):
        report.check(
            required in subjects,
            f"the {required!r} divergence is no longer recorded as a discrepancy",
        )

    # The deferrals are part of the specification here, not an omission from it.
    for deferred in ("modal", "predictive back", "fab component"):
        report.check(
            any(deferred in entry for entry in data["scope"]["deferred"]),
            f"{deferred!r} is no longer recorded as deferred; either it shipped or the record lost it",
        )

    print(f"navigation rail: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
