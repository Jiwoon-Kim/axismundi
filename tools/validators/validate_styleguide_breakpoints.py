#!/usr/bin/env python3
"""Keep the breakpoint thresholds equal in the two places they must be written.

WHY THIS EXISTS. `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` states the constraint
itself: "A custom property cannot supply a media-query condition, so those
threshold literals remain in the owning layout stylesheet." So `600`, `840`,
`1200` and `1600` are written twice by construction -- once in
`viewport.json`, which `use-window-size-class.js` reads, and once as literals in
each layout stylesheet. Reading the JSON from JavaScript does not make it a
single source; it only removes one of the copies.

Two copies with nothing comparing them is the shape this repository keeps
getting caught by. The navigation rail recorded a published 64dp figure and
never applied it, and 128 checks passed because they only compared the data file
with the token table.

WHAT IS CHECKED, AND WHAT IS DELIBERATELY NOT. A stylesheet is not required to
use every threshold -- the supporting pane reflows at 840 and has no business at
1200. What is required is that it invents none: every `min-width` a layout
stylesheet declares must be one the vocabulary declares.

No network.
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
FRONTEND = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
VIEWPORT = FRONTEND / "foundations/layout/breakpoints/viewport.json"
HOOK = FRONTEND / "foundations/layout/breakpoints/use-window-size-class.js"
LAYOUT_CSS = sorted((FRONTEND / "foundations/layout").rglob("*.css"))

MIN_WIDTH = re.compile(r"min-width:\s*(\d+)px")
COMMENT = re.compile(r"/\*.*?\*/", re.S)

# Material's five classes. Named here only so that losing one from the JSON is a
# failure rather than a silently shorter vocabulary.
EXPECTED_CLASSES = {"compact", "medium", "expanded", "large", "extraLarge"}


def main() -> int:
    checked = 0
    problems: list[str] = []

    def check(condition: bool, failure: str) -> None:
        nonlocal checked
        checked += 1
        if not condition:
            problems.append(failure)

    classes = json.loads(VIEWPORT.read_text(encoding="utf-8"))["windowSizeClasses"]
    check(
        set(classes) == EXPECTED_CLASSES,
        f"viewport.json declares {sorted(classes)}, not the five window size classes",
    )

    # `compact` starts at 0 and is the floor, so it is not a media-query threshold.
    thresholds = {int(c["minWidth"]) for c in classes.values() if 0 < int(c["minWidth"])}
    check(
        thresholds == {600, 840, 1200, 1600},
        f"the declared thresholds are {sorted(thresholds)}, not the documented 600/840/1200/1600",
    )

    # Every class above compact has a floor, and every floor is a threshold: a
    # class whose minWidth nobody can write as a media query is unreachable in CSS.
    for name, window in classes.items():
        minimum = int(window["minWidth"])
        check(
            0 == minimum or minimum in thresholds,
            f"{name} starts at {minimum}px, which is not one of the thresholds",
        )

    # THE HOOK READS THE JSON. A hardcoded figure would be a third copy.
    hook = HOOK.read_text(encoding="utf-8")
    check(
        "from './viewport.json'" in hook,
        "use-window-size-class.js no longer reads viewport.json; the thresholds would be a third copy",
    )
    for threshold in sorted(thresholds):
        check(
            str(threshold) not in COMMENT.sub("", hook),
            f"use-window-size-class.js hardcodes {threshold}; it must come from viewport.json",
        )

    # THE STYLESHEETS INVENT NONE. Using a subset is fine; using something else
    # is a breakpoint the vocabulary does not have.
    for sheet in LAYOUT_CSS:
        declared = {int(found) for found in MIN_WIDTH.findall(COMMENT.sub("", sheet.read_text(encoding="utf-8")))}
        stray = sorted(declared - thresholds)
        check(
            not stray,
            f"{sheet.relative_to(FRONTEND)} declares min-width {stray}, which viewport.json does not",
        )

    # And at least one of them uses each threshold, or the vocabulary has a class
    # no layout reacts to -- which is worth knowing even though it is allowed per
    # sheet. 1200 and 1600 are expected to be unused until the rail expands.
    used = set()
    for sheet in LAYOUT_CSS:
        used |= {int(found) for found in MIN_WIDTH.findall(COMMENT.sub("", sheet.read_text(encoding="utf-8")))}
    unused = sorted(thresholds - used)
    check(
        unused in ([], [1200], [1600], [1200, 1600]),
        f"thresholds {unused} are unused by every layout stylesheet; see the slicing decision on 1200/1600",
    )

    print(f"breakpoints: {checked} checks, {len(problems)} failed")
    for problem in problems:
        print(f"  - {problem}")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
