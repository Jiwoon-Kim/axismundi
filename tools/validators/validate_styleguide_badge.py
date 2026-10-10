#!/usr/bin/env python3
"""Check Badge evidence, visual ownership, and live host specimens."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent.parent
DATA = ROOT / "products/styleguide/_data/badge.yml"
TABLE = ROOT / "products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-BADGES-RAW.md"
BADGE = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/badges/badge.js"
CSS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/badge.css"
STYLE_INDEX = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/index.css"
PAGE = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/components/badges/index.js"
PAGE_INDEX = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/pages/stylebook/index.js"
APP = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/app.js"
ROUTE = ROOT / "products/wordpress/plugins/axismundi/includes/route.php"
PLUGIN = ROOT / "products/wordpress/plugins/axismundi/axismundi.php"
TABS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/tabs/tabs.js"
TABS_CSS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/tabs.css"
NAVIGATION_ITEM_CSS = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/navigation-item.css"
NAVIGATION_ITEM = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/navigations/navigation-item.js"

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


def dip(value: int) -> str:
    return json.dumps({"value": value, "unit": "DIPS"}, separators=(",", ":"))


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    raw = TABLE.read_text(encoding="utf-8")
    table = rows(raw)
    badge = BADGE.read_text(encoding="utf-8")
    css = CSS.read_text(encoding="utf-8")
    style_index = STYLE_INDEX.read_text(encoding="utf-8")
    page = PAGE.read_text(encoding="utf-8")
    page_index = PAGE_INDEX.read_text(encoding="utf-8")
    app = APP.read_text(encoding="utf-8")
    route = ROUTE.read_text(encoding="utf-8")
    plugin = PLUGIN.read_text(encoding="utf-8")
    tabs = TABS.read_text(encoding="utf-8")
    tabs_css = TABS_CSS.read_text(encoding="utf-8")
    navigation_item_css = NAVIGATION_ITEM_CSS.read_text(encoding="utf-8")
    navigation_item = NAVIGATION_ITEM.read_text(encoding="utf-8")
    report = Report()

    meta = data["meta"]
    report.check(meta["table_revision"] in raw, "recorded Badge table revision is absent")
    report.check(meta["included_token_count"] == 32 and "included: 32" in raw, "Badge token count drifted")
    report.check(meta["deprecated_token_count"] == 0 and "skipped (deprecated): 0" in raw, "Badge deprecation count drifted")

    small = data["variants"]["small"]
    large = data["variants"]["large"]
    report.check(table.get(small["size_token"]) == dip(small["size"]), "small Badge size drifted from the raw table")
    report.check(table.get(small["shape_token"]) == "-> md.sys.shape.corner.full", "small Badge shape drifted")
    report.check(table.get(large["size_token"]) == dip(large["minimum_size"]), "large Badge size drifted from the raw table")
    report.check(table.get(large["shape_token"]) == "-> md.sys.shape.corner.full", "large Badge shape drifted")
    report.check(table.get(large["label"]["color_token"]) == "-> md.sys.color.on-error", "large Badge label color drifted")
    report.check(table.get("md.comp.badge.large.label-text.size") == "-> md.sys.typescale.label-small.size" and table.get("md.sys.typescale.label-small.size") == '{"value":11,"unit":"POINTS"}', "large Badge label size drifted")
    report.check(table.get("md.comp.badge.large.label-text.line-height") == "-> md.sys.typescale.label-small.line-height" and table.get("md.sys.typescale.label-small.line-height") == '{"value":16,"unit":"POINTS"}', "large Badge label line height drifted")

    report.check(f"--md-comp-badge-small-size: {small['size']}px;" in css, "small Badge CSS no longer consumes badge.yml")
    report.check(f"--md-comp-badge-large-size: {large['minimum_size']}px;" in css, "large Badge CSS no longer consumes badge.yml")
    report.check("background: var( --md-comp-badge-color );" in css and "var( --md-sys-color-error )" in css, "Badge default error color drifted")
    report.check("var( --md-sys-color-on-error )" in css, "Badge on-error label color drifted")
    report.check("border-radius: 50%;" in css and "border-radius: 999px;" in css, "Badge corner.full rendering drifted")
    report.check("label-small-size" in css and "label-small-line-height" in css, "Badge label typography drifted")
    policy = data["project_policy"]["unpublished_geometry"]
    report.check(f"padding-inline: {policy['large_inline_padding']}px;" in css, "Badge inline padding no longer consumes its recorded local policy")
    report.check(f"--md-comp-badge-maximum-label-size: {policy['max_character_width']}px;" in css and "data-maximum-label" in badge,
                 "maximum-character Badge geometry drifted")
    report.check("aria-hidden=\"true\"" in badge and "tabIndex" not in badge, "Badge became its own focusable accessibility target")
    report.check("MAXIMUM_LABEL_LENGTH = 4" in badge and "slice( 0, MAXIMUM_LABEL_LENGTH - 1 )" in badge, "Badge label truncation drifted")

    placement = data["placement"]
    report.check(placement["navigation_item"]["owner"] == "NavigationItem", "NavigationItem no longer owns icon-corner placement")
    report.check(placement["tabs"]["owner"] == "Tabs", "Tabs no longer own their Badge placement")
    item = placement["navigation_item"]
    anchor = item["prose_anchor"]
    # Every positional figure is prose; the table has none of them.
    report.check(policy.get("anchors") == "placement.navigation_item.prose_anchor",
                 "the anchors lost their pointer and may be copied in two places again")
    for absent in ("anchor", "34", "padding"):
        report.check(absent not in TABLE.read_text(encoding="utf-8").lower(),
                     f"the badge token table now carries {absent}; it is no longer prose-only policy")
    report.check(item["coordinate_mapping"] == "resolved", "the badge anchor stopped being mapped onto CSS")
    # The host reads the figures off the badge rather than keeping a copy, so
    # every one of them has to be declared where a badge can see it.
    for size in ("small", "large"):
        for axis in ("block", "inline"):
            report.check(f"--md-comp-badge-navigation-{size}-anchor-{axis}: {anchor[size][axis]}px;" in css,
                         f"the {size} badge {axis} anchor no longer consumes badge.yml")
            report.check(f"var( --md-comp-badge-navigation-{size}-anchor-{axis} )" in navigation_item_css,
                         f"NavigationItem stopped reading the {size} badge {axis} anchor")
    # H reaches the badge's bottom and W its leading edge, so each is the
    # badge's own size minus the published figure -- never the figure alone.
    report.check("calc( 100% - var( --md-comp-badge-navigation-large-anchor-inline ) )" in navigation_item_css and
                 "calc( var( --md-comp-badge-navigation-large-anchor-block ) - 100% )" in navigation_item_css,
                 "the anchor stopped being measured from the badge's own corners")
    report.check("translate: 50% -50%;" not in navigation_item_css,
                 "the width-dependent centred anchor came back; a wider badge would slide inward")
    report.check("inline-size: max-content;" in navigation_item_css,
                 "NavigationItem badge width is capped by the icon again and will be cut off")
    # Placement wins over the anatomy drawing once a badge can collide.
    report.check(item["large_badge_on_horizontal_item"] == "trailing-edge" and
                 item["small_badge_on_horizontal_item"] == "icon-upper-trailing",
                 "the horizontal-item placement rule drifted")
    report.check("isLargeBadge" in navigation_item and "'horizontal' === axis && isLargeBadge( badge )" in navigation_item,
                 "NavigationItem stopped sending a colliding large badge to the trailing edge")
    report.check(f"margin-inline-start: var( --md-comp-badge-large-inline-padding );" in navigation_item_css,
                 "the trailing badge stopped consuming the published 4dp text-container padding")
    report.check(any(d["subject"] == "large badge on a horizontal navigation item" for d in data["discrepancies"]),
                 "the anatomy-versus-placement contradiction left the record")
    # 999+ against a 24dp icon is the width at which the two sections disagree.
    report.check('label="999+"' in page and "variant=\"expanded\"" in page,
                 "the Badge page lost its horizontal maximum-count specimen")
    report.check("NavigationBar" in page and "NavigationRail" in page, "Badge page no longer uses real navigation hosts")
    report.check("ax-tab-bar__badge" in tabs and "ax-tab-bar__inline-badge" in tabs and "badgeDescription" in tabs,
                 "Tabs no longer render Badge nodes or expose their information")
    report.check("ax-tab-bar__badge" in tabs_css and
                 f"--md-comp-tabs-stacked-icon-badge-overlap: {placement['tabs']['stacked_icon_overlap']}px;" in tabs_css and
                 ".ax-tab-bar__badge {\n\t\tposition: absolute;\n\t\tdisplay: grid;" in tabs_css and
                 "translate: calc( 100% - var( --md-comp-tabs-stacked-icon-badge-overlap ) ) 0;" in tabs_css,
                 "Tabs stacked Badge placement drifted")
    report.check("ax-tab-bar__label-with-badge" in tabs and f".ax-tab-bar__label-with-badge" in tabs_css and f"gap: {placement['tabs']['inline_label_gap']}px;" in tabs_css,
                 "Tabs inline Badge placement drifted")
    report.check("badgeDescription" in page and "aria-label={ accessibleName }" in (ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend/components/navigations/navigation-item.js").read_text(encoding="utf-8"), "navigation host no longer owns badge announcement")

    primary_tab_specimen = page.split("const TAB_ITEMS =", 1)[-1].split("const INLINE_TAB_ITEMS =", 1)[0]
    report.check("<Badge />" in page and "<Badge label=\"8\" />" in page and "<Badge label=\"999+\" />" in page, "Badge variants or maximum label specimen disappeared")
    report.check("<Badge label=\"999+\" />" in primary_tab_specimen, "the maximum-count Badge host specimen disappeared")
    report.check("SecondaryTabBar" in page and "INLINE_TAB_ITEMS" in page, "Badge page no longer demonstrates the real inline Tab host")
    report.check("StylebookBadgesPage" in page_index and "'badges' === component" in page_index, "stylebook does not expose Badges")
    report.check("badges" in app and route.count("tabs|badges|dividers") == 2, "frontend route omits Badges")
    report.check(re.search(r"AXISMUNDI_CAPSTONE_REWRITE_VERSION\s*=\s*'19';", plugin) is not None, "rewrite version did not advance for Badges")
    report.check("@import url( './components/badge.css' );" in style_index, "Badge stylesheet is not in frontend bundle")

    print(f"badge: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
