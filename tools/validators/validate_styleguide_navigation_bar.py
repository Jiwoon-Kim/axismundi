#!/usr/bin/env python3
"""Check `navigation_bar.yml` against the token tables committed beside it.

Same subject and same reason as `validate_styleguide_navigation_item.py`: the
restated figures are checked against the `md.comp.nav-bar.*` rows that
`tools/generators/fetch_m3_component_tokens.py` read out of the bar's own token
table, recorded in
`products/wordpress/plugins/axismundi/docs/SOURCE-M3-NAVIGATION-RAW.md`.

WHAT THIS CATCHES THAT THE ITEM'S VALIDATOR CANNOT. The item file only knows
rows under `md.comp.nav-bar.item.*`; the container rows are this host's, and one
of them has already been transcribed wrongly once in this repository -- the
overview was hand-copied as "no shadow" when the table publishes
`md.sys.elevation.level2` and a live `shadow-color`. So the three container rows
that decision rests on are pinned here as facts, not notes.

It also pins the three inferences this file admits to, each of which is a place
where the published source does not answer the question:

  the vertical/horizontal between-space reading
  no figure for the margin from the window edge
  the item layout axis as a prop, with no window observation

A pin is not a claim that the inference is right. It is a guarantee that if the
source grows the missing row, this check fails and says so, instead of the
inference quietly outliving its reason.

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
DATA = ROOT / "products/styleguide/_data/navigation_bar.yml"
ITEM = ROOT / "products/styleguide/_data/navigation_item.yml"
TABLES = ROOT / "products/wordpress/plugins/axismundi/docs/SOURCE-M3-NAVIGATION-RAW.md"
FRONTEND = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
# The implementation is named after the namespace it implements, `nav-bar`, not
# after the component's human-readable name. `navigation-bar` is the deprecated
# baseline namespace, and leaving it in a filename put the one string this
# validator exists to keep out back into the tree.
BAR_CSS = FRONTEND / "styles/components/nav-bar.css"
BAR_JS = FRONTEND / "components/navigations/nav-bar.js"
SCAFFOLD = FRONTEND / "foundations/layout/scaffold/scaffold.css"
VIEWPORT = FRONTEND / "foundations/layout/breakpoints/viewport.json"

ROW = re.compile(r"^ {2}(md\.[a-z0-9.\-]+) {2,}([A-Z_]+) {2,}(.+?)\s*$")
DEPRECATED = re.compile(r"^ {2}- (md\.[a-z0-9.\-]+)\s*$")



def declarations(css: str) -> str:
    """The stylesheet with its comments removed.

    Every check that searches a stylesheet for a figure has to use this. Three
    separate checks here and in the item's validator fired on their own
    explanatory comment -- a note saying "space75 is not a gap" tripped the check
    that space75 is not declared, and a note naming `max-content` tripped the
    check that no `max-content` track is used. A comment is where the reason
    lives, so the reason must not count as the thing.
    """
    return re.sub(r"/\*.*?\*/", "", css, flags=re.S)


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
    """The committed token rows, as a lookup rather than a transcript."""

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
        """A shape role keeps two segments -- `md.sys.shape.corner.none` is
        `corner.none`, and `none` alone is not a role name anywhere else."""
        referenced = self.reference(token)
        prefix = "md.sys.shape."
        if not referenced or not referenced.startswith(prefix):
            return referenced
        return referenced[len(prefix):]

    def elevation_role(self, token: str) -> str | None:
        """`md.sys.elevation.level2` keeps `elevation.level2`: `level2` alone
        would not say which scale it belongs to."""
        referenced = self.reference(token)
        prefix = "md.sys."
        if not referenced or not referenced.startswith(prefix):
            return referenced
        return referenced[len(prefix):]

    def matching(self, pattern: str) -> list[str]:
        return [name for name in self.rows if re.search(pattern, name)]


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    item = yaml.safe_load(ITEM.read_text(encoding="utf-8"))
    tables = Tables(TABLES.read_text(encoding="utf-8"))
    report = Report()

    report.check(
        len(tables.rows) > 100,
        f"the committed tables parsed as {len(tables.rows)} rows, which is too few to check against",
    )

    meta = data["meta"]
    report.equals(meta["namespace"], "md.comp.nav-bar", "the implemented namespace")
    report.check(
        bool(tables.matching(r"^md\.comp\.nav-bar\.")),
        "the flexible namespace is present in the committed table",
    )
    # The baseline namespace exists and is not what we read. Checked rather than
    # asserted in prose, because its container height is a different figure and
    # picking the wrong one would look like a transcription error later.
    report.check(
        bool(tables.matching(r"^md\.comp\.navigation-bar\.")),
        "the deprecated baseline namespace is present, which is why the data file names it",
    )
    report.check(
        tables.dips("md.comp.navigation-bar.container.height")
        != tables.dips("md.comp.nav-bar.container.height"),
        "the baseline and flexible container heights are no longer different, so naming the namespace stops mattering",
    )

    # NOTHING DEPRECATED REACHES THE IMPLEMENTATION, INCLUDING A NAME. The
    # component's slots were first written as `--md-comp-navigation-bar-*`, which
    # is the deprecated namespace, while holding figures read from the live
    # `md.comp.nav-bar.*` one -- a 64dp value under a name whose own published
    # height is 80dp. A slot name is where the next reader goes looking, so the
    # name has to point at the rows we actually implement.
    # The implementation's own names -- files, classes and slots -- say `nav-bar`,
    # the namespace actually implemented. `navigation-bar` is the deprecated
    # baseline, and a filename is exactly where that string survives a sweep of
    # the stylesheet's contents.
    report.check(BAR_CSS.exists(), f"{BAR_CSS.name} is missing; the stylesheet is not named after its namespace")
    report.check(BAR_JS.exists(), f"{BAR_JS.name} is missing; the component is not named after its namespace")
    report.check(
        not (FRONTEND / "styles/components/navigation-bar.css").exists()
        and not (FRONTEND / "components/navigations/navigation-bar.js").exists(),
        "a file named after the deprecated baseline namespace is back",
    )

    bar_css = BAR_CSS.read_text(encoding="utf-8")
    report.check(
        ".ax-nav-bar {" in bar_css and ".ax-navigation-bar" not in bar_css,
        "the component class does not use the live namespace",
    )
    slots = set(re.findall(r"--md-comp-[a-z0-9-]+?(?=-container|-item|:)", bar_css))
    report.check(
        not any(slot.startswith("--md-comp-navigation-bar") for slot in sorted(slots)),
        f"navigation-bar.css names the deprecated namespace in a slot: {sorted(slots)}",
    )
    report.check(
        "--md-comp-nav-bar" in slots,
        f"navigation-bar.css publishes no slot under the live namespace: {sorted(slots)}",
    )
    # And no deprecated token name appears anywhere in the stylesheet, under any
    # spelling. Read from the table's own skipped list rather than from a list
    # kept here, so a newly deprecated row is covered without an edit.
    for token in sorted(tables.deprecated):
        report.check(
            token.replace(".", "-") not in bar_css and token not in bar_css,
            f"navigation-bar.css references the deprecated token {token}",
        )

    # --- the container ---

    container = data["container"]
    report.equals(
        tables.role(container["color_token"]), container["color_role"], "container colour"
    )
    report.equals(
        tables.shape_role(container["shape_token"]), container["shape_role"], "container shape"
    )
    report.equals(
        tables.elevation_role(container["elevation_token"]),
        container["elevation_role"],
        "container elevation",
    )
    report.equals(
        tables.role(container["shadow_color_token"]),
        container["shadow_color_role"],
        "container shadow colour",
    )
    report.equals(tables.dips(container["height_token"]), container["height"], "container height")

    # THE SHADOW DECISION, pinned at both ends. The prose says no shadow and the
    # baseline's row was deprecated as a bug; the flexible rows are live. If
    # either of these two facts changes, the decision has to be made again.
    report.check(
        container["elevation_token"] not in tables.deprecated,
        f"{container['elevation_token']} is now deprecated; the shadow decision rests on it being live",
    )
    report.check(
        container["shadow_color_token"] not in tables.deprecated,
        f"{container['shadow_color_token']} is now deprecated, which is the prose's position -- re-adjudicate the shadow",
    )
    report.check(
        "md.comp.navigation-bar.container.shadow-color" in tables.deprecated,
        "the baseline shadow-color is no longer deprecated, so the asymmetry the decision rests on is gone",
    )

    # --- TRANSCRIPTION IS NOT IMPLEMENTATION ---
    #
    # The same gap the rail's validator was given, for the same reason: this one
    # passed while the bar's gap was space75 instead of space0 and its horizontal
    # items were content-sized instead of dividing the row. Comparing the data
    # file with the token table cannot see either, because both were faithful
    # transcriptions of a row read the wrong way.
    geometry = {
        "container height": f'--md-comp-nav-bar-container-height: {container["height"]}px;',
        "item gap": "column-gap: var( --md-sys-measurement-space0 );",
        "equal segments": "flex: 1 1 0;",
        "full-height targets": "align-items: stretch;",
        "horizontal indicator height": f'--md-comp-navigation-item-horizontal-indicator-height: {data["item_horizontal"]["indicator_height"]}px;',
    }
    for what, declaration in geometry.items():
        report.check(
            declaration in bar_css,
            f"nav-bar.css does not declare the {what} -- expected `{declaration}`",
        )
    report.check(
        f'min-block-size: var( --md-comp-nav-bar-container-height );' in bar_css,
        "the container height is not applied as a minimum, so a scaled label cannot grow the bar",
    )
    # EXACTLY ONE equal-segment rule, and it is the vertical one. The measurements
    # page calls the horizontal item "Fixed width" against the vertical item's
    # "Dynamic width", so a second `flex` rule means the horizontal item started
    # growing again.
    # ONE grow rule, for both configurations. The design kit's panel states the
    # item is `Fill` in each, with the icon container inside it `hug`; what
    # separates them is the row's outer padding, whose figure is not published.
    report.check(
        1 == bar_css.count("flex: 1 1 0;"),
        "there is more than one grow rule; both configurations grow their containers",
    )
    grow_rule = re.search(r"([^\n]*)\n\s*flex: 1 1 0;", bar_css)
    report.check(
        bool(grow_rule) and 'data-item-layout="vertical"]' in grow_rule.group(1),
        f"the grow rule is not scoped to the vertical configuration: {grow_rule and grow_rule.group(1).strip()}",
    )
    # A track sized to its content, as opposed to the `min-inline-size: max-content`
    # guard on a single container, which is a floor rather than a track.
    # `(?<!-)` so the container's own `min-inline-size: max-content` floor does not
    # read as a track: it contains this string, which is how this check first fired
    # on the guard it was written to coexist with.
    report.check(
        not re.search(r"(?<!-)inline-size:\s*max-content", declarations(bar_css))
        and "grid-auto-columns" not in declarations(bar_css),
        "a derived track is back; sizing the row to its longest label is a third kind of growth",
    )
    # The window-edge margin has a named seam and no figure. `0` is the initial
    # value, so the slot claims nothing while it waits; a non-zero default would
    # be a figure invented for a published sentence that supplies none.
    # --- the container ---

    container = data["container"]
    report.equals(
        tables.role(container["color_token"]), container["color_role"], "container colour"
    )
    report.equals(
        tables.shape_role(container["shape_token"]), container["shape_role"], "container shape"
    )
    report.equals(
        tables.elevation_role(container["elevation_token"]),
        container["elevation_role"],
        "container elevation",
    )
    report.equals(
        tables.role(container["shadow_color_token"]),
        container["shadow_color_role"],
        "container shadow colour",
    )
    report.equals(tables.dips(container["height_token"]), container["height"], "container height")

    # THE SHADOW DECISION, pinned at both ends. The prose says no shadow and the
    # baseline's row was deprecated as a bug; the flexible rows are live. If
    # either of these two facts changes, the decision has to be made again.
    report.check(
        container["elevation_token"] not in tables.deprecated,
        f"{container['elevation_token']} is now deprecated; the shadow decision rests on it being live",
    )
    report.check(
        container["shadow_color_token"] not in tables.deprecated,
        f"{container['shadow_color_token']} is now deprecated, which is the prose's position -- re-adjudicate the shadow",
    )
    report.check(
        "md.comp.navigation-bar.container.shadow-color" in tables.deprecated,
        "the baseline shadow-color is no longer deprecated, so the asymmetry the decision rests on is gone",
    )

    # --- TRANSCRIPTION IS NOT IMPLEMENTATION ---
    #
    # The same gap the rail's validator was given, for the same reason: this one
    # passed while the bar's gap was space75 instead of space0 and its horizontal
    # items were content-sized instead of dividing the row. Comparing the data
    # file with the token table cannot see either, because both were faithful
    # transcriptions of a row read the wrong way.
    geometry = {
        "container height": f'--md-comp-nav-bar-container-height: {container["height"]}px;',
        "item gap": "column-gap: var( --md-sys-measurement-space0 );",
        "equal segments": "flex: 1 1 0;",
        "full-height targets": "align-items: stretch;",
        "horizontal indicator height": f'--md-comp-navigation-item-horizontal-indicator-height: {data["item_horizontal"]["indicator_height"]}px;',
    }
    for what, declaration in geometry.items():
        report.check(
            declaration in bar_css,
            f"nav-bar.css does not declare the {what} -- expected `{declaration}`",
        )
    report.check(
        f'min-block-size: var( --md-comp-nav-bar-container-height );' in bar_css,
        "the container height is not applied as a minimum, so a scaled label cannot grow the bar",
    )
    # EXACTLY ONE equal-segment rule, and it is the vertical one. The measurements
    # page calls the horizontal item "Fixed width" against the vertical item's
    # "Dynamic width", so a second `flex` rule means the horizontal item started
    # growing again.
    # ONE grow rule, for both configurations. The design kit's panel states the
    # item is `Fill` in each, with the icon container inside it `hug`; what
    # separates them is the row's outer padding, whose figure is not published.
    report.check(
        1 == bar_css.count("flex: 1 1 0;"),
        "there is more than one grow rule; both configurations grow their containers",
    )
    grow_rule = re.search(r"([^\n]*)\n\s*flex: 1 1 0;", bar_css)
    report.check(
        bool(grow_rule) and 'data-item-layout="vertical"]' in grow_rule.group(1),
        f"the grow rule is not scoped to the vertical configuration: {grow_rule and grow_rule.group(1).strip()}",
    )
    # A track sized to its content, as opposed to the `min-inline-size: max-content`
    # guard on a single container, which is a floor rather than a track.

    # The window-edge margin has a named seam and no figure. `0` is the initial
    # value, so the slot claims nothing while it waits; a non-zero default would
    # be a figure invented for a published sentence that supplies none.
    # No wrapper. The kit's row is a padded frame of `Fill` items, so the margin is
    # the row's own padding; a group element would only be needed for the reading
    # the kit rules out.
    report.check(
        "__segments" not in bar_css,
        "a group element is back; the kit's horizontal row is a padded frame whose items are Fill",
    )

    # --- the arithmetic, and the inference inside it ---

    layout = data["layout"]

    # A CAPPED FLUID TARGET, named as one. The cap is this project's figure; the
    # `min-inline-size` is the guard that keeps a container around its own
    # indicator, and removing it would make the width fixed at the cost of either
    # an overflowing indicator or the label shrinking M3 prohibits.
    horizontal = layout["horizontal"]
    report.check(
        f'--ax-nav-bar-item-horizontal-max-width: {horizontal["target_max_width"]}px;' in bar_css,
        f'nav-bar.css does not carry the recorded cap of {horizontal["target_max_width"]}px',
    )
    report.check(
        "max-inline-size: var( --ax-nav-bar-item-horizontal-max-width );" in bar_css,
        "the cap is not applied through its slot",
    )
    report.check(
        "min-inline-size: max-content;" in bar_css,
        "the container can now be narrower than its own indicator; a fixed target needs the label to shrink, which M3 prohibits",
    )
    theme_block = bar_css.split("@layer axismundi.theme {", 1)
    report.check(2 == len(theme_block), "nav-bar.css has no theme layer; the cap is this project's figure, not a token")
    report.check(
        "--ax-nav-bar-item-horizontal-max-width" in theme_block[-1]
        and "--ax-nav-bar-item-horizontal-max-width:" not in theme_block[0],
        "the cap is declared in the component layer; a figure nobody publishes belongs in the theme layer",
    )
    report.check(
        "--md-comp-nav-bar-item-horizontal-max-width" not in bar_css,
        "the cap carries an `--md-` name again, which reads as a Material token",
    )
    report.equals(
        horizontal["target_width_model"],
        "content-based-with-max",
        "the width model is recorded as something the implementation does not do",
    )
    report.check(
        horizontal["matches_published_fixed_width"] is False,
        "the capped fluid target is recorded as satisfying the published fixed width; it does not",
    )
    # The window-edge margin is left over by the targets, never declared on the
    # bar. A target's OWN `padding-inline` is a different thing -- the space
    # between its edge and its indicator -- so the rules are told apart by
    # selector rather than by the property name.
    bar_rules = re.findall(r"([^{}]+)\{([^}]*)\}", declarations(bar_css))
    container_padding = [
        selector.strip()
        for selector, body in bar_rules
        if "__item" not in selector and "padding-inline" in body
    ]
    report.check(
        not container_padding,
        f"the bar declares a window-edge padding {container_padding}; it is left over by the targets, so a narrow window loses it instead of squeezing them",
    )
    # ONE GAP FOR BOTH ORIENTATIONS. The unqualified row is the gap; the
    # orientation-qualified space75 row is an inset inside the item and lives in
    # `navigation_item.yml`. Both are asserted, because reading the second as a
    # gap is the mistake that shipped once.
    report.equals(
        tables.role(layout["between_space_token"]),
        layout["between_space_role"],
        "the gap between item boxes",
    )
    report.equals(
        tables.role("md.comp.nav-bar.item.vertical.container.between-space"),
        item["meta"]["vertical_container_space_role"],
        "the vertical container inset, which is not a gap",
    )
    report.check(
        "space0" == layout["between_space_role"],
        "the gap is no longer the published space0",
    )
    # ITEM CONTAINER, NOT INDICATOR. The keys are named for the outer box on
    # purpose: calling both "item" is what produced three different readings of
    # the same sentence.
    report.equals(
        layout["vertical"]["item_container_width"], "equal_segments", "the vertical item container divides the row"
    )
    # Both orientations divide the row; the Guidelines caption says so of a
    # horizontal row explicitly. What is open is the figure, below.
    report.equals(
        layout["horizontal"]["item_container_width"],
        "equal_segments",
        "the horizontal containers no longer divide the row",
    )
    report.check(
        layout["horizontal"]["outer_padding_published"] is False,
        "the window-edge padding is recorded as published; no token carries it and the kit varies it by count",
    )
    kit = layout["horizontal"]["outer_padding_kit_by_count"]
    report.check(
        len(set(kit.values())) == len(kit),
        "the kit's outer padding no longer varies with the destination count, so it could be lifted as a constant",
    )
    report.check(
        layout["horizontal"]["fixed_width_published"] is False,
        "the horizontal container's fixed width is recorded as published; no live token carries it",
    )
    report.equals(
        layout["horizontal"]["container_grows"], True, "the horizontal container grows; the kit states it is Fill"
    )
    report.equals(
        layout["vertical"]["container_grows"], True, "the vertical container is dynamic"
    )
    report.equals(layout["horizontal"]["slack_goes_to"], "ends", "the slack goes to the ends")
    # If a width row ever appears, the open question closes and this says so.
    width_rows = tables.matching(r"^md\.comp\.nav-bar\.item\.(horizontal\.)?(container\.)?width$")
    report.check(
        not width_rows,
        f"the bar now publishes {width_rows}; the horizontal item container's width no longer has to be left open",
    )
    # The rhythms are what make the 64dp box checkable: both have to add up to it.
    report.equals(sum(layout["vertical_rhythm"]), container["height"], "the vertical rhythm sums to the container height")
    report.equals(sum(layout["horizontal_rhythm"]), container["height"], "the horizontal rhythm sums to the container height")
    report.check(
        layout["vertical_rhythm"][0] == layout["vertical_rhythm"][-1],
        "the vertical item's padding is no longer symmetric",
    )
    report.check(
        layout["item_container_height_is_bar_height"] is True,
        "the item container is recorded as shorter than the bar, which loses part of the target",
    )
    # A horizontal counterpart to the qualified row would mean the orientations
    # really do differ here, which is how we would find out.
    horizontal_rows = tables.matching(r"^md\.comp\.nav-bar\.item\.horizontal\..*between-space$")
    report.check(
        not horizontal_rows,
        f"the bar now publishes {horizontal_rows}; the single-gap reading has to be revisited",
    )
    # No token for the window-edge margin. Searched by concept rather than by
    # one name, so a row under any plausible spelling trips it.
    edge_rows = tables.matching(r"^md\.comp\.nav(igation)?-bar\..*(margin|window|edge|padding)")
    report.check(
        not edge_rows,
        f"the bar now publishes {edge_rows}; the window-edge margin no longer has to be left unapplied",
    )

    # --- the horizontal figures this host hands to the item ---

    horizontal = data["item_horizontal"]
    tokens = horizontal["tokens"]
    report.equals(
        tables.dips(tokens["indicator_height"]),
        horizontal["indicator_height"],
        "horizontal indicator height",
    )
    report.equals(
        tables.role(tokens["icon_label_space"]),
        horizontal["icon_label_space_role"],
        "horizontal icon-label space",
    )
    report.equals(
        tables.role(tokens["label_text_font"]),
        horizontal["label_typescale"],
        "horizontal label typescale",
    )

    # The two data files state the same three figures from opposite sides. They
    # have to agree, or one of the two is lying about the handover.
    mirror = next(
        entry for entry in item["orientations"] if "horizontal" == entry["name"]
    )["by_host"]["nav-bar"]
    for figure in ("indicator_height", "icon_label_space_role", "label_typescale"):
        report.equals(
            horizontal[figure],
            mirror[figure],
            f"{figure} disagrees with navigation_item.yml",
        )

    # The item refuses to default these. If it ever stops refusing, this host
    # should stop supplying them.
    report.check(
        next(entry for entry in item["orientations"] if "horizontal" == entry["name"])[
            "shared_across_hosts"
        ]
        is False,
        "navigation_item.yml now calls horizontal shared; this host no longer needs to supply it",
    )

    # --- the configuration axis, and why nothing switches on the window ---

    layout_axis = data["configurations"]["item_layout"]
    report.equals(layout_axis["default"], "vertical", "the published default item layout")
    report.equals(set(layout_axis["values"]), {"vertical", "horizontal"}, "the item layout values")
    report.equals(
        layout_axis["window_rule"], {"compact": "vertical", "medium": "horizontal"}, "the window rule"
    )
    report.equals(layout_axis["implemented_as"], "prop", "the axis is a prop")
    # Both halves of the reason are measured, so both are checked. If a window
    # observation appears, or the Scaffold stops hiding the bar at medium, the
    # recorded reason is no longer true and this says so.
    # HALF OF THE RECORDED REASON HAS EXPIRED, and this check is what noticed.
    # The note gave two reasons for not following the published window rule: the
    # app had no JS window-size signal, and the Scaffold made the medium window
    # unreachable for the bar anyway. `useWindowSizeClass` now exists, so the
    # first is gone and only the second still holds. The check therefore moved to
    # the half that is still true -- the Scaffold swap -- and the signal's
    # existence is asserted rather than forbidden, so that losing it would also
    # be noticed.
    signal = FRONTEND / "foundations/layout/breakpoints/use-window-size-class.js"
    report.check(
        signal.exists(),
        "the window size class signal is gone; the bar's recorded reason has to be rewritten again",
    )
    scaffold = SCAFFOLD.read_text(encoding="utf-8")
    report.check(
        re.search(r"min-width: 600px", scaffold) and re.search(r"__bar \{\s*display: none", scaffold),
        "scaffold.css no longer hides the bar from 600px up, so the bar can reach a medium window and the window rule becomes reachable",
    )
    report.check(
        layout_axis["switches_on_window_size"] is False,
        "the bar does not observe the window; see discrepancies",
    )

    # --- host semantics and the published bounds ---

    semantics = data["semantics"]
    report.equals(semantics["element"], "nav", "the bar is a navigation landmark")
    report.check(semantics["label_required"] is True, "a landmark needs a name")
    report.check(
        semantics["list_wrapper"] is False,
        "no list wrapper is invented: M3's anatomy is container plus items",
    )
    report.equals(meta["destinations_minimum"], 3, "the published minimum")
    report.equals(meta["destinations_maximum"], 5, "the published maximum")
    report.check(meta["exactly_one_active"] is True, "one destination is always active")
    report.equals(
        set(meta["window_size_classes"]),
        {"compact", "medium"},
        "the bar is published for compact and medium only",
    )
    # The classes it names have to be the ones the app declares, or the data file
    # is quoting a vocabulary this product does not use.
    declared = set(json.loads(VIEWPORT.read_text(encoding="utf-8"))["windowSizeClasses"])
    report.check(
        set(meta["window_size_classes"]) <= declared,
        f"window size classes {meta['window_size_classes']} are not all declared in viewport.json {sorted(declared)}",
    )

    report.check(
        container["height_is_minimum"] is True,
        "the container height is a minimum: the published text asks the bar to grow for scaled text",
    )
    report.check(
        container["full_window_width"] is True,
        "the container spans the full window width",
    )
    report.equals(container["placement_owned_by"], "scaffold", "placement belongs to the Scaffold")

    # Each admitted inference is recorded as a discrepancy rather than left in a
    # comment. Checked by subject, so deleting one fails here.
    subjects = " ".join(str(entry.get("token", "")) for entry in data["discrepancies"])
    for required in ("elevation", "between-space", "margin from the window edge", "item layout and window size"):
        report.check(
            required in subjects,
            f"the {required!r} divergence is no longer recorded as a discrepancy",
        )

    print(f"navigation bar: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
