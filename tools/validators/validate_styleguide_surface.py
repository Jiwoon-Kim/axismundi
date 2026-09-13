#!/usr/bin/env python3
"""Check the Surface spec data against the theme's shipped tokens.

products/styleguide/_data/surface.yml restates M3's published dialog and sheet
tables: a colour role beside the baseline hex M3 prints for it, an elevation
level, a scrim. A restated value drifts, so this checks each against the place
the theme actually defines it:

  - every `{role, hex}` pair: the role exists in tokens.sys.color.light.css and
    resolves, through tokens.ref.css or tokens.sys.elevation.css, to that hex;
  - every `{level, dp}` pair: the level has a shadow in tokens.sys.elevation.css
    and the dp is the one M3 publishes for that level;
  - every role in a row's numbered `color_roles` list is defined by the theme;
  - every `adaptive` rule names a real presentation, real layout.yml
    breakpoints, and a `becomes` that is a presentation or a modality;
  - the scrim's opacity is M3's 0.32.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

try:
    import yaml
except ImportError:  # pragma: no cover
    print("PyYAML is required: pip install pyyaml", file=sys.stderr)
    raise SystemExit(2)


ROOT = Path(__file__).resolve().parent.parent.parent
DATA = ROOT / "products/styleguide/_data/surface.yml"
LAYOUT = ROOT / "products/styleguide/_data/layout.yml"
ADAPTER = ROOT / "products/styleguide/assets/css/components/surface.css"
STYLES = ROOT / "products/wordpress/themes/axismundi/assets/styles"
REF = STYLES / "tokens.ref.css"
LIGHT = STYLES / "tokens.sys.color.light.css"
ELEVATION = STYLES / "tokens.sys.elevation.css"

# M3 elevation levels and the dp each is published as. The theme defines no dp
# token on purpose, so this table is the only place the pairing can be checked.
LEVEL_DP = {"level0": 0, "level1": 1, "level2": 3, "level3": 6, "level4": 8, "level5": 12}


class Report:
    def __init__(self) -> None:
        self.checked = 0
        self.problems: list[str] = []

    def check(self, condition: bool, message: str) -> None:
        self.checked += 1
        if not condition:
            self.problems.append(message)


def roles() -> dict[str, str]:
    """Colour role to hex, resolved through the ref palette."""
    ref = {
        name: value.upper()
        for name, value in re.findall(
            r"(--md-ref-palette-[a-z-]+\d+)\s*:\s*(#[0-9a-fA-F]{6})\b",
            REF.read_text(encoding="utf-8"),
        )
    }
    resolved: dict[str, str] = {}
    # Scheme roles live in the light file; scheme-neutral ones (scrim, shadow)
    # live beside elevation. The first definition of a name wins, which is the
    # light :root block rather than a later override.
    for path in (LIGHT, ELEVATION):
        for role, tone in re.findall(
            r"--md-sys-color-([a-z-]+)\s*:\s*var\(\s*(--md-ref-palette-[a-z-]+\d+)\s*\)",
            path.read_text(encoding="utf-8"),
        ):
            resolved.setdefault(role, ref.get(tone, f"unresolved {tone}"))
    return resolved


def walk(node: object, path: str):
    """Yield every mapping in the data with its dotted path."""
    if isinstance(node, dict):
        yield path, node
        for key, value in node.items():
            yield from walk(value, f"{path}.{key}" if path else str(key))
    elif isinstance(node, list):
        for index, value in enumerate(node):
            yield from walk(value, f"{path}[{index}]")


# M3 shape corner values by dp, as the adapter spends them.
CORNER = {
    0: "var(--md-sys-shape-corner-value-none)",
    16: "var(--md-sys-shape-corner-value-large)",
    28: "var(--md-sys-shape-corner-value-extra-large)",
}
SURFACE = ".wp-block-axismundi-dialog"


def rule(css: str, selector: str) -> str | None:
    """The body of the one rule whose whole selector is `selector`."""
    stylesheet = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    for match in re.finditer(r"([^{}]+)\{([^{}]*)\}", stylesheet, re.S):
        if match.group(1).strip() == selector:
            return match.group(2)
    return None


def declared(body: str | None, name: str) -> str | None:
    if body is None:
        return None
    match = re.search(r"(?:^|;|\s)" + re.escape(name) + r"\s*:\s*([^;]+);", body)
    return match.group(1).strip() if match else None


def check_adapter(data: dict, report: Report) -> None:
    """The static adapter in assets/css/components/surface.css spends the data.

    Each checked rule is written with a single selector, so a rule that moves
    into a comma list is reported as missing rather than silently skipped.
    """
    report.check(ADAPTER.is_file(), f"missing {ADAPTER.as_posix()}")
    if not ADAPTER.is_file():
        return
    css = ADAPTER.read_text(encoding="utf-8")
    rows = {row["name"]: row for row in data.get("presentations", [])}

    def host(presentation: str, modality: str | None = None) -> str:
        return f'{SURFACE}[data-presentation="{presentation}"]' + (f'[data-modality="{modality}"]' if modality else "")

    def container(presentation: str, modality: str | None = None) -> str:
        return f"{host(presentation, modality)} > {SURFACE}__container"

    def expect(selector: str, name: str, want: str) -> None:
        body = rule(css, selector)
        report.check(body is not None, f"surface.css has no rule for {selector}")
        if body is not None:
            got = declared(body, name)
            report.check(got == want, f"surface.css {selector} {name}: {got!r}, want {want!r}")

    basic = rows["dialog-basic"]
    expect(container("dialog-basic"), "background", f"var(--md-sys-color-{basic['spec']['container']['role']})")
    expect(container("dialog-basic"), "box-shadow", f"var(--md-sys-elevation-shadow-{basic['spec']['elevation']['level']})")
    expect(container("dialog-basic"), "border-radius", CORNER[basic["spec"]["shape"]])
    expect(host("dialog-basic"), "min-inline-size", f"{basic['measurements']['width']['min']}px")
    expect(host("dialog-basic"), "max-inline-size", f"{basic['measurements']['width']['max']}px")

    full = rows["dialog-full-screen"]
    expect(container("dialog-full-screen"), "background", f"var(--md-sys-color-{full['spec']['container']['role']})")
    expect(container("dialog-full-screen"), "box-shadow", f"var(--md-sys-elevation-shadow-{full['spec']['elevation']['level']})")
    expect(container("dialog-full-screen"), "border-radius", CORNER[full["spec"]["shape"]])

    bottom = rows["sheet-bottom"]
    expect(container("sheet-bottom"), "background", f"var(--md-sys-color-{bottom['spec']['container']['role']})")
    expect(container("sheet-bottom"), "box-shadow", f"var(--md-sys-elevation-shadow-{bottom['spec']['elevation']['modal']['level']})")
    expect(container("sheet-bottom"), "border-start-start-radius", CORNER[bottom["spec"]["shape"]["start_start"]])
    expect(container("sheet-bottom"), "border-start-end-radius", CORNER[bottom["spec"]["shape"]["start_end"]])
    expect(host("sheet-bottom"), "max-inline-size", f"{bottom['measurements']['width']['max']}px")

    side = rows["sheet-side"]
    for modality in ("modal", "standard"):
        expect(container("sheet-side", modality), "background", f"var(--md-sys-color-{side['spec']['container'][modality]['role']})")
        expect(container("sheet-side", modality), "box-shadow", f"var(--md-sys-elevation-shadow-{side['spec']['elevation'][modality]['level']})")
    expect(host("sheet-side"), "inline-size", f"{side['measurements']['width']['default']}px")
    expect(host("sheet-side"), "max-inline-size", f"{side['measurements']['width']['max']}px")

    report.check(
        f"color-mix(in srgb, var(--md-sys-color-{data['scrim']['role']}) {round(data['scrim']['opacity'] * 100)}%, transparent)" in css,
        "surface.css does not paint the scrim from the scrim role at the published opacity",
    )


def main() -> int:
    missing = [p.relative_to(ROOT).as_posix() for p in (DATA, LAYOUT, REF, LIGHT, ELEVATION) if not p.is_file()]
    if missing:
        print("missing:")
        print("\n".join(f"  - {path}" for path in missing))
        return 1

    data = yaml.safe_load(DATA.read_text(encoding="utf-8"))
    colour = roles()
    shadows = set(re.findall(r"--md-sys-elevation-shadow-(level\d)\s*:", ELEVATION.read_text(encoding="utf-8")))
    report = Report()

    for path, node in walk(data, ""):
        # `legacy` blocks describe the gap in prose, and `out_of_scope` names
        # roles as Figma option labels; neither is a published pair.
        if ".legacy" in path or path.startswith(("out_of_scope", "content_providers", "host")):
            continue
        if "role" in node and "hex" in node:
            role, want = node["role"], str(node["hex"]).upper()
            got = colour.get(role)
            report.check(got is not None, f"{path}: role '{role}' is not defined in the theme's light tokens")
            if got is not None:
                report.check(got == want, f"{path}: role '{role}' resolves to {got}, the spec prints {want}")
        if "level" in node and "dp" in node:
            level, dp = node["level"], node["dp"]
            report.check(level in shadows, f"{path}: elevation '{level}' has no shadow in tokens.sys.elevation.css")
            report.check(LEVEL_DP.get(level) == dp, f"{path}: {level} is published as {LEVEL_DP.get(level)}dp, the data says {dp}dp")

    # The numbered colour-role lists carry no hex, so all they can promise is
    # that each role is one the theme defines.
    # A row whose variants publish separate lists (side sheet: standard, modal)
    # stores a mapping of lists; every other row stores one list.
    for row in data.get("presentations", []):
        lists = row.get("color_roles", [])
        lists = lists.items() if isinstance(lists, dict) else [("", lists)]
        for variant, roles_listed in lists:
            where = f"{row.get('name')}.color_roles" + (f".{variant}" if variant else "")
            for role in roles_listed:
                report.check(role in colour, f"{where}: '{role}' is not defined in the theme's light tokens")

    # Adaptive rules name breakpoints and rows; both must exist.
    breakpoints = {c["name"] for c in yaml.safe_load(LAYOUT.read_text(encoding="utf-8"))["breakpoints"]["classes"]}
    presentation_names = {row.get("name") for row in data.get("presentations", [])}
    for index, rule in enumerate(data.get("adaptive", [])):
        where = f"adaptive[{index}]"
        report.check(rule.get("presentation") in presentation_names, f"{where}: '{rule.get('presentation')}' is not a presentation")
        named = list(rule.get("breakpoints", []))
        for key in ("from",):
            if key in rule:
                named.append(rule[key])
        for key in ("custom_from", "edge_margin_from"):
            if key in rule.get("position", {}):
                named.append(rule["position"][key])
        for name in named:
            report.check(name in breakpoints, f"{where}: breakpoint '{name}' is not in layout.yml")
        becomes = rule.get("becomes")
        if becomes is not None:
            modality = next((a["values"] for a in data.get("axes", []) if a.get("name") == "modality"), [])
            report.check(becomes in presentation_names or becomes in modality,
                         f"{where}: becomes '{becomes}', which is neither a presentation nor a modality")

    report.check(data.get("scrim", {}).get("opacity") == 0.32, "scrim: opacity differs from M3's 0.32 (§0.12)")

    check_adapter(data, report)

    names = [row.get("name") for row in data.get("presentations", [])]
    axis = next((a for a in data.get("axes", []) if a.get("name") == "presentation"), {})
    report.check(names == axis.get("values"), f"presentations {names} differ from the presentation axis {axis.get('values')}")

    print(f"surface: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
