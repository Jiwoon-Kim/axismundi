#!/usr/bin/env python3
"""Check the tooltip record against its token table and its own precedents.

This component is built three times in this repository already, with three
different ARIA decisions and three different gaps, so the checks here are less
about values than about the record staying honest:

* Every `*_token` key resolves through the table, including the string-valued
  ones -- a colour role or a shape that names the wrong token reads perfectly.
* The geometry is prose, not tokens. The record must keep saying so, and the
  block padding must keep being arithmetic rather than a fourth number.
* The three precedents must keep existing at the paths they are cited from,
  and the two that disagree about ARIA must keep disagreeing in the record
  until they stop disagreeing on disk.
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
DATA = ROOT / "products/styleguide/_data/tooltip.yml"
RAW = (
    ROOT
    / "products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-TOOLTIPS-RAW.md"
)
FAB_CSS = (
    ROOT
    / "products/wordpress/plugins/axismundi/src/apps/frontend/styles/components/fab.css"
)
FRONTEND = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
TOOLTIP_JS = FRONTEND / "components/tooltip/tooltip.js"
TOOLTIP_CSS = FRONTEND / "styles/components/tooltip.css"
PLACER_JS = FRONTEND / "components/overlays/place.js"
FAB_JS = FRONTEND / "components/fabs/fab.js"
ICON_BUTTON_JS = FRONTEND / "components/buttons/icon-button.js"
APP_JS = FRONTEND / "app.js"
STYLEBOOK_JS = FRONTEND / "pages/stylebook/index.js"
ROUTE_PHP = ROOT / "products/wordpress/plugins/axismundi/includes/route.php"

# Exact, not a floor: at a floor a renamed key takes its checks with it and the
# run still passes. Raise this in the same commit that adds a token.
EXPECTED_TOKEN_KEYS = 10

# body-small's line height, which is what makes the 24dp container and the 8dp
# padding agree with each other.
BODY_SMALL_LINE_HEIGHT = 16

ENTRY = re.compile(r"^  (md\.[A-Za-z0-9._-]+)\s{2,}([A-Z_]+)\s+(.*?)\s*$")
SKIPPED = re.compile(r"^  - (md\.[A-Za-z0-9._-]+)$")


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


class Table:
    def __init__(self, text: str) -> None:
        self.entries: dict[str, str] = {}
        self.deprecated: list[str] = []
        in_skipped = False
        for line in text.splitlines():
            if line.startswith("skipped (deprecated)"):
                in_skipped = True
            if in_skipped:
                deprecated = SKIPPED.match(line)
                if deprecated:
                    self.deprecated.append(deprecated.group(1))
                continue
            entry = ENTRY.match(line)
            if entry:
                self.entries[entry.group(1)] = entry.group(3)

    def live(self, prefix: str) -> list[str]:
        return [
            name
            for name in self.entries
            if name.startswith(f"{prefix}.") and name not in self.deprecated
        ]

    def reference(self, token: str) -> str | None:
        value = self.entries.get(token)
        if value and value.startswith("-> "):
            return value[3:].split(" | ")[0].strip()
        return None

    def number(self, token: str) -> float | None:
        seen: set[str] = set()
        while token and token not in seen:
            seen.add(token)
            value = self.entries.get(token)
            if value is None:
                return None
            if value.startswith("-> "):
                token = value[3:].split(" | ")[0].strip()
                continue
            if not value.startswith("{"):
                try:
                    return float(value.split(" | ")[0])
                except ValueError:
                    return None
            payload = json.loads(value.split(" | ")[0])
            if "value" in payload:
                return float(payload["value"])
            return None
        return None


def token_keys(node: object, path: str = "") -> list[tuple[str, dict]]:
    found: list[tuple[str, dict]] = []
    if isinstance(node, dict):
        for key, value in node.items():
            if isinstance(key, str) and key.endswith("_token") and isinstance(value, str):
                found.append((f"{path}.{key}", node))
            found.extend(token_keys(value, f"{path}.{key}"))
    elif isinstance(node, list):
        for index, value in enumerate(node):
            found.extend(token_keys(value, f"{path}[{index}]"))
    return found


def sweep_tokens(report: Report, data: dict, table: Table) -> None:
    sites = token_keys(data)
    report.check(
        len(sites) == EXPECTED_TOKEN_KEYS,
        f"the token sweep found {len(sites)} `*_token` keys, not the {EXPECTED_TOKEN_KEYS} it "
        "covers -- a rename has taken checks with it, or a new token needs this line raised",
    )
    for path, owner in sites:
        key = path.rsplit(".", 1)[1]
        base = key[: -len("_token")]
        token = owner[key]
        report.check(token in table.entries, f"{path}: `{token}` is not in the token table")
        report.check(
            token not in table.deprecated,
            f"{path}: `{token}` is deprecated and must not be restated as live",
        )
        expected = owner.get(base)
        if isinstance(expected, str) and expected.startswith("md."):
            reference = table.reference(token) or ""
            report.check(
                reference == expected,
                f"{path}: `{token}` points at `{reference or 'a literal'}`, but `{base}` "
                f"records `{expected}`",
            )
        elif isinstance(expected, (int, float)) and not isinstance(expected, bool):
            actual = table.number(token)
            report.check(
                actual is not None and abs(actual - expected) < 1e-9,
                f"{path}: `{token}` resolves to {actual}, but `{base}` records {expected}",
            )


def check_table_shape(report: Report, data: dict, table: Table) -> None:
    meta = data["meta"]
    report.check(
        meta["token_table"] == RAW.relative_to(ROOT).as_posix(),
        "meta.token_table must name the table this check reads",
    )
    plain = table.live("md.comp.plain-tooltip")
    report.check(
        len(plain) > 0 and len(table.live("md.comp.rich-tooltip")) > 0,
        "both tooltip namespaces must still exist, or this record describes nothing",
    )
    # The claim the whole geometry section rests on.
    geometry = [name for name in plain if re.search(r"\.(height|width|padding|space)", name)]
    report.check(
        not geometry,
        "the plain tooltip now publishes geometry tokens "
        f"({geometry}); the record says it publishes none and must be rewritten, not patched",
    )
    report.check(
        "four things" in meta["what_the_table_does_not_carry"]
        and "prose" in meta["what_the_table_does_not_carry"],
        "the record must keep saying how little the table carries and where the rest comes from",
    )


def check_geometry(report: Report, data: dict) -> None:
    plain = data["plain"]
    report.check(
        plain["padding_block_derived"] * 2 + BODY_SMALL_LINE_HEIGHT == plain["container_height"],
        "the derived block padding must still centre a body-small line in the container; if it "
        "does not, one of the three figures moved alone",
    )
    report.check(
        plain["invariant"] == "padding_block_derived * 2 + 16 == container_height",
        "the invariant stays written as an expression, not as three numbers nobody rechecks",
    )
    report.check(
        "16 + 4 + 4 = 24" in plain["padding_block_derivation"],
        "the derivation must keep its arithmetic, which is also what the shipped plugin wrote "
        "in its stylesheet",
    )
    report.check(
        set(plain["unpublished"]) == {"max_inline_size", "show_delay"},
        "exactly two things are unpublished here, and both must stay named: a maximum width "
        "and a show delay",
    )
    report.check(
        "700ms" in plain["unpublished"]["show_delay"]
        or "700" in str(plain["unpublished"]["show_delay"]),
        "the show delay is a project value and the record must say whose",
    )
    rich = data["rich"]
    report.check(
        rich["padding_block_start"] == 12
        and rich["padding_block_end"] == 8
        and rich["padding_inline"] == 16,
        "the rich tooltip's three paddings are published and asymmetric; collapsing them loses "
        "what the prose states",
    )


def check_precedents(report: Report, data: dict) -> None:
    precedents = data["precedents_in_this_repository"]
    for name in ("shipped_plugin", "lab_module", "fab_inline"):
        entry = precedents[name]
        report.check(
            (ROOT / entry["path"]).exists(),
            f"precedent `{name}` cites `{entry['path']}`, which does not exist",
        )
    report.check(
        "aria-hidden" in precedents["shipped_plugin"]["aria"]
        and 'role="tooltip"' in precedents["lab_module"]["aria"],
        "the two precedents disagree about ARIA, and the record must keep both positions as "
        "they are on disk rather than reporting a consensus",
    )
    report.check(
        "z-index" in precedents["shipped_plugin"]["why_the_top_layer"],
        "why the shipped plugin uses the top layer is the reason this record adopts it",
    )
    report.check(
        precedents["fab_inline"]["status"] == "replaced",
        "the FAB's inline tooltip must become recorded history when the shared primitive replaces it",
    )
    # Measured, not asserted: the historical inline implementation is gone.
    fab_css = FAB_CSS.read_text(encoding="utf-8")
    still_inline = ".ax-fab__tooltip" in fab_css
    report.check(
        not still_inline,
        "the FAB stylesheet still owns an inline tooltip after the shared primitive replaced it",
    )
    report.check(
        "shared primitive" in precedents["three_answers_one_question"],
        "the historical disagreement must say how the frontend resolved it",
    )


def check_policy(report: Report, data: dict) -> None:
    policy = data["project_policy"]
    report.check(
        policy["mechanism"]["adopted"].startswith("native popover"),
        "the adopted mechanism is the top layer, which is what the menu and the shipped plugin "
        "both concluded",
    )
    aria = policy["aria"]
    report.check(
        'role="tooltip"' in aria["rule"] and "aria-describedby" in aria["rule"],
        "the default branch must keep both halves of what M3 asks for",
    )
    report.check(
        "accessible name" in aria["rule"] and "aria-hidden" in aria["rule"],
        "the exception must stay in the rule, not in a footnote: where the tooltip is the name, "
        "it is not also a description",
    )
    report.check(
        "twice" in aria["why_the_exception"],
        "the reason for the exception is double announcement, and it stays written down",
    )
    report.check(
        "exception branch" in aria["consequence_for_the_fab"],
        "which branch the FAB is in must be stated, because it is the first consumer",
    )
    report.check(
        policy["delay"]["hide_after_leaving"] == data["behaviour"]["hide_after_leaving"] == 1500,
        "the published hide delay must agree with itself in both places it is written",
    )
    report.check(
        policy["delay"]["show"] == 700 and "plugin" in policy["delay"]["source"],
        "the show delay is carried from the shipped plugin rather than invented, and says so",
    )
    report.check(
        "label is visible" in policy["not_a_consumer"],
        "the extended FAB is deliberately not a consumer, for the reason M3 gives",
    )
    report.check(
        len(policy["consumers_waiting"]) == 2,
        "two components are waiting on this primitive, and both stay named",
    )


def check_placement_and_behaviour(report: Report, data: dict) -> None:
    placement = data["placement"]["plain"]
    report.check(
        placement["distance_from_a_visual_boundary"] == 4
        and placement["distance_without_a_boundary"] == 8,
        "the two gaps are published and different; one number would lose the distinction M3 "
        "draws between a button and a text baseline",
    )
    report.check(
        "app bar" in placement["in_an_app_bar"] or "block-end" in placement["in_an_app_bar"],
        "the app bar case flips the side and must stay recorded",
    )
    report.check(
        "rectangle" in data["placement"]["this_app"]["why"],
        "reusing the menu's placer rests on its anchor being a rectangle, which is the reason "
        "it generalises",
    )
    behaviour = data["behaviour"]
    report.check(
        behaviour["show_on"] == ["hover", "focus"],
        "focus shows a tooltip as well as hover; dropping focus makes it unreachable by keyboard",
    )
    report.check(
        behaviour["one_at_a_time"] is True and behaviour["a_new_one_closes_the_open_one"] is True,
        "both halves of the single-tooltip rule are published and stay recorded",
    )
    report.check(
        "does not move focus" in behaviour["focus_is_never_moved"],
        "a tooltip never moves focus, which is the line that separates it from a popover",
    )


def check_implementation(report: Report, data: dict) -> None:
    implementation = data["project_policy"]["implementation"]
    paths = {
        "component": TOOLTIP_JS,
        "stylesheet": TOOLTIP_CSS,
        "placement_helper": PLACER_JS,
    }
    for name, path in paths.items():
        report.check(path.exists() and implementation[name] == path.relative_to(ROOT).as_posix(),
                     f"the implemented {name} must exist at the path the record names")

    tooltip = TOOLTIP_JS.read_text(encoding="utf-8")
    css = TOOLTIP_CSS.read_text(encoding="utf-8")
    placer = PLACER_JS.read_text(encoding="utf-8")
    fab = FAB_JS.read_text(encoding="utf-8")
    icon_button = ICON_BUTTON_JS.read_text(encoding="utf-8")

    # These use complete component boundaries rather than a token substring: a
    # comment or an unrelated fallback cannot satisfy them.
    tooltip_component = re.search(r"export function Tooltip\(.*?\n}\n", tooltip, re.S)
    report.check(
        tooltip_component is not None
        and "popover={ canUsePopover ? 'manual' : undefined }" in tooltip_component.group(0)
        and "createPortal( surface, document.body )" in tooltip_component.group(0),
        "Tooltip must use the manual native-popover path and the documented body fallback",
    )
    report.check(
        tooltip_component is not None
        and "role={ descriptive ? 'tooltip' : undefined }" in tooltip_component.group(0)
        and "aria-hidden={ descriptive ? undefined : 'true' }" in tooltip_component.group(0)
        and "'aria-describedby': descriptive" in tooltip_component.group(0),
        "Tooltip must implement both ARIA branches on the surface and trigger",
    )
    # The three checks above pin what each branch does and not which branch is
    # taken. Measured: replacing this one line with `const descriptive = false`
    # sends every tooltip down the aria-hidden arm -- no role and no
    # description, ever -- and all of them still passed.
    report.check(
        tooltip_component is not None
        and "const descriptive = content && content !== accessibleName;"
        in tooltip_component.group(0),
        "the branch must still be decided by comparing the tooltip's words with the trigger's "
        "accessible name; pinning only the arms leaves the condition free to be constant",
    )
    report.check(
        tooltip_component is not None
        and "SHOW_DELAY" in tooltip_component.group(0)
        and "HIDE_DELAY" in tooltip_component.group(0)
        and "activeTooltip" in tooltip_component.group(0),
        "Tooltip must retain its recorded show delay, linger, and single-open coordinator",
    )
    report.check(
        "const SHOW_DELAY = 700;" in tooltip
        and "const HIDE_DELAY = 1500;" in tooltip
        and "hasVisualBoundary ? 4 : 8" in tooltip,
        "Tooltip must use the recorded 700/1500 timing and its two placement gaps",
    )
    report.check(
        "export function placePopover" in placer
        and "gap = 0" in placer
        and "preferEnd = true" in placer,
        "Tooltip and Menu must share the parameterized rectangle placer rather than fork placement",
    )
    report.check(
        "min-block-size: 24px;" in css
        and "max-inline-size: 240px;" in css
        and "padding: 4px 8px;" in css
        and "--md-sys-color-inverse-surface" in css
        and "--md-sys-color-inverse-on-surface" in css,
        "Tooltip CSS must consume the recorded plain geometry and colour roles",
    )
    report.check(
        "<Tooltip accessibleName={ label } content={ label }>" in fab
        and ".ax-fab__tooltip" not in fab
        and "<Tooltip accessibleName={ label } content={ tooltip ?? label }>" in icon_button,
        "FAB and IconButton must consume Tooltip; FAB cannot retain its inline surface",
    )

    route = implementation["stylebook_route"]
    route_slug = route.rsplit("/", 1)[1]
    route_php = ROUTE_PHP.read_text(encoding="utf-8")
    app = APP_JS.read_text(encoding="utf-8")
    stylebook = STYLEBOOK_JS.read_text(encoding="utf-8")
    report.check(
        route_php.count(f"|{route_slug}") == 2
        and f"|{route_slug})$" in app
        and f"if ( '{route_slug}' === component )" in stylebook
        and f'href="{route}"' in stylebook,
        "the tooltip stylebook route must occupy both PHP regexes, app dispatch, stylebook dispatch, and index link",
    )
    report.check(
        data["variants"]["plain"]["status"] == "implemented"
        and data["variants"]["rich"]["status"] == "not_implemented",
        "plain is the implemented slice and rich remains explicitly out of scope",
    )


def check_prose(report: Report, data: dict) -> None:
    sources = data["prose_sources"]
    for name, entry in sources.items():
        report.check(
            isinstance(entry, dict) and set(entry) == {"source", "statement"},
            f"prose_sources.{name} must carry exactly a source and a statement",
        )
        report.check(
            str(entry.get("source", "")).startswith(
                "https://m3.material.io/components/tooltips/"
            ),
            f"prose_sources.{name}: a statement without a tooltip page behind it is not a citation",
        )
    # The load-bearing citations, by page. The set check below only proves all
    # four pages are cited somewhere, which stays true when a statement moves to
    # the wrong one.
    for name, page in (
        ("plain_measurements", "specs"),
        ("rich_measurements", "specs"),
        ("role", "accessibility"),
        ("transient", "guidelines"),
        ("plain_placement", "guidelines"),
    ):
        report.check(
            str(sources[name]["source"]).rsplit("/", 1)[1] == page,
            f"prose_sources.{name} must cite the {page} page, which is where M3 states it",
        )
    pages = {str(entry["source"]).rsplit("/", 1)[1] for entry in sources.values()}
    report.check(
        pages == {"overview", "specs", "guidelines", "accessibility"},
        f"the record cites {sorted(pages)}; all four pages must stay cited, because the "
        "geometry is on one of them and the ARIA rule on another",
    )


def main() -> int:
    report = Report()
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    table = Table(RAW.read_text(encoding="utf-8"))

    sweep_tokens(report, data, table)
    check_table_shape(report, data, table)
    check_geometry(report, data)
    check_precedents(report, data)
    check_policy(report, data)
    check_placement_and_behaviour(report, data)
    check_implementation(report, data)
    check_prose(report, data)

    report.check(
        data["variants"]["plain"]["status"] == "planned"
        or data["variants"]["plain"]["status"] == "implemented",
        "the plain tooltip is the slice; if it is neither planned nor implemented the record "
        "has lost its subject",
    )
    report.check(
        data["variants"]["rich"]["status"] == "not_implemented"
        and "persistent" in data["variants"]["rich"]["why"],
        "rich stays unbuilt, and the reason stays the one that matters: it is a different "
        "component wearing the same name",
    )

    print(f"tooltip: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
