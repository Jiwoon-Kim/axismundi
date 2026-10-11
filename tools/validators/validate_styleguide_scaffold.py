#!/usr/bin/env python3
"""Pin the document-scroll scaffold topology recorded in the frontend decision."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
DECISION = APP.parents[2] / "docs/DECISION-FRONTEND-SCROLL-OWNERSHIP.md"
PLAN = APP.parents[2] / "docs/PLAN-FRONTEND-SCAFFOLD.md"
SCAFFOLD = APP / "foundations/layout/scaffold/scaffold.css"
FIXTURE = APP / "pages/stylebook/canonical-layout-fixtures.js"


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


def without_comments(source: str) -> str:
    return re.sub(r"/\*.*?\*/", "", source, flags=re.S)


def main() -> int:
    report = Report()
    decision = DECISION.read_text(encoding="utf-8")
    plan = PLAN.read_text(encoding="utf-8")
    scaffold = without_comments(SCAFFOLD.read_text(encoding="utf-8"))
    fixture = FIXTURE.read_text(encoding="utf-8")

    report.check("**채택됨." in decision, "scroll ownership is no longer recorded as adopted")
    report.check("app bar와 medium-and-up navigation rail은 그 문서에 sticky로 남는다" in decision,
                 "the decision no longer names both persistent chrome regions")

    app_bar_rule = re.search(
        r"\.ax-scaffold\[data-has-app-bar\].*?\.ax-scaffold__app-bar\s*\{(.*?)\}",
        scaffold,
        re.S,
    )
    report.check(app_bar_rule is not None, "the scaffold lost its app bar rule")
    if app_bar_rule:
        rule = app_bar_rule.group(1)
        report.check("position: sticky" in rule, "the app bar no longer persists with document scroll")
        report.check("inset-block-start: var( --ax-scaffold-safe-area-block-start )" in rule,
                     "the app bar no longer sticks below the top safety region")
        report.check("z-index: 1" in rule, "the app bar no longer paints above scrolling content")

    rail_rule = re.search(
        r"\.ax-scaffold\[data-has-rail\].*?\.ax-scaffold__rail\s*\{(.*?)\}",
        scaffold,
        re.S,
    )
    report.check(rail_rule is not None, "the scaffold lost its rail rule")
    if rail_rule:
        rule = rail_rule.group(1)
        report.check("position: sticky" in rule, "the rail no longer persists with document scroll")
        report.check("inset-block-start: var( --ax-scaffold-safe-area-block-start )" in rule,
                     "the rail no longer sticks below the top safety region")
        report.check("align-self: start" in rule, "the rail can stretch past its sticky viewport")
        # The window LESS both safety regions. This pin used to read
        # `block-size: 100dvb`, which fixed the defect in place: the Scaffold
        # has already spent the insets as padding, so a rail of a full `100dvb`
        # hangs past the box it sits in and its last destination lands under
        # the system UI. Measured by driving the two custom properties to a
        # notched phone's 44 and 34 -- the rail's bottom was 44px past the
        # viewport once stuck. Corrected rather than relaxed.
        compact_rule = re.sub(r"\s+", "", rule)
        report.check(
            "block-size:calc(100dvb-var(--ax-scaffold-safe-area-block-start)-var(--ax-scaffold-safe-area-block-end))"
            in compact_rule,
            "the rail no longer fits between the two safety regions",
        )

    report.check("safety region은 Scaffold가 소유한다" in plan,
                 "the Scaffold plan lost ownership of the safety region")
    report.check("env(safe-area-inset-*)" in plan,
                 "the Scaffold plan no longer names the web safety-region source")
    report.check("box-sizing: border-box" in scaffold,
                 "the Scaffold can grow beyond the viewport when safety insets apply")
    report.check("--ax-scaffold-safe-area-block-start: env( safe-area-inset-top, 0px )" in scaffold,
                 "the Scaffold lost the top safe-area fallback")
    report.check("--ax-scaffold-safe-area-block-end: env( safe-area-inset-bottom, 0px )" in scaffold,
                 "the Scaffold lost the bottom safe-area fallback")
    report.check(
        "padding-block: var( --ax-scaffold-safe-area-block-start ) var( --ax-scaffold-safe-area-block-end )" in scaffold,
        "the Scaffold no longer keeps panes out of the safety regions",
    )
    for owner in (".ax-scaffold", ".ax-scaffold__content", ".ax-scaffold__body"):
        owner_rule = re.search(rf"{re.escape(owner)}\s*\{{(.*?)\}}", scaffold, re.S)
        report.check(owner_rule is not None, f"the scroll owner selector {owner} disappeared")
        if owner_rule:
            report.check("overflow" not in owner_rule.group(1),
                         f"{owner} declares overflow and steals document scroll")

    report.check("window.addEventListener( 'scroll', measure, { passive: true } )" in fixture,
                 "the topology fixture no longer observes document scroll")
    for metric in ("railTop", "appBarTop", "documentScrollTop"):
        report.check(f"{metric}:" in fixture,
                     f"the topology fixture no longer reads {metric} from its measured geometry")

    if report.problems:
        for problem in report.problems:
            print(f"- {problem}")
        print(f"scaffold: {report.checked} checks, {len(report.problems)} failed")
        return 1

    print(f"scaffold: {report.checked} checks, 0 failed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
