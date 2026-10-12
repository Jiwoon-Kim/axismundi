#!/usr/bin/env python3
"""Check the implemented vertical Menu against its M3 record and token table.

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
FRONTEND = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
MENU = FRONTEND / "components/menus/menu.js"
MENU_CSS = FRONTEND / "styles/components/menu.css"
STYLE_INDEX = FRONTEND / "styles/index.css"
STYLEBOOK_INDEX = FRONTEND / "pages/stylebook/index.js"
STYLEBOOK_PAGE = FRONTEND / "pages/stylebook/components/menus/index.js"
ROUTE = ROOT / "products/wordpress/plugins/axismundi/includes/route.php"
APP = FRONTEND / "app.js"

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
        "Measurements image" in source and "55141-14250" in source,
        "item.geometry_source must keep citing the measurements image and the kit node it "
        "was confirmed against",
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
        declared == {
            "slot_height",
            "inset_block",
            "inset_inline",
            "state_layer_padding_block",
            "state_layer_padding_inline",
            "state_layer_between_space",
            "content_inline_size",
            "content_block_size_one_line",
            "content_block_size_with_supporting_text",
        },
        f"item.unpublished must name every measured figure; found {sorted(declared)}",
    )

    # The horizontal run, reconstructed end to end. Every figure in it is
    # measured except the two icon sizes, which are tokens, so this is the one
    # check that tests them together -- and it is what settles the kit's 8dp
    # gap against the table's 12dp. At 12 the text block would be 112.
    container_width = data["container"]["inline_size_floor"]
    remaining = (
        container_width
        - 2 * item["inset_inline"]
        - 2 * item["state_layer_padding_inline"]
        - item["leading_icon_size"]
        - item["trailing_icon_size"]
        - 2 * item["state_layer_between_space"]
    )
    report.check(
        remaining == item["content_inline_size"],
        f"the horizontal run leaves {remaining}dp for the text block, but the kit "
        f"measures {item['content_inline_size']}",
    )
    # The two line boxes the text block hugs, against the typescales they come
    # from. 20 is label-large's line height and 36 adds body-small's 16.
    report.check(
        item["content_block_size_with_supporting_text"]
        - item["content_block_size_one_line"]
        == 16,
        "the supporting-text line adds body-small's 16dp line box, or the two measured "
        "text-block heights no longer describe the typescales this record publishes",
    )

    # The one place a measured inset and a published token have to meet. If the
    # kit is ever re-read differently, this is what catches it.
    report.check(
        item["inset_inline"] + item["state_layer_padding_inline"] == item["leading_space"],
        f"{item['inset_inline']} + {item['state_layer_padding_inline']} is not the published "
        f"leading space {item['leading_space']}",
    )
    report.check(
        item.get("leading_space_composition")
        == "inset_inline + state_layer_padding_inline == leading_space",
        "item.leading_space_composition must state the arithmetic this check enforces",
    )
    # The painted box has to hold the label's line, which is what makes the
    # measured 12 believable and the published 8 not -- for a single-line item.
    report.check(
        container - 2 * item["state_layer_padding_block"] == 20,
        f"the painted box leaves {container - 2 * item['state_layer_padding_block']}dp for a "
        "label-large line box, which is 20",
    )
    report.check(
        item["state_layer_padding_block"] != item["top_space"],
        "the two vertical paddings are recorded as disagreeing; if they ever agree, "
        "the discrepancy entry should go rather than sit there saying nothing",
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


def check_policy(report: Report, data: dict) -> None:
    """The decisions made where nothing is published, and the shape of them."""
    policy = data["project_policy"]

    height = policy["maximum_height"]
    report.check(
        height["status"] == "viewport_bound",
        "the height limit is the window, which is the only limit M3 states",
    )
    cap = height["item_count_cap"]
    report.check(
        cap["status"] == "not_adopted",
        "an item-count cap is a number this project would be inventing; it stays unadopted",
    )
    report.check(
        cap["expressed_as"] == "container.block_size_formula"
        and "block_size_formula" in data["container"],
        "the cap must stay expressed in the verified height formula rather than a raw number",
    )
    # Every instance the kit was read at, reconstructed rather than restated, so
    # a changed slot height, label height or group padding breaks them. The
    # label rows are what generalised the formula past its first two instances.
    slot = data["item"]["slot_height"]
    padding = data["grouping"]["gap"]["group_padding"]
    label = data["section_label"]["height"]
    gap = data["grouping"]["gap"]["gap"]

    def group(items: int, labels: int = 0) -> int:
        return 2 * padding + items * slot + labels * label

    for items, labels, measured in ((4, 0, 196), (4, 1, 228), (6, 0, 292), (6, 1, 324)):
        report.check(
            group(items, labels) == measured,
            f"the height formula gives {group(items, labels)} for {items} items and "
            f"{labels} label(s), not the measured {measured}",
        )
    for groups, measured in ((2, 394), (3, 592)):
        total = groups * group(4) + (groups - 1) * gap
        report.check(
            total == measured,
            f"{groups} groups of four items give {total}, not the measured {measured}",
        )

    width = policy["inline_size"]
    report.check(
        width["status"] == "floor_from_measurement",
        "208 is one kit's instance and is taken as a floor, not as the width",
    )
    report.check(
        data["container"]["inline_size_floor"] == 208,
        "the recorded floor must be the measured one",
    )
    # The sibling block disagrees, and that is the point of recording it. If the
    # two ever line up, the entry should say so rather than keep claiming a
    # difference.
    sibling = width["sibling_precedent"]
    report.check(
        sibling["disagrees"] is True
        and sibling["minimum"] != data["container"]["inline_size_floor"],
        "the sibling precedent is recorded as disagreeing; its floor now matches ours",
    )
    report.check(
        Path(ROOT / sibling["where"]).exists(),
        f"the sibling precedent names a file that is not there: {sibling['where']}",
    )
    report.check(
        width["window_clamp"] is True,
        "the width is clamped to the window, as the height is",
    )
    # Both unpublished entries have to keep pointing at the policy that answers
    # them, or the blanks read as still-blank and get filled twice.
    for key in ("container_inline_size", "container_block_size"):
        report.check(
            "project_policy" in str(data["not_published"][key].get("note", "")),
            f"not_published.{key} must say which policy answers it",
        )


def check_design_kit(report: Report, data: dict) -> None:
    """The kit's own coordinates, walked the way the kit lays them out.

    The arithmetic check elsewhere subtracts everything from the container and
    compares one number. This walks the run instead, so a figure that is wrong
    in a way that cancels out still fails here.
    """
    kit = data["design_kit"]
    run = kit["horizontal_run"]
    item = data["item"]

    report.check(
        run["state_layer_inline_size"]
        == data["container"]["inline_size_floor"] - 2 * item["inset_inline"],
        "the painted box is the container less its two insets",
    )
    report.check(
        run["leading_at"] == item["state_layer_padding_inline"],
        "the leading element starts at the painted box's own padding",
    )
    report.check(
        run["content_at"]
        == run["leading_at"] + item["leading_icon_size"] + item["state_layer_between_space"],
        "the text block starts one icon and one gap after the leading element",
    )
    report.check(
        run["content_ends"] == run["content_at"] + item["content_inline_size"],
        "the text block ends where its measured width puts it",
    )
    report.check(
        run["trailing_at"] == run["content_ends"] + item["state_layer_between_space"],
        "the trailing element starts one gap after the text block",
    )
    report.check(
        run["trailing_ends"] == run["trailing_at"] + item["trailing_icon_size"],
        "the trailing element is one icon wide",
    )
    report.check(
        run["state_layer_inline_size"] - run["trailing_ends"]
        == item["state_layer_padding_inline"],
        "the run ends on the painted box's trailing padding",
    )
    # With no leading element the text block takes the space the icon and its
    # gap were using, which is a second way of asserting both.
    report.check(
        run["content_at_without_leading"] == item["state_layer_padding_inline"]
        and run["content_inline_size_without_leading"]
        == run["state_layer_inline_size"] - 2 * item["state_layer_padding_inline"],
        "without a leading element the text block spans the box less its padding",
    )

    # Density is measured, and it belongs to the row this project does not
    # build. Both halves have to keep being true together.
    density = kit["density"]
    report.check(
        density["status"] == "baseline_row_only"
        and data["variants"]["baseline"]["status"] == "not_implemented",
        "the measured density step belongs to the baseline row, which is not built",
    )
    for entry in density["measured"]:
        heights = entry["heights"]
        steps = [heights[i] - heights[i + 1] for i in range(len(heights) - 1)]
        report.check(
            len(set(steps)) == 1,
            f"{entry['subject']}: the density steps are {steps}, which are not one step",
        )
    report.check(
        kit["baseline_leading_icon_size"] != data["item"]["leading_icon_size"],
        "the baseline and expressive icon sizes are recorded as differing; if they ever "
        "agree the corroboration note says nothing",
    )
    report.check(
        kit["vibrant"]["status"] == "fully_drawn"
        and data["colour_styles"]["vibrant"]["status"] == "deferred",
        "vibrant is drawn in full and deferred by choice; the record must keep saying both",
    )


def check_group(report: Report, data: dict, table: Table) -> None:
    """The group axis: what the sources settled, and what is still the owner's.

    No pin touches the adapter. The rows are still flat, and a check that failed
    on that would turn the gate red while the shape is undecided.
    """
    group = data["group"]
    report.check(
        group["status"] == "axis_settled_composition",
        "the group axis and its composition API must both be recorded as settled",
    )
    report.check(
        len(group["evidence"]) >= 4
        and all( e.get("source") and e.get("says") for e in group["evidence"] ),
        "every source for the group being a level stays recorded with what it said",
    )

    # The two things the group owns that the table publishes. Read through, so
    # a republished value moves the record rather than drifting from it.
    owns = group["owns"]
    report.check(
        table.number("md.comp.menus.group.padding") == owns["padding_block"]["value"]
        and owns["padding_block"]["value"] == data["grouping"]["gap"]["group_padding"],
        "the group's padding must be the published one, and the same figure the CSS consumes",
    )
    report.check(
        (table.reference("md.comp.menus.group.shape") or "").endswith(owns["shape"]["role"])
        and table.number("md.comp.menus.group.shape") == owns["shape"]["value"],
        "the group's shape must be the published one",
    )
    # It is published and it cannot paint here, and both halves have to stay
    # said or the next reader implements a block that never shows.
    report.check(
        "gaps" in owns["shape"]["paints_when"]
        and data["grouping"]["gap"]["status"] == "not_implemented_on_web",
        "the group shape only paints where gaps do, which is not here",
    )

    report.check(
        group["roles"]["separator"] == "separator"
        and "aria-hidden" in group["roles"]["separator_is_not_hidden"],
        "the divider is a separator and the record must keep saying why hiding it is wrong",
    )
    report.check(
        "radiogroup" in group["roles"]["radiogroup_not_used"],
        "the record must keep the reason a radiogroup does not go inside a menu",
    )
    report.check(
        group["scroll_box"]["status"].startswith("the menu list")
        and "mutual_exclusion_scope" in group["scroll_box"]["reason"],
        "the menu list being the scroll box is a consequence of elevation, not an answer to the "
        "exclusion question, and the record must not let the two merge",
    )

    shape = group["api_shape"]
    report.check(
        shape["status"] == "composition" and shape["chosen"] == "composition",
        "the group API must preserve the owner's composition decision",
    )
    report.check(
        {"data", "composition"} == set(shape["options"])
        and "Menu.Group" in shape["implementation"],
        "both API options stay recorded and the chosen composition has a concrete shape",
    )


def check_scrollbar(report: Report, data: dict) -> None:
    """The scrollbar: a published requirement nothing meets, and a house rule
    that was read off three files rather than decided."""
    bar = data["scrollbar"]
    report.check(
        bar["persistent"]["status"] == "published_requirement_unmet",
        "M3 asks a scrolling menu for a persistent scrollbar and nothing here provides one; "
        "the record must keep saying so rather than letting the gap close quietly",
    )
    report.check(
        bool(bar["persistent"]["do_not_guess"]),
        "a requirement recorded as unmet has to say why it is not being guessed at",
    )
    # The convention is a reading of other files. If one stops doing it, the
    # reading is no longer true and the record should not keep claiming it.
    for entry in bar["house_convention"]["measured_at"]:
        path = ROOT / entry["where"]
        report.check(path.exists(), f"the convention cites a file that is not there: {entry['where']}")
        if path.exists():
            report.check(
                "scrollbar" in path.read_text(encoding="utf-8"),
                f"{entry['where']} no longer styles a scrollbar, so the house convention "
                "rests on one fewer place than it claims",
            )
    report.check(
        len(bar["house_convention"]["measured_at"]) >= 3,
        "the convention is a reading of several places, not one",
    )


def check_states(report: Report, data: dict) -> None:
    """The states record, and the one claim in it that reads a file.

    No pin here touches the adapter yet: the three state layers are still
    outstanding work, and a check that fails on them would turn the style-guide
    gate red for unrelated changes. These hold the record itself, and the
    sibling precedent it points at, until that work lands.
    """
    states = data["states"]
    report.check(
        {"enabled", "hovered", "focused", "pressed", "disabled", "active"}
        == set(states["published_layers"]),
        f"the record must carry all six published states; found "
        f"{sorted(states['published_layers'])}",
    )
    report.check(
        states["active_state_blocked_on_submenu"]["status"] == "not_implemented"
        and data["submenu"]["status"] == "deferred",
        "the sixth state and the submenu deferral stand or fall together",
    )

    # The precedent is cited as working code, so this reads the code. All three
    # opacities, or the claim that it solved this is not true any more.
    precedent = ROOT / states["sibling_precedent"]["where"]
    report.check(precedent.exists(), f"the sibling precedent is missing: {precedent}")
    if precedent.exists():
        css = precedent.read_text(encoding="utf-8")
        # Not "the file mentions the three opacities" -- measured, every
        # stylesheet in this theme mentions them, so that pin was bound to the
        # word rather than the claim. What makes this file a precedent is that
        # it applies them to the rows of a submenu popover, so that is what is
        # read: declarations inside a rule whose selector names one.
        rows = [
            block
            for block in re.split(r"(?<=\})", css)
            if "submenu-container" in block.split("{")[0]
        ]
        for state in ("hover", "focus", "pressed"):
            report.check(
                any(f"--md-sys-state-{state}-state-layer-opacity" in block for block in rows),
                f"the cited precedent no longer applies a {state} state layer to a menu row",
            )

    # Measured, and recorded so the states figure is not read onto a vertical
    # row: every per-state item shape in the table is a horizontal one.
    raw = RAW.read_text(encoding="utf-8")
    vertical = [
        line
        for line in raw.splitlines()
        if "md.comp.menus.menu-item." in line
        and "SHAPE" in line
        and any(k in line for k in ("hovered", "focused", "pressed", "active"))
    ]
    report.check(
        not vertical,
        f"the table now publishes a per-state shape for the vertical row: {vertical}",
    )


def check_submenu(report: Report, data: dict) -> None:
    """The placement answer, and the sources that make it one."""
    placement = data["submenu"]["placement"]

    inline = placement["inline"]
    report.check(
        inline["offset"] == 0,
        "the four agreeing sources all put the submenu flush against the parent, not offset",
    )
    report.check(
        len(inline["agreed_by"]) >= 4,
        f"the inline answer rests on agreement; {len(inline['agreed_by'])} sources is not it",
    )
    # Each source has to say which it is. An agreement list whose entries lose
    # their attribution is a claim with no evidence behind it.
    for entry in inline["agreed_by"]:
        report.check(
            bool(entry.get("source")) and bool(entry.get("says")),
            f"an agreeing source is recorded without its claim: {entry}",
        )

    block = placement["block"]
    report.check(
        block["derivation"] == "offset == -group_padding_block",
        "the block offset stays a derivation from this record's geometry",
    )
    report.check(
        block["status"] == "local_policy_pending_runtime_measurement",
        "the block offset is local policy until an adapter measures it; the record must "
        "not promote it to a settled figure without that measurement",
    )
    report.check(
        block["recorded_as"] == "local policy, pending measurement",
        "the block offset must keep saying what it is",
    )
    # The claim this entry was downgraded for. Both halves of the unresolved
    # question have to stay written down, or the policy reads as settled again.
    question = str(block.get("open_question", ""))
    report.check(
        "anchor-corner" in question and "item" in question and "container" in question,
        "the open question must keep naming both readings of Material Web's anchor",
    )
    report.check(
        any("Border width" in str(entry) for entry in block.get("not_evidence", [])),
        "core's offset must stay recorded as not being evidence for this one",
    )
    report.check(
        bool(block.get("settle_by")),
        "a pending policy has to say what would settle it",
    )
    # The derivation, recomputed. Aligning the two painted boxes leaves exactly
    # the group padding, so if either inset ever moves this stops holding.
    padding = data["grouping"]["gap"]["group_padding"]
    inset = data["item"]["inset_block"]
    parent_painted = inset
    submenu_painted = padding + inset
    report.check(
        parent_painted - submenu_painted == -padding,
        f"aligning the painted boxes leaves {submenu_painted - parent_painted}, not the "
        f"group padding {padding} the derivation names",
    )

    report.check(
        data["submenu"]["hover_bridge"]["status"] == "not_implemented",
        "the hover bridge is a recorded technique, not something this record claims to ship",
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
    # The constraint is inherited from sources whose submenus are in flow. The
    # record has to keep saying that it is open whether it binds here, and both
    # halves of the open question have to stay written down.
    scope = data["mutual_exclusion_scope"]
    report.check(
        scope["status"] == "owner_judgment_open",
        "whether the exclusion binds this implementation is the owner's to settle",
    )
    report.check(
        "top layer" in scope["why_it_may_not_bind"],
        "the record must keep the reason the exclusion may not bind: the host escapes "
        "an ancestor's overflow",
    )
    report.check(
        "group" in scope["why_the_unit_may_be_the_group"] and bool(scope["settle_by"]),
        "the per-group reading and what would settle it both stay recorded",
    )


def declarations_only(css: str) -> str:
    """CSS with its comments removed.

    A check that searches the whole file matches the prose explaining why a
    thing is not there. This one caught its own author: the comment naming the
    theme elevation tokens the frontend must not use failed the check that they
    are not used.
    """
    return re.sub(r"/\*.*?\*/", "", css, flags=re.S)


def check_implementation(report: Report, data: dict) -> None:
    """The shipped adapter consumes the record, not a second number table."""
    menu = MENU.read_text(encoding="utf-8")
    css = declarations_only( MENU_CSS.read_text(encoding="utf-8") )
    style_index = STYLE_INDEX.read_text(encoding="utf-8")
    stylebook_index = STYLEBOOK_INDEX.read_text(encoding="utf-8")
    stylebook_page = STYLEBOOK_PAGE.read_text(encoding="utf-8")
    route = ROUTE.read_text(encoding="utf-8")
    item = data["item"]
    container = data["container"]
    section_label = data["section_label"]

    report.check("popover=\"manual\"" in menu and "showPopover()" in menu and "hidePopover()" in menu,
                 "Menu no longer uses the manual popover host its record requires")
    report.check("role=\"menu\"" in menu and "role=\"menuitem\"" in menu,
                 "Menu no longer exposes list-like menu semantics")
    # The record names the attribute and the family; a row that can be checked
    # is not a plain `menuitem`, and `aria-current` is for a current item in a
    # set of links, not a checked state.
    report.check("aria-checked={ checkable ?" in menu and "aria-current" not in menu,
                 "selected menu rows must carry `aria-checked`, as `menu.yml` selection records")
    modes = data["selection"]["checked_state_role_by_mode"]
    report.check(f"'{modes['multi_select']}' : '{modes['single_select']}'" in menu,
                 "a checkable row's role must follow the recorded mapping from selection mode")
    report.check("MenuGroupContext" in menu and "selectionMode" in menu,
                 "checkability must belong to a Menu.Group rather than to the whole menu")
    report.check("setUncontrolledValue" in menu and "onValueChange?.( next );" in menu,
                 "a checkable group must update its own default selection and expose controlled selection")
    report.check("[role=\"menuitemradio\"], [role=\"menuitemcheckbox\"]" in menu,
                 "keyboard movement must walk the whole menuitem family, or checkable rows drop "
                 "out of it")
    report.check("'data-menu-open': open || undefined" in menu
                 and "ax-menu__trigger[data-menu-open]" in css,
                 "the trigger no longer stays visibly pressed while its menu is open")
    report.check("focusItem( 0 );" in menu and "'Escape' === event.key" in menu,
                 "Menu no longer places focus on its first item and restores its trigger on Escape")
    report.check("aria-disabled={ disabled || undefined }" in menu,
                 "disabled menu items no longer stay in the focus order")
    separator = re.search(r"export function MenuSeparator\(\).*?\n}\n", menu, re.S)
    report.check(separator is not None and "role=\"separator\"" in separator.group(0)
                 and "aria-hidden" not in separator.group(0),
                 "group dividers must be semantic separators rather than hidden rows")
    report.check("pointerdown" in menu and "window.addEventListener( 'scroll'" in menu,
                 "Menu no longer dismisses outside presses or repositions with its trigger")
    report.check("useEffect( () =>" in menu and "if ( ! triggerIsValid )" in menu
                 and menu.index("useEffect( () =>") < menu.index("if ( ! triggerIsValid )"),
                 "Menu conditionally skips a hook when its trigger is invalid")
    # The policy is the space the trigger leaves, so this reads that the limit is
    # computed from the trigger's box. "A max-block-size is set somewhere" would
    # pass with the whole window back in it, which is the thing it forbids.
    report.check(
        "menu.style.maxBlockSize" in menu
        and "window.innerHeight - target.bottom" in menu
        and "target.top - EDGE" in menu,
        "the height limit must be the space the trigger leaves above and below it, which is "
        "what `project_policy.maximum_height` records",
    )

    # Elevation is the frontend's own primitive. The theme does not define
    # `--md-sys-elevation-level*` at all, so a stylesheet reaching for one paints
    # its fallback instead -- measured, before this check existed.
    report.check(f"<Elevation level={{ {container['elevation_level']} }} />" in menu,
                 "Menu must take its elevation from the frontend primitive at the recorded level")
    report.check("--md-sys-elevation-level" not in css and "--md-sys-elevation-shadow-level" not in css,
                 "Menu CSS reaches for a theme elevation token, which "
                 "DECISION-FRONTEND-ELEVATION-OWNERSHIP.md keeps out of the frontend")
    report.check("box-shadow" not in css.split(".ax-menu__trigger")[0],
                 "the menu container draws its own shadow instead of letting the elevation layer do it")
    # Twice now in this repository: an author `display` beats the UA rule that
    # hides a closed popover or dialog, and every one on the page paints.
    report.check(
        "display:" in css.split(".ax-menu {")[1].split("}")[0]
        and ".ax-menu:not( :popover-open )" in css,
        "the menu sets a `display` without saying when it is not displayed, so closed menus paint",
    )

    # The elevation layer cannot live inside the box that scrolls.
    report.check("overflow-y: auto;" in css.split(".ax-menu__list")[-1]
                 and "overflow: visible;" in css.split(".ax-menu__list")[0],
                 "the scrolling box and the elevated host were collapsed back into one element")

    slots = {
        "container shape": f"--md-comp-menus-container-shape: var( --md-sys-shape-corner-value-{data['container']['shape_role'].split('.')[-1]} );",
        "group padding": f"--md-comp-menus-group-padding: {data['grouping']['gap']['group_padding']}px;",
        "menu floor": f"--md-comp-menus-menu-min-inline-size: {container['inline_size_floor']}px;",
        "slot height": f"--md-comp-menus-item-slot-height: {item['slot_height']}px;",
        "painted height": f"--md-comp-menus-item-container-height: {item['container_height']}px;",
        "block inset": f"--md-comp-menus-item-inset-block: {item['inset_block']}px;",
        "inline inset": f"--md-comp-menus-item-inset-inline: {item['inset_inline']}px;",
        "state inline padding": f"--md-comp-menus-item-state-padding-inline: {item['state_layer_padding_inline']}px;",
        "state block padding": f"--md-comp-menus-item-state-padding-block: {item['state_layer_padding_block']}px;",
        "kit between space": f"--md-comp-menus-item-between-space: {item['state_layer_between_space']}px;",
        "icon size": f"--md-comp-menus-item-icon-size: {item['leading_icon_size']}px;",
        "edge shape": f"--md-comp-menus-item-edge-shape: var( --md-sys-shape-corner-value-{item['first_child_shape_role'].split('.')[-1]} );",
        "section-label height": f"--md-comp-menus-section-label-height: {section_label['height']}px;",
        "section-label outer inset": f"--md-comp-menus-section-label-outer-padding-inline: {section_label['padding_inline']}px;",
        "section-label outer block inset": f"--md-comp-menus-section-label-outer-padding-block: {section_label['padding_block']}px;",
        "section-label content inline inset": f"--md-comp-menus-section-label-content-padding-inline: {section_label['content_padding_inline']}px;",
        "section-label content start inset": f"--md-comp-menus-section-label-content-padding-block-start: {section_label['content_padding_block_start']}px;",
        "section-label content end inset": f"--md-comp-menus-section-label-content-padding-block-end: {section_label['content_padding_block_end']}px;",
        "section-label content gap": f"--md-comp-menus-section-label-content-gap: {section_label['content_gap']}px;",
        "section-label icon": f"--md-comp-menus-section-label-icon-size: {section_label['leading_icon_size']}px;",
    }
    for name, declaration in slots.items():
        report.check(declaration in css, f"Menu CSS no longer consumes the recorded {name}: {declaration}")

    report.check(
        section_label["content_padding_block_start"] == 6
        and section_label["content_padding_block_end"] == 0,
        "the section-label no longer preserves the Figma-measured 6dp top and 0dp bottom padding",
    )
    report.check("labelIcon" in menu and "ax-menu__group-label-content" in menu,
                 "Menu.Group no longer renders the measured label content layer and optional icon")
    report.check("labelDivider" in menu and "ax-menu__group-label-divider" in menu
                 and "ax-menu__group-label-divider" in css,
                 "Menu.Group no longer exposes the label's optional divider slot")

    report.check("min-block-size: var( --md-comp-menus-item-slot-height );" in css
                 and "min-block-size: var( --md-comp-menus-item-container-height );" in css,
                 "the 48dp target and 44dp painted box were collapsed")
    report.check("padding-block: var( --md-comp-menus-item-inset-block );" in css
                 and "padding-inline: var( --md-comp-menus-item-inset-inline );" in css,
                 "the measured slot insets no longer surround the painted box")
    report.check(":first-of-type .ax-menu__item-container" in css
                 and ":last-of-type .ax-menu__item-container" in css,
                 "first and last rows no longer receive their published edge shapes")
    for state in ("hover", "focus", "pressed"):
        report.check(
            f"--md-sys-state-{state}-state-layer-opacity" in css,
            f"Menu CSS no longer consumes the published {state} state-layer opacity",
        )
    report.check("data-selected]:not" in css and "on-tertiary-container" in css,
                 "selected rows no longer retain hover, focus and pressed state layers")
    report.check("@import url( './components/menu.css' );" in style_index,
                 "Menu stylesheet is not in the frontend bundle")
    report.check("StylebookMenusPage" in stylebook_index and "'menus' === component" in stylebook_index,
                 "stylebook does not expose Menus")
    report.check("<Menu.Group" in stylebook_page and "<Menu.Item" in stylebook_page
                 and "<Menu.Separator" in stylebook_page,
                 "stylebook no longer demonstrates the composed group API")
    # A name is not a long menu. The sample has to be materially longer than the
    # others, which is a comparison rather than a number this file invents --
    # measured at the time of writing: fourteen rows against four.
    long_block = stylebook_page.split("LONG_ACTIONS = [")[-1].split("];")[0]
    long_rows = long_block.count("',")
    composed_rows = stylebook_page.count("<Menu.Item")
    report.check(
        long_rows > composed_rows,
        f"the overflow sample has {long_rows} rows against {composed_rows} written out one by "
        "one; it is no longer the long one and demonstrates nothing",
    )
    # Four places declare a stylebook route and the app's own regex is one of
    # them. Leaving it out does not fail any PHP check and does not fail a
    # build; the page simply falls back to the feed. Measured, that way.
    app = APP.read_text(encoding="utf-8")
    for label, text, expected in (
        ("rewrite rule and public-route guard", route, 2),
        ("app route", app, 1),
    ):
        report.check(
            text.count("app-bars|surfaces|menus") == expected,
            f"the {label} does not carry Menus in its component alternation",
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
    check_policy(report, data)
    check_design_kit(report, data)
    check_group(report, data, table)
    check_scrollbar(report, data)
    check_states(report, data)
    check_submenu(report, data)
    check_implementation(report, data)

    print(f"menu: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
