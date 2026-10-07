#!/usr/bin/env python3
"""Check the Carousel source contract before an implementation exists."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any

import yaml

ROOT = Path(__file__).resolve().parent.parent.parent
DATA = ROOT / "products/styleguide/_data/carousel.yml"
TABLE = ROOT / (
    "products/wordpress/plugins/axismundi/docs/m3-token-tables/"
    "SOURCE-M3-CAROUSEL-RAW.md"
)
HOME_PLAN = ROOT / "products/wordpress/plugins/axismundi/docs/PLAN-FRONTEND-HOME.md"
SCROLL_DECISION = ROOT / (
    "products/wordpress/plugins/axismundi/docs/"
    "DECISION-FRONTEND-SCROLL-OWNERSHIP.md"
)

TOKEN = re.compile(r"md\.[a-z0-9.\-]+")
ROW = re.compile(r"^  (md\.[a-z0-9.\-]+)\s+[A-Z_]+\s+(.+?)\s*$")


def rows(text: str) -> dict[str, str]:
    return {
        match.group(1): match.group(2)
        for line in text.splitlines()
        if (match := ROW.match(line))
    }


def strings(value: Any) -> list[str]:
    if isinstance(value, str):
        return [value]
    if isinstance(value, dict):
        return [item for child in value.values() for item in strings(child)]
    if isinstance(value, list):
        return [item for child in value for item in strings(child)]
    return []


def main() -> int:
    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    source = TABLE.read_text(encoding="utf-8")
    home = HOME_PLAN.read_text(encoding="utf-8")
    scroll = SCROLL_DECISION.read_text(encoding="utf-8")
    token_rows = rows(source)
    live_source, _, skipped_source = source.partition("skipped (deprecated):")
    live_component_tokens = set(
        re.findall(r"md\.comp\.carousel-item[.a-z-]+", live_source)
    )
    skipped_component_tokens = set(
        re.findall(r"md\.comp\.carousel-item[.a-z-]+", skipped_source)
    )
    skipped_system_tokens = set(
        re.findall(r"md\.sys\.[.a-z-]+", skipped_source)
    )
    recorded_tokens = {
        token
        for value in strings(data)
        for token in TOKEN.findall(value)
        if token.startswith("md.comp.carousel-item")
        and token != "md.comp.carousel-item"
    }
    failures: list[str] = []
    checks = 0

    def check(condition: bool, failure: str) -> None:
        nonlocal checks
        checks += 1
        if not condition:
            failures.append(failure)

    def row_is(token: str, value: str) -> None:
        check(
            token_rows.get(token) == "-> " + value,
            f"{token} no longer resolves to {value}",
        )

    meta = data["meta"]
    check(meta["table_revision"] in source, "recorded table revision is absent")
    check(meta["namespace"] == "md.comp.carousel-item", "namespace drifted")
    check(len(live_component_tokens) == 26, "live component token count is not 26")
    check(len(skipped_component_tokens) == 1, "deprecated component token count is not 1")
    check(
        "md.sys.color.surface-tint" in skipped_system_tokens,
        "the related deprecated surface-tint system row is no longer recorded",
    )
    check(meta["live_component_token_count"] == 26, "yml live count is not 26")
    check(
        meta["deprecated_component_token_count"] == 1,
        "yml deprecated component count is not 1",
    )
    check(
        meta["skipped_related_system_token_count"] == 1,
        "yml skipped related system count is not 1",
    )
    check(
        live_component_tokens <= recorded_tokens,
        "one or more live carousel-item tokens are absent from carousel.yml",
    )
    check(
        recorded_tokens == live_component_tokens | skipped_component_tokens,
        "carousel.yml token references no longer equal the captured component rows",
    )

    item = data["item"]
    row_is(item["container_color_token"], "md.sys.color." + item["container_color_role"])
    row_is(
        item["container_shape_token"],
        "md.sys.shape." + item["container_shape_role"],
    )
    row_is(item["container_shadow_color_token"], "md.sys.color.shadow")
    check(
        item["container_shape_resolved"] == 28,
        "resolved extra-large item shape is no longer 28dp",
    )
    check(
        item["widths"]["small"] == {
            "dynamic": True,
            "minimum": 40,
            "maximum": 56,
        },
        "small item width range no longer records 40-56dp dynamic",
    )

    layouts = data["layouts"]
    check(
        set(layouts) == {"multi-browse", "uncontained", "hero", "full-screen"},
        "the four layout strategies changed",
    )
    uncontained = layouts["uncontained"]
    check(uncontained["item_width"] == "unresolved", "Uncontained width was decided")
    check(
        uncontained["item_width_published"] is False,
        "Uncontained width is presented as published",
    )
    check(
        uncontained["padding_inline_trailing"] == 0
        and uncontained["padding_inline_trailing_published_here"] is False
        and "Inferred" in uncontained["padding_inline_trailing_provenance"],
        "Uncontained trailing zero lost its inference provenance",
    )
    check(
        layouts["hero"]["minimum_items_by_alignment"]["center"]
        == {"large": 1, "small": 2},
        "center-aligned Hero no longer adds the second small preview",
    )
    check(
        layouts["full-screen"]["required_scroll_behavior"] == "snap",
        "full-screen no longer requires snap scrolling",
    )

    expected_forbidden = [
        {"layout": "full-screen", "scroll_behavior": "default"},
        {"layout": "multi-browse", "scroll_behavior": "default"},
        {"layout": "hero", "scroll_behavior": "default"},
        {
            "layout": "full-screen",
            "window_size": "compact-or-medium",
            "orientation": "landscape",
        },
        {"configuration": "multi-aspect", "layout": "not-uncontained"},
        {
            "window_size": "compact",
            "item_has_text": True,
            "visible_item_count": "greater-than-3",
        },
    ]
    forbidden = data["constraints"]
    check(len(forbidden) == 6, "forbidden combination count is not 6")
    for combination in expected_forbidden:
        check(
            any(
                entry.get("combination") == combination
                and entry.get("allowed") is False
                for entry in forbidden
            ),
            f"forbidden combination is missing: {combination}",
        )

    reduced = data["behavior"]["reduced_motion"]
    check(reduced["all_items_same_size"] is True, "reduced motion is not uniform")
    check(
        reduced["geometry_path"] == "uniform-uncontained",
        "reduced-motion geometry is detached from Uncontained",
    )

    accessibility = data["accessibility"]
    container = accessibility["container"]
    check(container["published_role_name"] == "container", "published role wording changed")
    check(container["web_role"] == "unresolved", "web role was silently decided")
    check(
        container["web_role"] not in {"container", "region", "group"},
        "web role was silently mapped to a guessed ARIA role",
    )
    check(
        accessibility["skip_over_items"]
        == {"required": True, "mechanism": "unresolved-with-web-role"},
        "skip requirement is no longer tied to the unresolved web-role mechanism",
    )
    check(
        "Cross-component rule" in accessibility["items"]["nested_controls_source"]
        and "Card accessibility" in accessibility["items"]["nested_controls_source"],
        "nested-control prohibition lost its cross-component source",
    )
    alternative = accessibility["horizontal_scroll_alternative"]
    check(
        alternative["required_on_vertically_scrolling_pages"] is True,
        "horizontal-scroll alternative is no longer required",
    )
    check(
        alternative["exception_layouts"] == ["full-screen"],
        "full-screen is no longer the only Show all exception",
    )
    check(
        alternative["with_header"]["icon_button_size"] == 48,
        "header arrow is no longer 48dp",
    )
    check(
        alternative["without_header"]["padding"] == 4,
        "Show all button padding is no longer 4dp",
    )

    # THE TABLE IS THE RECORD, NOT THE SENTENCE. An earlier version pinned the
    # Korean sentence that states the count, including its markdown bold. That
    # fires on a reword that changes nothing, and the sentence is not where the
    # fact lives: the section table is. Four sections carry a Carousel; sections
    # 3 and 5 are a Card collection and a List, and saying so is the correction
    # this plan exists to carry.
    section_rows = [line for line in home.splitlines() if line.startswith("| Section ")]
    check(6 == len(section_rows), f"the Home plan lists {len(section_rows)} sections, not six")
    check(
        4 == sum(1 for row in section_rows if '| 있음 |' in row),
        "the Home plan no longer counts four Carousel sections",
    )
    for number in ("3", "5"):
        row = next(r for r in section_rows if r.startswith(f"| Section {number} "))
        check(
            '없음' in row,
            f"Home section {number} is no longer recorded as carrying no Carousel",
        )

    # Losing a step from the order is a real regression, so these stay -- but
    # matched on the distinctive phrase rather than on the code block's spacing.
    for marker in (
        "Section 3: existing non-actionable Card + grid",
        "Section 5: ListItem",
        "ActionableCard",
    ):
        check(marker in home, f"Home implementation order lost: {marker}")

    # The Show all obligation is owned by the scroll decision, pinned below. A
    # loose word pair here passed on coincidence, so it is gone.
    check(
        "### 3-5. Carousel의 비수평 전체 목록 경로" in scroll
        and "선택적 enhancement가 아니라 필수 조합" in scroll,
        "scroll decision no longer makes the Carousel alternative mandatory",
    )

    if failures:
        print(f"carousel: {checks} checks, {len(failures)} failed")
        for failure in failures:
            print(" - " + failure)
        return 1

    print(f"carousel: {checks} checks, 0 failed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
