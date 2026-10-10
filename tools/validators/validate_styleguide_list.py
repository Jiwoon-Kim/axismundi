#!/usr/bin/env python3
"""Check the List decision record, token evidence, and shipped source."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path
from typing import Any

import yaml

ROOT = Path(__file__).resolve().parent.parent.parent
DATA = ROOT / "products/styleguide/_data/list.yml"
TABLE = ROOT / "products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-LISTS-RAW.md"
THEME_JSON = ROOT / "products/wordpress/themes/axismundi/theme.json"
THEME_SHAPES = ROOT / "products/wordpress/themes/axismundi/assets/styles/tokens/tokens.sys.shape.css"
SEGMENTED = ROOT / "products/wordpress/themes/axismundi/styles/blocks/list-segmented.json"
LIST = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/lists/list.js"
LIST_ITEM = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/lists/list-item.js"
LIST_CSS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/list.css"
STYLE_INDEX = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/index.css"
STYLEBOOK_INDEX = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/index.js"
STYLEBOOK_PAGE = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/components/lists/index.js"
APP = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/app.js"
ROUTE = ROOT / "products/wordpress/plugins/axismundi/includes/route.php"
PLUGIN = ROOT / "products/wordpress/plugins/axismundi/axismundi.php"

ROW = re.compile(r"^  (md\.[a-z0-9.\-]+)\s+[A-Z_]+\s+(.+?)\s*$")


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


def rows(text: str) -> dict[str, str]:
    return {match.group(1): match.group(2) for line in text.splitlines() if (match := ROW.match(line))}


def css_declarations(text: str) -> str:
    return re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)


def css_var(token: str) -> str:
    return "--" + token.replace(".", "-")


def dip(value: int) -> str:
    return json.dumps({"value": value, "unit": "DIPS"}, separators=(",", ":"))


def spacing_sizes(theme: dict[str, Any]) -> dict[str, str]:
    return {item["slug"]: item["size"] for item in theme["settings"]["spacing"]["spacingSizes"]}


def shape_size(text: str, role: str) -> int | None:
    match = re.search(rf"--md-sys-shape-corner-value-{re.escape(role)}:\s*(-?\d+)px;", text)
    return int(match.group(1)) if match else None


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    table = TABLE.read_text(encoding="utf-8")
    theme = json.loads(THEME_JSON.read_text(encoding="utf-8"))
    theme_shapes = THEME_SHAPES.read_text(encoding="utf-8")
    segmented = json.loads(SEGMENTED.read_text(encoding="utf-8"))["styles"]["css"]
    list_source = LIST.read_text(encoding="utf-8")
    item_source = LIST_ITEM.read_text(encoding="utf-8")
    list_css = css_declarations(LIST_CSS.read_text(encoding="utf-8"))
    style_index = css_declarations(STYLE_INDEX.read_text(encoding="utf-8"))
    stylebook_index = STYLEBOOK_INDEX.read_text(encoding="utf-8")
    stylebook_page = STYLEBOOK_PAGE.read_text(encoding="utf-8")
    app = APP.read_text(encoding="utf-8")
    route = ROUTE.read_text(encoding="utf-8")
    plugin = PLUGIN.read_text(encoding="utf-8")
    report = Report()
    token_rows = rows(table)
    sizes = spacing_sizes(theme)
    measurements = data["measurements"]
    slots = data["slots"]
    focus = data["focus_indicator"]
    cross = data["theme_cross_surface"]

    def row_is(token: str, value: str) -> None:
        report.check(token_rows.get(token) == value, f"{token} no longer resolves to {value}")

    def role_is(token: str, role: str) -> None:
        row_is(token, "-> md.sys.shape." + role)

    def space_value(space_token: str, value: int) -> None:
        slug = space_token.removeprefix("md.sys.measurement.space")
        report.check(sizes.get(slug) == f"{value}px", f"theme {space_token} is not {value}px")

    meta = data["meta"]
    report.check(meta["namespace"] == "md.comp.list", "list namespace drifted")
    report.check(meta["table_revision"] in table, "recorded List table revision is absent")
    report.check("included: 305" in table, "List table included count is not 305")
    report.check(meta["included_token_count"] == 305, "list.yml included token count is not 305")
    report.check(data["prose_sources"]["variants"]["source"].endswith("/lists/specs"), "variant prose source drifted")
    report.check(data["prose_sources"]["selection"]["source"].endswith("/lists/specs"), "selection prose source drifted")

    # list.yml owns numerical policy; every value must match raw evidence and CSS.
    for yml_name, css_name in (("one_line", "one-line"), ("two_line", "two-line"), ("three_line", "three-line")):
        measurement = measurements[yml_name]
        row_is(measurement["token"], dip(measurement["minimum_height"]))
        report.check(f"--md-comp-list-list-item-{css_name}-container-height: {measurement['minimum_height']}px;" in list_css, f"React {yml_name} height no longer consumes list.yml")

    spacing_css = {
        "padding_block": (("md.comp.list.list-item.top-space", "--md-comp-list-list-item-top-space"), ("md.comp.list.list-item.bottom-space", "--md-comp-list-list-item-bottom-space")),
        "padding_inline": (("md.comp.list.list-item.leading-space", "--md-comp-list-list-item-leading-space"), ("md.comp.list.list-item.trailing-space", "--md-comp-list-list-item-trailing-space")),
        "slot_gap": (("md.comp.list.list-item.between-space", "--md-comp-list-list-item-between-space"),),
        "segmented_gap": (("md.comp.list.segmented.gap", "--md-comp-list-segmented-gap"),),
    }
    for name, bindings in spacing_css.items():
        measurement = measurements[name]
        space_token = measurement["space_token"]
        for token, css_property in bindings:
            row_is(token, "-> " + space_token)
            report.check(f"{css_property}: var( {css_var(space_token)} );" in list_css, f"React {name} no longer consumes {space_token}")
        space_value(space_token, measurement["value"])
        expected_cross = cross["theme_spacing_presets"]["space" + space_token.removeprefix("md.sys.measurement.space")]
        report.check(expected_cross == measurement["value"], f"cross-surface {name} drifted from list.yml")

    shape = data["segmented_group_shape_policy"]
    role_is(shape["source_token"], shape["source_role"])
    role_is(shape["unselected_inner_shape_token"], "corner.extra-small")
    role_is(shape["selected_row_shape_token"], "corner.large")
    report.check(shape_size(theme_shapes, shape["source_role"].removeprefix("corner.")) == shape["outer_shape"], "List container shape drifted from list.yml")
    report.check(shape_size(theme_shapes, "extra-small") == shape["unselected_inner_shape"], "expressive inner shape drifted from list.yml")
    report.check(shape_size(theme_shapes, "large") == shape["selected_row_shape"], "selected row shape drifted from list.yml")
    report.check(shape["dom_mapping"] == "first-middle-last", "segmented DOM mapping drifted")
    report.check(cross["segmented_gap"] == measurements["segmented_gap"]["value"], "cross-surface segmented gap drifted")
    report.check(cross["outer_shape"] == shape["outer_shape"], "cross-surface outer shape drifted")
    report.check(cross["unselected_inner_shape"] == shape["unselected_inner_shape"], "cross-surface inner shape drifted")
    report.check(cross["selected_shape"] == shape["selected_row_shape"], "cross-surface selected shape drifted")

    report.check(f"gap:var(--wp--preset--spacing--{measurements['segmented_gap']['space_token'].removeprefix('md.sys.measurement.space')});" in segmented, "theme segmented List no longer spends list.yml gap")
    report.check(f"border-radius:{shape['outer_shape']}px;" in segmented, "theme segmented List no longer declares list.yml outer shape")
    report.check(f"& > li{{box-sizing:border-box;min-block-size:{measurements['one_line']['minimum_height']}px;" in segmented and f"border-radius:{shape['unselected_inner_shape']}px;" in segmented, "theme segmented row no longer starts at list.yml one-line / inner shape")
    report.check(f"& > li:first-child{{border-start-start-radius:{shape['outer_shape']}px;border-start-end-radius:{shape['outer_shape']}px;}}" in segmented, "theme first row lost List-container corners")
    report.check(f"& > li:last-child{{border-end-start-radius:{shape['outer_shape']}px;border-end-end-radius:{shape['outer_shape']}px;}}" in segmented, "theme last row lost List-container corners")
    report.check(f"> li:has(input[type=\"checkbox\"]:checked){{background-color:var(--wp--preset--color--secondary-container);color:var(--wp--preset--color--on-secondary-container);border-radius:{shape['selected_row_shape']}px;}}" in segmented, "theme selected row no longer uses list.yml shape")

    for name, slot in (("avatar", slots["leading"]["avatar"]), ("image", slots["leading"]["image"]), ("video", slots["leading"]["video"])):
        if name == "avatar":
            row_is(slot["size_token"], dip(slot["size"]))
            role_is(slot["shape_token"], slot["shape_role"])
            report.check(f"--md-comp-list-list-item-leading-avatar-size: {slot['size']}px;" in list_css, "React avatar size no longer consumes list.yml")
        else:
            row_is(slot["width_token"], dip(slot["width"]))
            row_is(slot["height_token"], dip(slot["height"]))
            shape_token = slot.get("expressive_shape_token", slot.get("shape_token"))
            shape_role = slot.get("expressive_shape_role", slot.get("shape_role"))
            role_is(shape_token, shape_role)
            report.check(f"--md-comp-list-list-item-leading-{name}-width: {slot['width']}px;" in list_css, f"React {name} width no longer consumes list.yml")
            report.check(f"--md-comp-list-list-item-leading-{name}-height: {slot['height']}px;" in list_css, f"React {name} height no longer consumes list.yml")

    for state_name in ("baseline", "expressive"):
        for slot in (slots["leading"]["icon"], slots["trailing"]["icon"]):
            row_is(slot[f"{state_name}_token"], dip(slot[f"{state_name}_size"]))
    report.check(f"--md-comp-list-list-item-leading-icon-expressive-size: {slots['leading']['icon']['expressive_size']}px;" in list_css, "React leading icon size no longer consumes list.yml")
    report.check(f"--md-comp-list-list-item-trailing-icon-expressive-size: {slots['trailing']['icon']['expressive_size']}px;" in list_css, "React trailing icon size no longer consumes list.yml")
    report.check("const LEADING_TYPES = [ 'icon', 'avatar', 'image', 'video' ];" in item_source and "'media'" not in item_source, "leading types no longer match published slots")
    report.check("data-leading-type='video'" in list_css and "leading-type='media'" not in list_css, "leading video geometry drifted or legacy media survived")

    row_is(focus["thickness_token"], "-> md.sys.state.focus-indicator.thickness")
    row_is(focus["inner_offset_token"], "-> md.sys.state.focus-indicator.inner-offset")
    row_is("md.sys.state.focus-indicator.thickness", dip(focus["thickness"]))
    row_is("md.sys.state.focus-indicator.inner-offset", dip(focus["inner_offset"]))
    report.check(f"--md-sys-state-focus-indicator-thickness, {focus['thickness']}px" in list_css, "focus thickness no longer consumes list.yml")
    report.check(f"--md-sys-state-focus-indicator-inner-offset, {focus['inner_offset']}px" in list_css, "focus offset no longer consumes list.yml")

    inferred = data["line_count_inference"]["inferred"]
    report.check(data["line_count_inference"]["status"] == "project-policy", "line inference lost project-policy status")
    report.check(inferred == {"headline": 1, "headline_supporting": 2, "headline_overline": 2, "headline_overline_supporting": 3}, "line inference values drifted")
    report.check("return overline && supportingText ? 3 : overline || supportingText ? 2 : 1;" in item_source, "ListItem no longer implements recorded line inference")
    report.check(measurements["minimum_height_conditions"]["tallest_slot_expands_row"] is True, "minimum-height condition lost tallest-slot behavior")

    interaction = data["interaction_contract"]
    report.check(interaction["list_host"] == "ul" and interaction["item_host"] == "li", "native list host contract drifted")
    report.check(interaction["selected"] == "deferred-to-list-controller", "selected was opened without a List controller")
    report.check(data["selection_state"]["status"] == "published-deferred-to-list-controller", "published selected visuals are being claimed as implemented")
    report.check("single-select" in interaction["selection_modes_deferred"] and "multi-select" in interaction["selection_modes_deferred"], "selection modes must remain deferred")
    report.check("multi-action" in interaction["interactions_deferred"], "multi-action must remain deferred")
    report.check("swipe" not in interaction["interactions_deferred"], "swipe is declined, not deferred")
    report.check("swipe" in (interaction.get("interactions_declined") or {}), "the declined swipe interaction lost its record")
    disabled = interaction["disabled_input_safety"]
    report.check(disabled["button"] == "native-disabled" and disabled["link"] == "omit-href-and-click-handler-with-aria-disabled", "disabled safety contract drifted")
    report.check(disabled["status"] == "implemented-behavior-and-published-content-opacity", "disabled visual-state status drifted")
    for token in disabled["tokens"].values():
        report.check(token_rows.get(token) == str(disabled["content_opacity"]), f"{token} no longer has disabled content opacity")
        css_name = "--" + token.replace("md.comp.list.", "md-comp-list-").replace(".", "-")
        report.check(f"{css_name}: {disabled['content_opacity']};" in list_css, f"React disabled token {token} is not consumed")

    report.check("<ul" in list_source and "data-variant={ visualVariant }" in list_source, "List no longer renders native ul with variant")
    report.check("<li" in item_source and "const Host = hasLink ? 'a' : hasButton ? 'button' : 'div';" in item_source, "ListItem no longer routes semantics through li and native hosts")
    report.check("aria-selected" not in item_source and "listbox" not in list_source, "selection semantics appeared before List controller")
    report.check("data-lines={ rowLines }" in item_source, "ListItem no longer records 1/2/3 line minimum")
    report.check("'aria-disabled': true" in item_source and "disabled: isDisabled" in item_source and "data-disabled={ isDisabled || undefined }" in item_source, "disabled input no longer reaches safe native-host outcome")
    report.check("@import url( './components/list.css' );" in style_index, "List stylesheet is not in frontend bundle")
    report.check("StylebookListsPage" in stylebook_index and "'lists' === component" in stylebook_index, "stylebook does not expose Lists")
    report.check("carousels|lists|dividers" in app, "frontend route omits Lists")
    report.check(route.count("carousels|lists|dividers") == 2 and re.search(r"AXISMUNDI_CAPSTONE_REWRITE_VERSION\s*=\s*'17';", plugin) is not None, "public route/rewrite version omits Lists")
    report.check("<List" in stylebook_page and "<ListItem" in stylebook_page and 'leadingType="video"' in stylebook_page, "stylebook does not exercise all shipped primitives")

    print(f"list: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
