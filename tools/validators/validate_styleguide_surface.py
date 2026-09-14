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
  - the scrim's opacity is M3's 0.32;
  - motion, windows and the bottom sheet's behaviour: each presentation's
    motion properties name defined theme motion tokens, the runtime plays the
    recorded dialog choreography, and the stylesheet and runtime use the
    recorded window margin, breakpoints, admin bar offset, page share and
    handle thresholds.
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
DATA = ROOT / "products/styleguide/_data/surface.yml"
LAYOUT = ROOT / "products/styleguide/_data/layout.yml"
# The contract's only authored copy. The style guide wears a synced copy of it
# (tools/generators/sync_styleguide_assets.py), so the product file is checked.
ADAPTER = ROOT / "products/wordpress/plugins/axismundi-dialogs/blocks/dialog/style.css"
STYLES = ROOT / "products/wordpress/themes/axismundi/assets/styles"
REF = STYLES / "tokens.ref.css"
LIGHT = STYLES / "tokens.sys.color.light.css"
ELEVATION = STYLES / "tokens.sys.elevation.css"
MOTION = STYLES / "tokens.sys.motion.css"
# The Dialog block's runtime: motion, trigger state and the bottom sheet handle.
RUNTIME = ADAPTER.parent / "view.js"
SURFACE_PHP = ROOT / "products/wordpress/plugins/axismundi-dialogs/includes/surface.php"
BUTTON_BLOCK = ROOT / "products/wordpress/plugins/axismundi-dialogs/blocks/dialog-button/block.json"

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


PATTERNS = ROOT / "products/wordpress/plugins/axismundi-dialogs/patterns"


def pattern_blocks(path: Path) -> list[tuple[str, dict]]:
    """Block name and attributes for every opening block comment in a pattern.

    The patterns are PHP, so they are read as text. The group comments are
    literal JSON; only the button and icon comments are built with
    wp_json_encode(), and they carry no spacing, so skipping the ones that do
    not parse loses nothing checked here.
    """
    blocks = []
    for name, raw in re.findall(r"<!-- wp:([a-z0-9/-]+) (\{.*?\}) /?-->", path.read_text(encoding="utf-8")):
        try:
            blocks.append((name, json.loads(raw)))
        except ValueError:
            continue
    return blocks


def check_patterns(data: dict, report: Report) -> None:
    """The starter patterns carry the published section spacing.

    The Material 3 Design Kit puts padding on a dialog's sections rather than
    its container, so the patterns do too, as core/group attributes; each group
    is found by its metadata name.
    """
    rows = {row["name"]: row for row in data.get("presentations", [])}
    basic = rows["dialog-basic"]["measurements"]
    side = rows["sheet-side"]["measurements"]

    def px(value: int | float) -> str:
        return f"{value}px"

    def group(pattern: str, name: str) -> dict | None:
        path = PATTERNS / f"dialog-surface-{pattern}.php"
        report.check(path.is_file(), f"missing pattern {path.name}")
        if not path.is_file():
            return None
        for block, attrs in pattern_blocks(path):
            if block == "group" and attrs.get("metadata", {}).get("name") == name:
                return attrs
        report.check(False, f"{path.name} has no group named {name!r}")
        return None

    def spacing(attrs: dict | None) -> dict:
        return (attrs or {}).get("style", {}).get("spacing", {})

    def expect_padding(pattern: str, name: str, side_name: str, want: str) -> None:
        attrs = group(pattern, name)
        if attrs is None:
            return
        got = spacing(attrs).get("padding", {}).get(side_name)
        report.check(got == want, f"dialog-surface-{pattern}.php {name} padding-{side_name}: {got!r}, want {want!r}")

    def button_gap(pattern: str, want: str) -> None:
        path = PATTERNS / f"dialog-surface-{pattern}.php"
        gaps = [spacing(attrs).get("blockGap") for block, attrs in pattern_blocks(path) if block == "axismundi/dialog-button-group" and attrs.get("buttonType") != "icon"]
        report.check(bool(gaps) and all(gap == want for gap in gaps), f"dialog-surface-{pattern}.php button group gaps {gaps}, want {want!r}")

    full = rows["dialog-full-screen"]["measurements"]
    bottom = rows["sheet-bottom"]["measurements"]
    all_sides = ("top", "right", "bottom", "left")

    def expect_gap(pattern: str, name: str, want: str) -> None:
        attrs = group(pattern, name)
        if attrs is None:
            return
        got = spacing(attrs).get("blockGap")
        report.check(got == want, f"dialog-surface-{pattern}.php {name} gap: {got!r}, want {want!r}")

    # The slot model: every pattern is Header / Content / Actions groups, and
    # every Content slot starts at the theme's content width.
    for pattern in ("basic", "basic-icon", "list", "full-screen", "bottom-sheet", "side-sheet-modal", "side-sheet-standard"):
        attrs = group(pattern, "Content")
        if attrs is not None:
            got = attrs.get("layout", {}).get("type")
            report.check(got == "constrained", f"dialog-surface-{pattern}.php Content layout: {got!r}, want 'constrained'")

    # Basic dialog, and its icon version: Content 24 at top and sides with 16
    # headline to body; Actions 24 around, which is also body to actions; 8
    # between buttons.
    for pattern in ("basic", "basic-icon"):
        for side_name in ("top", "right", "left"):
            expect_padding(pattern, "Content", side_name, px(basic["padding"]))
        expect_gap(pattern, "Content", px(basic["headline_to_body"]))
        expect_padding(pattern, "Actions", "top", px(basic["body_to_actions"]))
        for side_name in ("right", "bottom", "left"):
            expect_padding(pattern, "Actions", side_name, px(basic["padding"]))
        button_gap(pattern, px(basic["between_buttons"]))
    expect_gap("basic-icon", "Headline", px(basic["icon_to_headline"]))

    # List dialog: the Header carries 24 all round and the list, as Content,
    # none - it runs to the dialog's edges.
    for side_name in all_sides:
        expect_padding("list", "Header", side_name, px(basic["padding"]))
        expect_padding("list", "Content", side_name, "0")
    expect_gap("list", "Header", px(basic["headline_to_body"]))
    button_gap("list", px(basic["between_buttons"]))

    # Full-screen dialog: Content 24 at top and sides.
    expect_padding("full-screen", "Content", "top", px(full["padding"]["top"]))
    for side_name in ("right", "left"):
        expect_padding("full-screen", "Content", side_name, px(full["padding"]["inline"]))

    # Bottom sheet: Content owns every edge, handle or not.
    for side_name in all_sides:
        expect_padding("bottom-sheet", "Content", side_name, px(bottom["content_padding"]))

    # Side sheets: 24 at the start, 12 between top elements, actions 16 above and 24 below.
    for pattern in ("side-sheet-modal", "side-sheet-standard"):
        expect_padding(pattern, "Header", "left", px(side["padding_inline"]))
        header = group(pattern, "Header")
        report.check(spacing(header).get("blockGap") == px(side["between_top_elements"]), f"dialog-surface-{pattern}.php Header gap: {spacing(header).get('blockGap')!r}, want {px(side['between_top_elements'])!r}")
        expect_padding(pattern, "Content", "left", px(side["padding_inline"]))
    expect_padding("side-sheet-modal", "Actions", "top", px(side["bottom_actions"]["padding_top"]))
    expect_padding("side-sheet-modal", "Actions", "bottom", px(side["bottom_actions"]["padding_bottom"]))


def check_adapter(data: dict, report: Report) -> None:
    """The Dialog block's stylesheet (the contract's only authored copy) spends the data.

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

    # The dialog is its own container, as a Group is. Its changeable defaults -
    # background, elevation, corner radius - sit at zero specificity so block
    # supports and Global Styles replace them, which is why they are checked in
    # the :where() rule rather than the geometry rule.
    def container(presentation: str, modality: str | None = None) -> str:
        return f":where({host(presentation, modality)})"

    def expect(selector: str, name: str, want: str) -> None:
        body = rule(css, selector)
        report.check(body is not None, f"dialog style.css has no rule for {selector}")
        if body is not None:
            got = declared(body, name)
            report.check(got == want, f"dialog style.css {selector} {name}: {got!r}, want {want!r}")

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

    # The drag handle: its bar, the padding that makes its 48dp target, and the
    # bottom sheet's focus indicator.
    handle = f"{SURFACE}__drag-handle"
    bar = bottom["spec"]["drag_handle"]
    expect(handle, "inline-size", f"{bar['width']}px")
    expect(handle, "block-size", f"{bar['height']}px")
    # The bar is the handle's ::before: on the button's own background it would
    # take the 48dp target's corner radius and render as a lens.
    expect(f"{handle}::before", "background", f"var(--md-sys-color-{bar['role']})")
    expect(f"{handle}::before", "border-radius", "var(--md-sys-shape-corner-full)")
    expect(f"{handle}::before", "inset", f"{bottom['measurements']['drag_handle']['padding_block']}px 8px")
    expect(handle, "padding", f"{bottom['measurements']['drag_handle']['padding_block']}px 8px")
    ring = bottom["spec"]["focus_indicator"]
    expect(f"{handle}:focus-visible", "outline", f"{ring['thickness']}px solid var(--md-sys-color-{ring['role']})")
    expect(f"{handle}:focus-visible", "outline-offset", f"{ring['offset']}px")

    side = rows["sheet-side"]
    for modality in ("modal", "standard"):
        expect(container("sheet-side", modality), "background", f"var(--md-sys-color-{side['spec']['container'][modality]['role']})")
        expect(container("sheet-side", modality), "box-shadow", f"var(--md-sys-elevation-shadow-{side['spec']['elevation'][modality]['level']})")
    expect(host("sheet-side"), "inline-size", f"{side['measurements']['width']['default']}px")
    expect(host("sheet-side"), "max-inline-size", f"{side['measurements']['width']['max']}px")

    # Typography. The WordPress theme ships no --md-sys-typescale-* tokens, so
    # every declaration falls back to the theme's font-size preset and to the
    # published line height, weight and tracking. The fallbacks restate the
    # spec, so they are held to it, and the preset they name must carry the
    # published size in theme.json.
    import json

    theme_json = ROOT / "products/wordpress/themes/axismundi/theme.json"
    report.check(theme_json.is_file(), f"missing {theme_json.as_posix()}")
    theme_sizes = {}
    if theme_json.is_file():
        theme_sizes = {
            size["slug"]: size.get("size")
            for size in json.loads(theme_json.read_text(encoding="utf-8"))["settings"]["typography"]["fontSizes"]
        }

    def tracking(value: float) -> str:
        return "0" if value == 0 else f"{value}px"

    def typescale(selector: str, scale: str, spec: dict) -> None:
        expect(selector, "font-size", f"var(--md-sys-typescale-{scale}-size, var(--wp--preset--font-size--{scale}))")
        expect(selector, "line-height", f"var(--md-sys-typescale-{scale}-line-height, {spec['line_height']}px)")
        expect(selector, "font-weight", f"var(--md-sys-typescale-{scale}-weight, {spec['weight']})")
        expect(selector, "letter-spacing", f"var(--md-sys-typescale-{scale}-tracking, {tracking(spec['tracking'])})")
        report.check(
            theme_sizes.get(scale) == f"{spec['size']}px",
            f"theme.json font-size preset {scale} is {theme_sizes.get(scale)!r}, the spec gives {spec['size']}px",
        )

    typescale(f"{host('dialog-basic')} h2", "headline-small", basic["spec"]["headline"]["type"])
    typescale(f"{host('dialog-basic')} > :not(header, footer)", "body-medium", basic["spec"]["supporting_text"]["type"])

    check_patterns(data, report)
    typescale(f"{host('dialog-full-screen')} header h2", "title-large", full["spec"]["headline"]["type"])
    typescale(f"{host('sheet-side')} header h2", "title-large", side["spec"]["headline"]["type"])

    report.check(
        f"color-mix(in srgb, var(--md-sys-color-{data['scrim']['role']}) {round(data['scrim']['opacity'] * 100)}%, transparent)" in css,
        "dialog style.css does not paint the scrim from the scrim role at the published opacity",
    )


def check_runtime(data: dict, report: Report) -> None:
    """Motion, the window and the page, and the bottom sheet's behaviour.

    surface.yml restates these as this project's choices; the stylesheet, the
    runtime and the theme's motion tokens are what ship them.
    """
    sources = (ADAPTER, RUNTIME, MOTION, LAYOUT, SURFACE_PHP, BUTTON_BLOCK)
    for path in sources:
        report.check(path.is_file(), f"missing {path.as_posix()}")
    if not all(path.is_file() for path in sources):
        return
    css = ADAPTER.read_text(encoding="utf-8")
    js = RUNTIME.read_text(encoding="utf-8")
    tokens = set(re.findall(r"(--md-sys-motion-[a-z0-9-]+)\s*:", MOTION.read_text(encoding="utf-8")))
    classes = {c["name"]: c for c in yaml.safe_load(LAYOUT.read_text(encoding="utf-8"))["breakpoints"]["classes"]}
    rows = {row["name"]: row for row in data.get("presentations", [])}
    host = "dialog.wp-block-axismundi-dialog"

    def expect(selector: str, name: str, want: str) -> None:
        body = rule(css, selector)
        report.check(body is not None, f"dialog style.css has no rule for {selector}")
        if body is not None:
            got = declared(body, name)
            report.check(got == want, f"dialog style.css {selector} {name}: {got!r}, want {want!r}")

    # Motion: each presentation's four properties name theme tokens that exist.
    # A presentation that does not redeclare a property inherits the base rule's.
    motion = data["motion"]
    selectors = {
        "dialog-basic": f":where({host})",
        "dialog-full-screen": f':where({host}[data-presentation="dialog-full-screen"])',
        "sheet": f':where({host}[data-presentation^="sheet-"])',
    }
    base = rule(css, selectors["dialog-basic"])
    for key, selector in selectors.items():
        body = rule(css, selector)
        report.check(body is not None, f"dialog style.css has no rule for {selector}")
        for field, prop in motion["properties"].items():
            token = f"--{motion['presentations'][key][field]}"
            report.check(token in tokens, f"motion.presentations.{key}.{field}: {token} is not defined in tokens.sys.motion.css")
            got = declared(body, prop)
            if got is None and key != "dialog-basic":
                got = declared(base, prop)
            report.check(got == f"var({token})", f"dialog style.css {selector} {prop}: {got!r}, want 'var({token})'")

    # The dialog choreography the runtime plays.
    choreography = motion["dialog"]
    report.check(js.count(f"translate: '0 -{choreography['translate']}px'") >= 2,
                 f"view.js does not move a dialog {choreography['translate']}px on opening and closing")
    report.check(f"inset(0px 0px {100 - choreography['folded_height']}% 0px" in js,
                 f"view.js does not fold the container to {choreography['folded_height']}% of its height")
    for slot, call in (("content", "fadeIn( slot, {hold}, enterDuration * {share} )"), ("actions", "fadeIn( slot, {hold}, enterDuration * {share} )")):
        want = call.format(hold=choreography[slot]["hold"], share=choreography[slot]["share_of_enter"])
        report.check(want in js, f"view.js does not fade {slot} as recorded: {want}")
    report.check(choreography["exit_slot_fade"] == "2/3" and "( exitDuration * 2 ) / 3" in js,
                 "view.js does not fade slots out over the recorded share of the exit duration")
    report.check("'(prefers-reduced-motion: reduce)'" in js, "view.js does not read prefers-reduced-motion")

    # The window: the basic dialog's margin, and full-screen becoming basic.
    windows = data["windows"]
    basic_max = rows["dialog-basic"]["measurements"]["width"]["max"]
    margin = windows["basic_dialog_margin"]["value"]
    window_width = f"min({basic_max}px, 100% - {2 * margin}px)"
    expect(f'{host}[data-presentation="dialog-basic"]', "max-inline-size", window_width)
    adaptive = next((r for r in data.get("adaptive", []) if r.get("presentation") == "dialog-full-screen"), {})
    report.check(windows["basic_from"] == adaptive.get("from"), "windows.basic_from differs from the full-screen dialog's adaptive rule")
    medium = classes[windows["basic_from"]]["min"]
    query = f"@media (width >= {medium}px)"
    report.check(query in css, f"dialog style.css has no {query} for the full-screen dialog's switch")
    inside = css.split(query, 1)[1] if query in css else ""
    report.check(
        re.search(re.escape(f'{host}[data-presentation="dialog-full-screen"]') + r"\s*\{[^}]*max-inline-size:\s*" + re.escape(window_width), inside) is not None,
        f"dialog style.css does not give the full-screen dialog the basic dialog's {window_width} inside {query}",
    )

    # The page: the admin bar and the standard side sheet's page share.
    expect(f'{host}[data-render-mode="standard-sheet"]', "inset", "var(--wp-admin--admin-bar--height, 0px) 0 0")
    expect("html.axismundi-dialog-pushed-start .wp-site-blocks", "padding-inline-start", "var(--axismundi-dialog-push, 0px)")
    expect("html.axismundi-dialog-pushed-end .wp-site-blocks", "padding-inline-end", "var(--axismundi-dialog-push, 0px)")
    expect("html.axismundi-dialog-moved .wp-site-blocks", "translate", "var(--axismundi-dialog-move, 0px) 0")
    compact = classes[windows["full_screen_until"]]["max"]
    report.check(f"'(max-width: {compact}px)'" in js, f"view.js does not switch at the compact window's {compact}px")
    report.check(set(windows["page_share"]["values"]) == {"resize", "move"} and "'move'" in js,
                 "windows.page_share values differ from the runtime's")

    # The bottom sheet's heights and handle.
    bottom = rows["sheet-bottom"]
    behaviour = bottom["behaviour"]
    cap = (f'{host}[data-presentation="sheet-bottom"]:has(> .wp-block-axismundi-dialog__header > '
           '.wp-block-axismundi-dialog__drag-handle):not([data-sheet-height="expanded"])')
    expect(cap, "max-block-size", f"{behaviour['initial_height_cap']}%")
    top = bottom["measurements"][behaviour["expanded"]]
    expanded = f'{host}[data-presentation="sheet-bottom"][data-sheet-height="expanded"]'
    expect(expanded, "block-size", f"calc(100% - {top}px)")
    expect(expanded, "max-block-size", f"calc(100% - {top}px)")
    wide = bottom["measurements"]["wide_window"]
    report.check(
        re.search(re.escape(f"@media (width > {wide['above']}px)") + r"\s*\{\s*" + re.escape(f"{expanded}:modal") + r"\s*\{[^}]*block-size:\s*" + re.escape(f"calc(100% - {wide['top_margin']}px)"), css) is not None,
        f"dialog style.css does not keep {wide['top_margin']}px above an expanded modal sheet wider than {wide['above']}px",
    )
    drag = behaviour["handle"]["drag"]
    for constant, key in (("DRAG_SLOP", "slop"), ("CLOSE_BELOW", "close_below"), ("EXPAND_ABOVE", "expand_above"), ("FLING_SPEED", "fling_speed"), ("FLING_WINDOW", "fling_window")):
        match = re.search(rf"^const {constant} = ([0-9.]+);", js, re.M)
        report.check(match is not None and float(match.group(1)) == float(drag[key]),
                     f"view.js {constant} is {match.group(1) if match else 'missing'}, surface.yml gives {drag[key]}")

    # The trigger: the actions exist, and a standard sheet's command is the custom one.
    trigger = data["host"]["trigger"]
    enum = json.loads(BUTTON_BLOCK.read_text(encoding="utf-8"))["attributes"]["action"]["enum"]
    for key in ("open_action", "close_action"):
        report.check(trigger[key] in enum, f"host.trigger.{key} '{trigger[key]}' is not in the Dialog Button's action enum")
    report.check("'--toggle'" in SURFACE_PHP.read_text(encoding="utf-8") and "'--toggle'" in js,
                 "a standard sheet's trigger command is not --toggle in both includes/surface.php and view.js")


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
    check_runtime(data, report)

    names = [row.get("name") for row in data.get("presentations", [])]
    axis = next((a for a in data.get("axes", []) if a.get("name") == "presentation"), {})
    report.check(names == axis.get("values"), f"presentations {names} differ from the presentation axis {axis.get('values')}")

    print(f"surface: {report.checked} checks, {len(report.problems)} failed")
    for problem in report.problems:
        print(f"  - {problem}")
    return 1 if report.problems else 0


if __name__ == "__main__":
    sys.exit(main())
