"""The React surface's half of the Surface contract.

Imported by validate_styleguide_surface.py. It lives beside that file rather
than inside it because the two bind the same yml to two different runtimes: the
block's stylesheet and view.js on one side, the frontend app's components on the
other. `surface.yml` is the one numeric original for both.

Every expectation here is read out of the yml. Nothing is spelled twice.
"""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
APP = ROOT / "products/wordpress/plugins/axismundi/src/apps/frontend"
SURFACE_CSS = APP / "styles/components/surface.css"
DIALOG_CSS = APP / "styles/components/dialog.css"
SCAFFOLD_CSS = APP / "foundations/layout/scaffold/scaffold.css"
STYLE_INDEX = APP / "styles/index.css"
PAGE = APP / "pages/stylebook/components/surfaces/index.js"
# The other runtime that carries the same rule, read but never written here.
BLOCK_CSS = ROOT / "products/wordpress/plugins/axismundi-dialogs/blocks/dialog/style.css"
SIDE_SHEET_CSS = APP / "styles/components/side-sheet.css"
BOTTOM_SHEET_CSS = APP / "styles/components/bottom-sheet.css"
SCAFFOLD_JS = APP / "foundations/layout/scaffold/index.js"
SCAFFOLD_CSS_FILE = APP / "foundations/layout/scaffold/scaffold.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8") if path.is_file() else ""


def _normalise(css: str) -> str:
    """Whitespace and the optional last semicolon are not part of the rule."""
    return re.sub(r"\s+", "", css).replace(";}", "}")


def _block(css: str, opener: str) -> str:
    """The body of one media query, matched by brace depth."""
    start = css.find(opener)
    if -1 == start:
        return ""
    depth = 0
    for index in range(css.index("{", start), len(css)):
        if "{" == css[index]:
            depth += 1
        elif "}" == css[index]:
            depth -= 1
            if 0 == depth:
                return css[start : index + 1]
    return ""


def _medium_block(css: str) -> str:
    return _block(css, "@media ( min-width: 600px )")


def _compact_block(css: str) -> str:
    return _block(css, "@media ( max-width: 599px )")


def check_react_surface(data: dict, report) -> None:
    react = data.get("react_surface")
    report.check(react is not None, "the React surface record disappeared from surface.yml")
    if react is None:
        return

    root = ROOT / react["root"]
    core_path = root / react["core"]["file"]
    core = read(core_path)
    report.check(bool(core), f"react: the shared core {react['core']['file']} is missing")
    dialog_js = read(root / "dialog.js")
    dialog_css = read(DIALOG_CSS)
    side_sheet_js = read(root / "side-sheet.js")
    side_sheet_css = read(SIDE_SHEET_CSS)
    bottom_sheet_js = read(root / "bottom-sheet.js")
    bottom_sheet_css = read(BOTTOM_SHEET_CSS)
    scaffold_js = read(SCAFFOLD_JS)
    page = read(PAGE)
    # Which stylesheet spends which row's tokens.
    row_stylesheets = {
        "dialog-basic": dialog_css,
        "dialog-full-screen": dialog_css,
        "sheet-bottom": bottom_sheet_css,
        "sheet-side": side_sheet_css,
    }

    # --- rows and components ------------------------------------------------
    # Four published rows map onto three components. The union has to stay
    # equal to the presentation axis, so a row cannot be quietly dropped by
    # deleting the component that claimed it.
    axis = next((a for a in data.get("axes", []) if a.get("name") == "presentation"), {})
    published = list(axis.get("values") or [])
    claimed: list[str] = []
    for component in react["components"]:
        claimed.extend(component.get("presentations") or [])
    report.check(
        sorted(claimed) == sorted(published),
        f"react: components claim {sorted(claimed)}, the presentation axis publishes {sorted(published)}",
    )
    report.check(len(claimed) == len(set(claimed)), "react: two components claim the same presentation")

    implemented: dict[str, str] = {}
    for component in react["components"]:
        name = component["name"]
        path = root / component["file"]
        shipped = "not implemented" != component.get("status")
        report.check(
            path.is_file() == shipped,
            f"react: {component['file']} is {'missing' if shipped else 'present'} "
            f"while the record says {component.get('status', 'implemented')}",
        )
        if shipped:
            source = read(path)
            report.check(f"export function {name}" in source, f"react: {component['file']} stops exporting {name}")
            for presentation in component.get("presentations") or []:
                implemented[presentation] = component["file"]
            # A component that pairs two rows has to say why in the record,
            # because the pairing is the one thing a reader cannot recover from
            # the source.
            if 1 < len(component.get("presentations") or []):
                report.check(
                    bool(component.get("why_paired")),
                    f"react: {name} pairs rows without recording why",
                )

    # --- the escape contract ------------------------------------------------
    report.check(
        "createPortal" not in core,
        "react: the core reaches for createPortal, which the escape record rules out",
    )
    report.check(react["host"]["modal"].rstrip("()") + "()" in core, "react: the core stopped calling showModal()")
    report.check(".show()" in core or "dialog.show()" in core, "react: the core stopped calling show()")

    # The scroll owner's prohibition, re-checked from this side. Its reason
    # changed when the escape question was measured; the pin did not.
    scaffold = read(SCAFFOLD_CSS)
    stripped = re.sub(r"/\*.*?\*/", "", scaffold, flags=re.S)
    offenders = [line.strip() for line in stripped.splitlines() if re.search(r"^\s*overflow[-a-z]*\s*:", line)]
    report.check(
        not offenders,
        f"react: the scaffold declares overflow, which the scroll record forbids: {offenders}",
    )
    report.check(
        "minmax( 0, 1fr )" in scaffold,
        "react: the scaffold's body lost its minmax( 0, 1fr ) track",
    )

    # --- the closed state ---------------------------------------------------
    # Cheap pin, expensive lesson: an author `display` on the host outranks the
    # UA's `dialog:not([open]) { display: none }`, so without this every closed
    # surface on the page paints at once.
    surface_css = read(SURFACE_CSS)
    closed = re.search(
        r"\.ax-surface:not\(\s*\[open\]\s*\)\s*\{[^}]*display:\s*none", surface_css, re.S
    )
    report.check(bool(closed), "react: closed surfaces no longer re-declare display: none")

    # --- the scrim ----------------------------------------------------------
    opacity = data["scrim"]["opacity"]
    report.check(
        f"--ax-surface-scrim-opacity: {opacity}" in surface_css,
        f"react: the scrim opacity is not the published {opacity}",
    )
    report.check(
        "--md-sys-color-scrim" in surface_css,
        "react: the scrim stopped reading the theme's scrim colour role",
    )
    # The scrim follows the resolved row, so it is keyed on modality in CSS and
    # nowhere in the components. A per-row attribute cannot answer a media
    # query, and the version that tried shipped a medium dialog floating over a
    # transparent backdrop.
    report.check(
        "[data-modality='modal']::backdrop" in surface_css,
        "react: the scrim is no longer keyed on modality",
    )
    for source in (core, dialog_js):
        # Setting it, not mentioning it. The comment that explains why the
        # attribute was wrong is worth keeping, and a pin that fires on its own
        # explanation is a pin nobody can satisfy.
        report.check(
            not re.search(r"data-scrim\s*=|dataset\.scrim", source),
            "react: a component sets a per-row scrim attribute again, which a media query cannot reach",
        )

    # --- the adaptive swap --------------------------------------------------
    # `transfers` and `keeps` are the yml's, and each side is bound to
    # something a reader can check. A swap that takes the box but not the
    # scrim, headline or speed is the defect this pins shut.
    swap = next(
        (a for a in data["adaptive"] if a.get("presentation") == "dialog-full-screen"),
        None,
    )
    report.check(swap is not None, "react: the full-screen adaptive rule disappeared")
    if swap is not None and "dialog-full-screen" in implemented:
        transfers = swap.get("transfers") or []
        keeps = swap.get("keeps") or []
        report.check(bool(transfers) and bool(keeps), "react: the adaptive swap stopped saying what crosses")

        medium = _medium_block(dialog_css)
        report.check(bool(medium), "react: the full-screen row has no medium media query to swap in")
        compact = _compact_block(dialog_css)
        report.check(bool(compact), "react: nothing scopes the full-screen row's scrim removal to compact")

        # Each transferred thing has a witness in the medium block.
        witnesses = {
            "size": "max-inline-size",
            "container colour": "--md-comp-dialog-container-color",
            "elevation": ".ax-elevation",
            "shape": "--md-comp-dialog-container-shape",
            "headline typescale": "--md-comp-dialog-headline-size",
            "motion": "--ax-surface-motion-enter",
            "scrim": None,
        }
        for item in transfers:
            report.check(item in witnesses, f"react: the swap transfers '{item}', which nothing checks")
            witness = witnesses.get(item)
            if witness is None:
                # The scrim crosses by NOT being removed above medium, so its
                # witness is the compact bound rather than the medium block.
                report.check(
                    "::backdrop" in compact and "transparent" in compact,
                    "react: the full-screen scrim is not removed on compact, or not only there",
                )
                continue
            report.check(witness in medium, f"react: the swap no longer transfers {item} ({witness})")

        # `keeps` is bound in both directions. A part the component renders
        # for this row has to be named in the record, or dropping the name
        # silently retires the check that the swap leaves it alone -- which is
        # how the first version of this pin passed while the record lost its
        # close button.
        parts = {
            "header": "ax-dialog__header",
            "close icon button": "ax-dialog__close",
            "header actions": "ax-dialog__header",
            "body": "ax-dialog__body",
        }
        for item, marker in parts.items():
            rendered = marker in dialog_js
            report.check(
                not rendered or item in keeps,
                f"react: the component renders '{item}' for this row and the record no longer keeps it",
            )
            if item in keeps:
                report.check(
                    f".{marker} {{" not in medium,
                    f"react: the medium swap restyles '{item}', which the record says it keeps",
                )

    # --- native close -------------------------------------------------------
    # The contract records form-method-dialog close, and the browser performs
    # it without asking. A close this component did not make has to reach the
    # caller, or `open` stays true and the trigger is dead for good.
    report.check(
        "onClose={ onNativeClose }" in core,
        "react: a native close no longer reaches the caller, so the surface cannot be reopened",
    )
    report.check(
        "selfClosingRef" in core,
        "react: nothing separates the component's own close from someone else's",
    )
    form_close = (data["components"]["dialog"].get("web") or {}).get("close_by_form")
    report.check(
        form_close is not None and form_close.replace('"', "'") in page.replace('"', "'"),
        "react: no specimen closes a surface the way the contract records",
    )

    # --- the scroll affordance stays fresh ----------------------------------
    # Both conditions change without the region's box changing, so a
    # ResizeObserver alone goes stale: content growing inside a max-block-size,
    # and a focusable appearing or leaving.
    # Bound to the wiring, not to the word. The first version of this pin
    # checked that "MutationObserver" appeared in the file, and it passed with
    # the constructor renamed to something that does not exist -- the word was
    # still there in a comment and in the feature guard.
    report.check(
        re.search(r"new window\.MutationObserver\(", core) is not None,
        "react: the scroll affordance no longer constructs a MutationObserver",
    )
    observed = re.search(r"\.observe\(\s*region,\s*\{(.*?)\}\s*\)", core, re.S)
    report.check(observed is not None, "react: nothing observes the scrolling region's contents")
    if observed is not None:
        for option in ("childList: true", "subtree: true", "attributes: true"):
            report.check(
                option in observed.group(1),
                f"react: the scrolling region is observed without {option}, so one of the two conditions goes stale",
            )
        # Focusability is decided by attributes, so the filter has to admit the
        # ones that change it.
        for attribute in ("disabled", "href", "tabindex"):
            report.check(
                f"'{attribute}'" in observed.group(1),
                f"react: a change to {attribute} no longer re-measures the scroll affordance",
            )

    # --- the scroll lock ----------------------------------------------------
    # Keyed on `:modal`, so it follows modality the way the scrim does. Both
    # runtimes carry the same rule and the yml holds both, so each is checked
    # against the file that ships it.
    lock = react.get("scroll_lock")
    report.check(lock is not None, "react: the scroll lock left the record")
    if lock is not None:
        report.check(
            "modality" == lock.get("follows"),
            "react: the scroll lock no longer follows modality",
        )
        report.check(
            _normalise(lock["react"]) in _normalise(surface_css),
            "react: the page behind a modal is no longer locked",
        )
        report.check(
            ":modal" in surface_css,
            "react: the lock is not keyed on :modal, so a standard sheet would lock the page too",
        )
        block_css = read(BLOCK_CSS)
        report.check(
            not block_css or _normalise(lock["block"]) in _normalise(block_css),
            "the block's scroll lock no longer matches the shared record",
        )

    # --- initial focus ------------------------------------------------------
    # This one is implemented rather than inherited, and the yml says why. If
    # the measurement note goes, the next reader will delete the code as
    # redundant with the platform.
    conflict = next((c for c in data["conflicts"] if c.get("subject") == "initial focus"), None)
    report.check(conflict is not None, "react: the initial-focus decision left the conflict record")
    if conflict is not None:
        report.check(
            bool(conflict.get("measured")),
            "react: the initial-focus record lost the measurement that forced an implementation",
        )
    report.check("applyInitialFocus" in core, "react: the core stopped implementing initial focus")
    report.check(
        "data-surface-scroll" in core,
        "react: initial focus no longer skips the surface's scrolling region",
    )
    report.check(
        'data-surface-scroll=""' in dialog_js,
        "react: the dialog body stopped marking itself as the scrolling region",
    )

    # --- motion -------------------------------------------------------------
    # Which token each row takes is this project's choice, recorded per
    # presentation. The stylesheet has to spend the recorded tokens, for the
    # rows that ship.
    for presentation, tokens in (data.get("motion", {}).get("presentations") or {}).items():
        if presentation not in implemented:
            continue
        for key, token in tokens.items():
            if "reason" == key or not isinstance(token, str):
                continue
            report.check(
                f"--{token}" in row_stylesheets.get(presentation, dialog_css),
                f"react: {presentation} no longer takes --{token} for {key}",
            )

    # The choreography's own numbers, which the component spends rather than
    # invents.
    choreography = data["motion"]["dialog"]
    report.check(
        f"CONTAINER_TRAVEL = {choreography['translate']}" in dialog_js,
        f"react: the container travel is not the recorded {choreography['translate']}px",
    )
    report.check(
        f"FOLDED_HEIGHT = {choreography['folded_height'] / 100}" in dialog_js,
        f"react: the folded height is not the recorded {choreography['folded_height']}%",
    )
    report.check(
        f"EXIT_SLOT_FADE = {choreography['exit_slot_fade'].replace('/', ' / ')}" in dialog_js,
        f"react: the exit slot fade is not the recorded {choreography['exit_slot_fade']}",
    )
    for slot in ("content", "actions"):
        hold = choreography[slot]["hold"]
        share = choreography[slot]["share_of_enter"]
        report.check(
            re.search(rf"hold: {hold}, share: {share}", dialog_js) is not None,
            f"react: the {slot} slot no longer holds {hold} and spends {share} of the enter",
        )

    # --- modality and display are two axes ----------------------------------
    # The defect this pins shut: placement keyed on modality put a docked
    # bottom sheet in the flow, and the row had to override the shared layer to
    # get back on its edge. A row fighting the shared layer is the symptom.
    display_map = react["pane_display"]
    # Comments stripped first: the comment that explains why placement moved off
    # modality names the old selector, and a pin that fires on its own
    # explanation is a pin nobody can satisfy.
    surface_rules = re.sub(r"/\*.*?\*/", "", surface_css, flags=re.S)
    report.check(
        "[data-display='co-planar']" in surface_rules,
        "react: the shared layer no longer places surfaces by display mode",
    )
    report.check(
        "[data-modality='standard']" not in surface_rules,
        "react: placement is keyed on modality again, which cannot tell a docked sheet from a co-planar one",
    )
    sources = {
        "dialog.js": dialog_js,
        "side-sheet.js": side_sheet_js,
        "bottom-sheet.js": read(root / "bottom-sheet.js"),
    }
    # Every mode the record names has a row that declares it.
    for mode in display_map:
        declared = any(f'display="{ mode }"'.replace("{ ", "").replace(" }", "") in source
                       or f"'{ mode }'".replace("{ ", "").replace(" }", "") in source
                       for source in sources.values())
        report.check(declared, f"react: no component declares the '{mode}' display the record names")
    # And the docked row does not restate placement for one modality.
    bottom_css = read(APP / "styles/components/bottom-sheet.css")
    if bottom_css:
        report.check(
            "[data-modality='standard']" not in bottom_css,
            "react: the bottom sheet places itself per modality again, fighting the shared layer",
        )

    # --- the side sheet -----------------------------------------------------
    row = next((r for r in data["presentations"] if r.get("name") == "sheet-side"), None)
    if row is not None and "sheet-side" in implemented:
        measurements = row["measurements"]
        spec = row["spec"]

        # Width: the published range, clamped rather than refused.
        report.check(
            f"MINIMUM_WIDTH = {measurements['width']['default']}" in side_sheet_js,
            f"react: the default width is not the published {measurements['width']['default']}dp",
        )
        report.check(
            f"MAXIMUM_WIDTH = {measurements['width']['max']}" in side_sheet_js,
            f"react: the maximum width is not the published {measurements['width']['max']}dp",
        )

        # Every measurement the row publishes is spent by the stylesheet.
        for name, value in (
            ("padding-inline", measurements["padding_inline"]),
            ("padding-inline-with-icon", measurements["padding_start_with_icon"]),
            ("between-top-elements", measurements["between_top_elements"]),
            ("actions-height", measurements["bottom_actions"]["height"]),
            ("actions-padding-top", measurements["bottom_actions"]["padding_top"]),
            ("actions-padding-bottom", measurements["bottom_actions"]["padding_bottom"]),
            ("detached-margin", measurements["detached_margin"]),
            ("shape-facing-page", spec["shape"]["docked_modal"]["facing_page"]),
            ("shape-detached", spec["shape"]["detached"]),
        ):
            report.check(
                f"--md-comp-side-sheet-{name}: {value}px" in side_sheet_css,
                f"react: side sheet {name} is not the published {value}dp",
            )

        report.check(
            f"--md-comp-side-sheet-shape-docked: {spec['shape']['docked']}" in side_sheet_css,
            "react: the docked side sheet no longer has square corners",
        )

        # Start-aligned actions, which is the published difference from a
        # dialog's trailing ones.
        report.check(
            measurements["bottom_actions"]["alignment"] == "start"
            and "justify-content: flex-start" in side_sheet_css,
            "react: the side sheet's actions are no longer start-aligned",
        )

        # Never horizontally, and never laid out to look as if it does.
        report.check(
            "overflow-x: hidden" in side_sheet_css,
            "react: the side sheet's body may scroll horizontally, which its guidelines forbid",
        )

        # The close affordance is unconditional, which is the stricter page.
        #
        # The first version of this looked at the text AFTER the opening tag,
        # so wrapping the button in `{ onClose ? ... }` -- which goes BEFORE it
        # -- passed. What binds is the JSX immediately preceding the element:
        # a conditional has to introduce it with `?` or `&&`.
        close_at = side_sheet_js.find('className="ax-side-sheet__close"')
        report.check(-1 != close_at, "react: the side sheet lost its close button")
        if -1 != close_at:
            opening = side_sheet_js.rfind("<IconButton", 0, close_at)
            preceding = side_sheet_js[max(0, opening - 120) : opening]
            report.check(
                "?" not in preceding and "&&" not in preceding,
                "react: the side sheet's close button became conditional; the accessibility page makes it always present",
            )

        # The compact swap: one open method, not two trees.
        report.check(
            "useWindowSizeClass" in side_sheet_js,
            "react: nothing observes the window class, so a standard sheet cannot become modal on compact",
        )
        report.check(
            "createPortal" not in side_sheet_js and "ReactDOM" not in side_sheet_js,
            "react: the side sheet moves its DOM between modalities instead of changing how it opens",
        )

    # --- the bottom sheet ---------------------------------------------------
    row = next((r for r in data["presentations"] if r.get("name") == "sheet-bottom"), None)
    bottom_component = next(
        (component for component in react["components"] if component.get("name") == "BottomSheet"),
        None,
    )
    if row is not None and bottom_component is not None and "sheet-bottom" in implemented:
        measurements = row["measurements"]
        spec = row["spec"]
        behaviour = row["behaviour"]
        record = bottom_component

        for name, value in (
            ("maximum-width", measurements["width"]["max"]),
            ("drag-handle-width", spec["drag_handle"]["width"]),
            ("drag-handle-height", spec["drag_handle"]["height"]),
            ("drag-handle-padding-block", measurements["drag_handle"]["padding_block"]),
            ("content-padding", measurements["content_padding"]),
            ("top-margin", measurements["top_margin"]),
            ("wide-top-margin", measurements["wide_window"]["top_margin"]),
            ("wide-inline-margin", measurements["wide_window"]["inline_margin"]),
        ):
            report.check(
                f"--md-comp-bottom-sheet-{name}: {value}px" in bottom_sheet_css,
                f"react: bottom sheet {name} is not the recorded {value}dp",
            )

        report.check(
            f"--md-comp-bottom-sheet-shape: {spec['shape']['start_start']}px" in bottom_sheet_css,
            "react: bottom sheet no longer has the recorded top corner shape",
        )
        report.check(
            f"--md-comp-bottom-sheet-initial-height-cap: {behaviour['initial_height_cap']}%" in bottom_sheet_css,
            "react: bottom sheet initial height is no longer capped at half the window",
        )
        report.check(
            "data-sheet-height" in bottom_sheet_js and "data-sheet-height='initial'" in bottom_sheet_css,
            "react: bottom sheet no longer renders the initial height state",
        )
        report.check(
            "data-sheet-height='expanded'" in bottom_sheet_css,
            "react: bottom sheet no longer renders the expanded height state",
        )
        # The contract is unchanged -- a standard bottom sheet stays on the
        # window's bottom edge with the page usable above it. What changed is
        # where that is expressed: the row is docked in both modalities, so the
        # base rule anchors it and the shared layer no longer pulls a standard
        # surface into the flow. This used to assert the per-modality override
        # that carried it before.
        bottom_rules = re.sub(r"/\*.*?\*/", "", bottom_sheet_css, flags=re.S)
        report.check(
            record["modality"]["standard"] == "anchored to the window bottom, page usable"
            and "inset-block-end: 0" in bottom_rules
            and "margin-block: auto 0" in bottom_rules,
            "react: standard bottom sheet no longer anchors to the window bottom",
        )
        report.check(
            'display="docked"' in sources["bottom-sheet.js"],
            "react: the bottom sheet stopped declaring itself docked, so the shared layer would flow it",
        )
        report.check(
            record["handle"]["element"] == "button"
            and 'className="ax-bottom-sheet__drag-handle"' in bottom_sheet_js
            and 'type="button"' in bottom_sheet_js,
            "react: bottom sheet handle is no longer a real button",
        )
        report.check(
            f"handleLabel = '{record['handle']['label']}'" in bottom_sheet_js,
            "react: bottom sheet handle no longer takes its recorded accessible name",
        )
        report.check(
            "onClick={ cycleHeight }" in bottom_sheet_js and "cycleHeight" in bottom_sheet_js,
            "react: click, Space and Enter no longer cycle the bottom sheet heights",
        )
        affordances = record["expanded_affordance"]
        modal_affordance = affordances["modal"]
        standard_affordance = affordances["standard"]
        affordance_actions = {
            "close": ("onClose", "ax-bottom-sheet__close"),
            "collapse": ("collapse", "ax-bottom-sheet__collapse"),
        }
        modal_callback, modal_class = affordance_actions.get(modal_affordance["action"], ("", ""))
        standard_callback, standard_class = affordance_actions.get(
            standard_affordance["action"], ("", "")
        )
        modal_branch = re.search(
            r"\{ expanded && 'modal' === resolvedModality \? \((.*?)\) : null \}",
            bottom_sheet_js,
            re.S,
        )
        standard_branch = re.search(
            r"\{ expanded && 'standard' === resolvedModality \? \((.*?)\) : null \}",
            bottom_sheet_js,
            re.S,
        )
        report.check(
            modal_affordance["action"] == "close"
            and modal_branch is not None
            and f"className=\"{modal_class}\"" in modal_branch.group(1)
            and f"label={{ {modal_affordance['prop']} }}" in modal_branch.group(1)
            and f"{modal_affordance['prop']} = '{modal_affordance['label']}'" in bottom_sheet_js
            and f'<Icon name="{modal_affordance["icon"]}" />' in modal_branch.group(1)
            and f"onClick={{ {modal_callback} }}" in modal_branch.group(1),
            "react: expanded modal bottom sheet no longer exposes its recorded close affordance",
        )
        report.check(
            standard_affordance["action"] == "collapse"
            and standard_affordance["result"] == "returns to initial height and leaves the controlled sheet open"
            and standard_branch is not None
            and f"className=\"{standard_class}\"" in standard_branch.group(1)
            and f"label={{ {standard_affordance['prop']} }}" in standard_branch.group(1)
            and f"{standard_affordance['prop']} = '{standard_affordance['label']}'" in bottom_sheet_js
            and f'<Icon name="{standard_affordance["icon"]}" />' in standard_branch.group(1)
            and f"onClick={{ {standard_callback} }}" in standard_branch.group(1)
            and "onClick={ onClose }" not in standard_branch.group(1)
            and "setHeight( 'initial' )" in bottom_sheet_js,
            "react: expanded standard bottom sheet no longer collapses without closing",
        )
        report.check(
            f".{modal_class},\n\t.{standard_class}" in bottom_sheet_css,
            "react: standard bottom-sheet collapse affordance no longer shares the app-bar placement",
        )
        report.check(
            "overflow: auto" in bottom_sheet_css and "overflow-x: hidden" not in bottom_sheet_css,
            "react: bottom sheet no longer permits horizontally scrolling content",
        )
        report.check(
            "<UncontainedCarousel" in page and "<BottomSheet" in page,
            "react: stylebook no longer demonstrates a real Carousel inside BottomSheet",
        )
        report.check(
            "modality=\"standard\"" in page,
            "react: stylebook no longer demonstrates a standard bottom sheet",
        )
        report.check(
            "modal specimen exposes Close" in page
            and "standard specimen" in page
            and "Collapse sheet" in page,
            "react: stylebook no longer records modal close versus standard collapse",
        )

        deferred = " ".join(record.get("deferred") or []) + " " + " ".join(
            react.get("not_implemented") or []
        )
        report.check(
            "pointer drag" in deferred and "keyboard/click" in deferred,
            "react: deferred bottom-sheet drag no longer records its keyboard/click alternative",
        )
        report.check(
            not re.search(r"onPointer|pointercapture|setPointerCapture|releasePointerCapture", bottom_sheet_js, re.I),
            "react: bottom sheet implements pointer drag despite the deferred contract",
        )

    # --- the Scaffold's sheet slot ------------------------------------------
    # The React record, not `windows.page_share`, which describes the block's
    # injected padding on the page root.
    share = react["page_share"]
    report.check(
        "grid column" in share["resize"],
        "react: the standard sheet's page share stopped being a grid column",
    )
    report.check(
        "not adopted" in share["move"],
        "react: `move` is back, and it would translate the sticky chrome with the page",
    )
    report.check(
        "none" == share.get("offsets", "").split(";")[0].strip(),
        "react: the sheet column claims offsets of its own again",
    )
    report.check(
        "sheet," in scaffold_js and "{ sheet }" in scaffold_js,
        "react: the Scaffold no longer takes a sheet slot",
    )
    # Inside the body, after main -- the JSX pin from the escape record.
    body_markup = scaffold_js.split('ax-scaffold__body', 1)[-1]
    report.check(
        body_markup.find("ax-scaffold__main") < body_markup.find("{ sheet }"),
        "react: the sheet slot is no longer inside the body after main",
    )
    scaffold_css_text = read(SCAFFOLD_CSS_FILE)
    report.check(
        '"main sheet"' in scaffold_css_text and '"sheet main"' in scaffold_css_text,
        "react: the sheet column cannot sit on both edges",
    )
    report.check(
        ":has( [data-edge='start'] )" in scaffold_css_text,
        "react: the scaffold no longer reads the sheet's own edge, which it cannot be told",
    )

    # --- the stylesheets are actually reachable -----------------------------
    index = read(STYLE_INDEX)
    for sheet in ("surface.css", "dialog.css", "bottom-sheet.css", "side-sheet.css"):
        report.check(
            f"components/{sheet}" in index,
            f"react: {sheet} is not imported, so none of it ships",
        )
