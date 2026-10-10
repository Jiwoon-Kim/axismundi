#!/usr/bin/env python3
"""Check Tabs evidence, the Tabs contract, and the frontend primitive."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent.parent
DATA = ROOT / "products/styleguide/_data/tabs.yml"
TABLE = ROOT / "products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-TABS-RAW.md"
TABS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/tabs/tabs.js"
CSS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/tabs.css"
STYLE_INDEX = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/index.css"
STYLEBOOK_INDEX = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/index.js"
STYLEBOOK_PAGE = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/components/tabs/index.js"
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


def token_rows(text: str) -> dict[str, str]:
    return {match.group(1): match.group(2) for line in text.splitlines() if (match := ROW.match(line))}


def dip(value: int) -> str:
    return json.dumps({"value": value, "unit": "DIPS"}, separators=(",", ":"))


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    rows = token_rows(TABLE.read_text(encoding="utf-8"))
    tabs = TABS.read_text(encoding="utf-8")
    css = CSS.read_text(encoding="utf-8")
    style_index = STYLE_INDEX.read_text(encoding="utf-8")
    stylebook_index = STYLEBOOK_INDEX.read_text(encoding="utf-8")
    stylebook_page = STYLEBOOK_PAGE.read_text(encoding="utf-8")
    app = APP.read_text(encoding="utf-8")
    route = ROUTE.read_text(encoding="utf-8")
    plugin = PLUGIN.read_text(encoding="utf-8")
    report = Report()

    def row_is(token: str, value: str) -> None:
        report.check(rows.get(token) == value, f"{token} no longer resolves to {value}")

    meta = data["meta"]
    report.check(meta["table_revision"] in TABLE.read_text(encoding="utf-8"), "recorded Tabs table revision is absent")
    report.check(meta["included_token_count"] == 126 and "included: 126" in TABLE.read_text(encoding="utf-8"), "Tabs included token count drifted")
    report.check(meta["deprecated_token_count"] == 4 and "skipped (deprecated): 4" in TABLE.read_text(encoding="utf-8"), "Tabs deprecated token count drifted")
    report.check(data["prose_sources"]["variants"]["source"].endswith("/tabs/guidelines"), "Tabs variant source drifted")

    for name, variant in data["variants"].items():
        container = variant["container"]
        indicator = variant["indicator"]
        prefix = f"--md-comp-{name}-navigation-tab"
        row_is(container["height_token"], dip(container["label_only_height"]))
        row_is(container["shape_token"], "-> md.sys.shape." + container["shape_role"])
        row_is(indicator["height_token"], dip(indicator["height"]))
        row_is(variant["icon"]["size_token"], dip(variant["icon"]["size"]))
        report.check(f"{prefix}-container-height: {container['label_only_height']}px;" in css, f"{name} container height no longer consumes tabs.yml")
        report.check(f"{prefix}-icon-size: {variant['icon']['size']}px;" in css, f"{name} icon size no longer consumes tabs.yml")
        report.check(f"{prefix}-active-indicator-height: {indicator['height']}px;" in css, f"{name} indicator height no longer consumes tabs.yml")
        if "icon_and_label_height" in container:
            row_is(container["icon_and_label_height_token"], dip(container["icon_and_label_height"]))
            report.check(f"{prefix}-icon-and-label-container-height: {container['icon_and_label_height']}px;" in css, "primary icon-and-label height no longer consumes tabs.yml")

    shared = data["shared"]
    report.check(rows.get("md.sys.typescale.title-small.size") == '{"value":14,"unit":"POINTS"}', "title-small size drifted")
    report.check(rows.get("md.sys.typescale.title-small.line-height") == '{"value":20,"unit":"POINTS"}', "title-small line height drifted")
    report.check(shared["label"]["size"] == 14 and shared["label"]["line_height"] == 20, "Tabs label policy drifted")
    report.check(rows.get("md.sys.state.focus-indicator.thickness") == dip(shared["focus_indicator"]["thickness"]), "Tabs focus thickness drifted")
    report.check(rows.get("md.sys.state.focus-indicator.inner-offset") == dip(shared["focus_indicator"]["inner_offset"]), "Tabs focus offset drifted")
    report.check(f"--md-comp-tabs-active-indicator-minimum-length: {data['variants']['primary']['indicator']['minimum_length']}px;" in css, "indicator minimum length no longer consumes tabs.yml")
    report.check(f"--md-comp-tabs-active-indicator-inset-inline: {data['variants']['primary']['indicator']['inset_inline']}px;" in css, "indicator inset no longer consumes tabs.yml")

    # Where the indicator sits is the difference between the variants:
    # material-web puts the primary one inside the tab's content box and
    # gives the secondary one fullWidthIndicator.
    primary_indicator = data["variants"]["primary"]["indicator"]
    secondary_indicator = data["variants"]["secondary"]["indicator"]
    report.check(primary_indicator["spans"] == "content-box", "the primary indicator stopped hugging its content")
    report.check(secondary_indicator["spans"] == "full-tab", "the secondary indicator stopped spanning its tab")
    report.check("'primary' === variant && <span aria-hidden=\"true\" className=\"ax-tab-bar__indicator\" />" in tabs,
                 "the primary indicator left the content box")
    report.check("'primary' !== variant && <span aria-hidden=\"true\" className=\"ax-tab-bar__indicator\" />" in tabs,
                 "the secondary indicator left the tab box")
    # Nothing is published for the secondary indicator but color and height.
    for absent in ("minimum_length", "shape"):
        report.check(secondary_indicator[absent] is None, f"secondary indicator {absent} was invented; no token publishes one")
    report.check(0 == secondary_indicator["inset_inline"], "the secondary indicator took an unpublished inset")
    # Every tab keeps an indicator, or the outgoing rect cannot be measured.
    report.check("selected && <span aria-hidden=\"true\" className=\"ax-tab-bar__indicator\"" not in tabs,
                 "the indicator is painted only when selected; the morph cannot measure where it came from")
    report.check(".ax-tab-bar__tab[aria-selected='true'] .ax-tab-bar__indicator {\n\t\topacity: 1;" in css,
                 "the indicator is no longer revealed by selection alone")

    motion = data["interaction_contract"]["motion"]
    report.check(f"INDICATOR_DURATION = {motion['duration']}" in tabs, "indicator duration no longer consumes tabs.yml")
    report.check(f"INDICATOR_EASING = '{motion['easing']}'" in tabs, "indicator easing no longer consumes tabs.yml")
    report.check("prefers-reduced-motion: reduce" in tabs, "the indicator morph ignores reduced motion")
    report.check("to.animate(" in tabs and "scaleX(" in tabs, "the indicator no longer travels from the previous one")
    report.check("badge" in data["interaction_contract"]["deferred"], "badge left the deferred list without a Badge component")
    report.check("badge" not in tabs.lower() and "badge" not in css.lower() and "badge" not in stylebook_page.lower(),
                 "a badge reappeared in Tabs; badges belong to the Badge component pass")

    layouts = data["tab_layouts"]
    # Secondary publishes an icon position but no stacked height, so 64dp
    # needs both an icon and a label and secondary never reaches it.
    report.check(layouts["label_and_icon"]["secondary_icon_and_label_height_token"] is None,
                 "a secondary icon-and-label height was invented; the table publishes none")
    report.check(layouts["label_and_icon"]["secondary_stacks"] is False, "the secondary icon started stacking")
    row_is("md.comp.secondary-navigation-tab.with-icon.icon.size", dip(24))
    report.check(rows.get("md.comp.secondary-navigation-tab.with-icon-and-label-text.container.height") is None,
                 "a secondary icon-and-label height appeared in the table; the record assumes none")
    report.check(":has( .ax-tab-bar__icon ):has( .ax-tab-bar__label )" in css,
                 "the 64dp height no longer requires both an icon and a label")
    # Icon-only: the label goes, the name does not.
    report.check(layouts["icon_only"]["accessible_name"] == "required", "the icon-only name requirement was dropped")
    report.check(layouts["icon_only"]["kit_publishes_for"] == ["primary"], "the kit's icon-only coverage drifted")
    report.check("{ tab.label && <span className=\"ax-tab-bar__label\">" in tabs, "the label stopped being optional")
    report.check("aria-label={ tab.label ? undefined : tab.name }" in tabs, "an icon-only tab lost its accessible name")
    report.check("has no label, so it needs a name" in tabs, "nothing warns when a tab has neither a label nor a name")

    scrollable = data["layouts"]["scrollable"]
    report.check(scrollable["intrinsic_width_guard"] == "min-inline-size-0", "the scrollable width guard left the record")
    for guarded in (".ax-tabs {", ".ax-tab-bar[data-layout='scrollable'] {"):
        block = css.split(guarded, 1)[-1].split("}", 1)[0]
        report.check("min-inline-size: 0;" in block,
                     f"{guarded.strip(' {{')} lost its min-inline-size guard and will widen its container")
    report.check(scrollable["wheel_scrolls_inline"] is True and scrollable["wheel_releases_at_both_ends"] is True,
                 "the wheel contract left the record")
    report.check("addEventListener( 'wheel', onWheel, { passive: false } )" in tabs,
                 "the wheel listener is gone or passive, and a passive listener cannot preventDefault")
    report.check("bar.scrollBy(" in tabs and "event.preventDefault()" in tabs, "the wheel no longer moves the bar")
    # A specimen that fits its container demonstrates nothing about scrolling.
    report.check(9 < stylebook_page.count("panel: '") + stylebook_page.count("panel: <"),
                 "the scrollable specimen no longer has enough tabs to overflow")

    motion = data["interaction_contract"]["motion"]
    report.check(motion["panel"] == "owned-by-the-consumer", "Tabs claimed the panel transition")
    report.check("startViewTransition" not in tabs, "Tabs took the route transition that belongs to its consumer")
    # Nesting is what the bar/panel split buys; a claim with no specimen is not a measurement.
    report.check("nested-tabsets" not in data["interaction_contract"]["deferred"], "nesting is demonstrated; it is no longer deferred")
    report.check("tabs-nested-inner" in stylebook_page and "<NestedPanel />" in stylebook_page,
                 "the Stylebook lost its nested tab-set specimen")

    divider = shared["divider"]
    report.check(divider["inside_container_height"] is True, "the divider left the container height")
    report.check(divider["row_height_is_container_minus_divider"] is True, "rows stopped yielding the divider its pixel")
    report.check(f"--md-comp-tabs-divider-thickness: {divider['thickness']}px;" in css, "divider thickness no longer consumes tabs.yml")
    # The bar carries the height and the border; a row that carries the height
    # too would add the divider on top and make the container 49.
    report.check("border-block-end: var( --md-comp-tabs-divider-thickness ) solid" in css,
                 "the divider is no longer the bar's own border, so it cannot sit inside the height")
    report.check("min-block-size: calc( var( --ax-tabs-container-height ) - var( --md-comp-tabs-divider-thickness ) );" in css,
                 "a row claims the whole container height again; the divider would add a pixel to it")
    report.check("align-self: stretch;" in css, "rows no longer stretch into the bar's content box")
    report.check(f"--md-comp-tabs-scrollable-leading-offset: {data['layouts']['scrollable']['leading_offset']}px;" in css, "scrollable leading offset no longer consumes tabs.yml")

    keyboard = data["keyboard"]
    report.check(keyboard["roving_tabindex"] is True and keyboard["active_tabindex"] == 0 and keyboard["inactive_tabindex"] == -1, "roving tabindex contract drifted")
    report.check(keyboard["home_end"] is True and keyboard["wraps"] is False, "Home/End or non-wrapping contract drifted")
    report.check(keyboard["rtl_mirrors_horizontal_arrows"] is True and keyboard["arrow_selection"] == "manual", "RTL/manual activation contract drifted")
    report.check(keyboard["disabled_tabs_skipped"] is True and keyboard["tab_exits_tabset"] is True, "disabled or Tab exit contract drifted")
    report.check("const ACTIVATION_KEYS = [ ' ', 'Enter' ];" in tabs, "Tabs activation keys drifted")
    report.check("tabIndex={ focused ? 0 : -1 }" in tabs and "aria-selected={ selected }" in tabs, "Tabs no longer implement roving tabindex")
    report.check("'Home' === event.key" in tabs and "'End' === event.key" in tabs and "enabledTabs[ currentIndex + ( isForward ? 1 : -1 ) ]" in tabs, "Tabs arrow/Home/End behavior drifted")
    report.check("getComputedStyle( event.currentTarget ).direction" in tabs, "Tabs RTL mirroring drifted")
    report.check("if ( ! nextTab || nextTab.id === currentId )" in tabs and "event.preventDefault();\n\t\tfocusTab( nextTab.id );" in tabs, "Tabs now wrap or capture arrows at an edge")
    report.check("nextButton?.scrollIntoView" in tabs and data["layouts"]["scrollable"]["focus_scrolls_into_view"] is True, "scrollable focus no longer scrolls into view")

    report.check("role=\"tablist\"" in tabs and "role=\"tab\"" in tabs and "role=\"tabpanel\"" in tabs, "Tabs ARIA roles drifted")
    composition = data["interaction_contract"]["composition"]
    report.check(composition["state_root"] == "Tabs" and composition["tab_bars"] == [ "PrimaryTabBar", "SecondaryTabBar" ] and composition["panel_view"] == "TabPanels", "Tabs composition contract drifted")
    report.check("const TabsContext = createContext( null );" in tabs and "export function PrimaryTabBar" in tabs and "export function SecondaryTabBar" in tabs and "export function TabPanels" in tabs, "tab state root, bars, and panels were collapsed")
    report.check("@import url( './components/tabs.css' );" in style_index, "Tabs stylesheet is not in frontend bundle")
    report.check("StylebookTabsPage" in stylebook_index and "'tabs' === component" in stylebook_index, "stylebook does not expose Tabs")
    report.check("tabs" in app and route.count("lists|tabs|dividers") == 2, "frontend route omits Tabs")
    report.check(re.search(r"AXISMUNDI_CAPSTONE_REWRITE_VERSION\s*=\s*'18';", plugin) is not None, "rewrite version did not advance for Tabs")
    report.check("PrimaryTabBar" in stylebook_page and "SecondaryTabBar" in stylebook_page and "TabPanels" in stylebook_page and "scrollable" in stylebook_page and "disabled: true" in stylebook_page, "stylebook does not exercise primary, secondary, scrollable, and disabled Tabs")

    print(f"tabs: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
