#!/usr/bin/env python3
"""Check `_data/menu.yml` against the raw M3 menu token table.

There is no Menu adapter yet, so the subject here is the record rather than an
implementation: every figure `menu.yml` restates is checked against
`SOURCE-M3-MENUS-RAW.md`, which is the measurement this project took off the
published table.

Three things this file exists to stop:

* A restated value drifting from the token it names. Every `*_token` key is
  resolved through the table and compared with its sibling value or role.
* The item geometry being pinned as three frozen numbers. The 48dp slot and the
  2dp inset are measured, not token-published, so what is bound is the
  arithmetic that ties them to the 44dp token -- if one moves alone, this fails.
* A claim going quiet because the key it read was renamed. The token sweep
  asserts a floor on how many tokens it found, and the structure comparison
  reads `list.yml` as well as `menu.yml` so that either side moving breaks it.
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
DATA = STYLEGUIDE / "_data/menu.yml"
LIST_DATA = STYLEGUIDE / "_data/list.yml"
RAW = (
    ROOT
    / "products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-MENUS-RAW.md"
)
TOOLTIP = ROOT / "products/wordpress/plugins/axismundi-dialogs/assets/tooltip.js"

# Every `*_token` key in the record is swept. If a rename drops some of them the
# sweep would quietly check fewer things, so it has to find exactly this many.
#
# Measured, and it has already earned its keep: at a floor of 24 a renamed key
# took three checks with it and the run still passed. An exact count is right
# here because adding a token to the record is deliberate -- raise this line in
# the same commit.
EXPECTED_TOKEN_KEYS = 28

ENTRY = re.compile(r"^  (md\.[A-Za-z0-9._-]+)\s{2,}([A-Z_]+)\s+(.*?)\s*$")
SKIPPED = re.compile(r"^  - (md\.[A-Za-z0-9._-]+)$")
COUNT_INCLUDED = re.compile(r"^included: (\d+)$")
COUNT_SKIPPED = re.compile(r"^skipped \(deprecated\): (\d+)$")


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


class Table:
    """The raw token table, parsed once."""

    def __init__(self, text: str) -> None:
        self.entries: dict[str, str] = {}
        self.deprecated: list[str] = []
        self.included_count = -1
        self.deprecated_count = -1
        in_skipped = False
        for line in text.splitlines():
            if line.startswith("skipped (deprecated)"):
                in_skipped = True
            included = COUNT_INCLUDED.match(line)
            if included:
                self.included_count = int(included.group(1))
            skipped = COUNT_SKIPPED.match(line)
            if skipped:
                self.deprecated_count = int(skipped.group(1))
            if in_skipped:
                deprecated = SKIPPED.match(line)
                if deprecated:
                    self.deprecated.append(deprecated.group(1))
                continue
            entry = ENTRY.match(line)
            if entry:
                self.entries[entry.group(1)] = entry.group(3)

    def reference(self, token: str) -> str | None:
        """The token this one points at, if it points at one."""
        value = self.entries.get(token)
        if value and value.startswith("-> "):
            # A few tokens publish several context-tagged alternatives; the
            # first is the one the table resolves to by default.
            return value[3:].split(" | ")[0].strip()
        return None

    def number(self, token: str) -> float | None:
        """The numeric value a token resolves to, following one reference."""
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
                return None
            payload = json.loads(value.split(" | ")[0])
            if "value" in payload:
                return float(payload["value"])
            size = payload.get("defaultSize")
            if isinstance(size, dict) and "value" in size:
                return float(size["value"])
            return None
        return None


def token_keys(node: object, path: str = "") -> list[tuple[str, dict]]:
    """Every mapping that declares a `*_token` key, with the key's base name."""
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
        f"the token sweep found {len(sites)} `*_token` keys, not the {EXPECTED_TOKEN_KEYS} "
        "it covers -- a rename has taken checks with it, or a new token needs this line raised",
    )

    for path, owner in sites:
        key = path.rsplit(".", 1)[1]
        base = key[: -len("_token")]
        token = owner[key]

        report.check(
            token in table.entries,
            f"{path}: `{token}` is not in the token table",
        )
        report.check(
            token not in table.deprecated,
            f"{path}: `{token}` is deprecated and must not be restated as live",
        )

        role = owner.get(f"{base}_role")
        if isinstance(role, str):
            reference = table.reference(token) or ""
            report.check(
                reference.endswith(role),
                f"{path}: `{token}` resolves to `{reference or 'a literal'}`, not the "
                f"recorded role `{role}`",
            )

        level = owner.get(f"{base}_level")
        if isinstance(level, int):
            reference = table.reference(token) or ""
            report.check(
                reference.endswith(f"level{level}"),
                f"{path}: `{token}` resolves to `{reference or 'a literal'}`, not level {level}",
            )

        for value_key in (base, f"{base}_dp"):
            expected = owner.get(value_key)
            if isinstance(expected, bool) or not isinstance(expected, (int, float)):
                continue
            actual = table.number(token)
            report.check(
                actual is not None and abs(actual - expected) < 1e-9,
                f"{path}: `{token}` resolves to {actual}, but `{value_key}` records {expected}",
            )


def check_item_geometry(report: Report, data: dict, table: Table) -> None:
    item = data["item"]
    slot = item["slot_height"]
    inset = item["inset_block"]
    container = item["container_height"]

    # The arithmetic, not the three numbers. This is the whole reason the record
    # carries an `invariant` line at all.
    report.check(
        slot - 2 * inset == container,
        f"item geometry: {slot} - 2 x {inset} is {slot - 2 * inset}, not the published "
        f"container height {container}",
    )
    report.check(
        item.get("invariant") == "slot_height - 2 * inset_block == container_height",
        "item.invariant must state the arithmetic this check enforces, in those names",
    )
    report.check(
        table.number("md.comp.menus.menu-item.height") == container,
        "item.container_height must stay the published token value",
    )

    # The slot and the insets are read off a picture. If that ever stops being
    # said out loud, the next reader takes them for tokens.
    source = str(item.get("geometry_source", ""))
    report.check(
        "Figma" in source and "image" in source,
        "item.geometry_source must keep naming the measurements image and the Figma check",
    )
    report.check(
        slot >= 48,
        "the item slot is the accessibility target and cannot fall below 48dp",
    )

    # Every number under `item` is either backed by a token it names, or listed
    # as one of the figures no token backs. Without this, an inferred value can
    # be added later and sit beside published ones reading exactly like them.
    declared = set(item.get("unpublished", []))
    report.check(
        declared == {"slot_height", "inset_block", "inset_inline"},
        f"item.unpublished must name the three measured figures; found {sorted(declared)}",
    )
    for key, value in item.items():
        if isinstance(value, bool) or not isinstance(value, (int, float)):
            continue
        if key in declared:
            report.check(
                f"{key}_token" not in item,
                f"item.{key} is listed as unpublished but names a token",
            )
        else:
            report.check(
                f"{key}_token" in item,
                f"item.{key} is a bare number with no token and is not listed in "
                "item.unpublished -- say where it came from",
            )


def check_variants(report: Report, data: dict, table: Table) -> None:
    variants = data["variants"]
    report.check(
        {"vertical", "baseline", "horizontal"} == set(variants),
        f"menu.yml must publish all three rows; found {sorted(variants)}",
    )
    report.check(
        variants["vertical"]["status"] == "implemented",
        "the vertical row is the one this project builds",
    )

    baseline = variants["baseline"]
    live = [
        token
        for token in table.entries
        if token.startswith("md.comp.menu.") and token not in table.deprecated
    ]
    report.check(
        sorted(live) == sorted(baseline["live_tokens"]),
        "baseline.live_tokens must be exactly the non-deprecated `md.comp.menu.*` set; "
        f"table has {sorted(live)}",
    )
    report.check(
        baseline["live_token_count"] == len(live),
        f"baseline.live_token_count says {baseline['live_token_count']}, table has {len(live)}",
    )
    report.check(
        baseline["deprecated"] is False,
        "baseline is available, not deprecated -- the record must not overstate the reason",
    )
    # The argument for not building it is that its items are gone. If M3 ever
    # republishes them, this stops being true and the decision reopens.
    items = [t for t in table.deprecated if t.startswith("md.comp.menu.list-item.")]
    report.check(
        len(items) > 0 and not any(
            t.startswith("md.comp.menu.list-item.") for t in table.entries
        ),
        "the baseline decision rests on every `md.comp.menu.list-item.*` being deprecated; "
        "the table no longer says that",
    )

    horizontal = [t for t in table.entries if t.startswith("md.comp.menus.horizontal.")]
    report.check(
        variants["horizontal"]["token_count"] == len(horizontal),
        f"horizontal.token_count says {variants['horizontal']['token_count']}, "
        f"table has {len(horizontal)}",
    )
    report.check(
        variants["horizontal"]["status"] == "published_without_prose",
        "horizontal is recorded as published and unexplained, not as deferred work",
    )


def check_structure(report: Report, data: dict) -> None:
    """Menu's values against List's, read from both files.

    A one-sided pin goes quiet when the other side moves, which is how a
    `keeps` check stopped working earlier in this project. Both numbers come
    from their own record here.
    """
    lists = yaml.safe_load(LIST_DATA.read_text(encoding="utf-8"))
    divergence = {entry["property"]: entry for entry in data["structure"]["divergence"]}

    report.check(
        {"item height", "label text", "supporting text"} == set(divergence),
        f"structure.divergence must compare all three measured properties; found {sorted(divergence)}",
    )

    menu_height = data["item"]["container_height"]
    list_height = lists["measurements"]["one_line"]["minimum_height"]
    report.check(
        str(menu_height) == str(divergence["item height"]["menu"]),
        "structure.divergence records a menu item height that menu.yml does not publish",
    )
    report.check(
        str(list_height) in str(divergence["item height"]["list"]),
        f"structure.divergence must keep list.yml's one-line height ({list_height}) in view",
    )
    report.check(
        menu_height != list_height,
        "the whole divergence claim collapses if the two heights ever agree",
    )

    report.check(
        data["item"]["label_text_typography_role"] == divergence["label text"]["menu"],
        "structure.divergence records a menu label typescale that menu.yml does not publish",
    )
    report.check(
        data["item"]["supporting_text_typography_role"]
        == divergence["supporting text"]["menu"],
        "structure.divergence records a menu supporting typescale that menu.yml does not publish",
    )


def check_host(report: Report, data: dict) -> None:
    host = data["host"]
    report.check(host["element"] == "popover", "the menu host is a popover")
    report.check(host["not"] == "dialog", "the record must keep saying what the host is not")

    # The precedent is cited as working code, so the check reads that code
    # rather than the sentence this file wrote about it.
    source = TOOLTIP.read_text(encoding="utf-8")
    report.check(
        "popover = 'manual'" in source,
        f"{TOOLTIP.name} no longer sets `popover = 'manual'`; the cited precedent has moved",
    )
    report.check(
        "hidePopover()" in source and "showPopover()" in source,
        f"{TOOLTIP.name} no longer leaves and rejoins the top layer; the ordering note "
        "this record relies on has moved",
    )


def check_grouping(report: Report, data: dict, table: Table) -> None:
    gap = data["grouping"]["gap"]
    report.check(
        gap["status"] == "not_implemented_on_web",
        "gap is recorded as published and unavailable on web",
    )
    # Both halves of the discrepancy, so it cannot go stale from either side.
    report.check(
        "md.comp.menus.gap" in table.entries,
        "the gap discrepancy only exists while the token is live; the table has dropped it",
    )
    report.check(
        any(entry["subject"] == "gap" for entry in data["discrepancies"]),
        "menu.yml must keep recording that a published token is not a published platform",
    )
    report.check(
        len(data["mutual_exclusions"]) >= 3,
        "the scroll constraint is recorded from three independent sources",
    )


def main() -> int:
    report = Report()
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    table = Table(RAW.read_text(encoding="utf-8"))

    meta = data["meta"]
    report.check(
        meta["included_token_count"] == table.included_count,
        f"meta.included_token_count says {meta['included_token_count']}, "
        f"table reports {table.included_count}",
    )
    report.check(
        meta["deprecated_token_count"] == table.deprecated_count,
        f"meta.deprecated_token_count says {meta['deprecated_token_count']}, "
        f"table reports {table.deprecated_count}",
    )
    report.check(
        meta["raw_evidence"] == RAW.relative_to(ROOT).as_posix(),
        "meta.raw_evidence must name the table this check reads",
    )

    sweep_tokens(report, data, table)
    check_item_geometry(report, data, table)
    check_variants(report, data, table)
    check_structure(report, data)
    check_host(report, data)
    check_grouping(report, data, table)

    print(f"menu: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
