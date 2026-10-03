#!/usr/bin/env python3
"""Resolve one M3 component's published tokens from its own token table.

WHY THIS EXISTS. m3.material.io renders the specs table client-side and shows
only the resolved value: the screen says `Filled card container color #E6E0E9`
and never says `md.sys.color.surface-container-highest`. A human copying that
table loses the column that matters, and the direction that remains cannot be
reversed -- in the Axismundi light scheme 32 sys colour roles resolve onto 27
distinct hexes, so `#FEF7FF` is `surface` or `surface-bright` and `#FFFFFF` is
any of five roles. comp -> sys -> ref -> hex is deterministic; hex -> sys is a
guess.

The component's token table is a static JSON the page fetches, and it carries
the reference chain outright, so this reads the mapping instead of inferring it.

HOW TO GET THE URL. The initial HTML does not contain it and the filename is
content-hashed per component, so it has to be observed. Load the component's
specs page in the browser and read it off the resource timing:

    performance.getEntriesByType('resource')
      .map(e => e.name).filter(n => /TOKEN_TABLE.*\\.json$/.test(n))

Then: python extract_m3_component_tokens.py <url-or-downloaded-path>

WHAT THIS IS NOT. It does not write `_data/*.yml`. The data files carry
decisions -- which figures we implement, what we deliberately do not build, what
is an inference -- and a generator cannot make those. This produces a draft to
read against the guidelines and accessibility prose, which stay a human's job.
"""

from __future__ import annotations

import collections
import json
import sys
import urllib.request
from pathlib import Path

# Keys on a value object that are metadata rather than the value itself.
VALUE_METADATA = {"name", "revisionId", "revisionCreateTime", "state", "createTime", "tokenName"}


def load(source: str) -> dict:
    if source.startswith("http"):
        request = urllib.request.Request(source, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(request, timeout=60) as response:
            return json.loads(response.read().decode("utf-8"))
    return json.loads(Path(source).read_text(encoding="utf-8"))


def literal(value: dict) -> str:
    """The value's own figure, whatever field carries it."""
    own = {key: item for key, item in value.items() if key not in VALUE_METADATA}
    if not own:
        return "(no value)"
    if 1 == len(own):
        only = next(iter(own.values()))
        return json.dumps(only, separators=(",", ":")) if isinstance(only, (dict, list)) else str(only)
    return json.dumps(own, separators=(",", ":"))


def main(argv: list[str]) -> int:
    if 2 != len(argv):
        print(__doc__)
        return 2

    table = load(argv[1])
    system = table.get("system")
    if not isinstance(system, dict):
        print("failed: no `system` object -- the payload is not a component token table")
        return 1

    tokens = system.get("tokens") or []
    if not tokens:
        print("failed: the token table carries no tokens")
        return 1

    # A value either references another token (`tokenName`) or holds a literal.
    values_by_token: dict[str, list[dict]] = collections.defaultdict(list)
    for value in system.get("values") or []:
        owner = str(value.get("name", "")).split("/values/")[0]
        if owner:
            values_by_token[owner].append(value)

    included: list[tuple[str, str, str]] = []
    skipped: list[tuple[str, str]] = []

    for token in sorted(tokens, key=lambda item: item.get("tokenName", "")):
        name = token.get("tokenName", "(unnamed)")

        deprecation = token.get("deprecationMessage")
        if deprecation:
            message = deprecation.get("message", "") if isinstance(deprecation, dict) else str(deprecation)
            skipped.append((name, " ".join(message.split())))
            continue

        kind = token.get("tokenValueType", "")

        """A sys or ref colour carries one value per scheme and context, and this
        project never spends those figures -- the theme owns them and components
        spend the role. Printing them buries the component's own rows in context
        tags, so they are collapsed to the fact that they exist."""
        if "COLOR" == kind and not name.startswith("md.comp."):
            included.append((name, kind, "(per scheme; the theme owns the value)"))
            continue

        resolved = []
        for value in values_by_token.get(token.get("name", ""), []):
            referenced = value.get("tokenName")
            resolved.append(f"-> {referenced}" if referenced else literal(value))

        included.append((name, kind, " | ".join(resolved) or "(no value)"))

    component = (system.get("components") or [{}])[0]
    print(f"component: {component.get('displayName', '(unnamed)')}   dsdb {system.get('dsdbVersion', '?')}")
    print(f"table revision: {system.get('revisionCreateTime', '?')}")
    print()

    width = max(len(name) for name, _, _ in included) if included else 0
    group = None
    for name, kind, value in included:
        # Group by the token's component prefix, which is also its variant.
        prefix = ".".join(name.split(".")[:3])
        if prefix != group:
            group = prefix
            print(f"[{prefix}]")
        print(f"  {name:<{width}}  {kind:<12}  {value}")

    print()
    print(f"included: {len(included)}")
    print(f"skipped (deprecated): {len(skipped)}")
    for name, message in skipped:
        print(f"  - {name}")
        print(f"      {message}")

    # A reference that resolves to nothing is the one failure mode that would
    # otherwise look like success, so it is counted rather than left to the eye.
    empty = [name for name, _, value in included if "(no value)" == value]
    if empty:
        print(f"unresolved: {len(empty)}")
        for name in empty:
            print(f"  - {name}")

    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
