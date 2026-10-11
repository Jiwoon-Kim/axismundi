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
SCAFFOLD_COMPONENT = APP / "foundations/layout/scaffold/index.js"
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
    component = SCAFFOLD_COMPONENT.read_text(encoding="utf-8")
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
        report.check("inset-block-start: var( --ax-scaffold-chrome-block-start )" in rule,
                     "the rail no longer sticks below window chrome and the top safety region")
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
            "block-size:calc(100dvb-var(--ax-scaffold-chrome-block-start)-var(--ax-scaffold-safe-area-block-end))"
            in compact_rule,
            "the rail no longer fits between window chrome and the bottom safety region",
        )

    report.check("const APP_BAR_SCOPES = [ 'pane', 'window' ]" in component,
                 "the Scaffold no longer exposes the two M3 app-bar scopes")
    report.check("appBarScope = 'pane'" in component,
                 "the legacy pane-scoped app bar is no longer the default")
    report.check("data-app-bar-scope={ appBar ? barScope : undefined }" in component,
                 "the chosen app-bar scope no longer reaches the Scaffold geometry")
    report.check("<aside className=\"ax-scaffold__companion\" aria-label={ companionLabel || undefined }>{ companion }</aside>" in component,
                 "the companion rail no longer has a labelled landmark wrapper")
    report.check("data-has-companion={ companion ? '' : undefined }" in component,
                 "the companion slot no longer declares its presence to the grid")
    report.check("companion && ! companionLabel" in component,
                 "the Scaffold no longer warns about an unnamed companion landmark")

    report.check("[data-app-bar-scope='window']" in scaffold,
                 "the Scaffold no longer has a full-window app-bar topology")
    report.check('"app-bar app-bar"' in scaffold and '"rail content"' in scaffold,
                 "the full-window app bar no longer spans both leading-rail and content columns")
    report.check('"app-bar app-bar app-bar"' in scaffold and '"rail content companion"' in scaffold,
                 "the full-window app bar no longer spans the companion-rail topology")
    report.check("--ax-scaffold-window-app-bar-block-size, 64px" in scaffold,
                 "the global bar no longer has a documented standard-bar height fallback")
    report.check(".ax-scaffold[data-has-companion] .ax-scaffold__companion" in scaffold,
                 "the companion rail no longer receives its grid and sticky geometry")

    report.check("safety region은 Scaffold가 소유한다" in plan,
                 "the Scaffold plan lost ownership of the safety region")
    report.check("window-scoped bar + companion rail B형 토폴로지  구현됨" in plan,
                 "the Scaffold plan no longer records the implemented B topology")
    report.check("flexible pane 최소 하나는 구조적으로 보장된다" in plan,
                 "the Scaffold plan no longer records why the flexible-pane invariant needs no dead warning")
    report.check("Topology fixture는 companion을 채우지 않는다" in plan,
                 "the Scaffold plan no longer records that its companion slot has no fixture proxy")
    report.check("`extraLarge`에서 expanded이고 그 아래에서는\ncollapsed인 rail" in plan,
                 "the Scaffold plan no longer records the limited rail-expansion experiment")
    report.check("arbitrary pane API" in plan,
                 "the Scaffold plan no longer records when to reopen the pane-count invariant")
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

    body_rule = re.search(r"\.ax-scaffold__body\s*\{(.*?)\}", scaffold, re.S)
    report.check(body_rule is not None, "the Scaffold lost its body grid rule")
    if body_rule:
        report.check("grid-template-areas: \"main\"" in body_rule.group(1),
                     "the body no longer starts from exactly one main pane")
        report.check("grid-template-columns: minmax( 0, 1fr )" in body_rule.group(1),
                     "the mandatory main pane is no longer the flexible track")
    report.check('"main supporting sheet"' in scaffold,
                 "the three-pane body topology no longer names its complete structural maximum")

    report.check("window.addEventListener( 'scroll', measure, { passive: true } )" in fixture,
                 "the topology fixture no longer observes document scroll")
    topology_fixture = fixture[fixture.index("function TopologyFixture"):fixture.index("function FeedFixture")]
    report.check('appBarScope="window"' in topology_fixture,
                 "the topology fixture no longer consumes the full-window app-bar scope")
    report.check("function CompanionTools" not in fixture,
                 "the topology fixture invented a companion-rail consumer again")
    # The slot is pinned on the Scaffold, not on the fixture. Requiring the
    # fixture to render one made a demo invented for the fixture a
    # precondition of measuring the scaffold -- and this repository's own
    # fourth promotion criterion says a boundary drawn by a single host is an
    # inference. The companion rail has a published definition and a measured
    # reference in the wild; it does not yet have a product consumer.
    # `readTopology` is declared before `TopologyFixture`, so it is outside the
    # slice above. Read its own slice rather than the fixture's.
    readout = fixture[fixture.index("function readTopology"):fixture.index("function TopologyFixture")]
    report.check("const companion = root.querySelector( '.ax-scaffold__companion' )" in readout,
                 "the topology readout no longer reports the companion rail when one is present")
    report.check("if ( ! rail || ! content || ! appBar ) {" in readout,
                 "the topology readout requires a companion rail again")
    for metric in ("railTop", "appBarTop", "appBarWidth", "documentScrollTop"):
        report.check(f"{metric}:" in fixture,
                     f"the topology fixture no longer reads {metric} from its measured geometry")

    report.check("railVariantForSizeClass" in fixture,
                 "the topology fixture no longer records its breakpoint rail experiment")
    report.check("'extraLarge' === sizeClass ? 'expanded' : 'collapsed'" in fixture,
                 "the breakpoint rail experiment no longer expands only at extra-large")
    report.check("aria-controls={ TOPOLOGY_RAIL_ID }" in fixture and "aria-expanded={ railExpanded }" in fixture,
                 "the app-bar control no longer reports the rail it changes")
    report.check("variant={ railVariant }" in fixture and "id={ TOPOLOGY_RAIL_ID }" in fixture,
                 "the app-bar experiment no longer controls the rendered rail")

    if report.problems:
        for problem in report.problems:
            print(f"- {problem}")
        print(f"scaffold: {report.checked} checks, {len(report.problems)} failed")
        return 1

    print(f"scaffold: {report.checked} checks, 0 failed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
