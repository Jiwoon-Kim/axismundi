#!/usr/bin/env python3
"""Check `navigation_item.yml` against the token tables committed beside it.

`products/styleguide/_data/navigation_item.yml` restates M3's navigation item
specification, and `products/styleguide/AGENTS.md` requires a restatement to come
with a validator.

THE SUBJECT IS THE EXTRACTED TABLE, not a shipped stylesheet. Neither host is
implemented yet, so there is nothing in the products to compare against -- but the
figures still have a source: the `md.comp.nav-bar.*` and `md.comp.nav-rail.*`
rows that `tools/generators/fetch_m3_component_tokens.py` read out of each
component's own token table, recorded in
`products/wordpress/plugins/axismundi/docs/SOURCE-M3-NAVIGATION-RAW.md`.

That is the check worth having right now. The failure this catches has already
happened once in this project: the same document's hand-transcribed overview
claimed the navigation bar has no shadow, when the published table gives it
`md.sys.elevation.level2` and a live `shadow-color`, and the level0 row belonged
to the rail. A figure typed from a screen drifts from a figure read from the
source, and nothing noticed until the table was extracted.

No network. When the tables are refreshed, re-run the generator into that
document and this validator says which restated figures moved.
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
DATA = ROOT / "products/styleguide/_data/navigation_item.yml"
TABLES = ROOT / "products/wordpress/plugins/axismundi/docs/SOURCE-M3-NAVIGATION-RAW.md"

# An included row: two leading spaces, the token name, its value type, its value.
ROW = re.compile(r"^ {2}(md\.[a-z0-9.\-]+) {2,}([A-Z_]+) {2,}(.+?)\s*$")
# A deprecated row: the generator lists the name, then its message on the next line.
DEPRECATED = re.compile(r"^ {2}- (md\.[a-z0-9.\-]+)\s*$")

HOSTS = ("nav-bar", "nav-rail")


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
        """The sys token this comp token points at, or None when it is a literal."""
        value = self.rows.get(token)
        if not value:
            return None
        return value[1][3:].strip() if value[1].startswith("-> ") else None

    def dips(self, token: str) -> int | None:
        """The token's own figure in dp, or None when it references another token."""
        value = self.rows.get(token)
        if not value or value[1].startswith("-> "):
            return None
        try:
            carried = json.loads(value[1])
        except json.JSONDecodeError:
            return None
        return int(carried["value"]) if "DIPS" == carried.get("unit") else None

    def role(self, token: str) -> str | None:
        """The last segment of the referenced sys token -- `secondary-container`."""
        referenced = self.reference(token)
        return referenced.rsplit(".", 1)[-1] if referenced else None

    def shape_role(self, token: str) -> str | None:
        """A shape role keeps two segments -- `md.sys.shape.corner.full` is
        `corner.full`, and `full` alone is not a role name anywhere else."""
        referenced = self.reference(token)
        prefix = "md.sys.shape."
        if not referenced or not referenced.startswith(prefix):
            return referenced
        return referenced[len(prefix):]

    def matching(self, pattern: str) -> list[str]:
        return [name for name in self.rows if re.search(pattern, name)]


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    tables = Tables(TABLES.read_text(encoding="utf-8"))
    report = Report()

    report.check(
        len(tables.rows) > 100,
        f"the committed tables parsed as {len(tables.rows)} rows, which is too few to check against",
    )

    meta = data["meta"]
    item_css = (
        ROOT
        / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/navigation-item.css"
    ).read_text(encoding="utf-8")

    # --- what the item owns outright, because both hosts publish it identically ---

    for token in meta["icon_size_tokens"]:
        report.equals(tables.dips(token), meta["icon_size"], f"{token} icon size")
    for token in meta["indicator_shape_tokens"]:
        report.equals(tables.shape_role(token), meta["indicator_shape_role"], f"{token} shape role")

    colors = data["colors"]
    for host in HOSTS:
        stem = f"md.comp.{host}.item"
        report.equals(
            tables.role(f"{stem}.active.icon.color"), colors["active"]["icon_role"], f"{host} active icon"
        )
        report.equals(
            tables.role(f"{stem}.active.indicator.color"),
            colors["active"]["indicator_role"],
            f"{host} active indicator",
        )
        report.equals(
            tables.role(f"{stem}.active.label-text.color"),
            colors["active"]["label_text_role"],
            f"{host} active label",
        )
        report.equals(
            tables.role(f"{stem}.inactive.icon.color"), colors["inactive"]["icon_role"], f"{host} inactive icon"
        )
        report.equals(
            tables.role(f"{stem}.inactive.label-text.color"),
            colors["inactive"]["label_text_role"],
            f"{host} inactive label",
        )
        # The single shared state-layer role is the claim most likely to rot: it
        # holds for all six combinations today, and one of them changing upstream
        # would turn the item's own colour into a per-host value.
        for activity in ("active", "inactive"):
            for state in ("hovered", "focused", "pressed"):
                report.equals(
                    tables.role(f"{stem}.{activity}.{state}.state-layer.color"),
                    data["colors"]["state_layer_role"],
                    f"{host} {activity} {state} state layer",
                )

    for state in data["states"]:
        if "opacity_token" not in state:
            continue
        for host in HOSTS:
            report.equals(
                tables.reference(f"md.comp.{host}.item.active.{state['name']}.state-layer.opacity"),
                state["opacity_token"],
                f"{host} {state['name']} opacity",
            )

    # The data file states that the inactive opacity is not republished. If it
    # ever is, the item must stop assuming one figure covers both.
    for host in HOSTS:
        republished = tables.matching(rf"^md\.comp\.{re.escape(host)}\.item\.inactive\..*state-layer\.opacity$")
        report.check(
            bool(republished) == bool(data["inactive_state_layer_opacity_is_republished"]),
            f"{host}: inactive state-layer opacity rows {republished} contradict the recorded claim",
        )

    # --- the two boxes, pinned against the stylesheet and the component ---

    structure = data["structure"]
    item_js = (
        ROOT
        / "products/wordpress/plugins/axismundi/src/apps/frontend/components/navigations/navigation-item.js"
    ).read_text(encoding="utf-8")
    report.equals(structure["item_container"]["element"], "a", "the target is an anchor")
    report.check(
        structure["item_container"]["fills_host_item_height"] is True,
        "the item container is recorded as hugging its contents; then part of the host's row is not a target",
    )
    report.equals(
        structure["item_container"]["width_owned_by"], "host", "the container's width is the host's arithmetic"
    )
    # The DOM claim, checked where it is made rather than described. The label is
    # a sibling of the indicator when vertical and a child of it when horizontal,
    # which is why one structure cannot serve both.
    report.check(
        structure["label_inside_indicator"]["vertical"] is False
        and structure["label_inside_indicator"]["horizontal"] is True,
        "the recorded label placement no longer matches the published anatomy",
    )
    # A horizontal item renders the label through `labelGroup`, which is the
    # label alone unless a large badge has been sent to the trailing edge and
    # has to travel with it. Vertical keeps the bare label below the indicator.
    report.check(
        "'horizontal' === axis ? labelGroup : null" in item_js
        and "'vertical' === axis ? labelSlot : null" in item_js
        and "labelGroup = trailingBadge ?" in item_js,
        "navigation-item.js no longer places the label by orientation",
    )
    for box in ("item_container", "active_indicator"):
        report.check(
            structure[box]["class"] in item_css,
            f"{structure[box]['class']} is not in the stylesheet; the two boxes have to stay distinguishable",
        )

    # --- vertical is shared; horizontal is not. The contract rests on this. ---

    vertical = next(item for item in data["orientations"] if "vertical" == item["name"])
    horizontal = next(item for item in data["orientations"] if "horizontal" == item["name"])

    report.check(vertical["shared_across_hosts"] is True, "vertical is recorded as shared across hosts")
    report.check(horizontal["shared_across_hosts"] is False, "horizontal is recorded as host-specific")

    tokens = vertical["tokens"]
    for token in tokens["indicator_height"]:
        report.equals(tables.dips(token), vertical["indicator_height"], f"{token} vertical indicator height")
    for token in tokens["indicator_width"]:
        report.equals(tables.dips(token), vertical["indicator_width"], f"{token} vertical indicator width")
    for token in tokens["icon_label_space"]:
        report.equals(tables.role(token), vertical["icon_label_space_role"], f"{token} vertical icon-label space")
    for token in tokens["label_text_font"]:
        report.equals(tables.role(token), vertical["label_typescale"], f"{token} vertical label typescale")

    # Shared means the two hosts agree. Asserted rather than assumed, because the
    # whole reason this file exists is that the horizontal rows do not.
    for figure in ("indicator_height", "indicator_width", "icon_label_space", "label_text_font"):
        pair = tokens[figure]
        reader = tables.dips if figure.endswith("height") or figure.endswith("width") else tables.role
        report.check(
            reader(pair[0]) == reader(pair[1]),
            f"vertical {figure} is claimed shared but the hosts differ: {pair[0]} vs {pair[1]}",
        )

    by_host = horizontal["by_host"]
    for host, expected in by_host.items():
        stem = expected["tokens"]
        report.equals(
            tables.dips(stem["indicator_height"]), expected["indicator_height"], f"{host} horizontal indicator height"
        )
        report.equals(
            tables.role(stem["icon_label_space"]), expected["icon_label_space_role"], f"{host} horizontal icon-label space"
        )
        report.equals(
            tables.role(stem["label_text_font"]), expected["label_typescale"], f"{host} horizontal label typescale"
        )

    bar, rail = by_host["nav-bar"], by_host["nav-rail"]
    report.check(
        bar["indicator_height"] != rail["indicator_height"],
        "horizontal indicator height is recorded as host-specific but both hosts carry one figure",
    )
    report.check(
        bar["icon_label_space_role"] != rail["icon_label_space_role"],
        "horizontal icon-label space is recorded as host-specific but both hosts carry one role",
    )
    report.check(
        bar["label_typescale"] != rail["label_typescale"],
        "horizontal label typescale is recorded as host-specific but both hosts carry one scale",
    )

    # --- figures the host owns ---

    host_owned = data["owned_by_host"]
    nav_bar = host_owned["nav_bar"]
    report.equals(
        tables.role("md.comp.nav-bar.item.between-space"), nav_bar["item_between_space_role"], "bar item between-space"
    )
    # THE VERTICAL CONTAINER SPACE IS THE ITEM'S PADDING, NOT A GAP. Both hosts
    # publish the figure under different names, which is what makes it the item's
    # -- and the bar's name ("between-space") is what got it implemented as a
    # `column-gap` between items until the design kit was measured.
    for token in meta["vertical_container_space_tokens"]:
        report.equals(
            tables.role(token), meta["vertical_container_space_role"], f"{token} vertical container space"
        )
    report.check(
        len({tables.role(token) for token in meta["vertical_container_space_tokens"]}) == 1,
        "the two hosts no longer agree on the item's vertical container space, so it stops being the item's",
    )

    nav_rail = host_owned["nav_rail"]
    report.equals(
        tables.dips("md.comp.nav-rail.item.container.height"), nav_rail["item_container_height"], "rail item height"
    )
    report.equals(
        tables.dips("md.comp.nav-rail.item.short.container.height"),
        nav_rail["item_short_container_height"],
        "rail short item height",
    )
    report.equals(
        tables.shape_role("md.comp.nav-rail.item.container.shape"), nav_rail["item_container_shape_role"], "rail item shape"
    )
    report.equals(
        tables.role("md.comp.nav-rail.item.header-space-minimum"),
        nav_rail["header_space_minimum_role"],
        "rail header space minimum",
    )
    # Every leading/trailing row either host publishes carries the same role, which
    # is the test that makes it the item's rather than each host's. Asserted over
    # all of them, so a host that starts publishing its own figure fails here
    # instead of silently inheriting one.
    inline_space = meta["indicator_leading_trailing_space_role"]
    edges = tables.matching(r"^md\.comp\.nav-(bar|rail)\.item\..*(leading|trailing)-space$")
    report.check(
        len(edges) >= 8,
        f"expected both hosts to publish leading/trailing rows, found {len(edges)}",
    )
    for token in sorted(edges):
        report.equals(tables.role(token), inline_space, f"{token}")

    # The padding has to reach the stylesheet. Recording a figure and not applying
    # it is the failure the rail hit once already, and this one was worse: the
    # figure WAS applied, as a gap between items, which is a different thing in the
    # same number.
    report.check(
        "--md-comp-navigation-item-vertical-container-space: var( --md-sys-measurement-space75 );"
        in item_css,
        "navigation-item.css does not define the vertical container space",
    )
    report.check(
        "padding-block: var( --md-comp-navigation-item-vertical-container-space );" in item_css,
        "the vertical container space is not applied as padding on the item",
    )
    bar_css = (
        ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/nav-bar.css"
    ).read_text(encoding="utf-8")
    # Declarations only. The comment beside the bar's gap rule names space75 in
    # order to say where it went, and an unscoped search fired on that -- the same
    # mistake this file's `wght` check made.
    bar_declarations = re.sub(r"/\*.*?\*/", "", bar_css, flags=re.S)
    report.check(
        "space75" not in bar_declarations,
        "nav-bar.css declares space75 again; that figure is the item's padding, not a gap the bar owns",
    )

    # --- the three recorded discrepancies are the reason for several decisions,
    #     so each one is checked as a fact rather than left as a note ---

    report.equals(
        tables.dips("md.comp.nav-bar.item.active-indicator.icon-label-space"),
        4,
        "the bar's unqualified icon-label space is a 4dp literal",
    )
    report.equals(
        tables.role("md.comp.nav-rail.item.active-indicator.icon-label-space"),
        "space100",
        "the rail's unqualified icon-label space references space100",
    )
    report.check(
        tables.dips("md.comp.nav-bar.item.active-indicator.icon-label-space") is not None
        and tables.reference("md.comp.nav-rail.item.active-indicator.icon-label-space") is not None,
        "the two unqualified icon-label rows are still of different kinds, which is why neither is the item's default",
    )

    for host in HOSTS:
        weights = tables.matching(rf"^md\.comp\.{re.escape(host)}\..*weight")
        report.check(
            not weights,
            f"{host} now publishes a label weight {weights}; the active-label inference can be replaced with it",
        )
    report.equals(
        tables.reference("md.comp.navigation-bar.active.label-text.weight"),
        "md.sys.typescale.label-medium.weight.prominent",
        "the only published active-label weight is the baseline one",
    )

    # THE ACTIVE LABEL'S EMPHASIS IS OURS, NOT MATERIAL'S, so it is checked
    # against the stylesheets rather than against the table -- the table is where
    # it is absent. What could rot: the figure drifting, the decision creeping
    # back into the component layer, or a published token appearing upstream and
    # making the whole policy unnecessary.
    frontend = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
    item_css = (frontend / "styles/components/navigation-item.css").read_text(encoding="utf-8")
    typography_css = (frontend / "styles/tokens/typography.css").read_text(encoding="utf-8")
    layers_css = (frontend / "styles/layers.css").read_text(encoding="utf-8")

    report.equals(
        colors["active_label_emphasis_source"],
        "local-policy",
        "the emphasis is recorded as this project's policy and not as a published figure",
    )

    # NOT TOKENISED, DELIBERATELY. It was briefly a ref token called
    # `emphasized`, which claimed a general typography primitive where there is
    # one axis of one typeface for one state of one component. A token by that
    # name reappearing means the over-general version is back.
    report.check(
        "--md-ref-typeface-emphasized:" not in typography_css,
        "an `--md-ref-typeface-emphasized` token is back; this figure is one component's decision, not a typography primitive",
    )

    # Applied in `axismundi.theme`, after `components`, so a reader of the
    # component layer is not left guessing which rules came from the spec.
    report.check(
        "axismundi.components, axismundi.theme," in layers_css,
        "the theme layer no longer sits immediately after the component layer",
    )
    theme_block = item_css.split("@layer axismundi.theme {", 1)
    report.check(
        2 == len(theme_block),
        "navigation-item.css has no axismundi.theme layer; the emphasis decision has nowhere to live",
    )
    report.check(
        "font-variation-settings" not in theme_block[0],
        "the emphasis is declared in the component layer; it is a product decision and belongs in the theme layer",
    )
    report.check(
        f'font-variation-settings: "{colors["active_label_emphasis_axis"]}" '
        f'{colors["active_label_emphasis_value"]};' in theme_block[-1],
        "the theme layer does not apply the recorded axis and figure",
    )
    report.check(
        "[aria-current]" in theme_block[-1],
        "the theme layer applies the emphasis to something other than the active item",
    )
    # `wght` inside `font-variation-settings` would override `font-weight` and
    # silently drop the typescale's weight. Checked because the source this policy
    # came from sets both.
    # Declarations only, not prose: the comment beside the rule names `wght` in
    # order to say it is kept out, and an unscoped substring search fired on that.
    variation_declarations = re.findall(r"font-variation-settings:([^;]*);", item_css)
    report.check(
        not any("wght" in value for value in variation_declarations),
        f"a variation-settings declaration sets `wght`, which overrides the typescale's font-weight: {variation_declarations}",
    )
    report.check(
        colors["active_label_weight_applied"] is False,
        "no active label weight is applied: label-medium already resolves to 500 here",
    )
    for host in HOSTS:
        emphasis = tables.matching(rf"^md\.comp\.{re.escape(host)}\..*(grade|grad|emphasi)")
        report.check(
            not emphasis,
            f"{host} now publishes an emphasis token {emphasis}; the local policy can be replaced with it",
        )
    report.check(
        "md.sys.typescale.label-medium.weight.prominent" in tables.deprecated,
        "the sys weight that the baseline token references is still deprecated",
    )

    for host in HOSTS:
        badges = tables.matching(rf"^md\.comp\.{re.escape(host)}\..*badge")
        report.check(
            not badges,
            f"{host} now publishes badge tokens {badges}; the item can stop deferring to the badge component",
        )

    # --- semantics that were got wrong on a neighbouring component ---

    semantics = data["semantics"]
    report.equals(semantics["element"], "a", "a navigation item is a link")
    report.equals(semantics["active_attribute"], "aria-current", "the active item is current, not pressed")
    keys = {row["keys"] for row in semantics["keyboard"]}
    report.equals(keys, {"Tab", "Space or Enter"}, "the published keyboard rows")

    # Three decisions that the token tables cannot carry, pinned here so that
    # reading the published table alone does not quietly reverse them.
    report.check(
        semantics["href_required"] is True,
        "navigation is link-first: an item without `href` is not a second kind of item",
    )
    report.check(
        semantics["space_activates"] is False,
        "Space is deliberately not implemented on an anchor host; see discrepancies",
    )
    report.check(
        any("Space" in str(item.get("token", "")) for item in data["discrepancies"]),
        "the Space divergence is recorded as a discrepancy, not left as a silent omission",
    )
    report.equals(data["colors"]["active_icon_fill"], 1, "the active icon is filled")
    report.check(
        not any("arrow" in row["keys"].lower() for row in semantics["keyboard"]),
        "no arrow-key row is invented here: the navigation bar publishes Tab, unlike the button group",
    )

    print(f"navigation item: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
